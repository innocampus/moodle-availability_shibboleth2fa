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
 * Privacy subsystem implementation.
 *
 * @see https://moodledev.io/docs/4.4/apis/subsystems/privacy
 *
 * @package    availability_shibboleth2fa
 * @copyright  2021 Lars Bonczek, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_shibboleth2fa\privacy;

use coding_exception;
use context;
use context_course;
use core_privacy\local\metadata\collection as metadata_collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as request_plugin_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use dml_exception;

/**
 * Privacy provider class.
 *
 * @see https://moodledev.io/docs/4.4/apis/subsystems/privacy
 *
 * @package    availability_shibboleth2fa
 * @copyright  2021 Lars Bonczek, innoCampus, TU Berlin
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements core_userlist_provider, metadata_provider, request_plugin_provider {

    /**
     * {@inheritDoc}
     *
     * @param metadata_collection $collection The initialised collection to add items to.
     */
    public static function get_metadata(metadata_collection $collection): metadata_collection {
        $collection->add_database_table(
            'availability_shibboleth2fa_e',
            [
                'courseid' => 'privacy:metadata:availability_shibboleth2fa_e:courseid',
                'userid'   => 'privacy:metadata:availability_shibboleth2fa_e:userid',
                'skipauth' => 'privacy:metadata:availability_shibboleth2fa_e:skipauth',
            ],
            'privacy:metadata:availability_shibboleth2fa_e',
        );
        return $collection;
    }

    /**
     * {@inheritDoc}
     *
     * @param int $userid The user to search.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course} crs ON crs.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {availability_shibboleth2fa_e} e ON e.courseid = crs.id
                 WHERE e.userid = :userid";
        $params = [
            'userid'       => $userid,
            'contextlevel' => CONTEXT_COURSE,
        ];
        return $contextlist->add_from_sql($sql, $params);
    }

    /**
     * {@inheritDoc}
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $user = $contextlist->get_user();
        $userid = $user->id;
        [$insql, $params] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $sql = "SELECT ctx.id AS contextid, e.courseid, e.userid, e.skipauth
                  FROM {context} ctx
                  JOIN {course} crs ON crs.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {availability_shibboleth2fa_e} e ON e.courseid = crs.id
                 WHERE e.userid = :userid AND ctx.id $insql";
        $params['userid'] = $userid;
        $params['contextlevel'] = CONTEXT_COURSE;
        $records = $DB->get_recordset_sql($sql, $params);
        foreach ($records as $record) {
            $context = context::instance_by_id($record->contextid);
            writer::with_context($context)->export_data([get_string('pluginname', 'availability_shibboleth2fa')], $record);
        }
        $records->close();
    }

    /**
     * {@inheritDoc}
     *
     * @param context $context The specific context to delete data for.
     * @throws dml_exception
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if ($context->contextlevel === CONTEXT_COURSE) {
            $DB->delete_records('availability_shibboleth2fa_e', ['courseid' => $context->instanceid]);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     * @throws dml_exception
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        if ($contextlist->count() === 0) {
            return;
        }
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (is_a($context, context_course::class)) {
                $DB->delete_records('availability_shibboleth2fa_e', ['courseid' => $context->instanceid, 'userid' => $userid]);
            }
        }
    }

    /**
     * {@inheritDoc}
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!is_a($context, context_course::class)) {
            return;
        }
        $sql = "SELECT e.userid
                  FROM {availability_shibboleth2fa_e} e
                  JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :contextlevel
                 WHERE ctx.id = :contextid";
        $params = [
            'contextid'    => $context->id,
            'contextlevel' => CONTEXT_COURSE,
        ];
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * {@inheritDoc}
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!is_a($context, context_course::class)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $select = "userid $insql AND courseid = :courseid";
        $params['courseid'] = $context->instanceid;
        $DB->delete_records_select('availability_shibboleth2fa_e', $select, $params);
    }
}
