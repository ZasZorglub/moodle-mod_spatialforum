<?php
// This file is part of Moodle - http://moodle.org/

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
            $mform->addElement('html', $OUTPUT->render_from_template('mod_spatialforum/excerpt_composer', []));
            $mform->addElement('advcheckbox', 'showparent', get_string('includeparent', 'mod_spatialforum'));
            $mform->addHelpButton('showparent', 'includeparent', 'mod_spatialforum');
            $mform->setDefault('showparent', 0);
        }

        $mform->addElement('editor', 'message_editor', get_string('message', 'mod_spatialforum'), null, [
            'maxfiles' => 0,
            'trusttext' => false,
        ]);
        $mform->setType('message_editor', PARAM_RAW);
        if (empty($customdata['originpostid'])) {
            $mform->addRule('message_editor', null, 'required', null, 'client');
        }

        $this->add_action_buttons(true, get_string($isbranch ? 'createbranch' : 'postreply', 'mod_spatialforum'));
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
        }
        $isbranch = ($this->_customdata['action'] ?? '') === 'branch';
        if ($isbranch && trim((string) ($data['branchname'] ?? '')) === '') {
            $errors['branchname'] = get_string('required');
        }
        $message = trim((string) ($data['message_editor']['text'] ?? ''));
        $hascomments = false;
        if (!empty($this->_customdata['origin'])) {
            try {
                $parts = \mod_spatialforum\local\excerpts::prepare((string) ($data['excerptdraft'] ?? '[]'),
                    $this->_customdata['origin'], $this->_customdata['context']);
                foreach (json_decode($parts, true) as $part) {
                    $hascomments = $hascomments || trim($part['comment']) !== '';
                }
            } catch (\moodle_exception $e) {
                $errors['message_editor'] = get_string('invalidexcerpt', 'mod_spatialforum');
            }
        }
        if (trim(strip_tags($message)) === '' && !$hascomments) {
            $errors['message_editor'] = get_string('required');
        }
        return $errors;
    }
}
