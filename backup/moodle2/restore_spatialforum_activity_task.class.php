<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/spatialforum/backup/moodle2/restore_spatialforum_stepslib.php');

/**
 * Spatial forum restore task.
 *
 * @package mod_spatialforum
 */
class restore_spatialforum_activity_task extends restore_activity_task {
    /** Define activity-specific settings. */
    protected function define_my_settings(): void {
    }

    /** Define restore steps. */
    protected function define_my_steps(): void {
        $this->add_step(new restore_spatialforum_activity_structure_step('spatialforum_structure', 'spatialforum.xml'));
    }

    /**
     * Content decoding rules.
     *
     * @return array
     */
    public static function define_decode_contents(): array {
        return [new restore_decode_content('spatialforum', ['intro'], 'spatialforum'),
            new restore_decode_content('spatialforum_posts', ['message'], 'spatialforum_post')];
    }

    /**
     * Link decoding rules.
     *
     * @return array
     */
    public static function define_decode_rules(): array {
        return [
            new restore_decode_rule('SPATIALFORUMTRACK', '/mod/spatialforum/view.php?id=$1&trackid=$2',
                ['course_module', 'spatialforum_track']),
            new restore_decode_rule('SPATIALFORUMPOST', '/mod/spatialforum/view.php?id=$1&postid=$2',
                ['course_module', 'spatialforum_post']),
            new restore_decode_rule('SPATIALFORUMANCHOR', '/mod/spatialforum/view.php?id=$1#post-$2',
                ['course_module', 'spatialforum_post']),
            new restore_decode_rule('SPATIALFORUMTRACKPOST', '/mod/spatialforum/view.php?id=$1&trackid=$2#post-$3',
                ['course_module', 'spatialforum_track', 'spatialforum_post']),
            new restore_decode_rule('SPATIALFORUMVIEWBYID', '/mod/spatialforum/view.php?id=$1', 'course_module'),
            new restore_decode_rule('SPATIALFORUMINDEX', '/mod/spatialforum/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Restore log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules(): array {
        return [];
    }

    /**
     * Restore course log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules_for_course(): array {
        return [];
    }
}
