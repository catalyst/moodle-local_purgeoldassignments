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
 * TODO describe file purge
 *
 * @package    local_purgeoldassignments
 * @copyright  2025 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/local/purgeoldassignments/lib.php');

$id = required_param('id', PARAM_INT);
$purge = optional_param('purge', null, PARAM_INT);
$component = optional_param('component', '', PARAM_COMPONENT);
$confirm = optional_param('confirm', 0, PARAM_INT);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'assign');

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

require_capability('local/purgeoldassignments:purgeassignments', $context);

$url = new moodle_url('/local/purgeoldassignments/purge.php', ['id' => $id, 'purge' => $purge, 'component' => $component]);
$PAGE->set_url($url);

$PAGE->set_heading($SITE->fullname);

// Process any form submission.
if (optional_param('savescheduling', false, PARAM_BOOL) && confirm_sesskey()) {
    $components = local_purgeoldassignments_components();

    foreach ($components as $component) {
        $scheduled = optional_param($component . 'scheduled', false, PARAM_BOOL);
        $newtimespan = optional_param($component . 'timespan', '', PARAM_ALPHANUM);
        $currentrecord = $DB->get_record('local_purgeoldassignments', ['cmid' => $id, 'component' => $component]);

        if ($currentrecord && !$scheduled) {
            $DB->delete_records('local_purgeoldassignments', ['id' => $currentrecord->id]);
        } else if ($scheduled && is_numeric($newtimespan)) {
            if ($currentrecord && ($newtimespan != $currentrecord->timespan)) {
                $newdata = new stdClass();
                $newdata->id = $currentrecord->id;
                $newdata->timespan = $newtimespan;
                $newdata->timemodified = time();
                $newdata->usermodified = $USER->id;
                $DB->update_record('local_purgeoldassignments', $newdata);
            } else if (!$currentrecord) {
                $data = new stdClass();
                $data->cmid = $id;
                $data->component = $component;
                $data->timespan = $newtimespan;
                $data->timemodified = time();
                $data->usermodified = $USER->id;
                $DB->insert_record('local_purgeoldassignments', $data);
            }
        }
    }
    $url->remove_params('purge', 'component');
    redirect($url, get_string('changessaved'), 1);

} else if (!empty($purge) && $confirm === 1 && confirm_sesskey()) {
    // Schedule deletion task.
    $task = new \local_purgeoldassignments\task\purge();
    // Add custom data.
    $task->set_custom_data([
        'contextid' => $context->id,
        'component' => $component,
        'purge' => $purge
    ]);
    // Queue it.
    \core\task\manager::queue_adhoc_task($task);

    $url->remove_params('purge', 'component');
    redirect($url, get_string('purgetriggered', 'local_purgeoldassignments'), 1);

} else {
    echo $OUTPUT->header();

    if ($confirm === 2) {
        $purgeoptions = [1, 2, 3];
        if (!in_array($purge, $purgeoptions)) {
            echo "invalid purge option";
            die;
        }
        $cancelurl = new moodle_url('/local/purgeoldassignments/purge.php', ['id' => $id]);
        $url->param('confirm', 1);
        echo $OUTPUT->confirm(get_string('areyousure', 'local_purgeoldassignments', $component), $url, $cancelurl);
    } else {
        // Get pending ad-hoc tasks.
        $sql = "SELECT *
                FROM {task_adhoc}
                WHERE (component = 'local_purgeoldassignments' or classname = '\\local_purgeoldassignments\\task\\purge')
                AND customdata like '%contextid\":{$context->id},%'
                AND faildelay = 0";
        $adhoctasks = $DB->get_records_sql($sql);
        $tasksrunning = [];
        if (!empty($adhoctasks)) {
            foreach ($adhoctasks as $task) {
                $customdata = json_decode($task->customdata);
                if ($customdata->contextid == $context->id) {
                    $time = !empty($task->timecreated) ? $task->timecreated : $task->nextruntime;
                    $tasksrunning[$customdata->component] = $time;
                }
            }
        }

        // Get Total size of current areas.
        $filesizes = local_purgeoldassignments_get_stats($context->id);

        echo html_writer::start_tag('form', ['action' => $url, 'method' => 'post']);
        echo html_writer::start_tag('div');
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $id]);

        $table = new html_table();
        $table->head  = [get_string('component', 'local_purgeoldassignments'),
                        get_string('sizeinfo', 'local_purgeoldassignments'),
                        get_string('enablesheduledpurge', 'local_purgeoldassignments'),
                        get_string('schedulefor', 'local_purgeoldassignments')];
        $table->colclasses = ['leftalign', 'leftalign', 'mdl-align', 'mdl-align'];
        $table->attributes['class'] = 'purge-old-assignments-table generaltable';
        $table->data = [];

        foreach ($filesizes as $component => $filesize) {
            $row = [];
            $row[] = $component;

            $totalsize = !empty($filesize->total) ? $filesize->total : 0;

            $componentinfo = html_writer::tag('p', get_string('componentcurrentsize', 'local_purgeoldassignments',
                            display_size($totalsize)));

            if (!empty($totalsize)) {

                $filesizesperperiods = [];
                if (!empty($filesize->olderthan1)) {
                    $filesizesperperiods["1"] = get_string('componentolderthan1', 'local_purgeoldassignments',
                                            display_size($filesize->olderthan1));
                }
                if (!empty($filesize->olderthan2)) {
                    $filesizesperperiods["2"] = get_string('componentolderthan2', 'local_purgeoldassignments',
                                            display_size($filesize->olderthan2));
                }
                if (!empty($filesize->olderthan3)) {
                    $filesizesperperiods["3"] = get_string('componentolderthan3', 'local_purgeoldassignments',
                                            display_size($filesize->olderthan3));
                }

                if (empty($tasksrunning[$component])) {
                    foreach ($filesizesperperiods as $key => $value) {
                        $purgeurl = new moodle_url($url, ['component' => $component, 'purge' => $key, 'confirm' => 2]);
                        $filesizesperperiods[$key] .= ' (' .
                                    html_writer::link($purgeurl, get_string('manualpurge', 'local_purgeoldassignments')) . ')';
                    }
                }

                $componentinfo .= html_writer::alist($filesizesperperiods);

            }

            if (!empty($tasksrunning[$component])) {
                $componentinfo .= html_writer::start_tag('b', array()) .
                        get_string("taskpending", "local_purgeoldassignments", userdate($tasksrunning[$component]))
                        . html_writer::end_tag('b');
            }

            $row[] = $componentinfo;

            $currentrecord = $DB->get_record('local_purgeoldassignments', ['cmid' => $id, 'component' => $component]);

            $isenabled = $currentrecord ? true : false;
            $enable = html_writer::checkbox($component . 'scheduled', 1, $isenabled);
            $row[] = $enable;

            $select = html_writer::label('0', 'menu'. $component . 'timespan', false, ['class' => 'accesshide']);
            $choices = [
                1 => '1 year',
                2 => '2 years',
                3 => '3 years'
            ];
            $selected = $currentrecord ? $currentrecord->timespan : '';
            $select .= html_writer::select($choices, $component . 'timespan', $selected);
            $row[] = $select;

            $table->data[] = $row;
        }

        echo $OUTPUT->render($table);
        echo html_writer::start_tag('div', ['class' => 'mdl-align']);
        echo html_writer::empty_tag('input', ['type' => 'submit', 'name' => 'savescheduling', 'value' => get_string('savechanges')]);
        echo html_writer::end_tag('div');
        echo html_writer::end_tag('div');
        echo html_writer::end_tag('form');

    }

    echo $OUTPUT->footer();
}
