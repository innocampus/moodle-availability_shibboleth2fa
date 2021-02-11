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

namespace availability_shibboleth2fa\privacy;

use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use \core_privacy\local\request\writer;
use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\transform;
use \core_privacy\local\request\approved_contextlist;
use \core_privacy\local\request\helper as request_helper;

defined('MOODLE_INTERNAL') || die();

class provider implements \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised item collection to add items to.
     *
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table(
                'availability_shibboleth2fa_e',
                [
                        'courseid' => 'privacy:metadata:availability_shibboleth2fa_e:courseid',
                        'userid'   => 'privacy:metadata:availability_shibboleth2fa_e:userid',
                        'skipauth' => 'privacy:metadata:availability_shibboleth2fa_e:skipauth',
                ],
                'privacy:metadata:availability_shibboleth2fa_e'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     *
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : \core_privacy\local\request\contextlist {
        $contextlist = new \core_privacy\local\request\contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course} crs ON crs.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {availability_shibboleth2fa_e} e ON e.courseid = crs.id
                 WHERE (
                    e.userid = :userid
                )
        ";
        $params = [
                'userid'       => $userid,
                'contextlevel' => CONTEXT_COURSE,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist)) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = $user->id;

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT
                    ctx.id AS contextid,
                    e.courseid, e.userid, e.skipauth
                  FROM {context} ctx
                  JOIN {course} crs ON crs.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {availability_shibboleth2fa_e} e ON e.courseid = crs.id
                 WHERE (
                    e.userid = :userid AND
                    ctx.id {$contextsql}
                )
        ";

        $params = [
                'userid' => $userid,
                'contextlevel' => CONTEXT_COURSE,
        ];
        $params += $contextparams;

        $data = $DB->get_recordset_sql($sql, $params);
        foreach ($data as $d) {
            $context = \context::instance_by_id($d->contextid);

            writer::with_context($context)->export_data([get_string('pluginname', 'availability_shibboleth2fa')], $d);
        }
        $data->close();
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $DB->delete_records('availability_shibboleth2fa_e', ['courseid' => $context->instanceid]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!is_a($context, \context_course::class)) {
                continue;
            }
            $DB->delete_records('availability_shibboleth2fa_e', ['courseid' => $context->instanceid, 'userid' => $userid]);
        }
    }

    /**
     * Get the list of users who have data within a context.
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!is_a($context, \context_course::class)) {
            return;
        }

        $params = [
            'contextid' => $context->id,
            'contextlevel' => CONTEXT_COURSE,
        ];
        $sql = "
            SELECT e.userid
              FROM {availability_shibboleth2fa_e} e
              JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :contextlevel
             WHERE ctx.id = :contextid
        ";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Delete multiple users within a single context
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!is_a($context, \context_course::class)) {
            return;
        }

        $userids = $userlist->get_userids();
        list($usql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $select = "userid $usql AND courseid = :courseid";
        $params['courseid'] = $context->instanceid;

        $DB->delete_records_select('availability_shibboleth2fa_e', $select, $params);
    }
}



