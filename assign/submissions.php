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
 * Upload barcode submissions
 *
 * @package    local_barcode
 * @copyright  2018 Coventry University
 * @author     Dez Glidden <dez.glidden@catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../../config.php');
require_once($CFG->dirroot . '/lib/pagelib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once('../barcode_submission_form.php');
require_once('../classes/barcode_assign.php');
require_once('../classes/event/submission_updated.php');
require_once('../classes/task/email_group.php');
require_once('../locallib.php');

$context = context_system::instance();
$id      = $context->id;

require_login();
require_capability('assignsubmission/physical:scan', $context);

$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_heading($SITE->fullname);
$PAGE->set_title($SITE->fullname . ': ' . get_string('pageheading', 'local_barcode'));
$PAGE->set_url(new moodle_url('/local/barcode/assign/submission.php'));
$PAGE->navbar->add(get_string('navigationbreadcrumb', 'local_barcode'), new moodle_url('/local/barcode/assign/submission.php'));
$PAGE->requires->js_call_amd('local_barcode/index', 'init', array($id, true));

 // Process the submitted form. Process the barcode and return the user to the grading
 // summary page or set the error to display.
$mform   = new barcode_submission_form();
$error   = '';
$success = '';
$barcode = '';
$isopen  = true;
$multiplescans = '0';
$data = new stdClass();

if ($mform->is_cancelled()) {
    $url = new moodle_url('/my/', []);
    redirect($url);
} elseif ($data->formdata = $mform->get_submitted_data()) {
    // Process the barcode & submission.
    $conditions = array('barcode' => $data->formdata->barcode);
    $data->barcoderecord = $DB->get_record('assignsubmission_barcode', $conditions, '*', IGNORE_MISSING);
    list($data->course, $data->cm) = get_course_and_cm_from_instance($data->barcoderecord->assignmentid, 'assign');
    $data->context          = context_module::instance($data->barcoderecord->cmid);
    $data->id               = $data->barcoderecord->cmid;
    $data->assign           = new local_barcode\barcode_assign($data->context, $data->cm, $data->course);
    $data->user             = $DB->get_record('user', array('id' => $data->barcoderecord->userid), $fields = '*', IGNORE_MISSING);
    $data->isopen           = $data->assign->student_submission_is_open($data->user->id, false, false, false);
    $data->groupid          = $data->barcoderecord->groupid;
    $data->submissionrecord = $DB->get_record('assign_submission',
                                              array('id' => $data->barcoderecord->submissionid),
                                              '*',
                                              IGNORE_MISSING);

    if (!local_barcode_is_valid_form($data) || !local_barcode_is_valid_submission($data)) {
        $error = (!local_barcode_is_valid_form($data)) ? local_barcode_get_form_error_message($data) :
                                                         local_barcode_get_invalid_submission_error($data);
        $barcode = $data->formdata->barcode;
    } else {

        if ($data->formdata->reverttodraft === '1') {
            if ($data->assign->submission_revert_to_draft($data)) {
                $success = get_string('reverttodraftresponse', 'local_barcode');
                // Email user.
                $data->emaildata = local_barcode_get_email_data($data);
                // If group assignment then create a task to send each member a reverted to draft email.
                if (local_barcode_is_group_submission($data)) {
                    $emailgroupmembers = new local_barcode\task\email_group_revert_to_draft();
                    $emailgroupmembers->set_custom_data($data->emaildata);
                     \core\task\manager::queue_adhoc_task($emailgroupmembers);
                } else {
                    $data->assign->send_revert_to_draft_email($data);
                }
            } else {
                $error = get_string('notsubmitted', 'local_barcode');
                $barcode = $data->formdata->barcode;
            }
        } elseif ($data->formdata->submitontime === '1') {
            $data->submitontime = true;
            $response = $data->assign->save_barcode_submission($data);
            if (local_barcode_is_response_success($response)) {
                $success = $response['data']['message'];
            } else {
                $error = $response['data']['message'];
                $barcode = $data->formdata->barcode;
            }
        } else {
            $data->submitontime = false;
            $response = $data->assign->save_barcode_submission($data);

            if (!local_barcode_is_response_success($response)) {
                $error = $response['data']['message'];
            } else {
                $success = $response['data']['message'];
                $eventdata = local_barcode_get_submission_event_data($data);
                $event = local_barcode\event\submission_updated::create($eventdata);
                $event->trigger();
            }
        }

        if (!empty($success)) {
            $eventdata = local_barcode_get_submission_event_data($data);
            $event = local_barcode\event\submission_updated::create($eventdata);
            $event->trigger();
        }
    }
}

$mform = new barcode_submission_form("./submissions.php?id=$id&action=scanning",
            array(
                'cmid'          => $id,
                'error'         => $error,
                'barcode'       => $barcode,
                'success'       => $success,
                'multiplescans' => $multiplescans,
            ),
            'post',
            '',
            'id="local_barcode_id_barcode_form"');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('barcodeheading', 'local_barcode'), 2, null, 'page_heading');
$mform->display();
echo $OUTPUT->footer();
