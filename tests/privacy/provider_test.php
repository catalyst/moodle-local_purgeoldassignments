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

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Privacy provider tests.
 *
 * @package    local_purgeoldassignments
 * @copyright  2026 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends provider_testcase {
    /** @var \context_module */
    private $context;

    /** @var \stdClass */
    private $user1;

    /** @var \stdClass */
    private $user2;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->context = \context_module::instance($assign->cmid);
        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();

        foreach (['assignsubmission_file' => $this->user1, 'assignfeedback_file' => $this->user2] as $component => $user) {
            $DB->insert_record('local_purgeoldassignments', (object)[
                'cmid' => $assign->cmid,
                'component' => $component,
                'timespan' => 5,
                'timemodified' => time(),
                'usermodified' => $user->id,
            ]);
        }
    }

    public function test_get_contexts_and_users(): void {
        $contextlist = provider::get_contexts_for_userid($this->user1->id);
        $this->assertEquals([$this->context->id], $contextlist->get_contextids());

        $userlist = new userlist($this->context, 'local_purgeoldassignments');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->user1->id, $this->user2->id], $userlist->get_userids());
    }

    public function test_export_user_data(): void {
        $contextlist = new approved_contextlist($this->user1, 'local_purgeoldassignments', [$this->context->id]);
        provider::export_user_data($contextlist);
        $data = writer::with_context($this->context)->get_data([get_string('pluginname', 'local_purgeoldassignments')]);
        $this->assertCount(1, $data->schedules);
        $this->assertSame('assignsubmission_file', $data->schedules[0]->component);
    }

    public function test_delete_data_for_user(): void {
        global $DB;
        $contextlist = new approved_contextlist($this->user1, 'local_purgeoldassignments', [$this->context->id]);
        provider::delete_data_for_user($contextlist);
        $this->assertSame(0, $DB->count_records('local_purgeoldassignments', ['usermodified' => $this->user1->id]));
        $this->assertSame(1, $DB->count_records('local_purgeoldassignments', ['usermodified' => $this->user2->id]));
        // Schedules are kept.
        $this->assertSame(2, $DB->count_records('local_purgeoldassignments'));
    }

    public function test_delete_data_for_users_and_all(): void {
        global $DB;
        $userlist = new approved_userlist($this->context, 'local_purgeoldassignments', [$this->user2->id]);
        provider::delete_data_for_users($userlist);
        $this->assertSame(0, $DB->count_records('local_purgeoldassignments', ['usermodified' => $this->user2->id]));
        $this->assertSame(1, $DB->count_records('local_purgeoldassignments', ['usermodified' => $this->user1->id]));

        provider::delete_data_for_all_users_in_context($this->context);
        $this->assertSame(2, $DB->count_records('local_purgeoldassignments', ['usermodified' => 0]));
    }
}
