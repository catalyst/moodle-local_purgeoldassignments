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
 * English language pack for Purgeoldassignments
 *
 * @package    local_purgeoldassignments
 * @category   string
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['areyousure'] = 'This action will delete all files older than {$a->years} from the component {$a->component} in this assignment. This action is not reversible and causes data-loss, are you sure you want to do this?';
$string['component'] = 'Component';
$string['componentcurrentsize'] = 'Total size: {$a}';
$string['componentolderthan'] = 'Older than {$a->years}: {$a->size}';
$string['enablesheduledpurge'] = 'Enable ongoing purge';
$string['enablesheduledpurgefor'] = 'Enable ongoing purge for {$a}';
$string['incompleteconfig'] = 'Some changes could not be saved as no schedule was set. For each component you wish to enable the scheduled purge for, make sure to tick the checkbox and set a value in the "Set for files older than" dropdown.';
$string['invalidcomponent'] = 'Invalid component.';
$string['invalidpurgeoption'] = 'Invalid purge option.';
$string['manualpurge'] = 'manual purge';
$string['nofiles'] = 'This assignment does not contain any files that can be purged yet.';
$string['numyears'] = '{$a} years';
$string['oneyear'] = '1 year';
$string['pagetitle'] = '{$a}: Purge old assignments';
$string['pluginname'] = 'Purge old assignments';
$string['privacy:metadata:local_purgeoldassignments'] = 'Ongoing purge schedules configured for assignments.';
$string['privacy:metadata:local_purgeoldassignments:cmid'] = 'The assignment course module the schedule applies to.';
$string['privacy:metadata:local_purgeoldassignments:component'] = 'The assignment file component the schedule applies to.';
$string['privacy:metadata:local_purgeoldassignments:timemodified'] = 'The time the schedule was last changed.';
$string['privacy:metadata:local_purgeoldassignments:timespan'] = 'The minimum age, in years, of files to purge.';
$string['privacy:metadata:local_purgeoldassignments:usermodified'] = 'The ID of the user who last changed the schedule.';
$string['purgeoldassignments:purgeassignments'] = 'Purge old assignment files';
$string['purgetriggered'] = 'The purge action was scheduled.';
$string['schedulefor'] = 'Set for files older than';
$string['sizeinfo'] = 'Size info';
$string['task:purge'] = 'Purge old assignment files (one-off)';
$string['task:purgeoldassignments'] = 'Purge old assignments';
$string['taskpending'] = 'A purge action was triggered at {$a} and is pending completion';
