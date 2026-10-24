<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');

use mod_spatialforum\local\forum;

// State changes are POST-only, explicit and idempotent (not toggle links).
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    throw new moodle_exception('invalidaction', 'mod_spatialforum');
}
$id = required_param('id', PARAM_INT);
$postid = required_param('postid', PARAM_INT);
$reaction = required_param('reaction', PARAM_INT);
$cm = get_coursemodule_from_id('spatialforum', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_course_login($course, true, $cm);
require_sesskey();
$context = context_module::instance($cm->id);
require_capability('mod/spatialforum:view', $context);
require_capability('mod/spatialforum:like', $context);
if (isguestuser()) {
    throw new moodle_exception('noguest');
}
$activity = $DB->get_record('spatialforum', ['id' => $cm->instance], '*', MUST_EXIST);
$post = $DB->get_record('spatialforum_posts', [
    'id' => $postid, 'spatialforumid' => $activity->id, 'deleted' => 0,
], '*', MUST_EXIST);
forum::set_reaction($activity, $post, $USER->id, $reaction);
$url = new moodle_url('/mod/spatialforum/view.php', ['id' => $cm->id, 'trackid' => $post->trackid]);
$url->set_anchor('post-' . $post->id);
redirect($url);
