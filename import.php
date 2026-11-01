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
 * Whole-forum preview, explicit hidden-copy confirmation and progress/recovery.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use mod_spatialforum\local\forum_import;

require_login();
$jobid = optional_param('jobid', 0, PARAM_INT);
$courseid = $jobid ? $DB->get_field('spatialforum_imports', 'courseid', ['id' => $jobid], MUST_EXIST)
    : required_param('courseid', PARAM_INT);
$course = get_course($courseid);
require_course_login($course, false);
$context = context_course::instance($courseid);
require_capability('mod/spatialforum:import', $context);
require_capability('moodle/course:manageactivities', $context);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_url('/mod/spatialforum/import.php', $jobid ? ['jobid' => $jobid] : ['courseid' => $courseid]);
$PAGE->set_title(get_string('importforum', 'mod_spatialforum'));
$PAGE->set_heading(format_string($course->fullname));
$action = optional_param('action', '', PARAM_ALPHA);
$preview = null;
$form = null;
$error = '';
if ($jobid) {
    $job = forum_import::job($jobid);
    if ($action !== '' && data_submitted()) {
        require_sesskey();
        if (
            !in_array($action, ['retry', 'discard'], true)
                || ($action === 'discard' && !optional_param('approved', 0, PARAM_BOOL))
        ) {
            throw new moodle_exception('importapprovalrequired', 'mod_spatialforum');
        }
        forum_import::recover($jobid, $action === 'discard');
        redirect($action === 'discard' ? new moodle_url('/course/view.php', ['id' => $courseid]) : $PAGE->url);
    }
} else {
    $sources = [];
    foreach (get_fast_modinfo($course)->get_instances_of('forum') as $cm) {
        if ($cm->uservisible && has_capability('mod/forum:viewdiscussion', context_module::instance($cm->id))) {
            $sources[$cm->id] = format_string($cm->name);
        }
    }
    if ($action === 'confirm') {
        $sourcecmid = required_param('sourcecmid', PARAM_INT);
        get_coursemodule_from_id('forum', $sourcecmid, $courseid, false, MUST_EXIST);
        $form = new \mod_spatialforum\form\import_form(null, ['confirm' => true, 'courseid' => $courseid,
            'sourcecmid' => $sourcecmid, 'name' => required_param('name', PARAM_TEXT),
            'fingerprint' => required_param('fingerprint', PARAM_ALPHANUM)]);
        if ($form->is_cancelled()) {
            redirect(new moodle_url('/mod/spatialforum/import.php', ['courseid' => $courseid]));
        }
        if ($data = $form->get_data()) {
            require_sesskey();
            try {
                $id = forum_import::confirm($sourcecmid, $data->fingerprint, $data->name, !empty($data->approved));
                redirect(new moodle_url('/mod/spatialforum/import.php', ['jobid' => $id]));
            } catch (moodle_exception $e) {
                $error = $e->getMessage();
            }
        }
        $preview = forum_import::preview($sourcecmid);
        $preview['targetname'] = required_param('name', PARAM_TEXT);
    } else {
        $form = new \mod_spatialforum\form\import_form(null, ['courseid' => $courseid, 'sources' => $sources]);
        if ($form->is_cancelled()) {
            redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
        }
        if ($data = $form->get_data()) {
            require_sesskey();
            get_coursemodule_from_id('forum', $data->sourcecmid, $courseid, false, MUST_EXIST);
            $preview = forum_import::preview($data->sourcecmid);
            $preview['targetname'] = $data->name;
            $form = $preview['issues'] ? null : new \mod_spatialforum\form\import_form(null, [
                'confirm' => true, 'courseid' => $courseid, 'sourcecmid' => $data->sourcecmid,
                'name' => $data->name, 'fingerprint' => $preview['fingerprint'],
            ]);
        }
    }
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importforum', 'mod_spatialforum'));
echo $OUTPUT->notification(get_string('importintro', 'mod_spatialforum'), 'info');
if ($error) {
    echo $OUTPUT->notification($error, 'error');
}
if ($jobid) {
    $states = ['importcopying', 'importrewriting', 'importfailed', 'importcomplete', 'importcancelled'];
    echo $OUTPUT->heading(get_string($states[(int) $job->status], 'mod_spatialforum'), 3);
    echo html_writer::tag('p', get_string(
        'importprogress',
        'mod_spatialforum',
        (object) ['copied' => $job->copiedposts, 'total' => $job->totalposts]
    ));
    if ((int) $job->status === forum_import::COMPLETE) {
        $cm = get_coursemodule_from_instance('spatialforum', $job->targetid, $courseid, false, MUST_EXIST);
        echo $OUTPUT->notification(get_string('importreview', 'mod_spatialforum'), 'success');
        echo html_writer::link(
            new moodle_url('/mod/spatialforum/view.php', ['id' => $cm->id]),
            get_string('importopencopy', 'mod_spatialforum'),
            ['class' => 'btn btn-primary']
        );
    } else {
        echo html_writer::tag('p', html_writer::link($PAGE->url, get_string('refresh')));
        if ((int) $job->status === forum_import::FAILED) {
            echo $OUTPUT->notification(get_string('importrecovery', 'mod_spatialforum'), 'warning');
            echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::tag(
                'button',
                get_string('importretry', 'mod_spatialforum'),
                ['name' => 'action', 'value' => 'retry', 'class' => 'btn btn-secondary']
            );
            echo html_writer::end_tag('form');
        }
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::tag('label', html_writer::empty_tag(
            'input',
            ['type' => 'checkbox', 'name' => 'approved', 'value' => 1, 'required' => 'required']
        ) . ' '
                . get_string('importdiscardconfirm', 'mod_spatialforum'));
        echo html_writer::tag(
            'button',
            get_string('importdiscard', 'mod_spatialforum'),
            ['name' => 'action', 'value' => 'discard', 'class' => 'btn btn-outline-danger']
        );
        echo html_writer::end_tag('form');
    }
} else {
    if ($preview) {
        echo $OUTPUT->heading(s($preview['source']->name), 3);
        echo html_writer::tag('p', s(get_string('importtargetname', 'mod_spatialforum')) . ': ' . s($preview['targetname']));
        echo html_writer::tag('p', get_string('importcounts', 'mod_spatialforum', (object) $preview));
        echo $OUTPUT->notification(get_string('importexcluded', 'mod_spatialforum'), 'info');
        foreach ($preview['issues'] as $issue) {
            echo $OUTPUT->notification(get_string('importissue' . $issue, 'mod_spatialforum'), 'error');
        }
        echo html_writer::tag('p', html_writer::link(
            new moodle_url('/mod/spatialforum/import.php', ['courseid' => $courseid]),
            get_string('importnewpreview', 'mod_spatialforum')
        ));
    }
    if ($form) {
        $form->display();
    }
}
echo $OUTPUT->footer();
