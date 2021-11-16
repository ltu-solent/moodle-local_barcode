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
 * This file contains a renderer for the custom_summary_grading_form class
 *
 * @package   local_barcode
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @author    Dez Glidden <dez.glidden@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// namespace local_barcode;

use \mod_assign\output\grading_app;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/lib/moodlelib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/lib/grouplib.php');

/**
 * Extend the assign class, allowing access to the assign class while extending it's functionality
 *
 * @package   local_barcode
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @author    Dez Glidden <dez.glidden@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class barcode_assign extends assign {
    /**
     * Is this assignment open for submissions?
     *
     * Check the due date,
     * prevent late submissions,
     * has this person already submitted,
     * is the assignment locked?
     *
     * @param int $userid - Optional userid so we can see if a different user can submit
     * @param bool $skipenrolled - Skip enrollment checks (because they have been done already)
     * @param stdClass $flags - Pre-fetched user flags record (or false to fetch it)
     * @param stdClass $gradinginfo - Pre-fetched user gradinginfo record (or false to fetch it)
     * @return bool
     */
    public function student_submission_is_open($userid = 0,
                                               $skipenrolled = false,
                                               $flags = false,
                                               $gradinginfo = false) {
        $time      = time();
        $dateopen  = true;
        $finaldate = false;

        if ($this->get_instance()->cutoffdate) {
            $finaldate = $this->get_instance()->cutoffdate;
        }

        if ($flags === false) {
            $flags = $this->get_user_flags($userid, false);
        }

        if ($flags && $flags->locked) {
            return false;
        }

        // User extensions.
        if ($finaldate) {
            if ($flags && $flags->extensionduedate) {
                // Extension can be before cut off date.
                if ($flags->extensionduedate > $finaldate) {
                    $finaldate = $flags->extensionduedate;
                }
            }
        }

        if ($finaldate) {
            $dateopen = ($this->get_instance()->allowsubmissionsfromdate <= $time && $time <= $finaldate);
        } else {
            $dateopen = ($this->get_instance()->allowsubmissionsfromdate <= $time);
        }

        if (!$dateopen) {
            return false;
        }

        // See if this user grade is locked in the gradebook.
        if ($gradinginfo === false) {
            $gradinginfo = grade_get_grades($this->get_course()->id,
                                            'mod',
                                            'assign',
                                            $this->get_instance()->id,
                                            array($userid));
        }

        if ($gradinginfo &&
                isset($gradinginfo->items[0]->grades[$userid]) &&
                $gradinginfo->items[0]->grades[$userid]->locked) {
            return false;
        }

        return true;
    }


    /**
     * Revert to draft.
     *
     * @param object $data Data object containing the userid and optional group id
     * @return boolean
     */
    public function submission_revert_to_draft($data) {
        global $USER, $DB;

        $timemodified = time();
        // First update the submission for the current user.
        $mysubmission = $this->get_user_submission($data->user->id, false);
        $mysubmission->timemodified = $timemodified;
        $mysubmission->status = ASSIGN_SUBMISSION_STATUS_DRAFT;
        try {
            $DB->update_record('assign_submission', $mysubmission);
        } catch (Exception $e) {
            return false;
        }

        $completion = new completion_info($this->get_course());
        if ($completion->is_enabled($this->get_course_module()) &&
                $this->get_instance()->completionsubmit) {
            $completion->update_state($this->get_course_module(), COMPLETION_INCOMPLETE, $data->user->id);
        }
        \mod_assign\event\submission_status_updated::create_from_submission($this, $mysubmission)->trigger();

        if ($this->get_instance()->teamsubmission) {
            // Update group submission.
            $params = array('assignment' => $this->get_instance()->id, 'groupid' => $data->groupid, 'userid' => 0);
            $submissions = $DB->get_records('assign_submission', $params, 'attemptnumber DESC', '*', 0, 1);
            $groupsubmission = reset($submissions);
            $groupsubmission->status = ASSIGN_SUBMISSION_STATUS_DRAFT;
            $groupsubmission->timemodified = $timemodified;
            try {
                $DB->update_record('assign_submission', $groupsubmission);
            } catch (Exception $e) {
                return false;
            }

            // Update each team members submission.
            $team = $this->get_submission_group_members($data->groupid, true);
            foreach ($team as $member) {
                if ($member->id !== $data->user->id) {
                    if (!$membersubmission = $this->get_user_submission($member->id, false)) {
                        $membersubmission = $this->get_user_submission($member->id, true, $groupsubmission->attemptnumber);
                    }
                    $membersubmission->attemptnumber = $groupsubmission->attemptnumber;
                    $membersubmission->status = $groupsubmission->status;
                    $membersubmission->timemodified = $timemodified;
                    try {
                        $DB->update_record('assign_submission', $membersubmission);
                    } catch (Exception $e) {
                        return false;
                    }

                    $completion = new completion_info($this->get_course());
                    if ($completion->is_enabled($this->get_course_module()) &&
                            $this->get_instance()->completionsubmit) {
                        $completion->update_state($this->get_course_module(), COMPLETION_INCOMPLETE, $member->id);
                    }
                    \mod_assign\event\submission_status_updated::create_from_submission($this, $membersubmission)->trigger();
                }
            }
        }
        return true;
    }


    /**
     * Notify both student and graders where the submission has notifications enabled
     *
     * @param object $data The data object that contains the user object and the context object
     * @return void
     */
    public function notify_users($data) {
        global $DB, $USER;

        $submission = $this->get_user_submission($data->user->id, false);

        $adminconfig = $this->get_admin_config();
        if (empty($adminconfig->submissionreceipts)) {
            // No need to do anything.
            return;
        }

        if (!$user = $DB->get_record('user', array('id' => $data->user->id), '*', MUST_EXIST)) {
            return;
        }
// SU_AMEND START - Barcode: Late date check preventing notifications
        // Late submission check. BROKEN!!
        // $late = $this->get_instance()->duedate && ($this->get_instance()->duedate < $submission->timemodified);
        //
        // if (!$this->get_instance()->sendnotifications && !($late && $this->get_instance()->sendlatenotifications)) {
        //     return;
        // }
// SU_AMEND END
        // If notifications have to be sent to the graders then send the notification.
        if ($this->get_instance()->sendnotifications) {
            if ($notifyusers = $this->get_notifiable_users($data)) {
                foreach ($notifyusers as $notifyuser) {
                    $this->send_notification(core_user::get_noreply_user(),
                                             $notifyuser,
                                             'gradersubmissionupdated',
                                             'assign_notification',
                                             $submission->timemodified);
                }
            }
        }

        // If notifying students - send submission receipt.
        if ($this->get_instance()->sendstudentnotifications) {
            $this->send_notification(core_user::get_noreply_user(),
                                     $user,
                                     'submissionreceipt',
                                     'assign_notification',
                                     $submission->timemodified);
        }
        return;
    }


    /**
     * Send an email to the student confirming a submmission has been reverted to draft status.
     *
     * @param  object $data The data used to construct the email
     * @return void
     */
    public function send_student_revert_to_draft_email($data) {
        global $CFG;

        $email = new stdClass();
        $email->userto          = $data->user;
        $email->replyto         = $CFG->noreplyaddress;
        $email->replytoname     = get_string('reverttodraftreplyname', 'local_barcode');
        $email->userfrom        = get_string('reverttodraftfromuser', 'local_barcode');
        $email->subject         = get_string('reverttodraftemailsubject', 'local_barcode');
        $email->fullmessage     = get_string('reverttodraftemailnonhtml',
                                    'local_barcode',
                                    ['timecreated'=>userdate($data->submissiontime, '%d %B %Y, %I:%M:%S %p') , 'linkurl' => $data->emaildata->linkurl, 'linktext' => $data->emaildata->linktext]);
        $email->fullmessagehtml = '<p>' .
                                  get_string('reverttodraftemail',
                                    'local_barcode',
                                    ['timecreated'=>userdate($data->submissiontime, '%d %B %Y, %I:%M:%S %p') , 'linkurl' => $data->emaildata->linkurl, 'linktext' => $data->emaildata->linktext]) .
                                  '</p>';
// SU_AMEND START - Barcode: Prevent revert to draft email
        //email_to_user($email->userto, $email->userfrom, $email->subject, $email->fullmessage, $email->fullmessagehtml, '', '');
// SU_AMEND END
    }


    /**
     * Save assignment submission for the current user.
     *
     * This function closely resembles the save_submission function from mod/assign/locallib.php but amended
     * for the barcode submission process.
     *
     * @param  stdClass $data
     * @return array $response The save response, success or error
     */
    public function save_barcode_submission(stdClass $data) {
        $userid = $data->user->id;
        $instance = $this->get_instance();
        $response = array();
        $update = new stdClass();

        if ($instance->teamsubmission) {
            $submission = $this->get_group_submission($userid, 0, true);
        } else {
            $submission = $this->get_user_submission($userid, true);
        }

        // Check the submission hasn't already been submitted.
        if ($submission->status === ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
            $response['data']['code'] = 422;
            $response['data']['message'] = get_string('alreadysubmitted', 'local_barcode');
            return $response;
        }

        // Set the status to now be submitted.
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $submission->timemodified = $data->submissiontime;

        $flags = $this->get_user_flags($userid, false);

        // Get the flags to check if it is locked.
        if ($flags && $flags->locked) {
            $response['data']['code'] = 422;
            $response['data']['message'] = get_string('submissionslocked', 'assign');
            return $response;
        }

        $this->update_submission($submission, $userid, true, $instance->teamsubmission);

        if ($instance->teamsubmission && !$instance->requireallteammemberssubmit) {
            $team = $this->get_submission_group_members($submission->groupid, true);

            foreach ($team as $member) {
                if ($member->id != $userid) {
                    $membersubmission = clone($submission);
                    $this->update_submission($membersubmission, $member->id, true, $instance->teamsubmission);
                }
            }
        }

        $complete = COMPLETION_COMPLETE;
        $completion = new completion_info($this->get_course());
        if ($completion->is_enabled($this->get_course_module()) && $instance->completionsubmit) {
            $completion->update_state($this->get_course_module(), $complete, $userid);
        }

        $this->notify_users($data);
        \mod_assign\event\assessable_submitted::create_from_submission($this, $submission, true)->trigger();

        $response['data']['code']    = 200;
        $response['data']['message'] = get_string('submissionsaved', 'local_barcode');
        return $response;
    }


    /**
     * Update grades in the gradebook based on submission time.
     *
     * @param stdClass $submission
     * @param int $userid
     * @param bool $updatetime
     * @param bool $teamsubmission
     * @return bool
     */
    protected function update_submission(stdClass $submission, $userid, $updatetime, $teamsubmission) {
        global $DB;

        if ($teamsubmission) {
            return $this->update_team_submission($submission, $userid, $updatetime);
        }

        $result = $DB->update_record('assign_submission', $submission);
        return $result;
    }


    /**
     * Update team submission.
     *
     * @param stdClass $submission
     * @param int $userid
     * @param bool $updatetime
     * @return bool
     */
    protected function update_team_submission(stdClass $submission, $userid, $updatetime) {
        global $DB;

        // First update the submission for the current user.
        $mysubmission = $this->get_user_submission($userid, true, $submission->attemptnumber);
        $mysubmission->timemodified = $submission->timemodified;
        $mysubmission->status = $submission->status;
        $this->update_submission($mysubmission, 0, true, false);

        // Now check the team settings to see if this assignment qualifies as submitted or draft.
        $team = $this->get_submission_group_members($submission->groupid, true);

        $allsubmitted = true;
        $anysubmitted = false;
        $result = true;
        if ($submission->status != ASSIGN_SUBMISSION_STATUS_REOPENED) {
            foreach ($team as $member) {
                $membersubmission = $this->get_user_submission($member->id, false, $submission->attemptnumber);

                // If no submission found for team member and member is active then everyone has not submitted.
                if (!$membersubmission || $membersubmission->status != ASSIGN_SUBMISSION_STATUS_SUBMITTED
                        && ($this->is_active_user($member->id))) {
                    $allsubmitted = false;
                    if ($anysubmitted) {
                        break;
                    }
                } else {
                    $anysubmitted = true;
                }
            }
            if ($this->get_instance()->requireallteammemberssubmit) {
                if ($allsubmitted) {
                    $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
                } else {
                    $submission->status = ASSIGN_SUBMISSION_STATUS_DRAFT;
                }
                $result = $DB->update_record('assign_submission', $submission);
            } else {
                if ($anysubmitted) {
                    $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
                } else {
                    $submission->status = ASSIGN_SUBMISSION_STATUS_DRAFT;
                }
                $result = $DB->update_record('assign_submission', $submission);
            }
        } else {
            // Set the group submission to reopened.
            foreach ($team as $member) {
                $membersubmission = $this->get_user_submission($member->id, true, $submission->attemptnumber);
                $membersubmission->status = ASSIGN_SUBMISSION_STATUS_REOPENED;
                $result = $DB->update_record('assign_submission', $membersubmission) && $result;
            }
            $result = $DB->update_record('assign_submission', $submission) && $result;
        }

        return $result;
    }


    /**
     * Returns a list of users that should receive notification about given submission.
     *
     * @param object $data The data parameter contains the context object and the user object
     * @return array
     */
    protected function get_notifiable_users($data) {
        // Potential users should be active users only.
        $potentialusers = get_enrolled_users($data->context, "mod/assign:receivegradernotifications",
                                             null, 'u.*', null, null, null, true);

        $notifiableusers = array();
        if (groups_get_activity_groupmode($this->get_course_module()) == SEPARATEGROUPS) {
            if ($groups = groups_get_all_groups($this->get_course()->id, $data->user->id, $this->get_course_module()->groupingid)) {
                foreach ($groups as $group) {
                    foreach ($potentialusers as $potentialuser) {
                        if ($potentialuser->id == $data->user->id) {
                            // Do not send self.
                            continue;
                        }
                        if (groups_is_member($group->id, $potentialuser->id)) {
                            $notifiableusers[$potentialuser->id] = $potentialuser;
                        }
                    }
                }
            } else {
                // User not in group, try to find graders without group.
                foreach ($potentialusers as $potentialuser) {
                    if ($potentialuser->id == $data->user->id) {
                        // Do not send self.
                        continue;
                    }
                    if (!groups_has_membership($this->get_course_module(), $potentialuser->id)) {
                        $notifiableusers[$potentialuser->id] = $potentialuser;
                    }
                }
            }
        } else {
            foreach ($potentialusers as $potentialuser) {
                if ($potentialuser->id == $data->user->id) {
                    // Do not send self.
                    continue;
                }
                // Must be enrolled.
                if (is_enrolled($this->get_course_context(), $potentialuser->id)) {
                    $notifiableusers[$potentialuser->id] = $potentialuser;
                }
            }
        }
        return $notifiableusers;
    }
}
