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

        if (!empty($customdata['originpostid'])) {
            global $OUTPUT;
            $mform->addElement('hidden', 'excerptdraft', '[]');
            $mform->setType('excerptdraft', PARAM_RAW);
            $composer = $OUTPUT->render_from_template(
                'mod_spatialforum/excerpt_composer',
                ['excerptmode' => !empty($customdata['excerptmode'])]
            );
            if (!empty($customdata['excerptmode'])) {
                $mform->addElement('html', $composer);
            }
        }

        $messagelabel = !empty($customdata['excerptmode']) ? 'closingcomment' : 'message';
        $options = isset($customdata['context'])
            ? \mod_spatialforum\local\media::editor_options($customdata['context'])
            : ['maxfiles' => 10, 'trusttext' => false];
        $mform->addElement('editor', 'message_editor', get_string($messagelabel, 'mod_spatialforum'), null, $options);
        $mform->setType('message_editor', PARAM_RAW);
        // Server validation recognises media-only posts; generic text-required checks do not.
        if (!empty($customdata['originpostid'])) {
            if (empty($customdata['excerptmode'])) {
                $mform->addElement('html', $composer);
            }
            $mform->addElement('html', '<details class="spatialforum-quote-options"><summary>' .
                get_string('quoteoptions', 'mod_spatialforum') . '</summary>');
            $mform->addElement('advcheckbox', 'showparent', get_string('includeparent', 'mod_spatialforum'));
            $mform->addHelpButton('showparent', 'includeparent', 'mod_spatialforum');
            $mform->setDefault('showparent', 0);
            $mform->addElement('html', '</details>');
        }

        $submitlabel = $isbranch ? 'createbranch' : (empty($customdata['originpostid']) ? 'createpost' : 'postreply');
        $this->add_action_buttons(true, get_string($submitlabel, 'mod_spatialforum'));
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
        $hascomments = false;
        if (!empty($this->_customdata['origin'])) {
            try {
                $parts = \mod_spatialforum\local\excerpts::prepare(
                    (string) ($data['excerptdraft'] ?? '[]'),
                    $this->_customdata['origin'],
                    $this->_customdata['context']
                );
                foreach (json_decode($parts, true) as $part) {
                    // Quote comments are plain text, so escape markup before checking visible content.
                    $hascomments = $hascomments || \mod_spatialforum\local\media::has_content(s($part['comment']));
                }
            } catch (\moodle_exception $e) {
                $errors['message_editor'] = get_string('invalidexcerpt', 'mod_spatialforum');
            }
        }
        if (!\mod_spatialforum\local\media::has_content($message) && !$hascomments) {
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
