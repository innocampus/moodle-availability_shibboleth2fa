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
 * Definition of the {@see exception_current_user_selector} class.
 *
 * @package    availability_shibboleth2fa
 * @copyright  2021 Lars Bonczek, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_shibboleth2fa;

use coding_exception;
use dml_exception;
use user_selector_base;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once("$CFG->dirroot/user/selector/lib.php");


/**
 * TODO: Reduce code duplication with {@see exception_potential_user_selector}
 */
class exception_current_user_selector extends user_selector_base {
    /** @var int ID of the course that the exceptions apply to */
    protected int $courseid;

    /**
     * {@inheritDoc}
     *
     * @param string $name The control name/id for use in the HTML.
     * @param array $options Other options needed to construct this selector. Must contain the `courseid`.
     */
    public function __construct($name, $options) {
        $this->courseid = $options['courseid'];
        parent::__construct($name, $options);
    }

    /**
     * Returns users for whom exceptions were defined in the associated course.
     * {@inheritDoc}
     *
     * @param string $search the search string.
     * @return array An array of arrays of users. The array keys of the outer array should be the string names of optgroups.
     *               The keys of the inner arrays should be userids, and the values should be user objects containing at least
     *               the list of fields returned by the method required_fields_sql(). If a user object has a ->disabled property
     *               that is true, then that option will be displayed greyed out, and will not be returned by get_selected_users.
     * @throws coding_exception
     * @throws dml_exception
     */
    public function find_users($search): array {
        global $DB;
        // By default, wherecondition retrieves all users except the deleted, not confirmed and guest.
        [$wherecondition, $params] = $this->search_sql($search, 'u');
        $params['courseid'] = $this->courseid;
        $fields = "SELECT {$this->required_fields_sql('u')}";
        $countfields = 'SELECT COUNT(1)';
        $sql = " FROM {user} u
                 JOIN {user_enrolments} AS ue ON ue.userid = u.id
                 JOIN {enrol} AS en ON ue.enrolid = en.id
                 JOIN {course} AS course ON en.courseid = course.id
                 JOIN {availability_shibboleth2fa_e} e ON (e.userid = u.id AND e.courseid = course.id AND e.skipauth = 1)
                WHERE $wherecondition
                      AND course.id = :courseid";
        [$sort, $sortparams] = users_order_by_sql('u', $search, $this->accesscontext);
        $order = " ORDER BY $sort";
        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > $this->maxusersperpage) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }
        $availableusers = $DB->get_records_sql($fields . $sql . $order, array_merge($params, $sortparams));
        if (empty($availableusers)) {
            return [];
        }
        if ($search) {
            $groupname = get_string('users_with_exception_matching', 'availability_shibboleth2fa', $search);
        } else {
            $groupname = get_string('users_with_exception', 'availability_shibboleth2fa');
        }
        return [$groupname => $availableusers];
    }

    /**
     * {@inheritDoc}
     */
    protected function get_options(): array {
        $options = parent::get_options();
        $options['courseid'] = $this->courseid;
        $options['file'] = '/availability/condition/shibboleth2fa/classes/exception_current_user_selector.php';
        return $options;
    }
}
