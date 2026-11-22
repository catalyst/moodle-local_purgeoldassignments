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

namespace local_purgeoldassignments\task;

/**
 * Delete old assignments if they are older than the set amount of years.
 *
 * @package    local_purgeoldassignments
 * @copyright  2025 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_old_assignments extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:purgeoldassignments', 'local_purgeoldassignments');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/purgeoldassignments/lib.php');

        $schedules = $DB->get_recordset('local_purgeoldassignments');
        $orphaned = [];

        foreach ($schedules as $schedule) {
            $context = \context_module::instance($schedule->cmid, IGNORE_MISSING);

            if (empty($context)) {
                // The course module no longer exists, remove the orphaned schedule.
                $orphaned[] = $schedule->id;
                continue;
            }
            $count = local_purgeoldassignments_purge($context->id, $schedule->component, $schedule->timespan);
            if ($count) {
                mtrace("Deleted {$count} file(s) from {$schedule->component} in course module {$schedule->cmid}.");
            }
        }
        $schedules->close();

        if ($orphaned) {
            $DB->delete_records_list('local_purgeoldassignments', 'id', $orphaned);
        }
    }
}
