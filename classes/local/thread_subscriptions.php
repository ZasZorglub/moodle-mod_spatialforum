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
 * Conversation preferences override an activity preference without duplicating deliveries.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_spatialforum\local;

/**
 * A conversation comprises its top-level post and every descendant branch.
 */
class thread_subscriptions {
    /**
     * Inherit the activity frequency, retaining an explicit history boundary for this conversation.
     */
    public const INHERIT = -1;

    /**
     * Resolve actual parent links; never infer a conversation from the shared activity root track.
     * @param \stdClass $post
     * @return int
     */
    public static function root(\stdClass $post): int {
        global $DB;
        $seen = [];
        $forumid = $post->spatialforumid;
        $groupid = $post->groupid;
        while (!isset($seen[$post->id])) {
            $seen[$post->id] = true;
            $parentid = $post->replytoid;
            if (!$parentid) {
                $parentid = $DB->get_field(
                    'spatialforum_tracks',
                    'originpostid',
                    ['id' => $post->trackid, 'spatialforumid' => $forumid],
                    MUST_EXIST
                );
            }
            if (!$parentid) {
                return (int) $post->id;
            }
            $post = $DB->get_record('spatialforum_posts', ['id' => $parentid, 'spatialforumid' => $forumid], '*', MUST_EXIST);
            if ($post->groupid != $groupid) {
                throw new \moodle_exception('invalidsubscription', 'mod_spatialforum');
            }
        }
        throw new \moodle_exception('invalidsubscription', 'mod_spatialforum');
    }

    /**
     * Return the authoritative frequency and history boundary for one post and recipient.
     * @param \stdClass $post
     * @param int $userid
     * @param int|null $rootid
     * @return \stdClass|null
     */
    public static function effective(\stdClass $post, int $userid, ?int $rootid = null): ?\stdClass {
        global $DB;
        $rootid = $rootid ?? self::root($post);
        $rows = $DB->get_records_select(
            'spatialforum_subscriptions',
            'spatialforumid = ? AND userid = ? AND (rootpostid = 0 OR rootpostid = ?)',
            [$post->spatialforumid, $userid, $rootid]
        );
        $activity = null;
        $thread = null;
        foreach ($rows as $row) {
            if ($row->rootpostid) {
                $thread = $row;
            } else {
                $activity = $row;
            }
        }
        $sub = $thread ?? $activity;
        if ($thread && (int) $thread->mode === self::INHERIT) {
            if (!$activity) {
                return null;
            }
            $sub = clone $activity;
            $sub->firstpostid = max($activity->firstpostid, $thread->firstpostid);
        }
        if (!$sub || !in_array((int) $sub->mode, [notifications::IMMEDIATE, notifications::DAILY], true)) {
            return null;
        }
        $sub->reason = $sub->rootpostid ? 'thread' : 'activity';
        return $sub;
    }

    /**
     * Reconcile pending work after a preference change; accepted messages are not recalled.
     * The caller holds the existing activity/user subscription lock.
     * @param int $forumid
     * @param int $userid
     * @param int $rootid Zero to reconcile the whole activity
     */
    public static function reschedule(int $forumid, int $userid, int $rootid = 0): void {
        global $DB;
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $rows = $DB->get_recordset('spatialforum_deliveries', [
            'spatialforumid' => $forumid, 'userid' => $userid, 'status' => notifications::PENDING,
        ]);
        try {
            foreach ($rows as $row) {
                $post = $DB->get_record('spatialforum_posts', ['id' => $row->postid]);
                if ($post && $rootid && self::root($post) !== $rootid) {
                    continue;
                }
                $sub = $post ? self::effective($post, $userid) : null;
                if (!$sub || $post->id <= $sub->firstpostid) {
                    $DB->delete_records('spatialforum_deliveries', ['id' => $row->id]);
                    continue;
                }
                if ((int) $row->mode === (int) $sub->mode) {
                    continue;
                }
                $DB->update_record('spatialforum_deliveries', (object) [
                    'id' => $row->id, 'mode' => $sub->mode, 'due' => notifications::due((int) $sub->mode, $user, time()),
                ]);
            }
        } finally {
            $rows->close();
        }
    }
}
