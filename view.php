<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_spatialforum\local\forum;

$id = required_param('id', PARAM_INT);
$trackid = optional_param('trackid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('spatialforum', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('spatialforum', ['id' => $cm->instance], '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/spatialforum:view', $context);

if (!$trackid) {
    $trackid = $activity->roottrackid;
}
$track = $DB->get_record('spatialforum_tracks', [
    'id' => $trackid,
    'spatialforumid' => $activity->id,
], '*', MUST_EXIST);

$PAGE->set_url('/mod/spatialforum/view.php', ['id' => $cm->id, 'trackid' => $track->id]);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->js_call_amd('mod_spatialforum/spatialforum', 'init');
$PAGE->requires->js_call_amd('mod_spatialforum/excerpts', 'init');

spatialforum_view($activity, $course, $cm, $context);
$data = forum::export_view_data($activity, $cm, $track, $context, true);
$viewmode = optional_param('sfview', '', PARAM_ALPHA);
$data['initialview'] = in_array($viewmode, ['map', 'list'], true) ? $viewmode : '';
$openpostid = optional_param('postid', 0, PARAM_INT);
foreach ($data['posts'] as &$post) {
    $post['opened'] = $post['id'] == $openpostid;
    if ($data['initialview'] !== '') {
        $post['replyurl'] = (new moodle_url($post['replyurl'], ['sfview' => $data['initialview']]))->out(false);
    }
}
unset($post);
if ($data['initialview'] !== '') {
    $data['newposturl'] = (new moodle_url($data['newposturl'], ['sfview' => $data['initialview']]))->out(false);
}

echo $OUTPUT->header();
// Moodle's activity header already renders the module introduction.
echo $OUTPUT->render_from_template('mod_spatialforum/main', $data);
echo $OUTPUT->footer();
