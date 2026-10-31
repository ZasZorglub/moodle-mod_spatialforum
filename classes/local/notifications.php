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


namespace mod_spatialforum\local;

/**
 * Opt-in subscriptions, transactional fan-out and permission-aware notification delivery.
 */
class notifications {
    /** Site policy for activity subscriptions. */
    public const POLICY_OPTIN = 'optin';
    public const POLICY_INITIAL = 'initial';
    public const POLICY_FORCED = 'forced';
    public const POLICY_DISABLED = 'disabled';
    /** No opt-in. */
    public const OFF = 0;
    /** One notification for each new post. */
    public const IMMEDIATE = 1;
    /** A digest at the next daily cutoff. */
    public const DAILY = 2;
    /** Not yet handed to Moodle. */
    public const PENDING = 0;
    /** Accepted by the Moodle Message API. */
    public const ACCEPTED = 1;
    /** Explicit send failures exhausted the retry budget. */
    public const FAILED = 2;
    /** Dispatch started but acceptance is not known. */
    public const UNCERTAIN = 3;
    /** Maximum age of delivery metadata, in seconds. */
    public const RETENTION = 30 * DAYSECS;

    /** Return the administrator-selected subscription policy. */
    public static function policy(): string {
        $policy = (string) get_config('mod_spatialforum', 'subscriptionpolicy');
        return in_array($policy, [self::POLICY_OPTIN, self::POLICY_INITIAL, self::POLICY_FORCED, self::POLICY_DISABLED], true)
            ? $policy : self::POLICY_OPTIN;
    }

    /** Default activity mode for a newly eligible participant. */
    public static function default_mode(): int {
        return match (self::policy()) {
            self::POLICY_INITIAL, self::POLICY_FORCED => self::IMMEDIATE,
            default => self::OFF,
        };
    }

    /** Materialise an administrator-selected initial/forced subscription when a participant opens the activity. */
    public static function ensure_activity_subscription(int $forumid, int $userid): void {
        global $DB;
        if (!in_array(self::policy(), [self::POLICY_INITIAL, self::POLICY_FORCED], true)) {
            return;
        }
        $cm = get_coursemodule_from_instance('spatialforum', $forumid, 0, false, MUST_EXIST);
        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user || !self::eligible($cm, $user)) {
            return;
        }
        if (!$DB->record_exists('spatialforum_subscriptions', ['spatialforumid' => $forumid, 'userid' => $userid, 'rootpostid' => 0])) {
            $first = (int) $DB->get_field_sql('SELECT MAX(id) FROM {spatialforum_posts} WHERE spatialforumid = ?', [$forumid]);
            $DB->insert_record('spatialforum_subscriptions', (object) [
                'spatialforumid' => $forumid, 'userid' => $userid, 'rootpostid' => 0,
                'mode' => self::IMMEDIATE, 'firstpostid' => $first,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
    }

    /**
     * An active participant must be able to view the activity now.
     *
     * @param \stdClass $cm
     * @param \stdClass $user
     * @return bool
     */
    public static function eligible(\stdClass $cm, \stdClass $user): bool {
        if (empty($user->id) || !empty($user->deleted) || !empty($user->suspended) || isguestuser($user)) {
            return false;
        }
        global $DB;
        $course = $DB->get_record('course', ['id' => $cm->course], 'id,visible');
        if (
            !$course || (!$course->visible && !has_capability(
                'moodle/course:viewhiddencourses',
                \context_course::instance($cm->course),
                $user->id
            ))
        ) {
            return false;
        }
        $context = \context_module::instance($cm->id);
        return is_enrolled($context, $user, 'mod/spatialforum:view', true)
            && get_fast_modinfo($cm->course, $user->id)->get_cm($cm->id)->uservisible;
    }

    /**
     * Set only the authenticated user's preference. Unsubscribe also works after access is lost.
     *
     * @param int $forumid
     * @param int $mode
     * @param int $postid Zero for the activity, otherwise a post in the conversation
     */
    public static function set_mode(int $forumid, int $mode, int $postid = 0): void {
        global $DB, $USER;
        $modes = $postid ? [thread_subscriptions::INHERIT, self::OFF, self::IMMEDIATE, self::DAILY]
            : [self::OFF, self::IMMEDIATE, self::DAILY];
        if (!isloggedin() || isguestuser() || !in_array($mode, $modes, true)) {
            throw new \moodle_exception('invalidsubscription', 'mod_spatialforum');
        }
        $cm = get_coursemodule_from_instance('spatialforum', $forumid, 0, false, MUST_EXIST);
        if ($mode && self::policy() === self::POLICY_DISABLED) {
            throw new \moodle_exception('invalidsubscription', 'mod_spatialforum');
        }
        if ($mode && !self::eligible($cm, $USER)) {
            throw new \moodle_exception('invalidsubscription', 'mod_spatialforum');
        }
        if (self::policy() === self::POLICY_FORCED && !$mode) {
            throw new \moodle_exception('invalidsubscription', 'mod_spatialforum');
        }
        $rootid = 0;
        if ($postid) {
            $own = $DB->get_record('spatialforum_subscriptions', [
                'spatialforumid' => $forumid, 'userid' => $USER->id, 'rootpostid' => $postid,
            ]);
            if ($mode === self::OFF && $own) {
                $rootid = $postid;
            } else {
                $post = $DB->get_record('spatialforum_posts', ['id' => $postid, 'spatialforumid' => $forumid], '*', MUST_EXIST);
                group_access::for_activity($forumid)->require_post($post);
                $rootid = thread_subscriptions::root($post);
            }
        }
        $lock = self::lock($forumid, $USER->id);
        if (!$lock) {
            throw new \moodle_exception('notificationbusy', 'mod_spatialforum');
        }
        try {
            $transaction = $DB->start_delegated_transaction();
            $activitywhere = ['spatialforumid' => $forumid, 'userid' => $USER->id];
            $where = $activitywhere + ['rootpostid' => $rootid];
            $old = $DB->get_record('spatialforum_subscriptions', $where);
            if ($mode === self::OFF && !$rootid) {
                $DB->delete_records('spatialforum_subscriptions', $activitywhere);
                $DB->delete_records('spatialforum_deliveries', $activitywhere);
            } else if (!$old) {
                $first = (int) $DB->get_field_sql('SELECT MAX(id) FROM {spatialforum_posts} WHERE spatialforumid = ?', [$forumid]);
                $DB->insert_record('spatialforum_subscriptions', (object) ($where + [
                    'mode' => $mode, 'firstpostid' => $first, 'timecreated' => time(), 'timemodified' => time(),
                ]));
            } else if ((int) $old->mode !== $mode) {
                if ((int) $old->mode === self::OFF) {
                    $old->firstpostid = (int) $DB->get_field_sql(
                        'SELECT MAX(id) FROM {spatialforum_posts} WHERE spatialforumid = ?',
                        [$forumid]
                    );
                }
                $old->mode = $mode;
                $old->timemodified = time();
                $DB->update_record('spatialforum_subscriptions', $old);
            }
            thread_subscriptions::reschedule($forumid, $USER->id, $rootid);
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
    }

    /**
     * Queue a task inside the post transaction; the worker cannot see uncommitted posts.
     *
     * @param int $postid
     */
    public static function schedule_post(int $postid): void {
        $task = new \mod_spatialforum\task\fanout_post();
        $task->set_component('mod_spatialforum');
        $task->set_custom_data(['postid' => $postid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Resolve subscribers in bounded pages; repeated fan-out is idempotent.
     *
     * @param int $postid
     */
    public static function enqueue_post(int $postid): void {
        global $DB;
        $post = $DB->get_record('spatialforum_posts', ['id' => $postid, 'deleted' => 0, 'deletedat' => 0]);
        if (!$post || $post->timecreated < time() - self::RETENTION) {
            return;
        }
        $cm = get_coursemodule_from_instance('spatialforum', $post->spatialforumid);
        if (!$cm) {
            return;
        }
        $rootid = thread_subscriptions::root($post);
        $lastid = 0;
        do {
            $subscriptions = $DB->get_records_select(
                'spatialforum_subscriptions',
                'spatialforumid = :forumid AND id > :lastid AND (rootpostid = 0 OR rootpostid = :rootid)',
                ['forumid' => $post->spatialforumid, 'lastid' => $lastid, 'rootid' => $rootid],
                'id ASC',
                '*',
                0,
                250
            );
            foreach ($subscriptions as $sub) {
                $lastid = (int) $sub->id;
                if ((int) $sub->userid === (int) $post->userid || $post->id <= $sub->firstpostid) {
                    continue;
                }
                $lock = self::lock($post->spatialforumid, $sub->userid);
                if (!$lock) {
                    throw new \moodle_exception('notificationbusy', 'mod_spatialforum');
                }
                try {
                    $sub = $DB->get_record('spatialforum_subscriptions', ['id' => $sub->id]);
                    $user = $sub ? $DB->get_record('user', ['id' => $sub->userid]) : false;
                    $sub = $user ? thread_subscriptions::effective($post, $user->id, $rootid) : null;
                    if (
                        !$sub || !$user || $post->id <= $sub->firstpostid
                        || !communication::allows($post, (int) $sub->mode) || !self::eligible($cm, $user)
                        || !group_access::for_activity($post->spatialforumid, $user->id)->can_view($post)
                    ) {
                        continue;
                    }
                    $where = ['postid' => $post->id, 'userid' => $user->id];
                    if (!$DB->record_exists('spatialforum_deliveries', $where)) {
                        $DB->insert_record('spatialforum_deliveries', (object) ($where + [
                            'spatialforumid' => $post->spatialforumid, 'mode' => $sub->mode,
                            'due' => self::due((int) $sub->mode, $user, (int) $post->timecreated),
                            'status' => self::PENDING, 'attempts' => 0, 'messageid' => 0,
                            'timecreated' => time(), 'timemodified' => time(),
                        ]));
                    }
                } finally {
                    $lock->release();
                }
            }
        } while (count($subscriptions) === 250);
    }

    /**
     * Configured publication delay or daily cutoff, including DST.
     *
     * @param int $mode
     * @param \stdClass $user
     * @param int $when
     * @return int
     */
    public static function due(int $mode, \stdClass $user, int $when): int {
        $settings = communication::settings();
        if ($mode === self::IMMEDIATE) {
            return $when + (int) $settings['delayminutes'] * MINSECS;
        }
        $date = new \DateTimeImmutable('@' . $when);
        $timezone = $settings['digesttimezone'] === 'site' ? \core_date::get_server_timezone_object()
            : \core_date::get_user_timezone_object($user);
        $date = $date->setTimezone($timezone);
        $cutoff = $date->setTime((int) $settings['digesthour'], (int) $settings['digestminute'], 0);
        if ($cutoff->getTimestamp() <= $when) {
            $cutoff = $cutoff->modify('+1 day');
        }
        return $cutoff->getTimestamp();
    }

    /**
     * Process at most 1000 due rows and 100 messages. Further work stays queued.
     *
     * @param int|null $now
     * @return array
     */
    public static function dispatch(?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $result = ['accepted' => 0, 'skipped' => 0, 'failed' => 0, 'uncertain' => 0];
        $DB->delete_records_select('spatialforum_deliveries', 'timecreated < ?', [$now - self::RETENTION]);
        $rows = $DB->get_records_select(
            'spatialforum_deliveries',
            'status = ? AND due <= ?',
            [self::PENDING, $now],
            'due ASC, id ASC',
            '*',
            0,
            1000
        );
        $buckets = [];
        foreach ($rows as $row) {
            $key = $row->mode == self::IMMEDIATE ? 'post:' . $row->id
                : implode(':', [$row->userid, $row->spatialforumid, $row->due]);
            // Digests have bounded content; later rows are picked up by the next task run.
            if (count($buckets[$key] ?? []) < 100) {
                $buckets[$key][] = $row;
            }
        }
        $messages = 0;
        foreach ($buckets as $batch) {
            if ($messages >= 100) {
                break;
            }
            $first = reset($batch);
            $lock = self::lock($first->spatialforumid, $first->userid);
            if (!$lock) {
                continue;
            }
            try {
                $cm = get_coursemodule_from_instance('spatialforum', $first->spatialforumid);
                $user = $DB->get_record('user', ['id' => $first->userid]);
                $policy = $cm && $user && self::eligible($cm, $user)
                    ? group_access::for_activity($cm->instance, $user->id) : null;
                $posts = [];
                $ids = [];
                foreach ($batch as $queued) {
                    $row = $DB->get_record('spatialforum_deliveries', ['id' => $queued->id, 'status' => self::PENDING]);
                    if (!$row || $row->due > $now) {
                        continue;
                    }
                    if ((int) $row->mode !== (int) $first->mode) {
                        continue;
                    }
                    $post = $DB->get_record('spatialforum_posts', ['id' => $row->postid,
                        'spatialforumid' => $row->spatialforumid, 'deleted' => 0]);
                    $sub = $post ? thread_subscriptions::effective($post, $first->userid) : null;
                    if (
                        !$sub || !$policy || !$post || $post->deletedat || !communication::allows($post, (int) $sub->mode)
                            || !$policy->can_view($post) || $post->id <= $sub->firstpostid
                    ) {
                        $DB->delete_records('spatialforum_deliveries', ['id' => $row->id]);
                        $result['skipped']++;
                        continue;
                    }
                    if ((int) $row->mode !== (int) $sub->mode) {
                        $DB->update_record('spatialforum_deliveries', (object) [
                            'id' => $row->id, 'mode' => $sub->mode, 'due' => self::due((int) $sub->mode, $user, $now),
                        ]);
                        continue;
                    }
                    $currentdue = self::due((int) $row->mode, $user, (int) $post->timecreated);
                    if ($currentdue > $now) {
                        $DB->set_field('spatialforum_deliveries', 'due', $currentdue, ['id' => $row->id]);
                        continue;
                    }
                    $posts[] = $post;
                    $ids[] = $row->id;
                }
                if (!$ids) {
                    continue;
                }
                // A crash/exception after this point is ambiguous, never silently auto-retried.
                [$in, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
                $DB->set_field_select('spatialforum_deliveries', 'status', self::UNCERTAIN, "id $in", $params);
                $DB->set_field_select('spatialforum_deliveries', 'timemodified', $now, "id $in", $params);
                try {
                    $id = static::send($cm, $user, $posts, (int) $first->mode);
                } catch (\Throwable $e) {
                    $result['uncertain'] += count($ids);
                    debugging(
                        'Spatialforum delivery is uncertain; inspect the scheduled task log and delivery report.',
                        DEBUG_DEVELOPER
                    );
                    throw $e;
                }
                $messages++;
                foreach ($ids as $rowid) {
                    $row = $DB->get_record('spatialforum_deliveries', ['id' => $rowid], '*', MUST_EXIST);
                    $row->attempts++;
                    $row->timemodified = $now;
                    $row->messageid = $id ?: 0;
                    $row->status = $id ? self::ACCEPTED : ($row->attempts >= 5 ? self::FAILED : self::PENDING);
                    if (!$id) {
                        $row->due = $now + min(DAYSECS, 60 * (2 ** $row->attempts));
                    }
                    $DB->update_record('spatialforum_deliveries', $row);
                }
                $result[$id ? 'accepted' : 'failed'] += count($ids);
            } finally {
                $lock->release();
            }
        }
        return $result;
    }

    /**
     * Send only a safe excerpt of each author's own content, with authenticated links to media.
     *
     * @param \stdClass $cm
     * @param \stdClass $user
     * @param array $posts
     * @param int $mode
     * @return int|false
     */
    protected static function send(\stdClass $cm, \stdClass $user, array $posts, int $mode) {
        global $DB, $SITE;
        $activity = $DB->get_record('spatialforum', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $name = format_string($activity->name, true, ['context' => $context, 'filter' => false]);
        $items = [];
        $reasons = [];
        foreach ($posts as $post) {
            $sub = thread_subscriptions::effective($post, $user->id);
            if ($sub) {
                $reasons[$sub->reason] = get_string_manager()->get_string(
                    'subscriptionreason' . $sub->reason,
                    'mod_spatialforum',
                    null,
                    $user->lang
                );
            }
            $author = $DB->get_record('user', ['id' => $post->userid]);
            $url = new \moodle_url('/mod/spatialforum/view.php', ['id' => $cm->id, 'postid' => $post->id]);
            $items[] = ['subject' => $post->subject,
                'author' => $author ? fullname($author) : get_string('unknownuser', 'mod_spatialforum'),
                'excerpt' => \core_text::substr(excerpts::source($post, $context), 0, 500), 'posturl' => $url->out(false)];
        }
        $settingsurl = new \moodle_url('/mod/spatialforum/subscription.php', ['id' => $cm->id]);
        $course = $DB->get_record('course', ['id' => $cm->course], 'id,fullname', MUST_EXIST);
        $rendered = communication::render(communication::settings($user->lang), $mode, [
            'site' => html_to_text($SITE->fullname), 'course' => html_to_text($course->fullname),
            'activity' => html_to_text($name), 'recipient' => fullname($user),
        ], $items, $settingsurl->out(false), $user->lang);
        $reasontext = implode(' ', $reasons);
        $rendered['text'] .= "\n\n" . $reasontext;
        $rendered['html'] .= \html_writer::tag('p', s($reasontext));
        $message = new \core\message\message();
        $message->component = 'mod_spatialforum';
        $message->name = $mode === self::DAILY ? 'digests' : 'posts';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->courseid = $cm->course;
        $message->subject = $rendered['subject'];
        $message->fullmessage = $rendered['text'];
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $rendered['html'];
        $message->smallmessage = $rendered['subject'];
        $message->notification = 1;
        $message->contexturl = (new \moodle_url('/mod/spatialforum/view.php', ['id' => $cm->id]))->out(false);
        $message->contexturlname = html_to_text($name);
        return message_send($message);
    }

    /**
     * Reconcile one bounded page after a site policy change, using the latest saved policy.
     * Retry backoff is never shortened; accepted/failed/uncertain deliveries are untouched.
     * @param int $lastid
     * @return int|null Next page cursor or null when finished
     */
    public static function reconcile(int $lastid = 0): ?int {
        global $DB;
        $rows = $DB->get_records_select(
            'spatialforum_deliveries',
            'status = ? AND id > ?',
            [self::PENDING, $lastid],
            'id ASC',
            '*',
            0,
            250
        );
        foreach ($rows as $queued) {
            $lastid = (int) $queued->id;
            $lock = self::lock($queued->spatialforumid, $queued->userid);
            if (!$lock) {
                throw new \moodle_exception('notificationbusy', 'mod_spatialforum');
            }
            try {
                $row = $DB->get_record('spatialforum_deliveries', ['id' => $queued->id, 'status' => self::PENDING]);
                if (!$row) {
                    continue;
                }
                $post = $DB->get_record('spatialforum_posts', ['id' => $row->postid, 'deleted' => 0]);
                $user = $DB->get_record('user', ['id' => $row->userid]);
                if (!$post || !$user || !communication::allows($post, (int) $row->mode)) {
                    $DB->delete_records('spatialforum_deliveries', ['id' => $row->id]);
                    continue;
                }
                $due = self::due((int) $row->mode, $user, (int) $post->timecreated);
                $row->due = $row->attempts ? max($due, $row->due) : $due;
                $row->timemodified = time();
                $DB->update_record('spatialforum_deliveries', $row);
            } finally {
                $lock->release();
            }
        }
        return count($rows) === 250 ? $lastid : null;
    }

    /**
     * Serialise subscription changes, fan-out and delivery for one participant/activity.
     *
     * @param int $forumid
     * @param int $userid
     * @return \core\lock\lock|false
     */
    private static function lock(int $forumid, int $userid) {
        return \core\lock\lock_config::get_lock_factory('mod_spatialforum')->get_lock('notify:' . $forumid . ':' . $userid, 10);
    }
}
