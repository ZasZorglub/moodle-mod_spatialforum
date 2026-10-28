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


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Activity settings form.
 *
 * @package mod_spatialforum
 */
class mod_spatialforum_mod_form extends moodleform_mod {
    /**
     * Defines the form.
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('spatialforumname', 'mod_spatialforum'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $depthoptions = array_combine(range(3, 12), range(3, 12));
        $mform->addElement('select', 'maxdepth', get_string('maxdepth', 'mod_spatialforum'), $depthoptions);
        $mform->setDefault('maxdepth', 8);
        $mform->addHelpButton('maxdepth', 'maxdepth', 'mod_spatialforum');

        $mform->addElement('advcheckbox', 'enablecolors', get_string('enablecolors', 'mod_spatialforum'));
        $mform->setDefault('enablecolors', 1);

        $mform->addElement('advcheckbox', 'enablelikes', get_string('enablelikes', 'mod_spatialforum'));
        $mform->setDefault('enablelikes', 1);
        $mform->addHelpButton('enablelikes', 'enablelikes', 'mod_spatialforum');
        if (get_config('mod_spatialforum', 'allowlikes') === '0') {
            $mform->addElement('static', 'likesdisabled', '', get_string('likesdisabledsite', 'mod_spatialforum'));
        }

        $mform->addElement('advcheckbox', 'locked', get_string('lockforum', 'mod_spatialforum'));
        $mform->setDefault('locked', 0);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
