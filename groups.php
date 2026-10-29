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
require_once($CFG->libdir . '/formslib.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('spatialforum', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_course_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('moodle/course:manageactivities', $context);
require_capability('moodle/site:accessallgroups', $context);
$activity = $DB->get_record('spatialforum', ['id' => $cm->instance], '*', MUST_EXIST);
$url = new moodle_url('/mod/spatialforum/view.php', ['id' => $id]);
if (!empty($activity->groupready)) {
    redirect($url);
}
$PAGE->set_url('/mod/spatialforum/groups.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('reviewgroupaudience', 'mod_spatialforum'));
$PAGE->set_heading(format_string($course->fullname));
$options = [-999 => get_string('choosedots'), -1 => get_string('allparticipants', 'mod_spatialforum')];
foreach (groups_get_all_groups($course->id, 0, $cm->groupingid) ?: [] as $group) {
    $options[$group->id] = format_string($group->name);
}
$form = new \mod_spatialforum\form\group_review_form(null, ['id' => $id, 'options' => $options]);
if ($form->is_cancelled()) {
    redirect($url);
}
if ($data = $form->get_data()) {
    require_sesskey();
    \mod_spatialforum\local\group_access::review_legacy(
        $activity->id,
        (int) $data->audience,
        !empty($data->confirmed)
    );
    redirect($url, get_string('groupreviewcomplete', 'mod_spatialforum'));
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reviewgroupaudience', 'mod_spatialforum'));
echo $OUTPUT->notification(get_string(
    'groupreviewexplanation',
    'mod_spatialforum',
    $DB->count_records('spatialforum_posts', ['spatialforumid' => $activity->id])
), 'warning');
$form->display();
echo $OUTPUT->footer();
