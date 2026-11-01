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


/**
 * Restores spatial forum activity data.
 *
 * @package mod_spatialforum
 */
class restore_spatialforum_activity_structure_step extends restore_activity_structure_step {
    /**
     * @var int Old root track id.
     */
    private int $oldroottrackid = 0;

    /**

     * @var array Track relationships waiting for all mappings to exist.

     */
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
            $paths[] = new restore_path_element('spatialforum_like', '/activity/spatialforum/likes/like');
        }
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the activity and map its original identifier.
     *

     * @param array $data

     */
    protected function process_spatialforum(array $data): void {
        global $DB;

        $record = (object) $data;
        $oldid = $record->id;
        $this->oldroottrackid = (int) $record->roottrackid;
        $record->course = $this->get_courseid();
        $record->roottrackid = 0;
        $record->groupready = $record->groupready ?? 0;
        $record->enablelikes = $record->enablelikes ?? 1;
        $record->grade = $record->grade ?? 0;
        foreach (['completionposts', 'completionreplies', 'completiondiscussions'] as $rule) {
            $record->$rule = $record->$rule ?? 0;
        }
        // A restored copy is independent of an excluded processing job.
        $record->importstate = 0;
        if ($record->grade < 0) {
            $scaleid = $this->get_mappingid('scale', abs($record->grade));
            if (!$scaleid) {
                throw new restore_step_exception('invalidgradedefinition', 'mod_spatialforum');
            }
            $record->grade = -$scaleid;
        }
        $record->timemodified = $this->apply_date_offset($record->timemodified);

        $newid = $DB->insert_record('spatialforum', $record);
        $this->apply_activity_instance($newid);
        $this->set_mapping('spatialforum', $oldid, $newid, true);
    }

    /**
     * Restore a track and map its original identifier.
     *

     * @param array $data

     */
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

    /**
     * Restore a post and remap its author.
     *

     * @param array $data

     */
    protected function process_spatialforum_post(array $data): void {
        global $DB;

        $record = (object) $data;
        $oldid = $record->id;
        $record->spatialforumid = $this->get_new_parentid('spatialforum');
        $record->trackid = $this->get_mappingid('spatialforum_track', $record->trackid, 0);
        $record->replytoid = $this->get_mappingid('spatialforum_post', $record->replytoid, 0);
        $record->showparent = $record->showparent ?? 1;
        $record->replyparts = $record->replyparts ?? null;
        $record->importedattachments = $record->importedattachments ?? 0;
        $record->discussionname = $record->discussionname ?? null;
        // Keep the original retention deadline; restoring a backup must not restart it.
        $record->deletedat = $record->deletedat ?? 0;
        $record->groupid = (int) ($record->groupid ?? -1);
        if ($record->groupid > 0) {
            $record->groupid = (int) $this->get_mappingid('group', $record->groupid, -2);
            if ($record->groupid < 0) {
                $DB->set_field('spatialforum', 'groupready', 0, ['id' => $record->spatialforumid]);
            }
        }

        $record->userid = $this->get_mappingid('user', $record->userid, 0);
        $record->timecreated = $this->apply_date_offset($record->timecreated);
        $record->timemodified = $this->apply_date_offset($record->timemodified);

        if (!$record->trackid || (!$record->userid && !$record->deleted)) {
            return;
        }
        $newid = $DB->insert_record('spatialforum_posts', $record);
        $this->set_mapping('spatialforum_post', $oldid, $newid, true);
    }

    /**
     * Restore a user read marker for its mapped post.
     *

     * @param array $data

     */
    protected function process_spatialforum_read(array $data): void {
        global $DB;

        $record = (object) $data;
        unset($record->id);
        $record->userid = $this->get_mappingid('user', $record->userid, 0);
        $record->postid = $this->get_mappingid('spatialforum_post', $record->postid, 0);
        $record->timeread = $this->apply_date_offset($record->timeread);

        if (
            $record->userid && $record->postid && !$DB->record_exists('spatialforum_read', [
            'userid' => $record->userid,
            'postid' => $record->postid,
            ])
        ) {
            $DB->insert_record('spatialforum_read', $record);
        }
    }

    /**

     * Restore likes only when both user and post mappings exist.

     */
    protected function process_spatialforum_like(array $data): void {
        global $DB;

        $record = (object) $data;
        $record->reaction = ((int) ($record->reaction ?? 1) === -1) ? -1 : 1;
        unset($record->id);
        $record->userid = $this->get_mappingid('user', $record->userid, 0);
        $record->postid = $this->get_mappingid('spatialforum_post', $record->postid, 0);
        $record->timecreated = $this->apply_date_offset($record->timecreated);
        if (
            $record->userid && $record->postid && !$DB->record_exists('spatialforum_likes', [
            'userid' => $record->userid, 'postid' => $record->postid,
            ])
        ) {
            $DB->insert_record('spatialforum_likes', $record);
        }
    }

    /**

     * Repairs track references after all source IDs have been mapped.

     */
    protected function after_execute(): void {
        global $DB;

        $forumid = $this->get_new_parentid('spatialforum');
        foreach ($this->pendingtracks as $newid => $oldids) {
            $DB->set_field(
                'spatialforum_tracks',
                'parenttrackid',
                $this->get_mappingid('spatialforum_track', $oldids['parent'], 0),
                ['id' => $newid]
            );
            $DB->set_field(
                'spatialforum_tracks',
                'originpostid',
                $this->get_mappingid('spatialforum_post', $oldids['origin'], 0),
                ['id' => $newid]
            );
        }
        $DB->set_field(
            'spatialforum',
            'roottrackid',
            $this->get_mappingid('spatialforum_track', $this->oldroottrackid, 0),
            ['id' => $forumid]
        );
        $this->add_related_files('mod_spatialforum', 'intro', null);
        $this->add_related_files('mod_spatialforum', 'post', 'spatialforum_post');
    }
}
