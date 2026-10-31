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
 * Author editing and a bounded moderator recycle window.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_spatialforum\local;

/**
 * Deleted content remains private for seven days, then cron removes its body and media.
 */
class post_lifecycle {
    /**
     * Time allowed for a moderator to undo a deletion.
     */
    public const RETENTION = 7 * DAYSECS;

    /**
     * Detect concurrent edits even when they occur within the same second.
     * @param \stdClass $post
     * @return string
     */
    public static function revision(\stdClass $post): string {
        return hash('sha256', json_encode([$post->subject, $post->message, $post->messageformat,
            $post->showparent, $post->replyparts, $post->deleted, $post->deletedat, $post->timemodified]));
    }

    /**
     * Preserve ownership, current group access and the forum/track locks when editing.
     * @param \stdClass $post
     * @param \context_module $context
     * @return bool
     */
    public static function can_edit(\stdClass $post, \context_module $context): bool {
        global $DB, $USER;
        if (
            !$USER->id || isguestuser() || $post->deleted || $post->userid != $USER->id
                || !has_capability('mod/spatialforum:reply', $context)
        ) {
            return false;
        }
        $activity = $DB->get_record('spatialforum', ['id' => $post->spatialforumid], '*', MUST_EXIST);
        $track = $DB->get_record('spatialforum_tracks', ['id' => $post->trackid], '*', MUST_EXIST);
        return !$activity->locked && !$activity->importstate && !$track->locked
            && group_access::for_activity($activity->id)->can_write((int) $post->groupid);
    }

    /**
     * Publish a validated edit without scheduling another new-post notification.
     * @param \stdClass $cm
     * @param int $postid
     * @param string $revision
     * @param \stdClass $data
     */
    public static function edit(\stdClass $cm, int $postid, string $revision, \stdClass $data): void {
        global $DB;
        $context = \context_module::instance($cm->id);
        require_capability('mod/spatialforum:view', $context);
        self::with_lock($postid, function () use ($cm, $postid, $revision, $data, $context, $DB) {
            $post = $DB->get_record('spatialforum_posts', ['id' => $postid, 'spatialforumid' => $cm->instance], '*', MUST_EXIST);
            if (!self::can_edit($post, $context)) {
                throw new \moodle_exception('cannoteditpost', 'mod_spatialforum');
            }
            if (!hash_equals(self::revision($post), $revision)) {
                throw new \moodle_exception('postchanged', 'mod_spatialforum');
            }
            $subject = trim($data->subject);
            if ($subject === '' || \core_text::strlen($subject) > 255 || !media::has_content($data->message_editor['text'])) {
                throw new \moodle_exception('invalidpost', 'mod_spatialforum');
            }
            $transaction = $DB->start_delegated_transaction();
            try {
                media::save($postid, $data->message_editor, $context);
                $DB->update_record('spatialforum_posts', (object) [
                    'id' => $postid, 'subject' => $subject, 'showparent' => empty($data->showparent) ? 0 : 1,
                    'timemodified' => max(time(), $post->timecreated + 1),
                ]);
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
        });
    }

    /**
     * Remove or restore a post without disconnecting its descendants.
     * @param \stdClass $cm
     * @param int $postid
     * @param bool $restore
     */
    public static function moderate(\stdClass $cm, int $postid, bool $restore = false): void {
        global $DB;
        $context = \context_module::instance($cm->id);
        require_capability('mod/spatialforum:view', $context);
        require_capability('mod/spatialforum:moderate', $context);
        self::with_lock($postid, function () use ($cm, $postid, $restore, $DB) {
            $post = $DB->get_record('spatialforum_posts', ['id' => $postid, 'spatialforumid' => $cm->instance], '*', MUST_EXIST);
            group_access::for_activity($cm->instance)->require_post($post, true);
            if ($restore && !self::restorable($post)) {
                throw new \moodle_exception('cannotrestorepost', 'mod_spatialforum');
            }
            if (!$restore && $post->deleted) {
                return;
            }
            $transaction = $DB->start_delegated_transaction();
            try {
                $DB->delete_records('spatialforum_deliveries', ['postid' => $postid]);
                $DB->update_record('spatialforum_posts', (object) [
                    'id' => $postid, 'deleted' => $restore ? 0 : 1, 'deletedat' => $restore ? $post->deletedat : time(),
                ]);
                \mod_spatialforum\completion\custom_completion::refresh($cm->instance, (int) $post->userid);
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
        });
    }

    /**
     * Old destructive deletions and privacy erasures have no restoration window.
     * @param \stdClass $post
     * @return bool
     */
    public static function restorable(\stdClass $post): bool {
        return !empty($post->deleted) && !empty($post->deletedat)
            && $post->deletedat > time() - self::RETENTION;
    }

    /**
     * Privacy erasure has no undo, and shares the edit/restore lock.
     * @param int $postid
     * @param \context_module $context
     * @param bool $remove Remove the entire row for context-wide erasure
     */
    public static function erase(int $postid, \context_module $context, bool $remove = false): void {
        global $DB;
        self::with_lock($postid, function () use ($postid, $context, $remove, $DB) {
            $post = $DB->get_record('spatialforum_posts', ['id' => $postid]);
            if (!$post) {
                return;
            }
            $cm = get_coursemodule_from_id('spatialforum', $context->instanceid, 0, false, MUST_EXIST);
            if ($post->spatialforumid != $cm->instance) {
                throw new \coding_exception('Privacy context does not match the post.');
            }
            media::delete($postid, $context);
            if ($remove) {
                $DB->delete_records('spatialforum_posts', ['id' => $postid]);
            } else {
                $DB->update_record('spatialforum_posts', (object) [
                    'id' => $postid, 'userid' => 0, 'subject' => '', 'discussionname' => null, 'submissionkey' => null,
                    'message' => '', 'replyparts' => null, 'deleted' => 1, 'deletedat' => 0,
                ]);
            }
        });
    }

    /**
     * Bounded cleanup rechecks state under the same lock as moderator restoration.
     * @return int
     */
    public static function purge(): int {
        global $DB;
        $posts = $DB->get_records_select(
            'spatialforum_posts',
            'deleted = 1 AND deletedat > 0 AND deletedat <= ?',
            [time() - self::RETENTION],
            'id',
            'id',
            0,
            250
        );
        foreach ($posts as $candidate) {
            self::with_lock($candidate->id, function () use ($candidate, $DB) {
                $post = $DB->get_record('spatialforum_posts', ['id' => $candidate->id], '*', MUST_EXIST);
                if (!$post->deleted || !$post->deletedat || self::restorable($post)) {
                    return;
                }
                $cm = get_coursemodule_from_instance('spatialforum', $post->spatialforumid, 0, false, MUST_EXIST);
                media::delete($post->id, \context_module::instance($cm->id));
                $DB->update_record('spatialforum_posts', (object) [
                    'id' => $post->id, 'message' => '', 'replyparts' => null, 'deletedat' => 0,
                ]);
            });
        }
        return count($posts);
    }

    /**
     * Serialize edits, removals, restoration and retention cleanup for the same post.
     * @param int $postid
     * @param callable $operation
     */
    private static function with_lock(int $postid, callable $operation): void {
        $lock = \core\lock\lock_config::get_lock_factory('mod_spatialforum')->get_lock('post:' . $postid, 10);
        if (!$lock) {
            throw new \moodle_exception('submissionbusy', 'mod_spatialforum');
        }
        try {
            $operation();
        } finally {
            $lock->release();
        }
    }
}
