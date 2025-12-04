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

$string['areyousure'] = 'This action will delete historical data from the component {$a} in this assignment. This action is not reversible and causes data-loss, are you sure you want to do this?';
$string['component'] = 'Component';
$string['componentcurrentsize'] = 'Total size: {$a}';
$string['componentolderthan1'] = 'Older than 1 year: {$a}';
$string['componentolderthan2'] = 'Older than 2 years: {$a}';
$string['componentolderthan3'] = 'Older than 3 years: {$a}';
$string['enablesheduledpurge'] = 'Enable ongoing purge';
$string['incompleteconfig'] = 'Some changes could not be saved as no schedule was set. For each component you wish to enable the scheduled purge for, make sure to tick the checkbox and set a value in the "Schedule for files older than" dropdown.';
$string['manualpurge'] = 'manual purge';
$string['pluginname'] = 'Purge old assignments';
$string['privacy:metadata'] = 'The Purge old assignments plugin doesn\'t store any personal data.';
$string['purgefilesolderthan'] = "Purge files older than:";
$string['purgeoldassignments:purgeassignments'] = 'Purge old assignments';
$string['purgetriggered'] = 'The purge action was scheduled.';
$string['schedulefor'] = 'Set for files older than';
$string['sizeinfo'] = 'Size info';
$string['task:purgeoldassignments'] = 'Purge old assignments';
$string['taskpending'] = 'A purge action was triggered at {$a} and is pending completion';
