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
 * Samtalespor spatial discussion activity.
 *
 * @package    mod_spatialforum
 * @copyright  2026 Anders Christian Sloth
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


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
