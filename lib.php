<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Library callbacks for Spatial forum.
 *
 * @package    mod_spatialforum
 * @copyright  2026 Anders Christian Sloth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Declares supported Moodle features.
 *
 * @param string $feature
 * @return mixed
 */
function spatialforum_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_INTRO => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_BACKUP_MOODLE2 => true,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_COMMUNICATION,
        default => null,
    };
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

    $transaction = $DB->start_delegated_transaction();
    $data->timemodified = time();
    $data->roottrackid = 0;
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
    $data->id = $data->instance;
    $data->timemodified = time();
    $DB->update_record('spatialforum', $data);

    if ($roottrack = $DB->get_record('spatialforum_tracks', ['id' => $existing->roottrackid])) {
        $roottrack->name = $data->name;
        $roottrack->timemodified = time();
        $DB->update_record('spatialforum_tracks', $roottrack);
    }
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

    if (!$DB->record_exists('spatialforum', ['id' => $id])) {
        return false;
    }
    $transaction = $DB->start_delegated_transaction();
    $postids = $DB->get_fieldset_select('spatialforum_posts', 'id', 'spatialforumid = :id', ['id' => $id]);
    if ($postids) {
        $DB->delete_records_list('spatialforum_likes', 'postid', $postids);
        $DB->delete_records_list('spatialforum_read', 'postid', $postids);
    }
    $DB->delete_records('spatialforum_posts', ['spatialforumid' => $id]);
    $DB->delete_records('spatialforum_tracks', ['spatialforumid' => $id]);
    $DB->delete_records('spatialforum', ['id' => $id]);
    $transaction->allow_commit();
    return true;
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
