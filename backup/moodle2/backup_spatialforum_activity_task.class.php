<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/spatialforum/backup/moodle2/backup_spatialforum_stepslib.php');

/**
 * Spatial forum backup task.
 *
 * @package mod_spatialforum
 */
class backup_spatialforum_activity_task extends backup_activity_task {
    /** Define activity-specific settings. */
    protected function define_my_settings(): void {
    }

    /** Define backup steps. */
    protected function define_my_steps(): void {
        $this->add_step(new backup_spatialforum_activity_structure_step('spatialforum_structure', 'spatialforum.xml'));
    }

    /**
     * Rewrites links to this activity in backed-up content.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content): string {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');
        $content = preg_replace(
            "/({$base}\/mod\/spatialforum\/index\.php\?id=)([0-9]+)/",
            '$@SPATIALFORUMINDEX*$2@$',
            $content
        );
        return preg_replace(
            "/({$base}\/mod\/spatialforum\/view\.php\?id=)([0-9]+)/",
            '$@SPATIALFORUMVIEWBYID*$2@$',
            $content
        );
    }
}
