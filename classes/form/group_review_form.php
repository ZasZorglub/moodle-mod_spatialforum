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
 * Samtalespor spatial discussion activity.
 *
 * @package    mod_spatialforum
 * @copyright  2026 Anders Christian Sloth
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace mod_spatialforum\form;

/**
 * Explicit preview/confirmation for the audience of existing conversations.
 */
class group_review_form extends \moodleform {
    /**
     * Build the audience choice and required confirmation.
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement(
            'select',
            'audience',
            get_string('groupaudience', 'mod_spatialforum'),
            $this->_customdata['options']
        );
        $mform->setType('audience', PARAM_INT);
        $mform->addElement('advcheckbox', 'confirmed', get_string('confirmgroupaudience', 'mod_spatialforum'));
        $this->add_action_buttons(true, get_string('applygroupaudience', 'mod_spatialforum'));
    }

    /**
     * Reject a stale/forged group and require deliberate confirmation on the server.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (
            (int) ($data['audience'] ?? -999) === -999
            || !array_key_exists((int) $data['audience'], $this->_customdata['options'])
        ) {
            $errors['audience'] = get_string('groupaccessdenied', 'mod_spatialforum');
        }
        if (empty($data['confirmed'])) {
            $errors['confirmed'] = get_string('required');
        }
        return $errors;
    }
}
