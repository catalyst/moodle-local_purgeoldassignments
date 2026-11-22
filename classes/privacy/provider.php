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

namespace local_purgeoldassignments\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem for local_purgeoldassignments.
 *
 * The plugin stores the id of the user who last changed an ongoing purge schedule.
 * The schedule itself is site configuration, so on deletion requests the user reference
 * is anonymised rather than the schedule being removed.
 *
 * @package    local_purgeoldassignments
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_purgeoldassignments', [
            'cmid' => 'privacy:metadata:local_purgeoldassignments:cmid',
            'component' => 'privacy:metadata:local_purgeoldassignments:component',
            'timespan' => 'privacy:metadata:local_purgeoldassignments:timespan',
            'timemodified' => 'privacy:metadata:local_purgeoldassignments:timemodified',
            'usermodified' => 'privacy:metadata:local_purgeoldassignments:usermodified',
        ], 'privacy:metadata:local_purgeoldassignments');
        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {local_purgeoldassignments} p
                  JOIN {context} ctx ON ctx.instanceid = p.cmid AND ctx.contextlevel = :contextlevel
                 WHERE p.usermodified = :userid";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $sql = "SELECT usermodified FROM {local_purgeoldassignments} WHERE cmid = :cmid";
        $userlist->add_from_sql('usermodified', $sql, ['cmid' => $context->instanceid]);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $records = $DB->get_records(
                'local_purgeoldassignments',
                ['cmid' => $context->instanceid, 'usermodified' => $userid],
                'component'
            );
            if (!$records) {
                continue;
            }
            $schedules = [];
            foreach ($records as $record) {
                $schedules[] = (object)[
                    'component' => $record->component,
                    'timespan' => $record->timespan,
                    'timemodified' => transform::datetime($record->timemodified),
                    'usermodified' => transform::user($record->usermodified),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_purgeoldassignments')],
                (object)['schedules' => $schedules]
            );
        }
    }

    /**
     * Anonymise user references in the given context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $DB->set_field('local_purgeoldassignments', 'usermodified', 0, ['cmid' => $context->instanceid]);
    }

    /**
     * Anonymise user references for the specified user in the given contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $DB->set_field(
                'local_purgeoldassignments',
                'usermodified',
                0,
                ['cmid' => $context->instanceid, 'usermodified' => $userid]
            );
        }
    }

    /**
     * Anonymise user references for the specified users in the given context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE || empty($userlist->get_userids())) {
            return;
        }
        [$usersql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['cmid'] = $context->instanceid;
        $DB->set_field_select('local_purgeoldassignments', 'usermodified', 0, "cmid = :cmid AND usermodified {$usersql}", $params);
    }
}
