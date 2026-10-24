<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Restores spatial forum activity data.
 *
 * @package mod_spatialforum
 */
class restore_spatialforum_activity_structure_step extends restore_activity_structure_step {
    /** @var int Old root track id. */
    private int $oldroottrackid = 0;

    /** @var array Track relationships waiting for all mappings to exist. */
    private array $pendingtracks = [];

    /**
     * Restore paths.
     *
     * @return restore_path_element[]
     */
    protected function define_structure(): array {
        $paths = [new restore_path_element('spatialforum', '/activity/spatialforum')];
        $paths[] = new restore_path_element('spatialforum_track', '/activity/spatialforum/tracks/track');

        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('spatialforum_post', '/activity/spatialforum/posts/post');
            $paths[] = new restore_path_element('spatialforum_read', '/activity/spatialforum/reads/read');
        }
        return $this->prepare_activity_structure($paths);
    }

    /** @param array $data */
    protected function process_spatialforum(array $data): void {
        global $DB;

        $record = (object) $data;
        $oldid = $record->id;
        $this->oldroottrackid = (int) $record->roottrackid;
        $record->course = $this->get_courseid();
        $record->roottrackid = 0;
        $record->timemodified = $this->apply_date_offset($record->timemodified);

        $newid = $DB->insert_record('spatialforum', $record);
        $this->apply_activity_instance($newid);
        $this->set_mapping('spatialforum', $oldid, $newid, true);
    }

    /** @param array $data */
    protected function process_spatialforum_track(array $data): void {
        global $DB;

        $record = (object) $data;
        $oldid = $record->id;
        $oldparent = (int) $record->parenttrackid;
        $oldorigin = (int) $record->originpostid;
        $record->spatialforumid = $this->get_new_parentid('spatialforum');
        $record->parenttrackid = 0;
        $record->originpostid = 0;
        $record->timecreated = $this->apply_date_offset($record->timecreated);
        $record->timemodified = $this->apply_date_offset($record->timemodified);

        $newid = $DB->insert_record('spatialforum_tracks', $record);
        $this->set_mapping('spatialforum_track', $oldid, $newid, true);
        $this->pendingtracks[$newid] = ['parent' => $oldparent, 'origin' => $oldorigin];
    }

    /** @param array $data */
    protected function process_spatialforum_post(array $data): void {
        global $DB;

        $record = (object) $data;
        $oldid = $record->id;
        $record->spatialforumid = $this->get_new_parentid('spatialforum');
        $record->trackid = $this->get_mappingid('spatialforum_track', $record->trackid, 0);
        $record->replytoid = $this->get_mappingid('spatialforum_post', $record->replytoid, 0);
        $record->userid = $this->get_mappingid('user', $record->userid, 0);
        $record->timecreated = $this->apply_date_offset($record->timecreated);
        $record->timemodified = $this->apply_date_offset($record->timemodified);

        if (!$record->trackid || !$record->userid) {
            return;
        }
        $newid = $DB->insert_record('spatialforum_posts', $record);
        $this->set_mapping('spatialforum_post', $oldid, $newid, true);
    }

    /** @param array $data */
    protected function process_spatialforum_read(array $data): void {
        global $DB;

        $record = (object) $data;
        unset($record->id);
        $record->userid = $this->get_mappingid('user', $record->userid, 0);
        $record->postid = $this->get_mappingid('spatialforum_post', $record->postid, 0);
        $record->timeread = $this->apply_date_offset($record->timeread);

        if ($record->userid && $record->postid && !$DB->record_exists('spatialforum_read', [
            'userid' => $record->userid,
            'postid' => $record->postid,
        ])) {
            $DB->insert_record('spatialforum_read', $record);
        }
    }

    /** Repairs track references after all source IDs have been mapped. */
    protected function after_execute(): void {
        global $DB;

        $forumid = $this->get_new_parentid('spatialforum');
        foreach ($this->pendingtracks as $newid => $oldids) {
            $DB->set_field('spatialforum_tracks', 'parenttrackid',
                $this->get_mappingid('spatialforum_track', $oldids['parent'], 0), ['id' => $newid]);
            $DB->set_field('spatialforum_tracks', 'originpostid',
                $this->get_mappingid('spatialforum_post', $oldids['origin'], 0), ['id' => $newid]);
        }
        $DB->set_field('spatialforum', 'roottrackid',
            $this->get_mappingid('spatialforum_track', $this->oldroottrackid, 0), ['id' => $forumid]);
        $this->add_related_files('mod_spatialforum', 'intro', null);
    }
}
