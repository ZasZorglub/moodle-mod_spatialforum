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
 * Library callbacks for Spatial forum.
 *
 * @package    mod_spatialforum
 * @copyright  2026 Anders Christian Sloth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Declares supported Moodle features.
 *
 * @param string $feature
 * @return mixed
 */
function spatialforum_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_INTRO => true,
        FEATURE_GROUPS => true,
        FEATURE_GROUPINGS => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_COMPLETION_HAS_RULES => true,
        FEATURE_BACKUP_MOODLE2 => true,
        FEATURE_GRADE_HAS_GRADE => true,
        FEATURE_GRADE_OUTCOMES => false,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_COMMUNICATION,
        default => null,
    };
}

/**
 * Publish the automatic contribution rules to Moodle's cached module information.
 * @param stdClass $coursemodule
 * @return cached_cm_info|false
 */
function spatialforum_get_coursemodule_info($coursemodule) {
    global $DB;
    $activity = $DB->get_record('spatialforum', ['id' => $coursemodule->instance]);
    if (!$activity) {
        return false;
    }
    $result = new cached_cm_info();
    $result->name = $activity->name;
    if ($coursemodule->showdescription) {
        $result->content = format_module_intro('spatialforum', $activity, $coursemodule->id, false);
    }
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        foreach (\mod_spatialforum\completion\custom_completion::get_defined_custom_rules() as $rule) {
            $result->customdata['customcompletionrules'][$rule] = (int) ($activity->$rule ?? 0);
        }
    }
    return $result;
}

/**
 * Adds an activity instance and its root track.
 *
 * @param stdClass $data
 * @param moodleform|null $mform
 * @return int
 */
function spatialforum_add_instance(stdClass $data, $mform = null): int {
    global $DB;

    $data->grade = $data->grade ?? 0;
    \mod_spatialforum\local\grading::validate_definition($data);
    $transaction = $DB->start_delegated_transaction();
    $data->timemodified = time();
    $data->roottrackid = 0;
    $data->groupready = 1;
    $id = $DB->insert_record('spatialforum', $data);

    $track = (object) [
        'spatialforumid' => $id,
        'parenttrackid' => 0,
        'originpostid' => 0,
        'name' => $data->name,
        'colour' => '#ffffff',
        'locked' => 0,
        'timecreated' => time(),
        'timemodified' => time(),
    ];
    $data->id = $id;
    $data->roottrackid = $DB->insert_record('spatialforum_tracks', $track);
    $DB->update_record('spatialforum', $data);
    if (spatialforum_grade_item_update($data) !== GRADE_UPDATE_OK) {
        $transaction->rollback(new moodle_exception('gradesavefailed', 'mod_spatialforum'));
    }
    $transaction->allow_commit();

    return $id;
}

/**
 * Updates an activity instance.
 *
 * @param stdClass $data
 * @param moodleform|null $mform
 * @return bool
 */
function spatialforum_update_instance(stdClass $data, $mform = null): bool {
    global $DB;

    $existing = $DB->get_record('spatialforum', ['id' => $data->instance], '*', MUST_EXIST);
    if (!empty($existing->importstate)) {
        throw new moodle_exception('importinprogress', 'mod_spatialforum');
    }
    $data->course = $existing->course;
    $data->grade = $data->grade ?? $existing->grade;
    \mod_spatialforum\local\grading::validate_definition($data, $existing);
    $transaction = $DB->start_delegated_transaction();
    $data->id = $data->instance;
    $data->timemodified = time();
    $DB->update_record('spatialforum', $data);

    if ($roottrack = $DB->get_record('spatialforum_tracks', ['id' => $existing->roottrackid])) {
        $roottrack->name = $data->name;
        $roottrack->timemodified = time();
        $DB->update_record('spatialforum_tracks', $roottrack);
    }
    if (spatialforum_grade_item_update($data) !== GRADE_UPDATE_OK) {
        $transaction->rollback(new moodle_exception('gradesavefailed', 'mod_spatialforum'));
    }
    $transaction->allow_commit();
    return true;
}

/**
 * Deletes an activity instance and its related records.
 *
 * @param int $id
 * @return bool
 */
function spatialforum_delete_instance(int $id): bool {
    global $DB;

    if (!$activity = $DB->get_record('spatialforum', ['id' => $id])) {
        return false;
    }
    if ($cm = get_coursemodule_from_instance('spatialforum', $id)) {
        get_file_storage()->delete_area_files(context_module::instance($cm->id)->id, 'mod_spatialforum', 'post');
    }
    $transaction = $DB->start_delegated_transaction();
    $postids = $DB->get_fieldset_select('spatialforum_posts', 'id', 'spatialforumid = :id', ['id' => $id]);
    if ($job = $DB->get_record('spatialforum_imports', ['targetid' => $id])) {
        $DB->delete_records('spatialforum_import_map', ['importid' => $job->id]);
        $DB->delete_records('spatialforum_imports', ['id' => $job->id]);
    }
    if ($postids) {
        $DB->delete_records_list('spatialforum_likes', 'postid', $postids);
        $DB->delete_records_list('spatialforum_read', 'postid', $postids);
    }
    $DB->delete_records('spatialforum_subscriptions', ['spatialforumid' => $id]);
    $DB->delete_records('spatialforum_deliveries', ['spatialforumid' => $id]);
    $DB->delete_records('spatialforum_posts', ['spatialforumid' => $id]);
    $DB->delete_records('spatialforum_tracks', ['spatialforumid' => $id]);
    $DB->delete_records('spatialforum', ['id' => $id]);
    if (spatialforum_grade_item_delete($activity) !== GRADE_UPDATE_OK) {
        $transaction->rollback(new moodle_exception('gradesavefailed', 'mod_spatialforum'));
    }
    $transaction->allow_commit();
    return true;
}

/**

 * Serve embedded post files only to participants who can view this activity and post.

 */
function spatialforum_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB;
    if (
        $context->contextlevel !== CONTEXT_MODULE || (int) $context->instanceid !== (int) $cm->id
            || $filearea !== 'post' || count($args) < 2
    ) {
        return false;
    }
    if (!has_capability('mod/spatialforum:view', $context)) {
        return false;
    }
    require_course_login($course, true, $cm);
    $postid = (int) array_shift($args);
    if (
        !$DB->record_exists('spatialforum_posts', [
        'id' => $postid, 'spatialforumid' => $cm->instance, 'deleted' => 0,
        ])
    ) {
        return false;
    }
    $post = $DB->get_record('spatialforum_posts', ['id' => $postid], '*', MUST_EXIST);
    if (!\mod_spatialforum\local\group_access::for_activity($cm->instance)->can_view($post)) {
        return false;
    }
    $filename = array_pop($args);
    $filepath = '/' . ($args ? implode('/', $args) . '/' : '');
    $file = get_file_storage()->get_file($context->id, 'mod_spatialforum', 'post', $postid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Records an activity view for completion and logs.
 *
 * @param stdClass $spatialforum
 * @param stdClass $course
 * @param cm_info|stdClass $cm
 * @param context_module $context
 */
function spatialforum_view(stdClass $spatialforum, stdClass $course, $cm, context_module $context): void {
    $event = \mod_spatialforum\event\course_module_viewed::create([
        'objectid' => $spatialforum->id,
        'context' => $context,
    ]);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('spatialforum', $spatialforum);
    $event->trigger();

    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Create/update grade-item metadata; null grades never regenerate manual results.
 * @param stdClass $activity
 * @param mixed $grades Optional explicit Grade API data
 * @return int
 */
function spatialforum_grade_item_update($activity, $grades = null): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    $details = ['itemname' => $activity->name];
    if (isset($activity->cmidnumber)) {
        $details['idnumber'] = $activity->cmidnumber;
    }
    if (($activity->grade ?? 0) > 0) {
        $details += ['gradetype' => GRADE_TYPE_VALUE, 'grademin' => 0, 'grademax' => $activity->grade, 'scaleid' => 0];
    } else if (($activity->grade ?? 0) < 0) {
        $details += ['gradetype' => GRADE_TYPE_SCALE, 'scaleid' => -(int) $activity->grade];
    } else {
        $details['gradetype'] = GRADE_TYPE_NONE;
    }
    return grade_update('mod/spatialforum', $activity->course, 'mod', 'spatialforum', $activity->id, 0, $grades, $details);
}

/**
 * Gradebook maintenance updates metadata only, preserving manual scores and feedback.
 * @param stdClass $activity
 * @param int $userid
 * @param bool $nullifnone
 */
function spatialforum_update_grades($activity, $userid = 0, $nullifnone = true): void {
    spatialforum_grade_item_update($activity);
}

/**
 * Delete the activity grade item through the Grade API.
 * @param stdClass $activity
 * @return int
 */
function spatialforum_grade_item_delete($activity): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update('mod/spatialforum', $activity->course, 'mod', 'spatialforum', $activity->id, 0, null, ['deleted' => 1]);
}

/**
 * Whether this activity uses the named scale.
 * @param int $instanceid
 * @param int $scaleid
 * @return bool
 */
function spatialforum_scale_used($instanceid, $scaleid): bool {
    global $DB;
    return $DB->record_exists('spatialforum', ['id' => $instanceid, 'grade' => -(int) $scaleid]);
}

/**
 * Prevent removal of a scale referenced by any activity.
 * @param int $scaleid
 * @return bool
 */
function spatialforum_scale_used_anywhere($scaleid): bool {
    global $DB;
    return $DB->record_exists('spatialforum', ['grade' => -(int) $scaleid]);
}

/**
 * Provide an entry point before any spatial forum has been created in the course.
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function spatialforum_extend_navigation_course($navigation, $course, $context): void {
    if (has_capability('mod/spatialforum:import', $context) && has_capability('moodle/course:manageactivities', $context)) {
        $navigation->add(
            get_string('importforum', 'mod_spatialforum'),
            new moodle_url('/mod/spatialforum/import.php', ['courseid' => $course->id]),
            navigation_node::TYPE_SETTING,
            null,
            'spatialforumimport'
        );
    }
}
