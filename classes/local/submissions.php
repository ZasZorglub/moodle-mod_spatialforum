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
 * Transactional replay protection for ordinary post submissions.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_spatialforum\local;

/**
 * One authenticated activity/form key produces one persisted post.
 */
class submissions {
    /**
     * Replays return the original post; failed transactions leave the key available for retry.
     * The caller supplies the normal authorised publication and File API operation.
     * @param \stdClass $cm
     * @param string $token
     * @param callable $create Returns the created post ID
     * @return int
     */
    public static function save(\stdClass $cm, string $token, callable $create): int {
        global $DB, $USER;
        require_course_login(get_course($cm->course), false, $cm);
        $context = \context_module::instance($cm->id);
        require_capability('mod/spatialforum:view', $context);
        require_capability('mod/spatialforum:reply', $context);
        $activity = $DB->get_record('spatialforum', ['id' => $cm->instance], '*', MUST_EXIST);
        if ($activity->locked || $activity->importstate) {
            throw new \moodle_exception('forumlocked', 'mod_spatialforum');
        }
        if (!isloggedin() || isguestuser() || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new \moodle_exception('invalidsubmission', 'mod_spatialforum');
        }
        $key = hash('sha256', json_encode([(int) $USER->id, (int) $cm->id, $token]));
        $lock = \core\lock\lock_config::get_lock_factory('mod_spatialforum')->get_lock('submission:' . $key, 10);
        if (!$lock) {
            throw new \moodle_exception('submissionbusy', 'mod_spatialforum');
        }
        try {
            if ($post = $DB->get_record('spatialforum_posts', ['submissionkey' => $key])) {
                self::require_post($post, $cm);
                return (int) $post->id;
            }
            $transaction = $DB->start_delegated_transaction();
            try {
                $postid = (int) $create();
                $post = $DB->get_record('spatialforum_posts', ['id' => $postid], '*', MUST_EXIST);
                self::require_post($post, $cm);
                $DB->set_field('spatialforum_posts', 'submissionkey', $key, ['id' => $postid]);
                $transaction->allow_commit();
                return $postid;
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Never return a replay across an author, activity, group or deletion boundary.
     * @param \stdClass $post
     * @param \stdClass $cm
     */
    private static function require_post(\stdClass $post, \stdClass $cm): void {
        global $USER;
        if ($post->userid != $USER->id || $post->spatialforumid != $cm->instance || $post->deleted) {
            throw new \moodle_exception('invalidsubmission', 'mod_spatialforum');
        }
        group_access::for_activity($cm->instance)->require_post($post, true);
    }
}
