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
 * External Web Service For Handling Barcode Scanning
 *
 * @package   local_barcode
 *
 * @author    Dez Glidden <dez.glidden@catalyst-eu.net>
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->dirroot . '/lib/externallib.php');
require_once($CFG->dirroot . '/mod/assign/submission/physical/lib.php');
require_once($CFG->dirroot . '/lib/moodlelib.php');
require_once($CFG->dirroot . '/local/barcode/classes/barcode_assign.php');
require_once($CFG->dirroot . '/local/barcode/classes/task/email_group.php');
require_once($CFG->dirroot . '/local/barcode/locallib.php');

/**
 * External web service for scanning barcodes
 * @package   local_barcode
 *
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_barcode_external extends external_api {
    /**
     * Save an assignment barcode submission
     * @param  string $barcode The barcode to process`
     * @param  string $revert  Revert to draft indicator
     * @param  string $ontime  Mark the submission as on time
     * @return array  The response with a status code and message
     */
    public static function save_barcode_submission(
        $barcode,
        $revert,
        $ontime
    ) {
        global $DB, $USER, $CFG;
        // Clense parameters.
        $params = self::validate_parameters(
            self::save_barcode_submission_parameters(),
            array('barcode' => $barcode, 'revert' => $revert, 'ontime' => $ontime));

        $data = new stdClass();
        // Remove extra params as they aren't used in $DB->get_record_sql, the barcode is.
        $data->revert = $params['revert'];
        $data->submitontime = $params['ontime'];
        unset($params['revert']);
        unset($params['ontime']);

        $response = array(
            'data' => array(
                'assignment'            => '',
                'course'                => '',
                'studentname'           => '',
                'idformat'              => '',
                'studentid'             => '',
                'participantid'         => 0,
                'duedate'               => '',
                'submissiontime'        => '',
                'assignmentdescription' => '',
                'islate'                => 0,
                'reverted'              => 0,
            ),
        );

        // If the username doesn't exist then return the error.
        if (!$username = local_barcode_get_username_format()) {
            $response['data']['code']    = 404;
            $response['data']['message'] = get_string('missinguseridentifier', 'local_barcode');
            return $response;
        }

        // If the barcode returns a record from the database then save the submission
        // and construct the response.
        if ($record = local_barcode_get_assignment_submmission($params)) {
            $data->groupid = $record->groupid;
            $data->id = $record->cmid;
            list($data->course, $data->cm) = get_course_and_cm_from_instance($record->assignmentid, 'assign');
            $data->context = context_module::instance($record->cmid);
            $data->assign = new barcode_assign($data->context, $data->cm, $data->course);
            $data->user = $DB->get_record('user', array('id' => $record->userid), '*', IGNORE_MISSING);
            $data->isopen = $data->assign->student_submission_is_open($data->user->id, false, false, false);
            $data->submissiontime = ($data->submitontime === '1') ? $record->duedate : time();

            $response['data']['assignment']            = $record->assignment;
            $response['data']['course']                = $record->course;
            $response['data']['studentname']           = $record->firstname . ' ' . $record->lastname;
            $response['data']['participantid']         = $record->participantid;
            $response['data']['duedate']               = date('jS F, \'y G:i', $record->duedate);
            $response['data']['submissiontime']        = date('jS F, \'y G:i:s', $data->submissiontime);
            $response['data']['assignmentdescription'] = strip_tags($record->assignmentdescription);
            $timestamp = time();
            $response['data']['islate']                = (($record->duedate - $timestamp) < 0) ? 1 : 0;

            // Get the username details.

            if (!$userdetails = assignsubmission_physical_get_username(array($record->userid, $username->value))) {
                $response['data']['code']    = 404;
                $response['data']['message'] = get_string('missingstudentid', 'local_barcode');
                return $response;
            }

            $response['data']['idformat'] = $userdetails->name;
            $response['data']['studentid'] = $userdetails->data;

            if (!$submission = $DB->get_record('assign_submission', array('id' => $record->submissionid), '*', IGNORE_MISSING)) {
                $response['data']['code']    = 404;
                $response['data']['message'] = get_string('submissionnotfound', 'local_barcode');
                return $response;
            }

            // If the submission has already been reverted, feedback to user.
            if ('1' === $data->revert && $submission && 'draft' === $submission->status) {
                $response['data']['code']    = 422;
                $response['data']['message'] = get_string('alreadydraftstatus', 'local_barcode');
                return $response;
            }

            if ('1' === $data->revert && $submission && 'submitted' === $submission->status) {
                $data->assign->submission_revert_to_draft($data);
                // Email user.
                $data->emaildata = local_barcode_get_email_data($data);
                // If group assignment then create a task to send each member a reverted to draft email.
                if ($record->groupid !== '0') {
                    $emailgroupmembers = new local_barcode\task\email_group_revert_to_draft();
                    $emailgroupmembers->set_custom_data($data);
                     \core\task\manager::queue_adhoc_task($emailgroupmembers);
                } else {
                    $data->assign->send_student_revert_to_draft_email($data);
                }

                $response['data']['code']     = 200;
                $response['data']['message']  = get_string('reverttodraftresponse', 'local_barcode');
                $response['data']['reverted'] = 1;
                return $response;
            }

            if ($submission && 'submitted' === $submission->status) {
                $response['data']['code']    = 422;
                $response['data']['message'] = get_string('alreadysubmitted', 'local_barcode');
                return $response;
            }

            $submmissionresponse = $data->assign->save_barcode_submission($data);
            return array_merge_recursive($response, $submmissionresponse);
        }

        // If the barcode was not found then return a 404.

        if (!$record) {
            $response['data']['code']    = 404;
            $response['data']['message'] = get_string('barcodenotfound', 'local_barcode');
            return $response;
        }

        // A lovely little catch all for the blue moon occasion.
        $response['data']['code']    = 418;
        $response['data']['message'] = get_string('catchall', 'local_barcode');
        return $response;
    }

    /**
     * Returns the description of the method parameters
     * @return external_function_parameters
     */
    public static function save_barcode_submission_parameters() {
        return new external_function_parameters(
            array(
                'barcode' => new external_value(PARAM_NOTAGS, 'barcode'),
                'revert'  => new external_value(PARAM_NOTAGS, 'revert'),
                'ontime'  => new external_value(PARAM_NOTAGS, 'ontime'),
            )
        );
    }

    /**
     * The return status from a request
     * @return array an array with a http code and message
     */
    public static function save_barcode_submission_returns() {
        return new external_function_parameters(
            array(
                'data' => new external_single_structure(
                    array(
                        'code'                  => new external_value(PARAM_INT, 'http status code'),
                        'message'               => new external_value(PARAM_TEXT,
                                                                     'status message confirming either success or failure'),
                        'assignment'            => new external_value(PARAM_TEXT, 'the assignment name'),
                        'course'                => new external_value(PARAM_TEXT, 'the course name'),
                        'studentname'           => new external_value(PARAM_NOTAGS, 'the name of the student'),
                        'idformat'              => new external_value(PARAM_TEXT, 'the student identifier format'),
                        'studentid'             => new external_value(PARAM_RAW, 'the student identifier'),
                        'participantid'         => new external_value(PARAM_INT,
                                                                     'if blind marking is in use then replace the student id'),
                        'duedate'               => new external_value(PARAM_TEXT, 'the assignment due date'),
                        'submissiontime'        => new external_value(PARAM_TEXT, 'the current time'),
                        'assignmentdescription' => new external_value(PARAM_RAW, 'assignment description'),
                        'islate'                => new external_value(PARAM_INT,
                                                                     'whether or not the assignment has been submitted late'),
                        'reverted'              => new external_value(PARAM_INT,
                                                                     'whether ot not the submission has been reverted to draft'),
                    )
                ),
            )
        );
    }
}
