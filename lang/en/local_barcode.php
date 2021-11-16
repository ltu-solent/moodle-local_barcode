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
 * Strings for component 'local_barcode', language 'en'.
 *
 * @package   local_barcode
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @author    Dez Glidden <dez.glidden@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addbuttonlabel']          = 'Add';
$string['allowsubmitontime']       = 'Allow late submission';
$string['allowmultiplescans']      = 'Allow multiple scans';
$string['alreadydraftstatus']      = 'This submission is already set to draft status';
$string['alreadysubmitted']        = 'This submission has already been submitted';
$string['assignmentdetails']       = 'Assignment details';
$string['barcode']                 = 'Barcode';
$string['barcodes']                = 'Barcodes';
$string['barcodeempty']            = 'Barcode field was empty';
$string['barcodeheading']          = 'Upload physical submissions';
$string['barcodenotfound']         = 'Barcode not found';
$string['barcodesameday']          = 'Barcode already scanned today';
$string['catchall']                = 'Error 418: Something\'s gone wrong somewhere and we can\'t identify where. Sorry.';
$string['draft']                   = 'Draft';
$string['draftandsubmissionerror'] = 'You cannot select "revert to draft" and "submit on time" together';
$string['due']                     = 'Due';
$string['missingstudentid']        = 'The student identifier is missing for this user.';
$string['missinguseridentifier']   = 'There\'s an error with this plugin\'s admin settings. Please ask an admin to check the User Identifier.';
$string['multiplescans']           = 'Allow multiple scans';
$string['multiplescans_help']      = 'By selecting this option, selected checboxes will remain checked, allowing ' .
                                     'multiple scans without the checkboxes resetting.';
$string['navigationbreadcrumb']    = 'Physical submissions';
$string['notsubmitted']            = 'Not submitted';
$string['pageheading']             = 'Upload physical submissions';
$string['pluginname']              = 'Barcode scanning';
$string['privacy:metadata']        = 'The local barcode plugin only referecnes existing assignment & submission data already held in Moodle.';
$string['requiredbarcode']         = 'At least 1 barcode is required.';
$string['reverttodraft']           = 'Revert this submission back to draft';

// SU_AMEND START - Barcode: Updated email strings
//$string['reverttodraftemail']      = 'Your physical assignment has been reverted to draft, perhaps because it ' .
//                                     'has been handed back to you for further work. You will now need to re-submit ' .
//                                     'the assignment before the deadline. If you have any questions about this please ' .
//                                     'speak with your tutor. The link to the assignment in question can be found below: <br />' .
//                                     '<a href="{$a->linkurl}">{$a->linktext}</a>';
$string['reverttodraftemail']       = 'The physical assignment submission listed below was reverted to draft on {$a->timecreated}'.
                                        '. You will now need to ensure it is resubmitted before the deadline. If you' .
                                        ' have any questions about this assessment please speak with your tutor' .
                                        '. The affected assessment is <a href="{$a->linkurl}">{$a->linktext}</a>';
//$string['reverttodraftemailnonhtml'] = 'Your physical assignment, {$a->linktext} has been reverted to draft, perhaps because it ' .
//                                     'has been handed back to you for further work. You will now need to re-submit ' .
//                                     'the assignment before the deadline. If you have any questions about this please ' .
//                                     'speak with your tutor. The assignment can be viewed at {$a->linkurl}';
$string['reverttodraftemailnonhtml'] = 'The physical assignment submission listed below was reverted to draft on {$a->timecreated}'.
                                        '. You will now need to ensure it is resubmitted before the deadline. If you' .
                                        ' have any questions about this assessment please speak with your tutor' .
                                        '. The affected assessment is <a href="{$a->linkurl}">{$a->linktext}</a>';
// SU_AMEND END

$string['reverttodraftemailsubject'] = 'Submission reverted to draft';
$string['reverttodraftfromuser']   = '(System) Submission reverted to draft';
$string['reverttodraftresponse']   = 'This submission has been reverted back to draft';
$string['reverttodraftreplyname']  = 'No reply';
$string['reverttodraft_help']      = 'If an assignment has been submitted, this option allows the submission to be reverted ' .
                                     'back to draft status';
$string['scanned']                 = 'Scanned';
$string['student']                 = 'Student';
$string['submissionclosed']        = 'The assignment is closed for this submission';
$string['submissionnotfound']      = 'There\'s an error somewhere. This submission was not found in Moodle';
$string['submissionontime']        = 'The submission has been submitted on time';
$string['submissionsaved']         = 'Submission saved';
$string['submissionscanned']       = 'A physical submission has been scanned';
$string['submit']                  = 'Submit';
$string['submitontime']            = 'Allow late submission';
$string['submitontime_help']       = 'By selecting this checkbox, the submission will be submitted on the due date and time, ' .
                                     'marking the assignment as submitted on time and not as a late submission.';
$string['submitted']               = 'Submitted';
