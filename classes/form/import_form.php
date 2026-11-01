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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Explicit source selection and confirmation of an inspected import preview.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_spatialforum\form;

/**
 * Both steps are session-key protected Moodle forms; preview never creates a job.
 */
class import_form extends \moodleform {
    /**
     * Keep the preview submission separate from the subsequent confirmation form.
     * @return string
     */
    protected function get_form_identifier() {
        return parent::get_form_identifier() . (!empty($this->_customdata['confirm']) ? '_confirm' : '_select');
    }
    /**
     * Present the selected stage without accepting an actor or target course from the client.
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);
        $confirm = !empty($this->_customdata['confirm']);
        $mform->addElement('hidden', 'action', $confirm ? 'confirm' : 'preview');
        $mform->setType('action', PARAM_ALPHA);
        if ($confirm) {
            foreach (['sourcecmid' => PARAM_INT, 'name' => PARAM_TEXT, 'fingerprint' => PARAM_ALPHANUM] as $key => $type) {
                $mform->addElement('hidden', $key, $this->_customdata[$key]);
                $mform->setType($key, $type);
            }
            $mform->addElement('advcheckbox', 'approved', get_string('importapprove', 'mod_spatialforum'));
            $mform->setType('approved', PARAM_INT);
        } else {
            $mform->addElement(
                'select',
                'sourcecmid',
                get_string('importsource', 'mod_spatialforum'),
                $this->_customdata['sources']
            );
            $mform->setType('sourcecmid', PARAM_INT);
            $mform->addElement('text', 'name', get_string('importtargetname', 'mod_spatialforum'), ['size' => 64]);
            $mform->setType('name', PARAM_TEXT);
            $mform->addRule('name', null, 'required', null, 'client');
            $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        }
        $this->add_action_buttons(true, get_string($confirm ? 'importcreate' : 'importpreview', 'mod_spatialforum'));
    }

    /**
     * An explicit checkbox is required to copy the reviewed source.
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!empty($this->_customdata['confirm']) && empty($data['approved'])) {
            $errors['approved'] = get_string('importapprovalrequired', 'mod_spatialforum');
        }
        return $errors;
    }
}
