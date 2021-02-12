<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    availability_shibboleth2fa
 * @copyright  2021 Lars Bonczek, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_shibboleth2fa;

use availability_shibboleth2fa\event\user_2fa_loggedin;
use core_availability\info;
use core_availability\info_module;
use core_availability\info_section;

defined('MOODLE_INTERNAL') || die();

class condition extends \core_availability\condition {

    /** @var int userid */
    private static $exceptioncacheuser = null;
    /** @var array list of courses with exception */
    private static $exceptioncache = [];

    /**
     * Determines whether a particular item is currently available
     * according to this availability condition.
     *
     * If implementations require a course or modinfo, they should use
     * the get methods in $info.
     *
     * The $not option is potentially confusing. This option always indicates
     * the 'real' value of NOT. For example, a condition inside a 'NOT AND'
     * group will get this called with $not = true, but if you put another
     * 'NOT OR' group inside the first group, then a condition inside that will
     * be called with $not = false. We need to use the real values, rather than
     * the more natural use of the current value at this point inside the tree,
     * so that the information displayed to users makes sense.
     *
     * @param bool $not Set true if we are inverting the condition
     * @param info $info Item we're checking
     * @param bool $grabthelot Performance hint: if true, caches information
     *   required for all course-modules, to make the front page and similar
     *   pages work more quickly (works only for current user)
     * @param int $userid User ID to check availability for
     * @return bool True if available
     * @throws \dml_exception
     */
    public function is_available($not, info $info, $grabthelot, $userid) {
        if ($grabthelot) {
            self::preload_exceptions($userid);
        }

        $course = $info->get_course();
        $ret = self::is_course_available($course->id, $userid);

        return ($not xor $ret);
    }

    /**
     * Obtains a string describing this restriction (whether or not
     * it actually applies). Used to obtain information that is displayed to
     * students if the activity is not available to them, and for staff to see
     * what conditions are.
     *
     * The $full parameter can be used to distinguish between 'staff' cases
     * (when displaying all information about the activity) and 'student' cases
     * (when displaying only conditions they don't meet).
     *
     * If implementations require a course or modinfo, they should use
     * the get methods in $info.
     *
     * The special string <AVAILABILITY_CMNAME_123/> can be returned, where
     * 123 is any number. It will be replaced with the correctly-formatted
     * name for that activity.
     *
     * @param bool $full Set true if this is the 'full information' view
     * @param bool $not Set true if we are inverting the condition
     * @param info $info Item we're checking
     * @return string Information string (for admin) about all restrictions on
     *   this item
     * @throws \moodle_exception
     */
    public function get_description($full, $not, info $info) {
        global $USER;

        $course = $info->get_course();

        if ($not) {
            $str = get_string('requires_no2fa', 'availability_shibboleth2fa');
        } else {
            $str = get_string('requires_2fa', 'availability_shibboleth2fa');

            if (!$full || !$this->is_available($not, $info, false, $USER->id)) {
                $url = new \moodle_url('/availability/condition/shibboleth2fa/index.php', array('id' => $course->id));
                if ($info instanceof info_module) {
                    $url->param('cmid', $info->get_course_module()->id);
                } else if ($info instanceof info_section) {
                    $url->param('sectionid', $info->get_section()->section);
                }
                $str = \html_writer::link($url, $str);
            }
        }

        if ($full) {
            $context = \context_course::instance($course->id, MUST_EXIST);
            if (has_capability('availability/shibboleth2fa:manageexceptions', $context)) {
                $manageurl = new \moodle_url('/availability/condition/shibboleth2fa/manage.php', array('id' => $course->id));
                $str .= ' (';
                $str .= \html_writer::link($manageurl, get_string('manage_exceptions', 'availability_shibboleth2fa'));
                $str .= ')';
            }
        }

        return $str;
    }

    /**
     * Obtains a representation of the options of this condition as a string,
     * for debugging.
     *
     * @return string Text representation of parameters
     */
    protected function get_debug_string() {
        return '';
    }

    /**
     * Saves tree data back to a structure object.
     *
     * @return \stdClass Structure object (ready to be made into JSON format)
     */
    public function save() {
        return (object) ['type' => 'shibboleth2fa'];
    }

    /**
     * Record the fact that the current user has successfully authenticated.
     *
     * @throws \coding_exception
     */
    public static function set_authenticated() {
        global $USER;

        if (!isset($USER->shibbolethcondauth) || !$USER->shibbolethcondauth) {
            $event = user_2fa_loggedin::create(array('userid' => $USER->id, 'objectid' => $USER->id));
            $event->trigger();
        }

        $USER->shibbolethcondauth = true;
    }

    /**
     * Preload all exceptions for the specified user.
     *
     * @param int $userid
     * @throws \dml_exception
     */
    private static function preload_exceptions(int $userid) {
        global $DB;

        self::$exceptioncacheuser = $userid;

        // Fetch exception records.
        self::$exceptioncache = $DB->get_fieldset_select('availability_shibboleth2fa_e', 'courseid',
            'userid = :userid AND skipauth = 1', [
                'userid' => $userid
            ]);
    }

    /**
     * Check for an exception for the specified user in the specified course.
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     * @throws \dml_exception
     */
    public static function get_exception(int $courseid, int $userid) {
        global $DB;

        // Use cache if available.
        if ($userid == self::$exceptioncacheuser) {
            return in_array($courseid, self::$exceptioncache);
        }

        // Fetch exception record.
        return $DB->record_exists('availability_shibboleth2fa_e', array(
            'courseid' => $courseid,
            'userid' => $userid,
            'skipauth' => 1
        ));
    }

    /**
     * Create or update an exception for the specified user in the specified course.
     *
     * @param int $courseid
     * @param int $userid
     * @param bool $skipauth
     * @throws \dml_exception
     */
    public static function set_exception(int $courseid, int $userid, bool $skipauth) {
        global $DB;

        // Insert or update exception record.
        $data = new \stdClass();
        $data->courseid = $courseid;
        $data->userid = $userid;
        $data->skipauth = $skipauth;
        if ($id = $DB->get_field('availability_shibboleth2fa_e', 'id', array('courseid' => $courseid, 'userid' => $userid))) {
            $data->id = $id;
            $DB->update_record('availability_shibboleth2fa_e', $data);
        } else {
            $DB->insert_record('availability_shibboleth2fa_e', $data);
        }

        // Invalidate exception cache.
        if ($userid == self::$exceptioncacheuser) {
            self::$exceptioncacheuser = null;
            self::$exceptioncache = [];
        }
    }

    /**
     * Check to see if the specified user has successfully authenticated or has an exception for this course.
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     * @throws \dml_exception
     */
    public static function is_course_available(int $courseid, int $userid) {
        global $USER;

        // Only works for current user.
        if ($USER->id == $userid) {
            // Return true if session is authenticated.
            if (isset($USER->shibbolethcondauth) && $USER->shibbolethcondauth) {
                return true;
            }
        }

        // Check for per-user exceptions.
        return self::get_exception($courseid, $userid);
    }

    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event) {
        global $DB;
        $courseid = $event->courseid;
        $userid = $event->relateduserid;

        $DB->delete_records('availability_shibboleth2fa_e', array('courseid' => $courseid, 'userid' => $userid));
    }

    public static function course_deleted(\core\event\course_deleted $event) {
        global $DB;
        $courseid = $event->contextinstanceid;
        $DB->delete_records('availability_shibboleth2fa_e', array('courseid' => $courseid));
    }

    public static function user_deleted(\core\event\user_deleted $event) {
        global $DB;
        $userid = $event->contextinstanceid;
        $DB->delete_records('availability_shibboleth2fa_e', array('userid' => $userid));
    }
}
