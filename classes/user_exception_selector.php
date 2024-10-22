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
 * Definition of the {@see user_exception_selector} class.
 *
 * @package    availability_shibboleth2fa
 * @copyright  2024 innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_shibboleth2fa;

use coding_exception;
use context_course;
use dml_exception;
use user_selector_base;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once("$CFG->dirroot/user/selector/lib.php");

/**
 * Selector for exceptions to the 2FA requirement on a per user-and-course basis.
 *
 * @package    availability_shibboleth2fa
 * @copyright  2024 innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_exception_selector extends user_selector_base {
    /** @var int ID of the course that the exceptions apply to */
    protected int $courseid;

    /**
     * @var bool Whether the instance is for selecting among existing user exceptions.
     *           If `true`, the selector will allow finding users, who are currently exempt from the 2FA requirement in a course.
     *           If `false`, it will allow finding users, who are currently not exempt from the 2FA requirement in a course.
     */
    protected bool $skipauth;

    /**
     * Constructs and returns a new instance for the specified course.
     *
     * This method is a wrapper around the regular constructor for convenience and type safety.
     *
     * @param int $courseid ID of the course that the exceptions apply to
     * @param bool $skipauth Whether the selector is intended to find users that are already exempt from the 2FA requirement in the
     *                       course (`true` value) or those that are not (`false` value).
     * @return self Selector instance for finding users and modifying exceptions in the course.
     */
    public static function instance(int $courseid, bool $skipauth): self {
        return new self(
            name: $skipauth ? 'removeselect' : 'addselect',
            options: [
                'courseid'      => $courseid,
                'skipauth'      => $skipauth,
                'accesscontext' => context_course::instance($courseid),
            ],
        );
    }

    /**
     * {@inheritDoc}
     *
     * @param string $name The control name/id for use in the HTML.
     * @param array $options Other options needed to construct this selector. Must contain the course ID as an integer under the
     *                       key `courseid`, as well as a boolean value under the `skipauth` key to indicate whether the selector is
     *                       intended to find users that are already exempt from the 2FA requirement in the course (`true` value) or
     *                       those that are not (`false` value).
     */
    public function __construct($name, $options) {
        $this->courseid = $options['courseid'];
        $this->skipauth = $options['skipauth'];
        parent::__construct($name, $options);
    }

    /**
     * Returns users for whom exceptions can be set/unset in the associated course.
     *
     * This method is used both when getting the list of choices to display to the user, and also when validating a list of users
     * that was selected.
     *
     * @param string $search the search string.
     * @return object[][] An array of arrays of users. The outer array will only have one element with an option group name as key.
     *                    The keys of the inner array will be user IDs and the values will be the corresponding user objects
     *                    containing the fields returned by the method {@see required_fields_sql}.
     *                    If no users match the provided `$search` string, an empty array is returned instead (without nesting).
     * @throws coding_exception
     * @throws dml_exception
     */
    public function find_users($search): array {
        global $DB;
        if (!$this->is_validating()) {
            // To avoid returning too many matching user records, we count them first.
            [$countsql, $countparams] = $this->build_find_users_query($search, selectcount: true);
            $userscount = $DB->count_records_sql($countsql, $countparams);
            if ($userscount > $this->maxusersperpage) {
                return $this->too_many_results($search, $userscount);
            }
        }
        [$sql, $params] = $this->build_find_users_query($search);
        $users = $DB->get_records_sql($sql, $params);
        if (empty($users)) {
            return [];
        }
        $stringkey = $this->skipauth ? 'users_with_exception' : 'users_without_exception';
        if ($search) {
            $groupname = get_string("{$stringkey}_matching", 'availability_shibboleth2fa', $search);
        } else {
            $groupname = get_string($stringkey, 'availability_shibboleth2fa');
        }
        return [$groupname => $users];
    }

    /**
     * Constructs and returns an SQL query along with the corresponding parameters for finding users via the selector.
     *
     * @param string $search Search string to find users by.
     * @param bool $selectcount Whether the resulting query is merely for counting the number of matching records.
     *                          If `false` (default), the query will select the fields returned by {@see required_fields_sql}.
     * @return array Two items, the first being the SQL query string, and the second an array of the corresponding parameters.
     * @throws coding_exception
     */
    protected function build_find_users_query(string $search, bool $selectcount = false): array {
        // By default, `wherecondition` retrieves all users except the deleted, not confirmed and guest.
        [$wherecondition, $params] = $this->search_sql($search, 'u');
        $params['courseid'] = $this->courseid;
        // Different queries depending on whether we want to search among users with or without existing exceptions in the course.
        if ($this->skipauth) {
            $ejoin = 'JOIN';
        } else {
            $ejoin = 'LEFT JOIN';
            $wherecondition .= ' AND e.id IS NULL';
        }
        if ($selectcount) {
            $fields = 'COUNT(1)';
            $orderby = '';
        } else {
            $fields = $this->required_fields_sql('u');
            [$sort, $sortparams] = users_order_by_sql('u', $search, $this->accesscontext);
            $orderby = "ORDER BY $sort";
            $params += $sortparams;
        }
        $sql = "SELECT $fields
                  FROM {user} u
                  JOIN {user_enrolments} AS ue ON ue.userid = u.id
                  JOIN {enrol} AS en ON ue.enrolid = en.id
                  JOIN {course} AS course ON en.courseid = course.id
                $ejoin {availability_shibboleth2fa_e} e ON (e.userid = u.id AND e.courseid = course.id AND e.skipauth = 1)
                 WHERE $wherecondition
                       AND course.id = :courseid
              $orderby";
        return [$sql, $params];
    }

    /**
     * Returns the options needed to recreate the given {@see user_exception_selector}.
     *
     * @return array Options to be passed to the class' constructor.
     */
    protected function get_options(): array {
        return parent::get_options() + [
            'courseid' => $this->courseid,
            'skipauth' => $this->skipauth,
        ];
    }

    /**
     * If any users have been selected, an exception for them is added/removed in the associated course.
     *
     * If the selector was created for users that are already exempt from 2FA requirements in the course (`skipauth` set to `true`),
     * the exceptions will be removed for the selected users. If it was created for users that are not (yet) exempt from 2FA
     * requirements in the course (`skipauth` set to `false`), exceptions will be added for the selected users.
     *
     * Calls the {@see invalidate_selected_users} method at the end.
     *
     * @return object[] The selected users for whom the exception status has been modified.
     * @throws dml_exception
     */
    public function set_exceptions_for_selected_users(): array {
        $users = $this->get_selected_users();
        foreach ($users as $user) {
            condition::set_exception(
                courseid: $this->courseid,
                userid: $user->id,
                skipauth: !$this->skipauth,
            );
        }
        $this->invalidate_selected_users();
        return $users;
    }
}
