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
use coding_exception;
use context_course;
use core\event\course_deleted;
use core\event\user_deleted;
use core\event\user_enrolment_deleted;
use core_availability\condition as abstract_condition;
use core_availability\info;
use core_availability\info_module;
use core_availability\info_section;
use dml_exception;
use html_writer;
use moodle_exception;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

class condition extends abstract_condition {

    /** @var int|null cached ID of user with an exception */
    private static int|null $exceptioncacheuser = null;

    /** @var int[] cached list of course IDs with exceptions */
    private static array $exceptioncache = [];

    /**
     * @inheritDoc
     * @throws dml_exception
     */
    public function is_available($not, info $info, $grabthelot, $userid): bool {
        if ($grabthelot) self::preload_exceptions($userid);
        $course = $info->get_course();
        $ret = self::is_course_available($course->id, $userid);
        return ($not xor $ret);
    }

    /**
     * @inheritDoc
     * @throws moodle_exception
     */
    public function get_description($full, $not, info $info): string {
        global $USER;
        $course = $info->get_course();
        if ($not) {
            $str = get_string('requires_no2fa', 'availability_shibboleth2fa');
        } else {
            $str = get_string('requires_2fa', 'availability_shibboleth2fa');
            if (!$full || !$this->is_available($not, $info, false, $USER->id)) {
                $url = new moodle_url('/availability/condition/shibboleth2fa/index.php', ['id' => $course->id]);
                if ($info instanceof info_module) {
                    $url->param('cmid', $info->get_course_module()->id);
                } else if ($info instanceof info_section) {
                    $url->param('sectionid', $info->get_section()->section);
                }
                $str = html_writer::link($url, $str);
            }
        }
        if ($full) {
            $context = context_course::instance($course->id);
            if (has_capability('availability/shibboleth2fa:manageexceptions', $context)) {
                $manageurl = new moodle_url('/availability/condition/shibboleth2fa/manage.php', ['id' => $course->id]);
                $link = html_writer::link($manageurl, get_string('manage_exceptions', 'availability_shibboleth2fa'));
                $str .= " ($link)";
            }
        }
        return $str;
    }

    /** @inheritDoc */
    protected function get_debug_string(): string {
        return '';
    }

    /** @inheritDoc */
    public function save(): stdClass {
        return (object) ['type' => 'shibboleth2fa'];
    }

    /**
     * Record the fact that the current user has successfully authenticated.
     *
     * @throws coding_exception
     */
    public static function set_authenticated(): void {
        global $USER;
        if (!isset($USER->shibbolethcondauth) || !$USER->shibbolethcondauth) {
            user_2fa_loggedin::create_and_trigger($USER->id);
        }
        $USER->shibbolethcondauth = true;
    }

    /**
     * Preload all exceptions for the specified user.
     *
     * @param int $userid
     * @throws dml_exception
     */
    private static function preload_exceptions(int $userid): void {
        global $DB;
        self::$exceptioncacheuser = $userid;
        // Fetch exception records.
        self::$exceptioncache = $DB->get_fieldset_select(
            table: 'availability_shibboleth2fa_e',
            return: 'courseid',
            select: 'userid = :userid AND skipauth = 1',
            params: ['userid' => $userid],
        );
    }

    /**
     * Check for an exception for the specified user in the specified course.
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     * @throws dml_exception
     */
    public static function get_exception(int $courseid, int $userid): bool {
        global $DB;
        // Use cache if available.
        if ($userid == self::$exceptioncacheuser) {
            return in_array($courseid, self::$exceptioncache);
        }
        // Fetch exception record.
        return $DB->record_exists('availability_shibboleth2fa_e', [
            'courseid' => $courseid,
            'userid' => $userid,
            'skipauth' => 1,
        ]);
    }

    /**
     * Create or update an exception for the specified user in the specified course.
     *
     * @param int $courseid
     * @param int $userid
     * @param bool $skipauth
     * @throws dml_exception
     */
    public static function set_exception(int $courseid, int $userid, bool $skipauth): void {
        global $DB;
        // Insert or update exception record.
        $data = new stdClass();
        $data->courseid = $courseid;
        $data->userid = $userid;
        $data->skipauth = $skipauth;
        if ($id = $DB->get_field('availability_shibboleth2fa_e', 'id', ['courseid' => $courseid, 'userid' => $userid])) {
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
     * @throws dml_exception
     */
    public static function is_course_available(int $courseid, int $userid): bool {
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

    /**
     * @throws dml_exception
     */
    public static function user_enrolment_deleted(user_enrolment_deleted $event): void {
        global $DB;
        $DB->delete_records('availability_shibboleth2fa_e', ['courseid' => $event->courseid, 'userid' => $event->relateduserid]);
    }

    /**
     * @throws dml_exception
     */
    public static function course_deleted(course_deleted $event): void {
        global $DB;
        $DB->delete_records('availability_shibboleth2fa_e', ['courseid' => $event->contextinstanceid]);
    }

    /**
     * @throws dml_exception
     */
    public static function user_deleted(user_deleted $event): void {
        global $DB;
        $DB->delete_records('availability_shibboleth2fa_e', ['userid' => $event->contextinstanceid]);
    }
}
