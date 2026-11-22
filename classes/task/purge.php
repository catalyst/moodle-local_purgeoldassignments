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

use core\task\adhoc_task;

/**
 * Ad hoc task performing a one-off purge of old files for one assignment component.
 *
 * @package    local_purgeoldassignments
 * @copyright  2025 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge extends adhoc_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:purge', 'local_purgeoldassignments');
    }

    /**
     * Execute the ad hoc task.
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/local/purgeoldassignments/lib.php');

        $data = $this->get_custom_data();
        if (empty($data->contextid) || empty($data->component) || !isset($data->purge)) {
            mtrace('Invalid custom data, nothing to purge.');
            return;
        }
        $count = local_purgeoldassignments_purge((int)$data->contextid, (string)$data->component, $data->purge);
        mtrace("Deleted {$count} file(s) from {$data->component} in context {$data->contextid}.");
    }
}
