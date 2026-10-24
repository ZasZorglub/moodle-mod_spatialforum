<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

use mod_spatialforum\form\post_form;
use mod_spatialforum\local\forum;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', 'reply', PARAM_ALPHA);
$trackid = required_param('trackid', PARAM_INT);
$originpostid = optional_param('originpostid', 0, PARAM_INT);

if (!in_array($action, ['reply', 'branch'], true)) {
    throw new moodle_exception('invalidaction', 'mod_spatialforum');
}

$cm = get_coursemodule_from_id('spatialforum', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('spatialforum', ['id' => $cm->instance], '*', MUST_EXIST);
$track = $DB->get_record('spatialforum_tracks', [
    'id' => $trackid,
    'spatialforumid' => $activity->id,
], '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/spatialforum:view', $context);
require_capability($action === 'branch' ? 'mod/spatialforum:startbranch' : 'mod/spatialforum:reply', $context);

if ($activity->locked || $track->locked) {
    throw new moodle_exception('forumlocked', 'mod_spatialforum');
}
if ($originpostid) {
    $origin = $DB->get_record('spatialforum_posts', [
        'id' => $originpostid,
        'trackid' => $track->id,
        'deleted' => 0,
    ], '*', MUST_EXIST);
}

$returnurl = new moodle_url('/mod/spatialforum/view.php', ['id' => $cm->id, 'trackid' => $track->id]);
$PAGE->set_url('/mod/spatialforum/post.php', [
    'id' => $cm->id,
    'action' => $action,
    'trackid' => $track->id,
    'originpostid' => $originpostid,
]);
$PAGE->set_title(get_string($action === 'branch' ? 'createbranch' : 'postreply', 'mod_spatialforum'));
$PAGE->set_heading(format_string($course->fullname));

$form = new post_form(null, [
    'cmid' => $cm->id,
    'action' => $action,
    'trackid' => $track->id,
    'originpostid' => $originpostid,
]);

if ($action === 'reply' && !empty($origin)) {
    $form->set_data(['subject' => forum::reply_subject($origin->subject)]);
}

if ($form->is_cancelled()) {
    redirect($returnurl);
} elseif ($data = $form->get_data()) {
    require_sesskey();
    $transaction = $DB->start_delegated_transaction();
    $targettrackid = $track->id;
    if ($action === 'branch') {
        if (!$originpostid) {
            throw new moodle_exception('branchrequirespost', 'mod_spatialforum');
        }
        $targettrackid = forum::create_track($activity, $track, $originpostid, $data->branchname);
    }
    $message = $data->message_editor['text'];
    $format = $data->message_editor['format'];
    forum::create_post(
        $activity,
        $targettrackid,
        $USER->id,
        $data->subject,
        $message,
        $format,
        $action === 'reply' ? $originpostid : 0
    );
    $transaction->allow_commit();
    redirect(new moodle_url('/mod/spatialforum/view.php', [
        'id' => $cm->id,
        'trackid' => $targettrackid,
    ]), get_string('postsaved', 'mod_spatialforum'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($action === 'branch' ? 'createbranch' : 'postreply', 'mod_spatialforum'));
if ($action === 'reply' && !empty($origin)) {
    echo $OUTPUT->render_from_template('mod_spatialforum/reply_context',
        forum::export_reply_context($origin, $context, $cm->id));
}
$form->display();
echo $OUTPUT->footer();

