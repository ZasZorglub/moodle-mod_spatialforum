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
 * Site administrator communication setup.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

use mod_spatialforum\local\communication;

admin_externalpage_setup('spatialforumcommunication');
require_capability('moodle/site:config', context_system::instance());
$form = new \mod_spatialforum\form\communication_form();
$form->set_data(communication::settings() + ['previewmode' => 1]);
$preview = null;
$feedback = null;
if ($data = $form->get_data()) {
    require_sesskey();
    $values = (array) $data;
    if (!empty($data->savepolicy)) {
        communication::save($values);
        redirect($PAGE->url, get_string('communicationsaved', 'mod_spatialforum'));
    }
    $preview = communication::preview($values, (int) $data->previewmode);
    if (!empty($data->sendtest)) {
        try {
            $accepted = communication::send_test($values, (int) $data->previewmode);
            $feedback = [$accepted ? 'communicationtestaccepted' : 'communicationtestfailed', $accepted ? 'success' : 'error'];
        } catch (\Throwable $e) {
            // No automatic retry: a transport exception may have happened after sending.
            debugging('Spatialforum communication test failed: ' . get_class($e), DEBUG_DEVELOPER);
            $feedback = ['communicationtestuncertain', 'warning'];
        }
    }
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('communicationtitle', 'mod_spatialforum'));
echo $OUTPUT->notification(get_string('communicationintro', 'mod_spatialforum'), 'info');
if ($feedback) {
    echo $OUTPUT->notification(get_string($feedback[0], 'mod_spatialforum'), $feedback[1]);
}
if ($preview) {
    echo $OUTPUT->heading(get_string('communicationpreview', 'mod_spatialforum'), 3);
    echo html_writer::tag('p', s($preview['subject']));
    echo html_writer::tag('pre', s($preview['text']), ['style' => 'white-space:pre-wrap']);
}
$form->display();
echo $OUTPUT->footer();
