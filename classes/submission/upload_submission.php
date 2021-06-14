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
 * This file contains the class for uploading barcode submissions
 *
 * @package   local_barcode
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @author    Dez Glidden <dez.glidden@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// namespace local_barcode\submission;


defined('MOODLE_INTERNAL') || die();


require_once($CFG->dirroot . '/local/barcode/externallib.php');

/**
 * Upload physical submission via a web service
 *
 * @package   local_barcode
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_submission {

    /**
     * The name of the plugin function to use for submissions
     * @var string
     */
    private $functionname = 'local_barcode_save_barcode_submission';
    /**
     * The domain name for the application. eg. http://www.example.com
     * @var string
     */
    private $domainname;
    /**
     * The auth token parameter
     * @var string
     */
    private $token;
    /**
     * The barcode parameter
     * @var string
     */
    private $barcode;
    /**
     * The revert to draft value
     * @var string
     */
    private $revert;
    /**
     * Mark the submission as on time, rather than late
     * @var string
     */
    private $ontime;


    /**
     * Set the domain name, token and the barcode values
     */
    public function __construct($data) {
        global $CFG;

        $this->domainname = $CFG->wwwroot;
        $this->token      = $this->get_wstoken();
        $this->barcode    = $data->barcode;
        $this->revert     = $data->revert;
        $this->ontime     = $data->ontime;
    }


    /**
     * Get the web service authentication token
     */
    private function get_wstoken() {
        global $DB;

        $sql = 'SELECT et.token
                  FROM {external_tokens} et
                  JOIN {external_services} es ON es.id = et.externalserviceid
                 WHERE es.name = ?';

        return $DB->get_field_sql($sql, array('Barcode Scanning'), IGNORE_MULTIPLE);
    }

    function get_due_date() {
      global $DB;

      $sql = 'SELECT a.duedate
                FROM {assign} a
                JOIN {assignsubmission_barcode} b ON b.assignmentid = a.id
               WHERE b.barcode = ?';

      return $DB->get_field_sql($sql, array($this->barcode), IGNORE_MULTIPLE);
    }


    /**
     * Save the barcode submission in the database and echo the data
     *
     * @return object   Status object confirming either 200 or 404
     */
    public function save_submission() {

        $data = local_barcode_external::save_barcode_submission($this->barcode, $this->revert, $this->ontime);

        $resp = new stdClass();
        $resp->code = $data['data']['code'];
        $resp->message = $data['data']['message'];
        $resp->assignment = $data['data']['assignment'];
        $resp->assignmentdescription = "";
        $resp->course = $data['data']['course'];
        $resp->duedate = date("F j, Y, g:i a", ($this->get_due_date()));
        $resp->idformat = "Student ID";
        $resp->studentid = $data['data']['studentid'];
        $resp->studentname = $data['data']['studentname'];
        $resp->submissiontime = date("F j, Y, g:i a");
        $resp->islate = $this->ontime;
        $resp->reverted = $this->revert;

        echo json_encode($resp);
    }
}
