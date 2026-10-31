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
if (!empty($SESSION->spatialforumdraftclear)) {
    $PAGE->requires->js_call_amd('mod_spatialforum/drafts', 'init', [$SESSION->spatialforumdraftclear]);
    unset($SESSION->spatialforumdraftclear);
}

spatialforum_view($activity, $course, $cm, $context);
$data = forum::export_view_data($activity, $cm, $track, $context, true);
$data['subscriptionurl'] = !isguestuser() && !empty($USER->id)
    ? (new moodle_url('/mod/spatialforum/subscription.php', ['id' => $cm->id]))->out(false) : '';
$data['assessmenturl'] = '';
if (\mod_spatialforum\local\grading::enabled() && !empty($activity->grade) && !isguestuser()) {
    $grader = has_capability('mod/spatialforum:grade', $context);
    if ($grader || has_capability('mod/spatialforum:receivegrade', $context)) {
        $data['assessmenturl'] = (new moodle_url('/mod/spatialforum/grade.php', ['id' => $cm->id]))->out(false);
        $data['assessmentlabel'] = get_string($grader ? 'assessparticipants' : 'owngrade', 'mod_spatialforum');
    }
}

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
$groupaccess = new \mod_spatialforum\local\group_access($activity, $cm);
if ($groupaccess->pending()) {
    echo $OUTPUT->notification(get_string('groupmigrationrequired', 'mod_spatialforum'), 'warning');
    echo html_writer::link(
        new moodle_url('/mod/spatialforum/groups.php', ['id' => $cm->id]),
        get_string('reviewgroupaudience', 'mod_spatialforum')
    );
}
groups_print_activity_menu($cm, new moodle_url('/mod/spatialforum/view.php', ['id' => $cm->id]));

// Moodle's activity header already renders the module introduction.
echo $OUTPUT->render_from_template('mod_spatialforum/main', $data);
echo $OUTPUT->footer();
