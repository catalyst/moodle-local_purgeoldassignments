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
 * Callback implementations for Purge old assignments
 *
 * @package    local_purgeoldassignments
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Inject a button for cleaning up old assignments.
 *
 * @param navigation_node $navigation
 * @param stdclass $context
 * @return void
 */
function local_purgeoldassignments_extend_settings_navigation(navigation_node $navigation, $context) {
    global $PAGE;
    $cm = $PAGE->cm;

    if (!$cm) {
        return false;
    }
    if ($cm->modname != 'assign') {
        return false;
    }
    if (!$assignsettingsnode = $navigation->find('modulesettings', navigation_node::TYPE_SETTING)) {
        return;
    }
    if (!has_capability('local/purgeoldassignments:purgeassignments', $context)) {
        return;
    }
    $node = navigation_node::create(get_string('pluginname', 'local_purgeoldassignments'),
                new moodle_url('/local/purgeoldassignments/purge.php', ['id' => $cm->id]),
                navigation_node::TYPE_SETTING);
    $assignsettingsnode->add_node($node);

}

/**
 * Purge selected files.
 *
 * @param int $contextid
 * @param string $component
 * @param int|float $purge
 * @return int count of deletions.
 */
function local_purgeoldassignments_purge(int $contextid, $component, $purge) {
    global $DB;

    if (!is_numeric($purge) || empty($component) || empty($contextid)) {
        // Safety check.
        return;
    }
    if (!in_array($component, local_purgeoldassignments_components())) {
        // Not an allowed component.
        return;
    }
    if ($purge < 0) {
        return;
    }
    // Check to make sure contextid is valid - if not ignore and return.
    $context = context::instance_by_id($contextid, IGNORE_MISSING);
    if (empty($context)) {
        return;
    }

    $olderthan = time() - (YEARSECS * $purge);

    $sql = "SELECT *
            FROM {files}
            WHERE timemodified < :olderthan AND component = :component AND contextid = :contextid and filearea <> 'stamps'";
    $params = ['olderthan' => $olderthan, 'component' => $component, 'contextid' => $contextid];
    $records = $DB->get_recordset_sql($sql, $params);
    $fs = get_file_storage();
    $count = 0;
    foreach ($records as $record) {
        $file = $fs->get_file_instance($record);
        if (!$file->is_directory()) {
            $file->delete();
            $count++;
        }
    }
    $records->close();

    return $count;
}

/**
 * List of fileareas components we support.
 *
 * @return array
 */
function local_purgeoldassignments_components() {
    return ['assignfeedback_editpdf',
            'assignfeedback_file',
            'assignsubmission_file'];
}
/**
 * Get existing filesize stats.
 *
 * @param int $contextid
 * @return array
 */
function local_purgeoldassignments_get_stats($contextid) {
    global $DB;

    // File areas we want to allow purging.
    $fileareas = local_purgeoldassignments_components();
    list($componentsql, $params) = $DB->get_in_or_equal($fileareas, SQL_PARAMS_NAMED);
    $filesizes = [];
    $sqlbase = "SELECT sum(filesize) as filesize, component
             FROM {files}
            WHERE component {$componentsql}
                  and filearea <> 'stamps'
                  and contextid = :context";
    $sqlend = " GROUP BY component";
    $params['context'] = $contextid;
    $records = $DB->get_records_sql($sqlbase.$sqlend, $params);
    foreach ($records as $record) {
        $filesizes[$record->component] = new stdClass;
        if (!empty($record->filesize)) {
            $filesizes[$record->component]->total = $record->filesize;
        }
    }

    // Now get stats for older than 1 year.
    $params['olderthan'] = time() - (YEARSECS);
    $sql = $sqlbase." AND timemodified < :olderthan ".$sqlend;
    $records = $DB->get_records_sql($sql, $params);
    foreach ($records as $record) {
        if (!empty($record->filesize)) {
            $filesizes[$record->component]->olderthan1 = $record->filesize;
        }
    }

    // Now get stats for older than 2 years.
    $params['olderthan'] = time() - (YEARSECS * 2);
    $sql = $sqlbase." AND timemodified < :olderthan ".$sqlend;
    $records = $DB->get_records_sql($sql, $params);
    foreach ($records as $record) {
        if (!empty($record->filesize)) {
            $filesizes[$record->component]->olderthan2 = $record->filesize;
        }
    }

    // Now get stats for older than 3 year.
    $params['olderthan'] = time() - (YEARSECS * 3);
    $sql = $sqlbase." AND timemodified < :olderthan ".$sqlend;
    $records = $DB->get_records_sql($sql, $params);
    foreach ($records as $record) {
        if (!empty($record->filesize)) {
            $filesizes[$record->component]->olderthan3 = $record->filesize;
        }
    }

    return $filesizes;
}
