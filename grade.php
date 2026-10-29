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
 * Private assessment and own-result views, separate from the public discussion map.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use mod_spatialforum\local\grading;

$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$groupid = optional_param('groupid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('spatialforum', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$activity = $DB->get_record('spatialforum', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/spatialforum:view', $context);
$grader = has_capability('mod/spatialforum:grade', $context);
$PAGE->set_cm($cm, $course);
$PAGE->set_url('/mod/spatialforum/grade.php', ['id' => $id, 'userid' => $userid, 'page' => $page, 'groupid' => $groupid]);
$PAGE->set_title(get_string($grader ? 'assessparticipants' : 'owngrade', 'mod_spatialforum'));
$PAGE->set_heading(format_string($course->fullname));
$returnurl = new moodle_url('/mod/spatialforum/view.php', ['id' => $id]);
$rosterurl = new moodle_url('/mod/spatialforum/grade.php', ['id' => $id]);
$state = null;
$form = null;
$error = '';
if ($grader && $userid) {
    $state = grading::assessment($activity->id, $userid);
    if (!$state['reason']) {
        $form = new \mod_spatialforum\form\assessment_form(null, ['id' => $id, 'userid' => $userid, 'state' => $state]);
        $raw = $state['grade']->rawgrade ?? null;
        $form->set_data(['score' => $raw === null ? '' : (string) (float) $raw,
            'feedback' => $state['grade']->feedback ?? '', 'revision' => $state['token']]);
        if ($form->is_cancelled()) {
            redirect($rosterurl);
        }
        if ($data = $form->get_data()) {
            require_sesskey();
            try {
                grading::save($activity->id, $userid, (string) $data->score, $data->feedback, $data->revision);
                redirect(
                    new moodle_url('/mod/spatialforum/grade.php', ['id' => $id, 'userid' => $userid]),
                    get_string('assessmentsaved', 'mod_spatialforum')
                );
            } catch (moodle_exception $e) {
                $error = $e->getMessage();
            }
        }
    }
} else if ($grader) {
    $roster = grading::roster($activity->id, $page, $groupid);
} else {
    if ($userid && $userid !== (int) $USER->id) {
        throw new moodle_exception('gradeaccessdenied', 'mod_spatialforum');
    }
    $own = grading::own_result($activity->id);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string($grader ? 'assessparticipants' : 'owngrade', 'mod_spatialforum'));
echo html_writer::tag('p', html_writer::link($returnurl, get_string('backtoconversation', 'mod_spatialforum')));
if ($state) {
    echo $OUTPUT->heading(s(fullname($state['user'])), 3);
    if ($error) {
        echo $OUTPUT->notification($error, 'error');
        echo html_writer::tag('p', html_writer::link($PAGE->url, get_string('reloadassessment', 'mod_spatialforum')));
    }
    echo html_writer::tag('p', get_string('currentgrade', 'mod_spatialforum', s($state['result']->str_long_grade)));
    if ($state['result']->hidden) {
        echo $OUTPUT->notification(get_string('gradehidden', 'mod_spatialforum'), 'info');
    }
    if ($state['reason']) {
        echo $OUTPUT->notification(get_string($state['reason'], 'mod_spatialforum'), 'warning');
        echo $state['result']->str_feedback;
    } else {
        $form->display();
    }
    echo html_writer::tag('p', html_writer::link($rosterurl, get_string('assessparticipants', 'mod_spatialforum')));
    if (has_capability('moodle/grade:viewall', context_course::instance($course->id))) {
        echo html_writer::link(
            new moodle_url('/grade/report/grader/index.php', ['id' => $course->id]),
            get_string('opengradebook', 'mod_spatialforum')
        );
    }
} else if ($grader) {
    echo $OUTPUT->notification(get_string('assessmentintro', 'mod_spatialforum'), 'info');
    if ($roster['groups']) {
        $choices = [0 => get_string('allpermittedgroups', 'mod_spatialforum')];
        foreach ($roster['groups'] as $group) {
            $choices[$group->id] = format_string($group->name);
        }
        echo $OUTPUT->single_select(
            $rosterurl,
            'groupid',
            $choices,
            $groupid,
            null,
            'gradinggroup',
            ['label' => get_string('group')]
        );
    }
    $table = new html_table();
    $table->head = [get_string('fullname'), get_string('gradenoun'), get_string('assessment', 'mod_spatialforum')];
    foreach ($roster['users'] as $user) {
        $result = $roster['grades'][$user->id] ?? null;
        $table->data[] = [s(fullname($user)), s($result->str_long_grade ?? '-'),
            html_writer::link(
                new moodle_url('/mod/spatialforum/grade.php', ['id' => $id, 'userid' => $user->id]),
                get_string('assessparticipant', 'mod_spatialforum')
            )];
    }
    echo html_writer::table($table);
    if (!$roster['total']) {
        echo html_writer::tag('p', get_string('nogradableparticipants', 'mod_spatialforum'));
    }
    echo $OUTPUT->paging_bar(
        $roster['total'],
        $page,
        50,
        new moodle_url('/mod/spatialforum/grade.php', ['id' => $id, 'groupid' => $groupid])
    );
} else if ($own) {
    echo html_writer::tag('p', get_string('currentgrade', 'mod_spatialforum', s($own->str_long_grade)));
    echo $own->str_feedback;
    if ($own->grade === null && !$own->feedback) {
        echo html_writer::tag('p', get_string('notgraded', 'mod_spatialforum'));
    }
} else {
    echo $OUTPUT->notification(get_string('gradenotavailable', 'mod_spatialforum'), 'info');
}
echo $OUTPUT->footer();
