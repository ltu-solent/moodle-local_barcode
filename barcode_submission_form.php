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
 * Barcode scanning form view
 * @package   local_barcode
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @author    Dez Glidden <dez.glidden@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once("$CFG->libdir/formslib.php");

/**
 * The Barcode Submission Form - create a new submission form
 *
 * @package   local_barcode
 * @copyright 2018 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class barcode_submission_form extends moodleform {
    /**
     * Set the new form definition
     * Sets the layout and logic for the barcode submission form, displaying the barcode
     * input field and the cancel and submit buttons
     * @return void
     */
    public function definition() {
        $ontimeavailable = get_config('assignsubmission_physical', 'submitontime');
        $mform = $this->_form;

        if (! empty($this->_customdata['error'])) {
            $mform->addElement('html', '<div class="form-group local-barcode-text-center local-barcode-has-danger" id="feedback-group">');
            $mform->addElement('html', '<span class="form-control-feedback" id="feedback">'.$this->_customdata['error'].'</span>');
            $mform->addElement('html', '</div>');
        }

        if (! empty($this->_customdata['success'])) {
            $mform->addElement('html', '<div class="form-group local-barcode-text-center local-barcode-has-success" id="feedback-group">');
            $mform->addElement('html',
                               '<span class="form-control-feedback" id="feedback">'.$this->_customdata['success'].'</span>');
            $mform->addElement('html', '</div>');
        }

        // Default feedback section that's displayed when there's no error or success
        // message. Used for js feedback.
        if (empty($this->_customdata['success']) && empty($this->_customdata['error'])) {
            $mform->addElement('html', '<div class="form-group local-barcode-text-center" id="feedback-group">');
            $mform->addElement('html', '<span class="form-control-feedback" id="feedback"></span>');
            $mform->addElement('html', '</div>');
        }

        $formgroup = array();
        $formgroup[] = $mform->createElement('text', 'barcode', get_string('barcode', 'local_barcode'), 'maxlength="20" size="20"');
        $mform->setType('barcode', PARAM_ALPHANUM);
        $mform->setDefault('barcode', $this->_customdata['barcode']);
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('html', '<div class="local-barcode-form">');
        $mform->addGroup($formgroup,  'barcodegroup', get_string('barcode', 'local_barcode'), ' ',  false);
        $mform->addElement('advcheckbox',
                           'reverttodraft',
                           get_string('reverttodraft', 'local_barcode'),
                           '',
                           array('class' => 'local-barcode-form-item'),
                           array(0, 1));

        $mform->addHelpButton('reverttodraft', 'reverttodraft', 'local_barcode');

        if ($ontimeavailable) {
            $mform->addElement('advcheckbox',
                               'submitontime',
                               get_string('allowsubmitontime', 'local_barcode'),
                               '',
                               array('class' => 'local-barcode-form-item'),
                               array(0, 1));
            $mform->addHelpButton('submitontime', 'submitontime', 'local_barcode');
        }

        $mform->addElement('advcheckbox',
                           'multiplescans',
                           get_string('allowmultiplescans', 'local_barcode'),
                           '',
                           array('class' => 'local-barcode-form-item'),
                           array(0, 1));

        $mform->addHelpButton('multiplescans', 'multiplescans', 'local_barcode');
        $mform->setType('multiplescans', PARAM_ALPHANUM);
        //$mform->setDefault('multiplescans', $this->_customdata['multiplescans']);
        $mform->setDefault('multiplescans', 1);
        $mform->hardFreeze('multiplescans');
        $mform->addElement('html', '</div>');
        $mform->addElement('hidden', 'cmid', $this->_customdata['cmid']);
        $this->add_action_buttons(true, get_string('submit', 'local_barcode'));
//         $this->add_action_buttons(true, 'submit');
    }
}
