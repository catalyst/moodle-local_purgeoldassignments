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
 * Upgrade script for Purge old assignments
 *
 * @package    local_purgeoldassignments
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * upgrade code
 * @param int $oldversion The old version of the plugin
 * @return bool
 */
function xmldb_local_purgeoldassignments_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025031300) {
        // Define table local_purgeoldassignments to be created.
        $table = new xmldb_table('local_purgeoldassignments');

        // Adding fields to table local_purgeoldassignments.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timespan', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table local_purgeoldassignments.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for local_purgeoldassignments.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Purgeoldassignments savepoint reached.
        upgrade_plugin_savepoint(true, 2025031300, 'local', 'purgeoldassignments');
    }

    if ($oldversion < 2025051300) {
        // Delete broken table rows.
        $DB->delete_records('local_purgeoldassignments', ['timespan' => '0']);

        // Purgeoldassignments savepoint reached.
        upgrade_plugin_savepoint(true, 2025051300, 'local', 'purgeoldassignments');
    }

    if ($oldversion < 2026112200) {
        $table = new xmldb_table('local_purgeoldassignments');

        // Allow schedules of up to 10 years.
        $field = new xmldb_field('timespan', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null, 'component');
        $dbman->change_field_precision($table, $field);

        // Remove duplicate schedules (keep the most recent one) before adding the unique index.
        $duplicates = $DB->get_recordset_sql(
            "SELECT cmid, component, MAX(id) AS keepid
               FROM {local_purgeoldassignments}
           GROUP BY cmid, component
             HAVING COUNT(1) > 1"
        );
        foreach ($duplicates as $duplicate) {
            $DB->delete_records_select(
                'local_purgeoldassignments',
                'cmid = :cmid AND component = :component AND id <> :keepid',
                ['cmid' => $duplicate->cmid, 'component' => $duplicate->component, 'keepid' => $duplicate->keepid]
            );
        }
        $duplicates->close();

        $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $dbman->add_key($table, $key);

        $index = new xmldb_index('cmidcomponent', XMLDB_INDEX_UNIQUE, ['cmid', 'component']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026112200, 'local', 'purgeoldassignments');
    }

    return true;
}
