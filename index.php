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

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);

$PAGE->set_url('/mod/spatialforum/index.php', ['id' => $course->id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_spatialforum'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_spatialforum'));

$instances = get_all_instances_in_course('spatialforum', $course);
if (!$instances) {
    echo $OUTPUT->notification(get_string('noinstances', 'mod_spatialforum'), 'info');
} else {
    $table = new html_table();
    $table->head = [get_string('name'), get_string('description')];
    foreach ($instances as $instance) {
        $url = new moodle_url('/mod/spatialforum/view.php', ['id' => $instance->coursemodule]);
        $table->data[] = [
            html_writer::link($url, format_string($instance->name)),
            format_text($instance->intro, $instance->introformat),
        ];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
