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

use context_module;
use moodle_url;
use stdClass;

/**
 * Domain operations and view data for a spatial forum.
 *
 * @package mod_spatialforum
 */
class forum {
    /**
     * @var array Track colour palette.
     */
    private const COLOURS = [
        '#54b9ec', '#f0aa4f', '#a98be8', '#ef769c',
        '#59d6c2', '#ff8366', '#78d59b', '#c18bea',
    ];

    /**

     * Editable reply subject, without accumulating repeated Re: prefixes.

     */
    public static function reply_subject(string $subject): string {
        $subject = trim($subject);
        if (!preg_match('/^re\s*:/i', $subject)) {
            $subject = 'Re: ' . $subject;
        }
        return \core_text::substr($subject, 0, 255);
    }

    /**

     * Parent content is read live so moderation and privacy erasure take effect in every quote.

     */
    public static function export_reply_context(
        stdClass $post,
        context_module $context,
        int $cmid,
        ?array $posts = null,
        ?array $users = null,
        ?group_access $access = null
    ): array {
        global $DB;

        $access = $access ?? group_access::for_activity($post->spatialforumid);
        if (!empty($post->deleted) || !$access->can_view($post)) {
            return ['available' => false];
        }
        $user = $users !== null ? ($users[$post->userid] ?? null) : $DB->get_record('user', ['id' => $post->userid]);
        $url = new moodle_url('/mod/spatialforum/view.php', ['id' => $cmid, 'trackid' => $post->trackid]);
        $url->set_anchor('post-' . $post->id);
        return [
            ...self::post_times($post),
            'available' => true,
            'subject' => format_string($post->subject),
            'author' => $user ? fullname($user) : get_string('unknownuser', 'mod_spatialforum'),
            'time' => userdate($post->timecreated),
            'message' => excerpts::render($post, $context, $cmid, $posts, $users, $access),
            'url' => $url->out(false),
        ];
    }

    /**

     * Whether the site and this activity both allow likes. Disabling preserves existing data.

     */
    public static function likes_enabled(stdClass $activity): bool {
        return !empty($activity->enablelikes) && get_config('mod_spatialforum', 'allowlikes') !== '0';
    }

    /**

     * Set one user's like state, serialising duplicate submissions. Caller must check capabilities.

     */
    public static function set_like(stdClass $activity, stdClass $post, int $userid, bool $liked): void {
        self::set_reaction($activity, $post, $userid, $liked ? 1 : 0);
    }

    /**

     * One reaction per user: 1 = like, -1 = dislike, 0 = remove.

     */
    public static function set_reaction(stdClass $activity, stdClass $post, int $userid, int $reaction): void {
        global $DB;

        group_access::for_activity($activity->id, $userid)->require_post($post, true);
        if (!in_array($reaction, [-1, 0, 1], true)) {
            throw new \moodle_exception('invalidaction', 'mod_spatialforum');
        }
        if (!self::likes_enabled($activity)) {
            throw new \moodle_exception('likesdisabled', 'mod_spatialforum');
        }
        if (!$userid || $post->spatialforumid != $activity->id || $post->deleted) {
            throw new \moodle_exception('invalidaction', 'mod_spatialforum');
        }
        $factory = \core\lock\lock_config::get_lock_factory('mod_spatialforum');
        $lock = $factory->get_lock('like:' . $post->id . ':' . $userid, 10);
        if (!$lock) {
            throw new \moodle_exception('likelocktimeout', 'mod_spatialforum');
        }
        try {
            $conditions = ['userid' => $userid, 'postid' => $post->id];
            $existing = $DB->get_record('spatialforum_likes', $conditions);
            if (!$reaction) {
                $DB->delete_records('spatialforum_likes', $conditions);
            } else if ($existing) {
                if ((int) $existing->reaction !== $reaction) {
                    $existing->reaction = $reaction;
                    $existing->timecreated = time();
                    $DB->update_record('spatialforum_likes', $existing);
                }
            } else {
                $DB->insert_record('spatialforum_likes', (object) ($conditions + [
                    'reaction' => $reaction, 'timecreated' => time(),
                ]));
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Creates a named child track.
     *
     * @param stdClass $activity
     * @param stdClass $parenttrack
     * @param int $originpostid
     * @param string $name
     * @return int
     */
    public static function create_track(
        stdClass $activity,
        stdClass $parenttrack,
        int $originpostid,
        string $name
    ): int {
        global $DB;

        if (!$originpostid && group_access::for_activity($activity->id)->uses_groups()) {
            throw new \moodle_exception('groupaccessdenied', 'mod_spatialforum');
        }
        if ($originpostid) {
            $origin = $DB->get_record('spatialforum_posts', ['id' => $originpostid,
                'spatialforumid' => $activity->id], '*', MUST_EXIST);
            group_access::for_activity($activity->id)->require_post($origin, true);
        }
        if (trim($name) === '' || \core_text::strlen(trim($name)) > 255) {
            throw new \moodle_exception('invalidtrackname', 'mod_spatialforum');
        }
        $depth = count(self::get_path($parenttrack->id, $activity->id));
        if ($depth >= $activity->maxdepth) {
            throw new \moodle_exception('maxdepthreached', 'mod_spatialforum');
        }
        $siblingcount = $DB->count_records('spatialforum_tracks', ['parenttrackid' => $parenttrack->id]);
        $colour = self::COLOURS[($depth + $siblingcount - 1) % count(self::COLOURS)];
        if (empty($activity->enablecolors)) {
            $colour = '#dfe7e2';
        }
        $now = time();
        return $DB->insert_record('spatialforum_tracks', (object) [
            'spatialforumid' => $activity->id,
            'parenttrackid' => $parenttrack->id,
            'originpostid' => $originpostid,
            'name' => trim($name),
            'colour' => $colour,
            'locked' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**

     * Publish each reply in a separate child track. Caller checks reply and branch capabilities.

     */
    public static function reply_in_new_track(
        stdClass $activity,
        stdClass $parenttrack,
        int $originpostid,
        int $userid,
        string $branchname,
        string $subject,
        string $message,
        int $messageformat,
        bool $showparent = false,
        string $excerptdraft = '[]'
    ): int {
        global $DB;

        if ($parenttrack->spatialforumid != $activity->id || trim($branchname) === '') {
            throw new \moodle_exception('invalidaction', 'mod_spatialforum');
        }
        if ($activity->locked || $parenttrack->locked) {
            throw new \moodle_exception('forumlocked', 'mod_spatialforum');
        }
        $DB->get_record('spatialforum_posts', [
            'id' => $originpostid, 'trackid' => $parenttrack->id,
            'spatialforumid' => $activity->id, 'deleted' => 0,
        ], '*', MUST_EXIST);
        if (count(self::get_path($parenttrack->id, $activity->id)) >= $activity->maxdepth) {
            throw new \moodle_exception('maxdepthreached', 'mod_spatialforum');
        }
        $transaction = $DB->start_delegated_transaction();
        try {
            $trackid = self::create_track($activity, $parenttrack, $originpostid, $branchname);
            self::create_post(
                $activity,
                $trackid,
                $userid,
                $subject,
                $message,
                $messageformat,
                $originpostid,
                $showparent,
                $excerptdraft
            );
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
        return $trackid;
    }

    /**
     * Inserts a post.
     *
     * @param stdClass $activity
     * @param int $trackid
     * @param int $userid
     * @param string $subject
     * @param string $message
     * @param int $messageformat
     * @param int $replytoid
     * @param bool $showparent Whether the author opted in to displaying the parent quote.
     * @param string $excerptdraft Selected quotations and the reply author's comments.
     * @return int
     */
    public static function create_post(
        stdClass $activity,
        int $trackid,
        int $userid,
        string $subject,
        string $message,
        int $messageformat,
        int $replytoid = 0,
        bool $showparent = false,
        string $excerptdraft = '[]',
        ?int $groupid = null,
        bool $notify = true
    ): int {
        global $DB;

        $access = group_access::for_activity($activity->id, $userid);
        $track = $DB->get_record('spatialforum_tracks', ['id' => $trackid,
            'spatialforumid' => $activity->id], '*', MUST_EXIST);
        $parentid = $replytoid ?: $track->originpostid;
        if ($parentid) {
            $parent = $DB->get_record('spatialforum_posts', ['id' => $parentid,
                'spatialforumid' => $activity->id], '*', MUST_EXIST);
            $access->require_post($parent, true);
            $groupid = (int) ($parent->groupid ?? -1);
        } else {
            $groupid = $groupid ?? $access->selected_group();
        }
        if (!($parentid ? $access->can_write($groupid) : $access->can_start($groupid))) {
            throw new \moodle_exception('groupaccessdenied', 'mod_spatialforum');
        }
        if (trim($subject) === '' || \core_text::strlen(trim($subject)) > 255) {
            throw new \moodle_exception('invalidsubject', 'mod_spatialforum');
        }
        $replyparts = null;
        if ($excerptdraft !== '[]' && $excerptdraft !== '') {
            $origin = $DB->get_record('spatialforum_posts', ['id' => $replytoid,
                'spatialforumid' => $activity->id, 'deleted' => 0], '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('spatialforum', $activity->id, 0, false, MUST_EXIST);
            $replyparts = excerpts::prepare($excerptdraft, $origin, context_module::instance($cm->id));
        }
        $now = time();
        $transaction = $DB->start_delegated_transaction();
        try {
            $postid = $DB->insert_record('spatialforum_posts', (object) [
            'spatialforumid' => $activity->id,
            'trackid' => $trackid,
            'groupid' => $groupid,
            'replytoid' => $replytoid,
            'showparent' => $replytoid && $showparent ? 1 : 0,
            'replyparts' => $replyparts,
            'userid' => $userid,
            'subject' => trim($subject),
            'message' => $message,
            'messageformat' => $messageformat,
            'deleted' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            ]);
            if ($notify) {
                notifications::schedule_post($postid);
            }
            \mod_spatialforum\completion\custom_completion::refresh($activity->id, $userid);
            $transaction->allow_commit();
            return $postid;
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
    }

    /**
     * Returns a root-to-current track path.
     *
     * @param int $trackid
     * @param int $forumid
     * @return array
     */
    public static function get_path(int $trackid, int $forumid): array {
        global $DB;

        $path = [];
        $visited = [];
        while ($trackid && !isset($visited[$trackid])) {
            $visited[$trackid] = true;
            $track = $DB->get_record('spatialforum_tracks', [
                'id' => $trackid,
                'spatialforumid' => $forumid,
            ], '*', MUST_EXIST);
            array_unshift($path, $track);
            $trackid = (int) $track->parenttrackid;
        }
        return $path;
    }

    /**
     * Returns a complete depth-first track tree.
     *
     * @param int $forumid
     * @param int $roottrackid
     * @return array
     */
    public static function get_tree(int $forumid, int $roottrackid): array {
        global $DB;

        $tracks = $DB->get_records('spatialforum_tracks', ['spatialforumid' => $forumid], 'timecreated ASC');
        $children = [];
        foreach ($tracks as $track) {
            $children[(int) $track->parenttrackid][] = $track;
        }
        $flat = [];
        $walk = function (int $id, int $depth) use (&$walk, &$flat, $children, $tracks): void {
            if (!isset($tracks[$id])) {
                return;
            }
            $track = clone $tracks[$id];
            $track->depth = $depth;
            $flat[] = $track;
            foreach ($children[$id] ?? [] as $child) {
                $walk((int) $child->id, $depth + 1);
            }
        };
        $walk($roottrackid, 0);
        return $flat;
    }

    /**
     * Marks posts as read without overwriting an existing read timestamp.
     *
     * @param array $postids
     * @param int $userid
     */
    public static function mark_read(array $postids, int $userid): void {
        global $DB;

        if (!$postids || !$userid) {
            return;
        }
        $postids = array_unique(array_map('intval', $postids));
        $policies = [];
        foreach ($DB->get_records_list('spatialforum_posts', 'id', $postids) as $post) {
            $policies[$post->spatialforumid] ??= group_access::for_activity($post->spatialforumid, $userid);
            $policies[$post->spatialforumid]->require_post($post);
        }

        $factory = \core\lock\lock_config::get_lock_factory('mod_spatialforum');
        $lock = $factory->get_lock('read:' . $userid, 10);
        if (!$lock) {
            throw new \moodle_exception('readlocktimeout', 'mod_spatialforum');
        }
        try {
            [$insql, $params] = $DB->get_in_or_equal($postids, SQL_PARAMS_NAMED);
            $params['userid'] = $userid;
            $existing = $DB->get_records_select(
                'spatialforum_read',
                "postid $insql AND userid = :userid",
                $params,
                '',
                'id,postid'
            );
            $readids = array_map(static fn($read): int => (int) $read->postid, $existing);
            foreach (array_diff($postids, $readids) as $postid) {
                $DB->insert_record('spatialforum_read', (object) [
                    'userid' => $userid, 'postid' => $postid, 'timeread' => time(),
                ]);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Returns unread post IDs for one user and forum.
     *
     * @param int $forumid
     * @param int $userid
     * @return array
     */
    public static function get_unread_postids(int $forumid, int $userid, ?group_access $access = null): array {
        global $DB;

        $access = $access ?? group_access::for_activity($forumid, $userid);
        [$groupsql, $groupparams] = $access->sql();
        $sql = "SELECT p.id
                  FROM {spatialforum_posts} p
             LEFT JOIN {spatialforum_read} r ON r.postid = p.id AND r.userid = :userid
                 WHERE p.spatialforumid = :forumid
                   AND p.userid <> :userid2
                   AND p.deleted = 0
                   AND r.id IS NULL AND $groupsql";
        return $DB->get_fieldset_sql($sql, [
            'userid' => $userid,
            'forumid' => $forumid,
            'userid2' => $userid,
            ...$groupparams,
        ]);
    }

    /**
     * Converts forum state into Mustache template data.
     *
     * @param stdClass $activity
     * @param stdClass $cm
     * @param stdClass $currenttrack
     * @param context_module $context
     * @param bool $wholemap Include every track in the activity, rather than only the current track.
     * @return array
     */
    public static function export_view_data(
        stdClass $activity,
        stdClass $cm,
        stdClass $currenttrack,
        context_module $context,
        bool $wholemap = false
    ): array {
        global $DB, $USER;

        $access = new group_access($activity, $cm);
        $access->require_track($currenttrack);
        $path = self::get_path($currenttrack->id, $activity->id);
        $tree = self::get_tree($activity->id, $activity->roottrackid);
        [$groupsql, $groupparams] = $access->sql();
        $params = ['sfforumid' => $activity->id, ...$groupparams];
        $renderposts = $DB->get_records_sql(
            "SELECT p.* FROM {spatialforum_posts} p
              WHERE p.spatialforumid = :sfforumid AND $groupsql ORDER BY p.timecreated ASC, p.id ASC",
            $params
        );
        $posts = $wholemap ? $renderposts : array_filter(
            $renderposts,
            static fn($item) => (int) $item->trackid === (int) $currenttrack->id
        );
        $maptracks = $DB->get_records('spatialforum_tracks', ['spatialforumid' => $activity->id]);
        $maptracks[$currenttrack->id] = $currenttrack;
        $posts = array_filter($posts, $access->can_view(...));
        $renderposts = array_filter($renderposts, $access->can_view(...));
        $visibletracks = [(int) $activity->roottrackid => true];
        foreach ($maptracks as $id => $item) {
            if (isset($renderposts[$item->originpostid])) {
                $visibletracks[$id] = true;
            }
        }
        if (!$access->uses_groups()) {
            $visibletracks = array_fill_keys(array_keys($maptracks), true);
        }
        $maptracks = array_intersect_key($maptracks, $visibletracks);
        $tree = array_filter($tree, static fn($item) => isset($visibletracks[$item->id]));
        $path = array_filter($path, static fn($item) => isset($visibletracks[$item->id]));

        $depths = [];
        foreach ($tree as $item) {
            $depths[$item->id] = $item->depth + 1;
        }
        $childtracks = $DB->get_records('spatialforum_tracks', [
            'parenttrackid' => $currenttrack->id,
        ], 'timecreated ASC');
        $childtracks = array_intersect_key($childtracks, $visibletracks);
        $cantrackread = !isguestuser() && !empty($USER->id);
        $unreadids = $cantrackread ? array_flip(self::get_unread_postids($activity->id, $USER->id, $access)) : [];
        $pathids = array_flip(array_column($path, 'id'));
        $canreply = has_capability('mod/spatialforum:reply', $context)
            && empty($activity->locked) && empty($currenttrack->locked);
        $canbranch = has_capability('mod/spatialforum:startbranch', $context)
            && empty($activity->locked) && empty($currenttrack->locked)
            && count($path) < $activity->maxdepth;
        $canmoderate = has_capability('mod/spatialforum:moderate', $context);
        $showlikes = self::likes_enabled($activity);
        $canlike = $showlikes && !isguestuser() && has_capability('mod/spatialforum:like', $context);
        $likecounts = [];
        $userlikes = [];
        $scope = $wholemap ? 'p.spatialforumid = :scopeid' : 'p.trackid = :scopeid';
        $scopeid = $wholemap ? $activity->id : $currenttrack->id;
        if ($showlikes) {
            $likecounts = $DB->get_records_sql(
                "SELECT l.postid,
                        SUM(CASE WHEN l.reaction = 1 THEN 1 ELSE 0 END) AS total,
                        SUM(CASE WHEN l.reaction = -1 THEN 1 ELSE 0 END) AS dislikes
                   FROM {spatialforum_likes} l
                   JOIN {spatialforum_posts} p ON p.id = l.postid
                  WHERE $scope AND p.deleted = 0 AND $groupsql
               GROUP BY l.postid",
                ['scopeid' => $scopeid, ...$groupparams]
            );
            $userlikes = $DB->get_records_sql(
                "SELECT l.postid, l.id, l.reaction FROM {spatialforum_likes} l
                   JOIN {spatialforum_posts} p ON p.id = l.postid
                  WHERE $scope AND l.userid = :userid AND $groupsql",
                ['scopeid' => $scopeid, 'userid' => $USER->id, ...$groupparams]
            );
        }

        $users = [];
        $userids = array_unique(array_column($renderposts, 'userid'));
        if ($userids) {
            $userfields = 'id,' . implode(',', \core_user\fields::get_name_fields());
            $users = $DB->get_records_list('user', 'id', $userids, '', $userfields);
        }

        $postdata = [];
        foreach ($posts as $post) {
            $posttrack = $maptracks[$post->trackid] ?? null;
            $postdepth = $depths[$post->trackid] ?? $activity->maxdepth;
            $parentid = $post->replytoid ?: ($posttrack->originpostid ?? 0);
            $parentid = isset($posts[$parentid]) && $parentid != $post->id ? (int) $parentid : 0;
            $postcanreply = $posttrack && !$activity->locked && !$posttrack->locked
                && $postdepth < $activity->maxdepth
                && has_capability('mod/spatialforum:reply', $context)
                && has_capability('mod/spatialforum:startbranch', $context)
                && $access->can_write((int) ($post->groupid ?? -1));
            $replycontext = [];
            if (!$post->deleted && $post->replytoid && $post->showparent) {
                $parent = $renderposts[$post->replytoid] ?? null;
                $replycontext[] = $parent ? self::export_reply_context($parent, $context, $cm->id, $renderposts, $users, $access)
                    : ['available' => false];
            }
            $user = $users[$post->userid] ?? null;
            $fullname = $user ? fullname($user) : get_string('unknownuser', 'mod_spatialforum');
            $message = $post->deleted
                ? get_string('deletedpost', 'mod_spatialforum')
                : excerpts::render($post, $context, $cm->id, $renderposts, $users, $access);
            $reaction = (int) ($userlikes[$post->id]->reaction ?? 0);
            $postdata[] = [
                ...self::post_times($post),
                'id' => $post->id,
                'trackid' => $post->trackid,
                'parentid' => $parentid,
                'colour' => self::normalise_colour($posttrack->colour ?? '#ffffff'),
                'preview' => $post->deleted ? self::preview($message) :
                    self::preview(s(excerpts::source($post, $context))),
                'cantrackread' => $cantrackread && !$post->deleted,
                'readlabel' => get_string($post->deleted ? 'removedpost' :
                    ($cantrackread ? 'readpost' : 'readnottracked'), 'mod_spatialforum'),
                'subject' => format_string($post->subject),
                'message' => $message,
                'author' => $fullname,
                'initials' => self::initials($fullname),
                'time' => userdate($post->timecreated),
                'unread' => isset($unreadids[$post->id]),
                'replycontext' => $replycontext,
                'showlikes' => $showlikes && !$post->deleted,
                'canlike' => $canlike && !$post->deleted && $access->can_write((int) ($post->groupid ?? -1)),
                'likecount' => $likecounts[$post->id]->total ?? 0,
                'dislikecount' => $likecounts[$post->id]->dislikes ?? 0,
                'liked' => $reaction === 1,
                'disliked' => $reaction === -1,
                'nextreaction' => $reaction === 1 ? 0 : 1,
                'nextdislike' => $reaction === -1 ? 0 : -1,
                'likelabel' => get_string($reaction === 1 ? 'unlike' : 'like', 'mod_spatialforum'),
                'dislikelabel' => get_string($reaction === -1 ? 'undislike' : 'dislike', 'mod_spatialforum'),
                'canreply' => $postcanreply && !$post->deleted,
                'canbranch' => $canbranch && !$post->deleted,
                'replyurl' => (new moodle_url('/mod/spatialforum/post.php', [
                    'id' => $cm->id,
                    'action' => 'reply',
                    'trackid' => $post->trackid,
                    'originpostid' => $post->id,
                ]))->out(false),
                'branchurl' => (new moodle_url('/mod/spatialforum/post.php', [
                    'id' => $cm->id,
                    'action' => 'branch',
                    'trackid' => $post->trackid,
                    'originpostid' => $post->id,
                ]))->out(false),
                'candelete' => $canmoderate && !$post->deleted && $access->can_write((int) ($post->groupid ?? -1)),
                'canrestore' => $canmoderate && post_lifecycle::restorable($post)
                    && $access->can_write((int) ($post->groupid ?? -1)),
                'canedit' => !$post->deleted && $post->userid == $USER->id && !isguestuser()
                    && !$activity->locked && $posttrack && !$posttrack->locked
                    && has_capability('mod/spatialforum:reply', $context) && $access->can_write((int) $post->groupid),
                'editurl' => (new moodle_url('/mod/spatialforum/edit.php', ['id' => $cm->id, 'postid' => $post->id]))->out(false),
                'canmoderate' => $canmoderate && (bool) $posttrack
                    && $access->can_write((int) ($post->groupid ?? -1)),
                'canlocktrack' => $canmoderate && (bool) $posttrack
                    && $access->can_write((int) ($post->groupid ?? -1))
                    && ((int) $posttrack->id !== (int) $activity->roottrackid
                        || has_capability('moodle/site:accessallgroups', $context)
                        || !groups_get_activity_groupmode($cm)),
                'tracklocked' => !empty($posttrack->locked),
                'moderatelabel' => get_string(!empty($posttrack->locked) ? 'unlocktrack' : 'locktrack', 'mod_spatialforum'),
                'moderateurl' => (new moodle_url('/mod/spatialforum/action.php', [
                    'id' => $cm->id, 'action' => 'toggletrack', 'trackid' => $post->trackid, 'sesskey' => sesskey(),
                ]))->out(false),
                'deleteurl' => (new moodle_url('/mod/spatialforum/action.php', [
                    'id' => $cm->id,
                    'action' => 'deletepost',
                    'postid' => $post->id,
                    'trackid' => $post->trackid,
                    'sesskey' => sesskey(),
                ]))->out(false),
            ];
        }

        $byparent = [];
        foreach ($postdata as $item) {
            if ($item['parentid']) {
                $byparent[$item['parentid']][] = [
                    'id' => $item['id'], 'subject' => $item['subject'], 'author' => $item['author'],
                ];
            }
        }
        foreach ($postdata as &$item) {
            $item['children'] = $byparent[$item['id']] ?? [];
            $item['haschildren'] = !empty($item['children']);
        }
        unset($item);

        $pathdata = [];
        foreach ($path as $index => $track) {
            $pathdata[] = [
                'name' => format_string($track->name),
                'colour' => self::normalise_colour($track->colour),
                'depth' => $index + 1,
                'active' => $track->id == $currenttrack->id,
                'url' => (new moodle_url('/mod/spatialforum/view.php', [
                    'id' => $cm->id,
                    'trackid' => $track->id,
                ]))->out(false),
            ];
        }

        $childcounts = [];
        foreach ($renderposts as $item) {
            $childcounts[$item->trackid] = ($childcounts[$item->trackid] ?? 0) + 1;
        }
        $childdata = [];
        foreach ($childtracks as $child) {
            $childpostcount = $childcounts[$child->id] ?? 0;
            $childdata[] = [
                'name' => format_string($child->name),
                'colour' => self::normalise_colour($child->colour),
                'postcount' => $childpostcount,
                'url' => (new moodle_url('/mod/spatialforum/view.php', [
                    'id' => $cm->id,
                    'trackid' => $child->id,
                ]))->out(false),
            ];
        }

        $treedata = [];
        foreach ($tree as $track) {
            $treedata[] = [
                'name' => format_string($track->name),
                'colour' => self::normalise_colour($track->colour),
                'indent' => $track->depth * 14,
                'active' => $track->id == $currenttrack->id,
                'onpath' => isset($pathids[$track->id]),
                'url' => (new moodle_url('/mod/spatialforum/view.php', [
                    'id' => $cm->id,
                    'trackid' => $track->id,
                ]))->out(false),
            ];
        }

        $moderateurl = new moodle_url('/mod/spatialforum/action.php', [
            'id' => $cm->id,
            'action' => 'toggletrack',
            'trackid' => $currenttrack->id,
            'sesskey' => sesskey(),
        ]);
        $newposttrack = $wholemap ? $maptracks[$activity->roottrackid] : $currenttrack;
        return [
            'forumname' => format_string($activity->name),
            'cmid' => $cm->id,
            'enablecolors' => empty($activity->enablecolors) ? 0 : 1,
            'sesskey' => sesskey(),
            'readurl' => (new moodle_url('/mod/spatialforum/read.php'))->out(false),
            'readcount' => count(array_filter($postdata, static fn($item) => $item['unread'])),
            'postcount' => count($postdata),
            'likeurl' => (new moodle_url('/mod/spatialforum/like.php'))->out(false),
            'moderationurl' => (new moodle_url('/mod/spatialforum/action.php'))->out(false),
            'trackname' => format_string($currenttrack->name),
            'trackcolour' => self::normalise_colour($currenttrack->colour),
            'depth' => count($path),
            'maxdepth' => $activity->maxdepth,
            'posts' => $postdata,
            'hasposts' => !empty($postdata),
            'path' => $pathdata,
            'children' => $childdata,
            'haschildren' => !empty($childdata),
            'tree' => $treedata,
            'canreply' => has_capability('mod/spatialforum:reply', $context)
                && !$activity->locked && !$newposttrack->locked
                && $access->can_start($access->selected_group()),
            'newposturl' => (new moodle_url('/mod/spatialforum/post.php', [
                'id' => $cm->id,
                'action' => 'reply',
                'trackid' => $newposttrack->id,
            ]))->out(false),
            'canmoderate' => $canmoderate,
            'tracklocked' => !empty($currenttrack->locked),
            'moderateurl' => $moderateurl->out(false),
            'moderatelabel' => get_string($currenttrack->locked ? 'unlocktrack' : 'locktrack', 'mod_spatialforum'),
            'locked' => !empty($activity->locked) || (!$wholemap && !empty($currenttrack->locked)),
        ];
    }

    /**

     * Localised creation/edit labels plus unambiguous machine-readable timestamps.

     */
    private static function post_times(stdClass $post): array {
        $shortformat = get_string('strftimedatetimeshort', 'langconfig');
        $edited = empty($post->deleted) && $post->timemodified > $post->timecreated;
        return [
            'creatediso' => gmdate('c', $post->timecreated),
            'createdlabel' => get_string('createdat', 'mod_spatialforum', userdate($post->timecreated)),
            'createdshort' => get_string('createdat', 'mod_spatialforum', userdate($post->timecreated, $shortformat)),
            'edited' => $edited,
            'editediso' => $edited ? gmdate('c', $post->timemodified) : '',
            'editedlabel' => $edited ? get_string('editedat', 'mod_spatialforum', userdate($post->timemodified)) : '',
            'editedshort' => $edited ? get_string(
                'editedat',
                'mod_spatialforum',
                userdate($post->timemodified, $shortformat)
            ) : '',
        ];
    }

    /**

     * Plain preview of already formatted content, preserving the author's letter case.

     */
    private static function preview(string $formatted): string {
        $formatted = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $formatted);
        $formatted = preg_replace('~<(?:br\b[^>]*|/(?:p|div|li|h[1-6]|tr|blockquote))\s*>~i', ' ', $formatted);
        $text = html_entity_decode(strip_tags($formatted), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        return \core_text::substr($text, 0, 512);
    }

    /**
     * Creates two-letter initials.
     *
     * @param string $name
     * @return string
     */
    private static function initials(string $name): string {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= \core_text::strtoupper(\core_text::substr($part, 0, 1));
        }
        return $letters;
    }

    /**
     * Keeps restored colour data safe for use in CSS custom properties.
     *
     * @param string $colour
     * @return string
     */
    private static function normalise_colour(string $colour): string {
        return preg_match('/^#[0-9a-f]{6}$/i', $colour) ? $colour : '#ffffff';
    }
}
