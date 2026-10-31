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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for replies and new conversation tracks.
 *
 * @package mod_spatialforum
 */
class post_form extends \moodleform {
    /**
     * Defines the form.
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'submissionkey', $this->_customdata['submissionkey'] ?? bin2hex(random_bytes(32)));
        $mform->setType('submissionkey', PARAM_ALPHANUM);
        if (!empty($this->_customdata['editing'])) {
            $mform->addElement('hidden', 'postid', $this->_customdata['postid']);
            $mform->setType('postid', PARAM_INT);
            $mform->addElement('hidden', 'revision', $this->_customdata['revision']);
            $mform->setType('revision', PARAM_ALPHANUM);
        }
        $mform->addElement('hidden', 'group', $this->_customdata['groupid'] ?? 0);
        $mform->setType('group', PARAM_INT);
        $customdata = $this->_customdata;
        $isbranch = $customdata['action'] === 'branch';

        $mform->addElement('hidden', 'id', $customdata['cmid']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'action', $customdata['action']);
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('hidden', 'trackid', $customdata['trackid']);
        $mform->setType('trackid', PARAM_INT);
        $mform->addElement('hidden', 'originpostid', $customdata['originpostid']);
        $mform->setType('originpostid', PARAM_INT);
        $mform->addElement('hidden', 'sfview', $customdata['sfview'] ?? '');
        $mform->setType('sfview', PARAM_ALPHA);

        if ($isbranch) {
            $mform->addElement('text', 'branchname', get_string('branchname', 'mod_spatialforum'), ['size' => 64]);
            $mform->setType('branchname', PARAM_TEXT);
            $mform->addRule('branchname', null, 'required', null, 'client');
            $mform->addRule('branchname', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        }

        $mform->addElement('text', 'subject', get_string('subject', 'mod_spatialforum'), ['size' => 64]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', null, 'required', null, 'client');
        $mform->addRule('subject', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $options = isset($customdata['context'])
            ? \mod_spatialforum\local\media::editor_options($customdata['context'])
            : ['maxfiles' => 10, 'trusttext' => false];
        $mform->addElement('editor', 'message_editor', get_string('message', 'mod_spatialforum'), null, $options);
        $mform->setType('message_editor', PARAM_RAW);
        // Server validation recognises media-only posts; generic text-required checks do not.
        if (!empty($customdata['originpostid'])) {
            $mform->addElement('advcheckbox', 'showparent', get_string('includeparent', 'mod_spatialforum'));
            $mform->addHelpButton('showparent', 'includeparent', 'mod_spatialforum');
            $mform->setDefault('showparent', 0);
        }

        $submitlabel = $isbranch ? 'createbranch' : (empty($customdata['originpostid']) ? 'createpost' : 'postreply');
        if (!empty($customdata['allowfollow'])) {
            $mform->addElement('advcheckbox', 'followthread', get_string('followthread', 'mod_spatialforum'));
            $mform->setDefault('followthread', $customdata['followdefault'] ?? 1);
        }
        $label = !empty($customdata['editing']) ? get_string('savechanges') : get_string($submitlabel, 'mod_spatialforum');
        $this->add_action_buttons(true, $label);
    }

    /**
     * Server-side validation for required text fields.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (trim((string) ($data['subject'] ?? '')) === '') {
            $errors['subject'] = get_string('required');
        } else if (\core_text::strlen(trim($data['subject'])) > 255) {
            $errors['subject'] = get_string('maximumchars', '', 255);
        }
        $isbranch = ($this->_customdata['action'] ?? '') === 'branch';
        if ($isbranch && trim((string) ($data['branchname'] ?? '')) === '') {
            $errors['branchname'] = get_string('required');
        } else if ($isbranch && \core_text::strlen(trim($data['branchname'])) > 255) {
            $errors['branchname'] = get_string('maximumchars', '', 255);
        }
        $message = trim((string) ($data['message_editor']['text'] ?? ''));
        if (!\mod_spatialforum\local\media::has_content($message)) {
            $errors['message_editor'] = get_string('required');
        }
        if (isset($this->_customdata['context'])) {
            $options = \mod_spatialforum\local\media::editor_options($this->_customdata['context']);
            if (
                $error = \mod_spatialforum\local\media::validate_draft(
                    (int) ($data['message_editor']['itemid'] ?? 0),
                    $options
                )
            ) {
                $errors['message_editor'] = $error;
            }
        }
        return $errors;
    }
}
