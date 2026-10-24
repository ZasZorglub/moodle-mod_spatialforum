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
        $isbranch = !empty($customdata['newtrack']) || $customdata['action'] === 'branch';

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
            $mform->addElement('advcheckbox', 'showparent', get_string('includeparent', 'mod_spatialforum'));
            $mform->addHelpButton('showparent', 'includeparent', 'mod_spatialforum');
            $mform->setDefault('showparent', 0);
        }

        $mform->addElement('editor', 'message_editor', get_string('message', 'mod_spatialforum'), null, [
            'maxfiles' => 0,
            'trusttext' => false,
        ]);
        $mform->setType('message_editor', PARAM_RAW);
        $mform->addRule('message_editor', null, 'required', null, 'client');

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
        $isbranch = !empty($this->_customdata['newtrack']) || ($data['action'] ?? '') === 'branch';
        if ($isbranch && trim((string) ($data['branchname'] ?? '')) === '') {
            $errors['branchname'] = get_string('required');
        }
        $message = trim((string) ($data['message_editor']['text'] ?? ''));
        if ($message === '') {
            $errors['message_editor'] = get_string('required');
        }
        return $errors;
    }
}
