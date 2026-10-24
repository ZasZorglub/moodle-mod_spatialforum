<?php
// This file is part of Moodle - http://moodle.org/

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
        $table->data[] = [html_writer::link($url, format_string($instance->name)), format_text($instance->intro, $instance->introformat)];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();

