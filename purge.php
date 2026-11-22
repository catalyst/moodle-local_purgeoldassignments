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
 * Manage manual and scheduled purges of old files for a single assignment.
 *
 * @package    local_purgeoldassignments
 * @copyright  2025 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\html_writer;
use core\output\notification;
use core\url;
use core_table\output\html_table;

require('../../config.php');
require_once($CFG->dirroot . '/local/purgeoldassignments/lib.php');

$id = required_param('id', PARAM_INT);
$purge = optional_param('purge', 0, PARAM_INT);
$component = optional_param('component', '', PARAM_COMPONENT);
$confirm = optional_param('confirm', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'assign');

require_login($course, false, $cm);

$context = context_module::instance($cm->id);

require_capability('local/purgeoldassignments:purgeassignments', $context);

$components = local_purgeoldassignments_components();
$timespanoptions = local_purgeoldassignments_timespan_options();

// Validate the purge request parameters up front.
if ($component !== '' && !in_array($component, $components, true)) {
    throw new moodle_exception('invalidcomponent', 'local_purgeoldassignments');
}
if (!empty($purge) && !local_purgeoldassignments_is_valid_timespan($purge)) {
    throw new moodle_exception('invalidpurgeoption', 'local_purgeoldassignments');
}

$baseurl = new url('/local/purgeoldassignments/purge.php', ['id' => $id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(get_string('pagetitle', 'local_purgeoldassignments', format_string($cm->name, true, ['context' => $context])));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => context_course::instance($course->id)]));

// Process any form submission.
if (optional_param('savescheduling', false, PARAM_BOOL)) {
    require_sesskey();
    $incompleteconfig = false;

    foreach ($components as $comp) {
        $scheduled = optional_param($comp . 'scheduled', false, PARAM_BOOL);
        $newtimespan = optional_param($comp . 'timespan', 0, PARAM_INT);
        $currentrecord = $DB->get_record('local_purgeoldassignments', ['cmid' => $cm->id, 'component' => $comp]);

        if ($currentrecord && !$scheduled) {
            $DB->delete_records('local_purgeoldassignments', ['id' => $currentrecord->id]);
        } else if ($scheduled) {
            if (!local_purgeoldassignments_is_valid_timespan($newtimespan)) {
                $incompleteconfig = true;
            } else if ($currentrecord && ((int)$newtimespan !== (int)$currentrecord->timespan)) {
                $DB->update_record('local_purgeoldassignments', (object)[
                    'id' => $currentrecord->id,
                    'timespan' => $newtimespan,
                    'timemodified' => time(),
                    'usermodified' => $USER->id,
                ]);
            } else if (!$currentrecord) {
                $DB->insert_record('local_purgeoldassignments', (object)[
                    'cmid' => $cm->id,
                    'component' => $comp,
                    'timespan' => $newtimespan,
                    'timemodified' => time(),
                    'usermodified' => $USER->id,
                ]);
            }
        }
    }

    if ($incompleteconfig) {
        redirect($baseurl, get_string('incompleteconfig', 'local_purgeoldassignments'), null, notification::NOTIFY_WARNING);
    }
    redirect($baseurl, get_string('changessaved'), null, notification::NOTIFY_SUCCESS);
}

if (!empty($purge) && $component !== '' && $confirm === 1) {
    require_sesskey();
    // Schedule deletion task.
    $task = new \local_purgeoldassignments\task\purge();
    $task->set_custom_data([
        'contextid' => $context->id,
        'component' => $component,
        'purge' => $purge,
    ]);
    \core\task\manager::queue_adhoc_task($task, true);

    redirect($baseurl, get_string('purgetriggered', 'local_purgeoldassignments'), null, notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_purgeoldassignments'));

if (!empty($purge) && $component !== '' && $confirm === 2) {
    $confirmurl = new url($baseurl, ['component' => $component, 'purge' => $purge, 'confirm' => 1]);
    $a = (object)['component' => $component, 'years' => $timespanoptions[$purge]];
    echo $OUTPUT->confirm(get_string('areyousure', 'local_purgeoldassignments', $a), $confirmurl, $baseurl);
    echo $OUTPUT->footer();
    exit;
}

// Get pending ad-hoc tasks for this context.
$tasksrunning = [];
$adhoctasks = $DB->get_records_select(
    'task_adhoc',
    'classname = :classname AND faildelay = 0 AND ' . $DB->sql_like('customdata', ':customdata'),
    [
        'classname' => '\\' . \local_purgeoldassignments\task\purge::class,
        'customdata' => '%"contextid":' . (int)$context->id . ',%',
    ]
);
foreach ($adhoctasks as $task) {
    $customdata = json_decode($task->customdata);
    if (
        !empty($customdata->contextid) && (int)$customdata->contextid === (int)$context->id
        && !empty($customdata->component)
    ) {
        $tasksrunning[$customdata->component] = !empty($task->timecreated) ? $task->timecreated : $task->nextruntime;
    }
}

// Get Total size of current areas.
$filesizes = local_purgeoldassignments_get_stats($context->id);

if (empty($filesizes)) {
    echo $OUTPUT->notification(get_string('nofiles', 'local_purgeoldassignments'), notification::NOTIFY_INFO);
}

$schedules = $DB->get_records('local_purgeoldassignments', ['cmid' => $cm->id], '', 'component, id, timespan');

echo html_writer::start_tag('form', ['action' => $baseurl->out(false), 'method' => 'post']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);

$table = new html_table();
$table->head = [
    get_string('component', 'local_purgeoldassignments'),
    get_string('sizeinfo', 'local_purgeoldassignments'),
    get_string('enablesheduledpurge', 'local_purgeoldassignments'),
    get_string('schedulefor', 'local_purgeoldassignments'),
];
$table->colclasses = ['leftalign', 'leftalign', 'mdl-align', 'mdl-align'];
$table->attributes['class'] = 'purge-old-assignments-table generaltable';
$table->data = [];

foreach ($components as $comp) {
    $filesize = $filesizes[$comp] ?? (object)['total' => 0, 'olderthan' => []];
    $componentname = get_string_manager()->string_exists('pluginname', $comp) ? get_string('pluginname', $comp) : $comp;
    $row = [html_writer::div(s($componentname)) . html_writer::div(s($comp), 'small text-muted')];

    $componentinfo = html_writer::tag(
        'p',
        get_string('componentcurrentsize', 'local_purgeoldassignments', display_size($filesize->total))
    );

    $periods = [];
    foreach ($filesize->olderthan as $years => $size) {
        $item = get_string('componentolderthan', 'local_purgeoldassignments', (object)[
            'years' => $timespanoptions[$years],
            'size' => display_size($size),
        ]);
        if (empty($tasksrunning[$comp])) {
            $purgeurl = new url($baseurl, ['component' => $comp, 'purge' => $years, 'confirm' => 2]);
            $item .= ' (' . html_writer::link($purgeurl, get_string('manualpurge', 'local_purgeoldassignments')) . ')';
        }
        $periods[] = $item;
    }
    if ($periods) {
        $componentinfo .= html_writer::alist($periods);
    }

    if (!empty($tasksrunning[$comp])) {
        $componentinfo .= html_writer::tag(
            'strong',
            get_string('taskpending', 'local_purgeoldassignments', userdate($tasksrunning[$comp]))
        );
    }
    $row[] = $componentinfo;

    $currentrecord = $schedules[$comp] ?? null;

    $row[] = html_writer::checkbox(
        $comp . 'scheduled',
        1,
        !empty($currentrecord),
        html_writer::span(get_string('enablesheduledpurgefor', 'local_purgeoldassignments', $componentname), 'accesshide'),
        ['id' => 'id_' . $comp . 'scheduled']
    );

    $select = html_writer::label(
        get_string('schedulefor', 'local_purgeoldassignments'),
        'menu' . $comp . 'timespan',
        false,
        ['class' => 'accesshide']
    );
    $select .= html_writer::select(
        $timespanoptions,
        $comp . 'timespan',
        $currentrecord ? (int)$currentrecord->timespan : '',
        ['' => 'choosedots'],
        ['class' => 'custom-select form-select']
    );
    $row[] = $select;

    $table->data[] = $row;
}

echo html_writer::table($table);
echo html_writer::div(
    html_writer::empty_tag('input', [
        'type' => 'submit',
        'name' => 'savescheduling',
        'value' => get_string('savechanges'),
        'class' => 'btn btn-primary',
    ]),
    'mdl-align'
);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
