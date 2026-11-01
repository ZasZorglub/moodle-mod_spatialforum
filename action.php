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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new moodle_exception('invalidrequest', 'error');
}
$id = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$trackid = optional_param('trackid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('spatialforum', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$activity = $DB->get_record('spatialforum', ['id' => $cm->instance], '*', MUST_EXIST);

require_course_login($course, true, $cm);
require_sesskey();
$context = context_module::instance($cm->id);
require_capability('mod/spatialforum:moderate', $context);

$returnurl = new moodle_url('/mod/spatialforum/view.php', ['id' => $cm->id]);

if ($action === 'toggletrack') {
    $track = $DB->get_record('spatialforum_tracks', [
        'id' => $trackid,
        'spatialforumid' => $activity->id,
    ], '*', MUST_EXIST);
    \mod_spatialforum\local\group_access::for_activity($activity->id)->require_track($track, true);
    $track->locked = empty($track->locked) ? 1 : 0;
    $track->timemodified = time();
    $DB->update_record('spatialforum_tracks', $track);
    $returnurl->param('trackid', $track->id);
} else if (in_array($action, ['deletepost', 'restorepost'], true)) {
    $postid = required_param('postid', PARAM_INT);
    $post = $DB->get_record('spatialforum_posts', [
        'id' => $postid,
        'spatialforumid' => $activity->id,
    ], '*', MUST_EXIST);
    \mod_spatialforum\local\group_access::for_activity($activity->id)->require_post($post, true);
    if ($action === 'deletepost' && !optional_param('confirmed', 0, PARAM_BOOL)) {
        $PAGE->set_url('/mod/spatialforum/action.php', ['id' => $cm->id, 'action' => 'deletepost', 'postid' => $post->id]);
        $PAGE->set_title(get_string('delete'));
        $PAGE->set_heading(format_string($course->fullname));
        $confirmurl = new moodle_url('/mod/spatialforum/action.php', [
            'id' => $cm->id, 'action' => 'deletepost', 'postid' => $post->id,
            'confirmed' => 1, 'sesskey' => sesskey(),
        ]);
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('confirmdeletepost', 'mod_spatialforum'),
            new single_button($confirmurl, get_string('delete'), 'post'),
            $returnurl
        );
        echo $OUTPUT->footer();
        exit;
    }
    \mod_spatialforum\local\post_lifecycle::moderate($cm, $post->id, $action === 'restorepost');
    $returnurl->param('trackid', $post->trackid);
    $returnurl->param('postid', $post->id);
    $returnurl->set_anchor('post-' . $post->id);
} else {
    throw new moodle_exception('invalidaction', 'mod_spatialforum');
}

redirect($returnurl, get_string('changessaved'));
