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

namespace local_purgeoldassignments;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/purgeoldassignments/lib.php');

/**
 * Tests for the purge library functions and tasks.
 *
 * @package    local_purgeoldassignments
 * @copyright  2026 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('local_purgeoldassignments_purge')]
#[CoversFunction('local_purgeoldassignments_get_stats')]
#[CoversFunction('local_purgeoldassignments_timespan_options')]
#[CoversFunction('local_purgeoldassignments_is_valid_timespan')]
#[CoversClass(task\purge_old_assignments::class)]
#[CoversClass(task\purge::class)]
final class lib_test extends \advanced_testcase {
    /**
     * Create a file in an assignment component file area.
     *
     * @param \context $context
     * @param string $component
     * @param string $filename
     * @param int $ageyears How old the file should be, in years.
     * @param string $filearea
     * @return \stored_file
     */
    private function create_file(
        \context $context,
        string $component,
        string $filename,
        int $ageyears,
        string $filearea = 'submission_files'
    ): \stored_file {
        $time = time() - (YEARSECS * $ageyears) - DAYSECS;
        return get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => $component,
            'filearea' => $filearea,
            'itemid' => 1,
            'filepath' => '/',
            'filename' => $filename,
            'timecreated' => $time,
            'timemodified' => $time,
        ], str_repeat('x', 100));
    }

    /**
     * Count files (not directories) in a component.
     *
     * @param \context $context
     * @param string $component
     * @return int
     */
    private function count_files(\context $context, string $component): int {
        global $DB;
        return $DB->count_records_select(
            'files',
            'contextid = :contextid AND component = :component AND filename <> :dot',
            ['contextid' => $context->id, 'component' => $component, 'dot' => '.']
        );
    }

    /**
     * Set up an assignment context.
     *
     * @return \context_module
     */
    private function create_assign_context(): \context_module {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        return \context_module::instance($assign->cmid);
    }

    public function test_timespan_options(): void {
        $options = local_purgeoldassignments_timespan_options();
        $this->assertSame(range(1, 10), array_keys($options));
        $this->assertTrue(local_purgeoldassignments_is_valid_timespan(10));
        $this->assertTrue(local_purgeoldassignments_is_valid_timespan('5'));
        $this->assertFalse(local_purgeoldassignments_is_valid_timespan(0));
        $this->assertFalse(local_purgeoldassignments_is_valid_timespan(11));
        $this->assertFalse(local_purgeoldassignments_is_valid_timespan('abc'));
    }

    public function test_purge_deletes_only_old_files(): void {
        $this->resetAfterTest();
        $context = $this->create_assign_context();

        $this->create_file($context, 'assignsubmission_file', 'new.txt', 0);
        $this->create_file($context, 'assignsubmission_file', 'two.txt', 2);
        $this->create_file($context, 'assignsubmission_file', 'seven.txt', 7);
        $this->create_file($context, 'assignfeedback_file', 'feedback.txt', 7, 'feedback_files');

        // Ten years: nothing is old enough.
        $this->assertSame(0, local_purgeoldassignments_purge($context->id, 'assignsubmission_file', 10));
        $this->assertSame(3, $this->count_files($context, 'assignsubmission_file'));

        // Five years: only the seven year old file goes, other components untouched.
        $this->assertSame(1, local_purgeoldassignments_purge($context->id, 'assignsubmission_file', 5));
        $this->assertSame(2, $this->count_files($context, 'assignsubmission_file'));
        $this->assertSame(1, $this->count_files($context, 'assignfeedback_file'));

        // One year.
        $this->assertSame(1, local_purgeoldassignments_purge($context->id, 'assignsubmission_file', 1));
        $this->assertSame(1, $this->count_files($context, 'assignsubmission_file'));
    }

    public function test_purge_keeps_editpdf_stamps(): void {
        $this->resetAfterTest();
        $context = $this->create_assign_context();

        $this->create_file($context, 'assignfeedback_editpdf', 'stamp.png', 5, 'stamps');
        $this->create_file($context, 'assignfeedback_editpdf', 'combined.pdf', 5, 'combined');

        $this->assertSame(1, local_purgeoldassignments_purge($context->id, 'assignfeedback_editpdf', 1));
        $this->assertSame(1, $this->count_files($context, 'assignfeedback_editpdf'));
    }

    public function test_purge_rejects_invalid_input(): void {
        $this->resetAfterTest();
        $context = $this->create_assign_context();
        $this->create_file($context, 'assignsubmission_file', 'old.txt', 5);

        // Not an allowed component.
        $this->assertSame(0, local_purgeoldassignments_purge($context->id, 'mod_assign', 1));
        // Negative age.
        $this->assertSame(0, local_purgeoldassignments_purge($context->id, 'assignsubmission_file', -1));
        // Missing context.
        $this->assertSame(0, local_purgeoldassignments_purge(999999999, 'assignsubmission_file', 1));
        // Not a module context.
        $this->assertSame(0, local_purgeoldassignments_purge(\context_system::instance()->id, 'assignsubmission_file', 1));

        // Module context that is not an assignment.
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $pagecontext = \context_module::instance($page->cmid);
        $this->create_file($pagecontext, 'assignsubmission_file', 'old.txt', 5);
        $this->assertSame(0, local_purgeoldassignments_purge($pagecontext->id, 'assignsubmission_file', 1));
        $this->assertSame(1, $this->count_files($pagecontext, 'assignsubmission_file'));

        $this->assertSame(1, $this->count_files($context, 'assignsubmission_file'));
    }

    public function test_get_stats(): void {
        $this->resetAfterTest();
        $context = $this->create_assign_context();

        $this->assertSame([], local_purgeoldassignments_get_stats($context->id));

        $this->create_file($context, 'assignsubmission_file', 'new.txt', 0);
        $this->create_file($context, 'assignsubmission_file', 'seven.txt', 7);

        $stats = local_purgeoldassignments_get_stats($context->id);
        $this->assertArrayHasKey('assignsubmission_file', $stats);
        $this->assertSame(200, $stats['assignsubmission_file']->total);
        $this->assertSame(range(1, 7), array_keys($stats['assignsubmission_file']->olderthan));
        $this->assertSame(100, $stats['assignsubmission_file']->olderthan[7]);
    }

    public function test_scheduled_task(): void {
        global $DB;
        $this->resetAfterTest();
        $context = $this->create_assign_context();
        $this->create_file($context, 'assignsubmission_file', 'new.txt', 0);
        $this->create_file($context, 'assignsubmission_file', 'old.txt', 8);

        $DB->insert_record('local_purgeoldassignments', (object)[
            'cmid' => $context->instanceid,
            'component' => 'assignsubmission_file',
            'timespan' => 6,
            'timemodified' => time(),
            'usermodified' => 2,
        ]);
        // Orphaned schedule for a deleted course module.
        $DB->insert_record('local_purgeoldassignments', (object)[
            'cmid' => 999999999,
            'component' => 'assignsubmission_file',
            'timespan' => 1,
            'timemodified' => time(),
            'usermodified' => 2,
        ]);

        $task = new task\purge_old_assignments();
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertSame(1, $this->count_files($context, 'assignsubmission_file'));
        $this->assertSame(1, $DB->count_records('local_purgeoldassignments'));
    }

    public function test_adhoc_task(): void {
        $this->resetAfterTest();
        $context = $this->create_assign_context();
        $this->create_file($context, 'assignfeedback_file', 'old.txt', 4, 'feedback_files');

        $task = new task\purge();
        $task->set_custom_data(['contextid' => $context->id, 'component' => 'assignfeedback_file', 'purge' => 3]);
        \core\task\manager::queue_adhoc_task($task);

        ob_start();
        $this->runAdhocTasks(task\purge::class);
        ob_end_clean();

        $this->assertSame(0, $this->count_files($context, 'assignfeedback_file'));
    }
}
