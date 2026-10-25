<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

use mod_spatialforum\local\forum;

// Looking at the map or a preview must never mark every post as read.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    throw new moodle_exception('invalidaction', 'mod_spatialforum');
}
$id = required_param('id', PARAM_INT);
$postid = required_param('postid', PARAM_INT);
$ajax = optional_param('ajax', false, PARAM_BOOL);
$cm = get_coursemodule_from_id('spatialforum', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_course_login($course, true, $cm);
require_sesskey();
$context = context_module::instance($cm->id);
require_capability('mod/spatialforum:view', $context);
if (isguestuser()) {
    throw new moodle_exception('noguest');
}
$post = $DB->get_record('spatialforum_posts', [
    'id' => $postid, 'spatialforumid' => $cm->instance, 'deleted' => 0,
], '*', MUST_EXIST);
forum::mark_read([$post->id], $USER->id);
if ($ajax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'postid' => (int) $post->id]);
    exit;
}
$url = new moodle_url('/mod/spatialforum/view.php', ['id' => $cm->id, 'postid' => $post->id]);
$url->set_anchor('post-' . $post->id);
redirect($url);
