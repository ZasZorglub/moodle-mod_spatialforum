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
 * Administrator communication policy, templates, preview and self-test controls.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_spatialforum\form;

/**
 * Administrator-only communication settings and explicit preview/test actions.
 */
class communication_form extends \moodleform {
    /**
     * Define labelled configuration and explicit actions.
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('header', 'rules', get_string('communicationrules', 'mod_spatialforum'));
        foreach (['rootenabled', 'replyenabled', 'immediateenabled', 'dailyenabled'] as $key) {
            $mform->addElement('advcheckbox', $key, get_string('communication' . $key, 'mod_spatialforum'));
            $mform->setType($key, PARAM_INT);
        }
        $mform->addElement('text', 'delayminutes', get_string('communicationdelayminutes', 'mod_spatialforum'), ['size' => 5]);
        $mform->setType('delayminutes', PARAM_RAW_TRIMMED);
        $hours = array_combine(range(0, 23), range(0, 23));
        $mform->addElement('select', 'digesthour', get_string('digesthour', 'mod_spatialforum'), $hours);
        $mform->addElement(
            'select',
            'digestminute',
            get_string('communicationdigestminute', 'mod_spatialforum'),
            array_combine(range(0, 59), array_map(fn($minute) => sprintf('%02d', $minute), range(0, 59)))
        );
        $mform->addElement('select', 'digesttimezone', get_string('communicationdigesttimezone', 'mod_spatialforum'), [
            'recipient' => get_string('communicationrecipienttimezone', 'mod_spatialforum'),
            'site' => get_string('communicationsitetimezone', 'mod_spatialforum', \core_date::get_server_timezone()),
        ]);
        $mform->addElement('static', 'queuehint', '', get_string('communicationqueuehint', 'mod_spatialforum'));
        $mform->addElement('header', 'templates', get_string('communicationtemplates', 'mod_spatialforum'));
        foreach (\mod_spatialforum\local\communication::variables() as $key => $variables) {
            $type = str_ends_with($key, 'subject') ? 'text' : 'textarea';
            $mform->addElement(
                $type,
                $key,
                get_string('communication' . $key, 'mod_spatialforum'),
                $type === 'text' ? ['size' => 70, 'maxlength' => 200] : ['rows' => 6, 'cols' => 70]
            );
            $mform->setType($key, PARAM_RAW);
            $names = implode(', ', array_map(fn($name) => '{{' . $name . '}}', $variables));
            $mform->addElement(
                'static',
                $key . 'variables',
                '',
                get_string('communicationvariables', 'mod_spatialforum', $names)
            );
        }
        $mform->addElement('header', 'testing', get_string('communicationtesting', 'mod_spatialforum'));
        $mform->addElement('select', 'previewmode', get_string('communicationpreviewmode', 'mod_spatialforum'), [
            1 => get_string('subscriptionimmediate', 'mod_spatialforum'), 2 => get_string('subscriptiondaily', 'mod_spatialforum'),
        ]);
        $mform->setType('previewmode', PARAM_INT);
        $mform->addElement('static', 'testhint', '', get_string('communicationtesthint', 'mod_spatialforum'));
        $buttons = [];
        $actions = ['savepolicy' => 'savechanges', 'preview' => 'communicationpreview', 'sendtest' => 'communicationsendtest'];
        foreach ($actions as $key => $label) {
            $component = $key === 'savepolicy' ? 'moodle' : 'mod_spatialforum';
            $buttons[] = $mform->createElement('submit', $key, get_string($label, $component));
        }
        $mform->addGroup($buttons, 'actions', '', ' ', false);
        $mform->closeHeaderBefore('actions');
    }

    /**
     * All actions validate the same unsaved values and do not implicitly persist previews/tests.
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files) + \mod_spatialforum\local\communication::validate($data);
        if (!in_array((int) ($data['previewmode'] ?? 0), [1, 2], true)) {
            $errors['previewmode'] = get_string('invaliddata', 'error');
        }
        return $errors;
    }
}
