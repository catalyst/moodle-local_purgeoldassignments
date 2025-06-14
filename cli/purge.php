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
 * Purge assignment component files.
 *
 * @package    local_purgeoldassignments
 * @copyright  2025 James Calder and Otago Polytechnic
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . "/clilib.php");
require_once(__DIR__ . '/../lib.php');

list($options, $unrecognized) = cli_get_params(
    [
        'help' => false,
        'cmid' => null,
        'contextid' => null,
        'component' => null,
        'minageyears' => null,
    ], [
        'h' => 'help',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

// Check non-negative integer arguments.
foreach (['cmid', 'contextid'] as $optionname) {
    if (isset($options[$optionname]) && (!is_string($options[$optionname]) || !preg_match('/^\d+$/', $options[$optionname]))) {
        cli_error(get_string(
            'cliincorrectvalueerror', 'admin',
            (object)['value' => $options[$optionname], 'option' => $optionname]
        ));
    }
}

// Check string arguments.
foreach (['component'] as $optionname) {
    if (isset($options[$optionname]) && !is_string($options[$optionname])) {
        cli_error(get_string(
            'cliincorrectvalueerror', 'admin',
            (object)['value' => $options[$optionname], 'option' => $optionname]
        ));
    }
}

// Check non-negative number arguments.
foreach (['minageyears'] as $optionname) {
    if (isset($options[$optionname]) && (!is_numeric($options[$optionname]) || ($options[$optionname] < 0))) {
        cli_error(get_string(
            'cliincorrectvalueerror', 'admin',
            (object)['value' => $options[$optionname], 'option' => $optionname]
        ));
    }
}

// Fetch arguments.
$cmid = isset($options['cmid']) ? intval($options['cmid']) : null;
$contextid = isset($options['contextid']) ? intval($options['contextid']) : null;
$componentname = $options['component'] ?? null;
$minageyears = isset($options['minageyears']) ? floatval($options['minageyears']) : null;

$help = "Purge assignment component files.

Options:
 -h, --help             Print out this help
     --cmid             Course module ID
     --contextid        Context ID
     --component        Name of component
     --minageyears      Minimum age in years

Examples:
\$sudo -u www-data /usr/bin/php local/purgeoldassignments/cli/purge.php --cmid=123456 --component=component_name --minageyears=2
\$sudo -u www-data /usr/bin/php local/purgeoldassignments/cli/purge.php --contextid=123456 --component=component_name --minageyears=0
";

if ($options['help']) {
    echo $help;
    exit(0);
}

$context = null;

if (isset($cmid)) {
    try {
        $newcontext = context_module::instance($cmid);
    } catch (moodle_exception $e) {
        cli_error("Course module not found.");
    }
    if (isset($context) && !$context->is_parent_of($newcontext, false)) {
        cli_error("Incompatible contexts specified.");
    }
    $context = $newcontext;
}

if (isset($contextid)) {
    try {
        $newcontext = context::instance_by_id($contextid);
    } catch (moodle_exception $e) {
        cli_error("Context not found.");
    }
    if (isset($context) && !$context->is_parent_of($newcontext, true)) {
        cli_error("Incompatible contexts specified.");
    }
    $context = $newcontext;
}

if (!isset($context)) {
    cli_error("Context not specified.");
}

if (($context->contextlevel != CONTEXT_MODULE)) {
    cli_error("Context not module.");
}

$cm = $DB->get_record_sql("SELECT cm.*, md.name AS modname
                            FROM {course_modules} cm
                            JOIN {modules} md ON md.id = cm.module
                            WHERE cm.id = ?", [$context->instanceid]);
if ($cm->modname != 'assign') {
    cli_error("Context not assignment.");
}

if (!isset($componentname)) {
    cli_error("Component not specified.");
}

if (!in_array($componentname, local_purgeoldassignments_components())) {
    cli_error("Component must be one of:\n" . implode(', ', local_purgeoldassignments_components()));
}

if (!isset($minageyears)) {
    cli_error("Minimum age not specified.");
}

$count = local_purgeoldassignments_purge($context->id, $componentname, $minageyears);
echo "Deleted files count: {$count}\n";
exit(0);
