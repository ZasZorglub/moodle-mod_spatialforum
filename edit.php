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
 * Edit an authored contribution without detaching its replies.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

use mod_spatialforum\local\media;
use mod_spatialforum\local\post_lifecycle;

$id = required_param('id', PARAM_INT);
$postid = required_param('postid', PARAM_INT);
$cm = get_coursemodule_from_id('spatialforum', $id, 0, false, MUST_EXIST);
require_course_login(get_course($cm->course), false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/spatialforum:view', $context);
$post = $DB->get_record('spatialforum_posts', ['id' => $postid, 'spatialforumid' => $cm->instance], '*', MUST_EXIST);
\mod_spatialforum\local\group_access::for_activity($cm->instance)->require_post($post, true);
if (!post_lifecycle::can_edit($post, $context)) {
    throw new moodle_exception('cannoteditpost', 'mod_spatialforum');
}
$PAGE->set_url('/mod/spatialforum/edit.php', ['id' => $id, 'postid' => $postid]);
$PAGE->set_title(get_string('editpost', 'mod_spatialforum'));
$PAGE->set_heading(format_string(get_course($cm->course)->fullname));
$PAGE->activityheader->set_description('');
$draftscope = implode(':', [$USER->id, $cm->id, 'edit', $postid]);
$PAGE->requires->js_call_amd('mod_spatialforum/drafts', 'init', [[
    'scope' => $draftscope, 'restore' => $_SERVER['REQUEST_METHOD'] === 'GET',
    'ready' => get_string('draftready', 'mod_spatialforum'),
    'saved' => get_string('draftsaved', 'mod_spatialforum'),
    'available' => get_string('draftavailable', 'mod_spatialforum'),
    'restoreLabel' => get_string('draftrestore', 'mod_spatialforum'),
    'discardLabel' => get_string('draftdiscard', 'mod_spatialforum'),
    'unavailable' => get_string('draftunavailable', 'mod_spatialforum'),
]]);
$form = new \mod_spatialforum\form\post_form(null, [
    'submissionkey' => optional_param('submissionkey', bin2hex(random_bytes(32)), PARAM_ALPHANUM),
    'editing' => true, 'postid' => $postid,
    'revision' => optional_param('revision', post_lifecycle::revision($post), PARAM_ALPHANUM),
    'context' => $context, 'cmid' => $id, 'groupid' => $post->groupid,
    'action' => 'edit', 'trackid' => $post->trackid, 'originpostid' => $post->replytoid,
]);
$return = new moodle_url('/mod/spatialforum/view.php', ['id' => $id, 'postid' => $postid, 'trackid' => $post->trackid]);
$return->set_anchor('post-' . $postid);
if ($form->is_cancelled()) {
    redirect($return);
}
if ($data = $form->get_data()) {
    require_sesskey();
    try {
        post_lifecycle::edit($cm, $postid, $data->revision, $data);
        $SESSION->spatialforumdraftclear = ['scope' => $draftscope, 'clear' => $data->submissionkey];
        redirect($return, get_string('changessaved'));
    } catch (moodle_exception $e) {
        if ($e->errorcode !== 'postchanged') {
            throw $e;
        }
        $editerror = $e->getMessage();
    }
}
if (!$form->is_submitted()) {
    $prepared = file_prepare_standard_editor(
        clone $post,
        'message',
        media::editor_options($context),
        $context,
        'mod_spatialforum',
        'post',
        $postid
    );
    $prepared->id = $cm->id;
    $form->set_data($prepared);
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('editpost', 'mod_spatialforum'));
if (!empty($editerror)) {
    echo $OUTPUT->notification($editerror, 'warning');
    echo html_writer::link($return, get_string('reviewcurrentpost', 'mod_spatialforum'));
}
echo $OUTPUT->notification(get_string('editposthelp', 'mod_spatialforum'), 'info');
$form->display();
echo $OUTPUT->footer();
