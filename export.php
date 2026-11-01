<?php
// phpcs:ignoreFile
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

/** Export a Spatialforum activity as standard forum interchange JSON. */
// @package    mod_spatialforum
// @copyright  2026 Anders Christian Sloth
// @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('spatialforum', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$activity = $DB->get_record('spatialforum', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/spatialforum:import', $context);
require_capability('mod/spatialforum:view', $context);

$posts = $DB->get_records('spatialforum_posts', ['spatialforumid' => $activity->id, 'deleted' => 0], 'id ASC');
$payload = [
    'format' => 'moodle-standard-forum-interchange', 'version' => 1,
    'activity' => ['name' => $activity->name, 'intro' => $activity->intro, 'introformat' => $activity->introformat],
    'posts' => [],
];
foreach ($posts as $post) {
    $payload['posts'][] = [
        'id' => (int) $post->id, 'parent' => (int) $post->replytoid, 'groupid' => (int) $post->groupid,
        'userid' => (int) $post->userid, 'subject' => $post->subject, 'message' => $post->message,
        'messageformat' => (int) $post->messageformat, 'timecreated' => (int) $post->timecreated,
        'timemodified' => (int) $post->timemodified,
    ];
}
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="spatialforum-' . $activity->id . '.json"');
echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
