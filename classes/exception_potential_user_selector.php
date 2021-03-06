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

use user_selector_base;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/selector/lib.php');


class exception_potential_user_selector extends user_selector_base {
    protected $courseid;

    public function __construct($name, $options) {
        $this->courseid  = $options['courseid'];
        parent::__construct($name, $options);
    }

    /**
     * Candidate users
     * @param string $search
     * @return array
     */
    public function find_users($search) {
        global $DB;
        // By default wherecondition retrieves all users except the deleted, not confirmed and guest.
        list($wherecondition, $params) = $this->search_sql($search, 'u');
        $params['courseid'] = $this->courseid;

        $fields      = 'SELECT ' . $this->required_fields_sql('u');
        $countfields = 'SELECT COUNT(1)';

        $sql = " FROM {user} u
                 JOIN {user_enrolments} AS ue ON ue.userid = u.id
                 JOIN {enrol} AS en ON ue.enrolid = en.id
                 JOIN {course} AS course ON en.courseid = course.id
            LEFT JOIN {availability_shibboleth2fa_e} e ON (e.userid = u.id AND e.courseid = course.id AND e.skipauth = 1)
                WHERE $wherecondition
                      AND e.id IS NULL
                      AND course.id = :courseid";

        list($sort, $sortparams) = users_order_by_sql('u', $search, $this->accesscontext);
        $order = ' ORDER BY ' . $sort;

        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > $this->maxusersperpage) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }

        $availableusers = $DB->get_records_sql($fields . $sql . $order, array_merge($params, $sortparams));

        if (empty($availableusers)) {
            return array();
        }


        if ($search) {
            $groupname = get_string('users_without_exception_matching', 'availability_shibboleth2fa', $search);
        } else {
            $groupname = get_string('users_without_exception', 'availability_shibboleth2fa');
        }

        return array($groupname => $availableusers);
    }

    protected function get_options() {
        $options = parent::get_options();
        $options['courseid'] = $this->courseid;
        $options['file'] = '/availability/condition/shibboleth2fa/classes/exception_potential_user_selector.php';
        return $options;
    }
}
