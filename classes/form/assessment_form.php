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
 * Nullable manual grade and plain-text private feedback.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_spatialforum\form;

/**
 * One participant's whole-activity assessment with an immutable form revision token.
 */
class assessment_form extends \moodleform {
    /**
     * Construct grade/scale controls without exposing other participants.
     */
    public function definition(): void {
        $mform = $this->_form;
        foreach (['id', 'userid'] as $key) {
            $mform->addElement('hidden', $key, $this->_customdata[$key]);
            $mform->setType($key, PARAM_INT);
        }
        $mform->addElement('hidden', 'revision', $this->_customdata['state']['token']);
        $mform->setType('revision', PARAM_ALPHANUM);
        $item = $this->_customdata['state']['item'];
        if ((int) $item->gradetype === GRADE_TYPE_SCALE) {
            $scale = $item->load_scale();
            $choices = ['' => get_string('notgraded', 'mod_spatialforum')];
            foreach ($scale->scale_items as $index => $label) {
                $choices[$index + 1] = $label;
            }
            $mform->addElement('select', 'score', get_string('manualgrade', 'mod_spatialforum'), $choices);
        } else {
            $maximum = format_float($item->grademax, 5, true, true);
            $mform->addElement('text', 'score', get_string('manualgradepoints', 'mod_spatialforum', $maximum));
        }
        $mform->setType('score', PARAM_RAW_TRIMMED);
        $mform->addElement('static', 'scorehint', '', get_string('manualgradehint', 'mod_spatialforum'));
        $mform->addElement('textarea', 'feedback', get_string('privatefeedback', 'mod_spatialforum'), ['rows' => 8, 'cols' => 70]);
        $mform->setType('feedback', PARAM_RAW);
        $this->add_action_buttons(true, get_string('saveassessment', 'mod_spatialforum'));
    }

    /**
     * Validate presentation values; the save API repeats checks on fresh Gradebook state.
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        try {
            \mod_spatialforum\local\grading::parse_score($this->_customdata['state']['item'], (string) $data['score']);
        } catch (\moodle_exception $e) {
            $errors['score'] = $e->getMessage();
        }
        if (\core_text::strlen($data['feedback'] ?? '') > 10000) {
            $errors['feedback'] = get_string('gradefeedbacklimit', 'mod_spatialforum');
        }
        return $errors;
    }
}
