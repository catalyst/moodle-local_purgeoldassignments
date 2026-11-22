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
 * Maximum age, in years, that can be selected for a purge.
 */
define('LOCAL_PURGEOLDASSIGNMENTS_MAX_YEARS', 10);

/**
 * Inject a link for cleaning up old assignment files into the assignment settings.
 *
 * @param settings_navigation $navigation
 * @param context $context
 * @return void
 */
function local_purgeoldassignments_extend_settings_navigation(settings_navigation $navigation, context $context): void {
    $cm = $navigation->get_page()->cm;

    if (!$cm || $cm->modname !== 'assign') {
        return;
    }
    if (!$assignsettingsnode = $navigation->find('modulesettings', navigation_node::TYPE_SETTING)) {
        return;
    }
    if (!has_capability('local/purgeoldassignments:purgeassignments', $context)) {
        return;
    }
    $node = navigation_node::create(
        get_string('pluginname', 'local_purgeoldassignments'),
        new \core\url('/local/purgeoldassignments/purge.php', ['id' => $cm->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_purgeoldassignments'
    );
    $assignsettingsnode->add_node($node);
}

/**
 * Purge files of an assignment component that are older than the given number of years.
 *
 * @param int $contextid The assignment module context id.
 * @param string $component One of {@see local_purgeoldassignments_components()}.
 * @param int|float $purge Minimum file age in years.
 * @return int count of deletions.
 */
function local_purgeoldassignments_purge(int $contextid, $component, $purge): int {
    global $DB;

    if (!is_numeric($purge) || $purge < 0 || empty($component) || empty($contextid)) {
        // Safety check.
        return 0;
    }
    if (!in_array($component, local_purgeoldassignments_components(), true)) {
        // Not an allowed component.
        return 0;
    }
    // Check to make sure contextid is valid and belongs to an assignment - if not ignore and return.
    $context = context::instance_by_id($contextid, IGNORE_MISSING);
    if (empty($context) || $context->contextlevel != CONTEXT_MODULE) {
        return 0;
    }
    $modname = $DB->get_field_sql(
        "SELECT md.name
           FROM {course_modules} cm
           JOIN {modules} md ON md.id = cm.module
          WHERE cm.id = :cmid",
        ['cmid' => $context->instanceid]
    );
    if ($modname !== 'assign') {
        return 0;
    }

    $olderthan = time() - (int)round(YEARSECS * $purge);

    $sql = "SELECT *
              FROM {files}
             WHERE timemodified < :olderthan
               AND component = :component
               AND contextid = :contextid
               AND filearea <> :stamps
               AND filename <> :dot";
    $params = [
        'olderthan' => $olderthan,
        'component' => $component,
        'contextid' => $contextid,
        'stamps' => 'stamps',
        'dot' => '.',
    ];
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
 * @return string[]
 */
function local_purgeoldassignments_components(): array {
    return [
        'assignfeedback_editpdf',
        'assignfeedback_file',
        'assignsubmission_file',
    ];
}

/**
 * The selectable purge ages, in years.
 *
 * @return array years => human readable label
 */
function local_purgeoldassignments_timespan_options(): array {
    $options = [];
    for ($years = 1; $years <= LOCAL_PURGEOLDASSIGNMENTS_MAX_YEARS; $years++) {
        $options[$years] = ($years === 1)
            ? get_string('oneyear', 'local_purgeoldassignments')
            : get_string('numyears', 'local_purgeoldassignments', $years);
    }
    return $options;
}

/**
 * Is the given value a valid purge age (in whole years)?
 *
 * @param mixed $years
 * @return bool
 */
function local_purgeoldassignments_is_valid_timespan($years): bool {
    return is_numeric($years) && (int)$years == $years
        && $years >= 1 && $years <= LOCAL_PURGEOLDASSIGNMENTS_MAX_YEARS;
}

/**
 * Get existing filesize stats.
 *
 * Returns an array keyed by component. Each value has a 'total' property and an 'olderthan'
 * array keyed by number of years (1 .. LOCAL_PURGEOLDASSIGNMENTS_MAX_YEARS), containing only non-empty sizes.
 *
 * @param int $contextid
 * @return stdClass[]
 */
function local_purgeoldassignments_get_stats(int $contextid): array {
    global $DB;

    [$componentsql, $params] = $DB->get_in_or_equal(local_purgeoldassignments_components(), SQL_PARAMS_NAMED);
    $params['contextid'] = $contextid;
    $params['stamps'] = 'stamps';

    $sqlbase = "SELECT component, SUM(filesize) AS filesize
                  FROM {files}
                 WHERE component {$componentsql}
                   AND filearea <> :stamps
                   AND contextid = :contextid";
    $sqlend = " GROUP BY component";

    $filesizes = [];
    $records = $DB->get_records_sql($sqlbase . $sqlend, $params);
    foreach ($records as $record) {
        $filesizes[$record->component] = (object)[
            'total' => (int)$record->filesize,
            'olderthan' => [],
        ];
    }
    if (empty($filesizes)) {
        return $filesizes;
    }

    $now = time();
    for ($years = 1; $years <= LOCAL_PURGEOLDASSIGNMENTS_MAX_YEARS; $years++) {
        $params['olderthan'] = $now - (YEARSECS * $years);
        $records = $DB->get_records_sql($sqlbase . " AND timemodified < :olderthan" . $sqlend, $params);
        if (empty($records)) {
            // Nothing older than this, so nothing older than any larger value either.
            break;
        }
        foreach ($records as $record) {
            if (!empty($record->filesize) && isset($filesizes[$record->component])) {
                $filesizes[$record->component]->olderthan[$years] = (int)$record->filesize;
            }
        }
    }

    return $filesizes;
}
