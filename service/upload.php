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
 * This file contains functions for the local barcode plugin
 *
 * An XMLRPC client based on a web service example by Jerome Mouneyrac
 * @see https://github.com/moodlehq/moodle-local_wstemplate
 *
 * This script does not depend of any Moodle code,
 * and it can be called from a browser.
 *
 * @package   local_barcode
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @author    Dez Glidden <dez.glidden@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->dirroot . '/lib/moodlelib.php');
require_once($CFG->dirroot . '/lib/filelib.php');
require_once($CFG->dirroot . '/local/barcode/locallib.php');
require_once($CFG->dirroot . '/local/barcode/classes/submission/upload_submission.php');

$data = new stdClass();
$data->id      = optional_param('id', 0, PARAM_INT);
$data->barcode = required_param('barcode', PARAM_ALPHANUM);
$data->revert  = required_param('revert', PARAM_TEXT);
$data->ontime  = required_param('ontime', PARAM_TEXT);

if ($data->id !== 0 && $data->id !== 1) {
    list($course, $cm) = get_course_and_cm_from_cmid($data->id, 'assign');
    $context           = context_module::instance($cm->id);
    require_login($course, true, $cm);
} else {
    require_login();
    $context = context_system::instance();
}

require_capability('assignsubmission/barcode:scan', $context);

// Save the new submission to the database.
$upload = new upload_submission($data);
$upload->save_submission();
