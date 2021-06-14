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
 * This file contains functions for the local barcode plugin.
 *
 * @package   local_barcode
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @author    Dez Glidden <dez.glidden@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');


/**
 * Construct the data for use in the submission event.
 *
 * @param  object $data The context, course, barcode record and submission details
 * @return array        An array that holds the required data for a submission event
 */
function local_barcode_get_submission_event_data($data) {
    global $USER;

    return array(
        'context'       => $data->context,
        'courseid'      => $data->course->id,
        'objectid'      => $data->barcoderecord->submissionid,
        'relateduserid' => $data->barcoderecord->userid,
        'userid'        => $USER->id,
        'other'         => array(
            'submissionid'      => $data->submissionrecord->id,
            'submissionattempt' => $data->submissionrecord->attemptnumber,
            'submissionstatus'  => $data->submissionrecord->status
        )
    );
}


/**
 * Is the response success or an error.
 *
 * @param  array  $response The response from a saved submission
 * @return boolean          True if a 200 status code
 */
function local_barcode_is_response_success($response) {
    return ($response['data']['code'] === 200) ? true : false;
}


/**
 * Get the email data to use in sending submission email confirmations
 *
 * @param  object $data The user, assign and barcode data
 * @return object       The email data
 */
function local_barcode_get_email_data($data) {
    global $CFG;

    $emaildata = new stdClass();
    $emaildata->user     = $data->user;
    $emaildata->linkurl  = $CFG->wwwroot . '/mod/assign/view.php?id=' . $data->id;
    $emaildata->linktext = $data->assign->get_instance()->name;
    $emaildata->groupid  = $data->groupid;
    return $emaildata;
}


/**
 * Check if the barcode submitted is valid
 *
 * @param  objecy  $data The data object containing the barcode database record and the submitted form barcode input field
 * @return boolean       True if it's a valid barcode, false if not
 */
function local_barcode_is_valid_form($data) {
    if (!empty($data->barcoderecord)) {
        return true;
    }
    if (!empty($data->formdata->barcode)) {
        return true;
    }
    return false;
}


/**
 * Get the error message to display if the submitted form barcode is not valid.
 *
 * @param  object $data The data object containing the barcode database record and the submitted form barcode input field
 * @return string       The error message to display to the user
 */
function local_barcode_get_form_error_message($data) {
    if (empty($data->formdata->barcode)) {
        return get_string('barcodeempty', 'local_barcode');
    }
    if (empty($data->barcoderecord)) {
        return get_string('barcodenotfound', 'local_barcode');
    }
}


/**
 * Is the submission open and valid to allow a submission
 *
 * @param  object $data The data object containing the form options, submission record from the database and the isopen boolean
 * @return boolean      True if a valid submission
 */
function local_barcode_is_valid_submission($data) {
    if (!$data->isopen && $data->formdata->reverttodraft === '0' && $data->formdata->submitontime === '1') {
        return true;
    }
    if ($data->isopen && $data->submissionrecord) {
        return true;
    }
    return false;
}


/**
 * Get the error message to display if the submission is not valid and the chosen form options conflict
 *
 * @param  object $data The submitted form and the submission record
 * @return [type]       [description]
 */
function local_barcode_get_invalid_submission_error($data) {
    if ($data->formdata->reverttodraft === '1' && $data->formdata->submitontime === '1') {
        return get_string('draftandsubmissionerror', 'local_barcode');
    }
    if (!$data->isopen) {
        return get_string('submissionclosed', 'local_barcode');
    }
    return get_string('submissionnotfound', 'local_barcode');;
}


/**
 * Is the submission for a group.
 *
 * @param  object  $data The data containing the group submission id to check
 * @return boolean       True if it's a group submission
 */
function local_barcode_is_group_submission($data) {
    return ($data->barcoderecord->groupid !== '0') ? true : false;
}


/**
 * Is the submission to be submitted on time.
 *
 * @param  object $data The formdata
 * @return boolean      True if the submit on time has been selected
 */
function local_barcode_is_submit_ontime($data) {
    return ($data->formdata->submitontime === '1') ? true : false;
}


/**
 * Get the username format that's used. eg. Student ID
 *
 * @return object The username object
 */
function local_barcode_get_username_format() {
    global $DB;
    $conditions = array('plugin' => 'assignsubmission_physical', 'name' => 'usernamesettings');
    return $DB->get_record('config_plugins', $conditions, 'value', IGNORE_MISSING);
}


/**
 * Get the assignment & submission details
 *
 * @param  array $barcode The barcode field
 * @return object The database object containing the assignent & submission details
 */
function local_barcode_get_assignment_submmission($barcode) {
    global $DB;
    $sql = "SELECT b.assignmentid,
                   b.groupid,
                   b.userid,
                   b.barcode,
                   b.courseid,
                   b.submissionid,
                   b.cmid,
                   a.name AS assignment,
                   a.intro AS assignmentdescription,
                   a.duedate,
                   a.blindmarking,
                   c.fullname AS course,
                   u.firstname,
                   u.lastname,
                   m.id AS participantid
              FROM {assignsubmission_barcode} b
              JOIN {assign} a ON b.assignmentid = a.id
              JOIN {course} c ON b.courseid = c.id
              JOIN {user} u ON b.userid = u.id
         LEFT JOIN {assign_user_mapping} m ON b.userid = m.userid AND b.assignmentid = m.assignment
             WHERE b.barcode = ?";

    return $DB->get_record_sql($sql, $barcode, IGNORE_MISSING);
}
