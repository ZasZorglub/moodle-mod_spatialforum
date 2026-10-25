<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

use mod_spatialforum\form\post_form;
use mod_spatialforum\local\forum;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', 'reply', PARAM_ALPHA);
$trackid = required_param('trackid', PARAM_INT);
$originpostid = optional_param('originpostid', 0, PARAM_INT);
$viewmode = optional_param('sfview', '', PARAM_ALPHA);
$viewmode = in_array($viewmode, ['map', 'list'], true) ? $viewmode : '';
$excerptmode = optional_param('startquote', 0, PARAM_BOOL)
    || optional_param('excerptdraft', '[]', PARAM_RAW) !== '[]';

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
$createstrack = $action === 'branch' || $originpostid !== 0;
if ($createstrack) {
    require_capability('mod/spatialforum:startbranch', $context);
    if (!$originpostid) {
        throw new moodle_exception('branchrequirespost', 'mod_spatialforum');
    }
    if (count(forum::get_path($track->id, $activity->id)) >= $activity->maxdepth) {
        throw new moodle_exception('maxdepthreached', 'mod_spatialforum');
    }
}

$returnurl = new moodle_url('/mod/spatialforum/view.php', ['id' => $cm->id, 'trackid' => $track->id]);
if ($originpostid) {
    $returnurl->param('postid', $originpostid);
    $returnurl->set_anchor('post-' . $originpostid);
}
if ($viewmode) {
    $returnurl->param('sfview', $viewmode);
}
$pagelabel = $action === 'branch' ? 'createbranch' : ($originpostid ? 'postreply' : 'newpost');
$PAGE->set_url('/mod/spatialforum/post.php', [
    'id' => $cm->id,
    'action' => $action,
    'trackid' => $track->id,
    'originpostid' => $originpostid,
]);
$PAGE->set_title(get_string($pagelabel, 'mod_spatialforum'));
$PAGE->set_heading(format_string($course->fullname));

$form = new post_form(null, [
    'cmid' => $cm->id,
    'action' => $action,
    'trackid' => $track->id,
    'originpostid' => $originpostid,
    'newtrack' => $createstrack,
    'origin' => $origin ?? null,
    'context' => $context,
    'sfview' => $viewmode,
    'excerptmode' => $excerptmode,
]);

if ($action === 'reply' && !empty($origin)) {
    $defaults = ['subject' => forum::reply_subject($origin->subject)];
    if (optional_param('startquote', 0, PARAM_BOOL)) {
        require_sesskey();
        $selection = optional_param('initialexcerpt', '', PARAM_RAW);
        $draft = json_encode([['quote' => $selection, 'comment' => '']], JSON_UNESCAPED_UNICODE);
        \mod_spatialforum\local\excerpts::prepare($draft, $origin, $context);
        $defaults['excerptdraft'] = $draft;
    }
    $form->set_data($defaults);
}
$PAGE->requires->js_call_amd('mod_spatialforum/excerpts', 'init');

if ($form->is_cancelled()) {
    redirect($returnurl);
} elseif ($data = $form->get_data()) {
    require_sesskey();
    $targettrackid = $track->id;
    $message = $data->message_editor['text'];
    $format = $data->message_editor['format'];
    if ($createstrack) {
        $targettrackid = forum::reply_in_new_track($activity, $track, $originpostid, $USER->id,
            $action === 'branch' ? $data->branchname : $data->subject,
            $data->subject, $message, $format, !empty($data->showparent), (string) ($data->excerptdraft ?? '[]'));
        $newpostid = $DB->get_field('spatialforum_posts', 'id', ['trackid' => $targettrackid], MUST_EXIST);
    } else {
        $newpostid = forum::create_post($activity, $targettrackid, $USER->id, $data->subject, $message, $format);
    }
    redirect(new moodle_url('/mod/spatialforum/view.php', [
        'id' => $cm->id,
        'trackid' => $targettrackid,
        'postid' => $newpostid,
        'sfview' => $viewmode,
    ]), get_string('postsaved', 'mod_spatialforum'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($pagelabel, 'mod_spatialforum'));
if (!empty($origin)) {
    $origincontext = forum::export_reply_context($origin, $context, $cm->id);
    $origincontext['compact'] = true;
    $origincontext['excerptmode'] = $excerptmode;
    echo $OUTPUT->render_from_template('mod_spatialforum/reply_context',
        $origincontext);
}
$form->display();
echo $OUTPUT->footer();

