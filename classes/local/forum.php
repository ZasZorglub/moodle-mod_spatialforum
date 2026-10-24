<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_spatialforum\local;

use context_module;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Domain operations and view data for a spatial forum.
 *
 * @package mod_spatialforum
 */
class forum {
    /** @var array Track colour palette. */
    private const COLOURS = [
        '#54b9ec', '#f0aa4f', '#a98be8', '#ef769c',
        '#59d6c2', '#ff8366', '#78d59b', '#c18bea',
    ];

    /** Editable reply subject, without accumulating repeated Re: prefixes. */
    public static function reply_subject(string $subject): string {
        $subject = trim($subject);
        if (!preg_match('/^re\s*:/i', $subject)) {
            $subject = 'Re: ' . $subject;
        }
        return \core_text::substr($subject, 0, 255);
    }

    /** Parent content is read live so moderation and privacy erasure take effect in every quote. */
    public static function export_reply_context(stdClass $post, context_module $context, int $cmid): array {
        global $DB;

        if (!empty($post->deleted)) {
            return ['available' => false];
        }
        $user = $DB->get_record('user', ['id' => $post->userid]);
        $url = new moodle_url('/mod/spatialforum/view.php', ['id' => $cmid, 'trackid' => $post->trackid]);
        $url->set_anchor('post-' . $post->id);
        return [
            'available' => true,
            'subject' => format_string($post->subject),
            'author' => $user ? fullname($user) : get_string('unknownuser', 'mod_spatialforum'),
            'time' => userdate($post->timecreated),
            'message' => format_text($post->message, $post->messageformat, ['context' => $context]),
            'url' => $url->out(false),
        ];
    }

    /** Whether the site and this activity both allow likes. Disabling preserves existing data. */
    public static function likes_enabled(stdClass $activity): bool {
        return !empty($activity->enablelikes) && get_config('mod_spatialforum', 'allowlikes') !== '0';
    }

    /** Set one user's like state, serialising duplicate submissions. Caller must check capabilities. */
    public static function set_like(stdClass $activity, stdClass $post, int $userid, bool $liked): void {
        self::set_reaction($activity, $post, $userid, $liked ? 1 : 0);
    }

    /** One reaction per user: 1 = like, -1 = dislike, 0 = remove. */
    public static function set_reaction(stdClass $activity, stdClass $post, int $userid, int $reaction): void {
        global $DB;

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

    /** Publish each reply in a separate child track. Caller checks reply and branch capabilities. */
    public static function reply_in_new_track(
        stdClass $activity,
        stdClass $parenttrack,
        int $originpostid,
        int $userid,
        string $branchname,
        string $subject,
        string $message,
        int $messageformat,
        bool $showparent = false
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
        $transaction = $DB->start_delegated_transaction();
        $trackid = self::create_track($activity, $parenttrack, $originpostid, $branchname);
        self::create_post($activity, $trackid, $userid, $subject, $message, $messageformat, $originpostid, $showparent);
        $transaction->allow_commit();
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
        bool $showparent = false
    ): int {
        global $DB;

        $now = time();
        return $DB->insert_record('spatialforum_posts', (object) [
            'spatialforumid' => $activity->id,
            'trackid' => $trackid,
            'replytoid' => $replytoid,
            'showparent' => $replytoid && $showparent ? 1 : 0,
            'userid' => $userid,
            'subject' => trim($subject),
            'message' => $message,
            'messageformat' => $messageformat,
            'deleted' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
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
        $walk = function(int $id, int $depth) use (&$walk, &$flat, $children, $tracks): void {
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
        $existing = $DB->get_records_list('spatialforum_read', 'postid', $postids, '', 'id,postid');
        $readids = array_map(static fn($read): int => (int) $read->postid, $existing);
        $now = time();
        foreach (array_diff($postids, $readids) as $postid) {
            $DB->insert_record('spatialforum_read', (object) [
                'userid' => $userid,
                'postid' => $postid,
                'timeread' => $now,
            ]);
        }
    }

    /**
     * Returns unread post IDs for one user and forum.
     *
     * @param int $forumid
     * @param int $userid
     * @return array
     */
    public static function get_unread_postids(int $forumid, int $userid): array {
        global $DB;

        $sql = "SELECT p.id
                  FROM {spatialforum_posts} p
             LEFT JOIN {spatialforum_read} r ON r.postid = p.id AND r.userid = :userid
                 WHERE p.spatialforumid = :forumid
                   AND p.userid <> :userid2
                   AND p.deleted = 0
                   AND r.id IS NULL";
        return $DB->get_fieldset_sql($sql, [
            'userid' => $userid,
            'forumid' => $forumid,
            'userid2' => $userid,
        ]);
    }

    /**
     * Converts forum state into Mustache template data.
     *
     * @param stdClass $activity
     * @param stdClass $cm
     * @param stdClass $currenttrack
     * @param context_module $context
     * @return array
     */
    public static function export_view_data(
        stdClass $activity,
        stdClass $cm,
        stdClass $currenttrack,
        context_module $context
    ): array {
        global $DB, $USER;

        $path = self::get_path($currenttrack->id, $activity->id);
        $tree = self::get_tree($activity->id, $activity->roottrackid);
        $posts = $DB->get_records('spatialforum_posts', [
            'trackid' => $currenttrack->id,
        ], 'timecreated ASC, id ASC');
        $childtracks = $DB->get_records('spatialforum_tracks', [
            'parenttrackid' => $currenttrack->id,
        ], 'timecreated ASC');
        $unreadids = array_flip(self::get_unread_postids($activity->id, $USER->id));
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
        if ($showlikes) {
            $likecounts = $DB->get_records_sql(
                'SELECT l.postid,
                        SUM(CASE WHEN l.reaction = 1 THEN 1 ELSE 0 END) AS total,
                        SUM(CASE WHEN l.reaction = -1 THEN 1 ELSE 0 END) AS dislikes
                   FROM {spatialforum_likes} l
                   JOIN {spatialforum_posts} p ON p.id = l.postid
                  WHERE p.trackid = :trackid AND p.deleted = 0
               GROUP BY l.postid', ['trackid' => $currenttrack->id]);
            $userlikes = $DB->get_records_sql(
                'SELECT l.postid, l.id, l.reaction FROM {spatialforum_likes} l
                   JOIN {spatialforum_posts} p ON p.id = l.postid
                  WHERE p.trackid = :trackid AND l.userid = :userid',
                ['trackid' => $currenttrack->id, 'userid' => $USER->id]);
        }

        $users = [];
        $userids = array_unique(array_column($posts, 'userid'));
        if ($userids) {
            $userfields = 'id,' . implode(',', \core_user\fields::get_name_fields());
            $users = $DB->get_records_list('user', 'id', $userids, '', $userfields);
        }

        $postdata = [];
        foreach ($posts as $post) {
            $replycontext = [];
            if (!$post->deleted && $post->replytoid && $post->showparent) {
                $parent = $posts[$post->replytoid] ?? $DB->get_record('spatialforum_posts', [
                    'id' => $post->replytoid, 'spatialforumid' => $activity->id,
                ]);
                $replycontext[] = $parent ? self::export_reply_context($parent, $context, $cm->id)
                    : ['available' => false];
            }
            $user = $users[$post->userid] ?? null;
            $fullname = $user ? fullname($user) : get_string('unknownuser', 'mod_spatialforum');
            $message = $post->deleted
                ? get_string('deletedpost', 'mod_spatialforum')
                : format_text($post->message, $post->messageformat, ['context' => $context]);
            $reaction = (int) ($userlikes[$post->id]->reaction ?? 0);
            $postdata[] = [
                'id' => $post->id,
                'subject' => format_string($post->subject),
                'message' => $message,
                'author' => $fullname,
                'initials' => self::initials($fullname),
                'time' => userdate($post->timecreated),
                'unread' => isset($unreadids[$post->id]),
                'replycontext' => $replycontext,
                'showlikes' => $showlikes && !$post->deleted,
                'canlike' => $canlike && !$post->deleted,
                'likecount' => $likecounts[$post->id]->total ?? 0,
                'dislikecount' => $likecounts[$post->id]->dislikes ?? 0,
                'liked' => $reaction === 1,
                'disliked' => $reaction === -1,
                'nextreaction' => $reaction === 1 ? 0 : 1,
                'nextdislike' => $reaction === -1 ? 0 : -1,
                'likelabel' => get_string($reaction === 1 ? 'unlike' : 'like', 'mod_spatialforum'),
                'dislikelabel' => get_string($reaction === -1 ? 'undislike' : 'dislike', 'mod_spatialforum'),
                'canreply' => $canreply && $canbranch && !$post->deleted,
                'canbranch' => $canbranch && !$post->deleted,
                'replyurl' => (new moodle_url('/mod/spatialforum/post.php', [
                    'id' => $cm->id,
                    'action' => 'reply',
                    'trackid' => $currenttrack->id,
                    'originpostid' => $post->id,
                ]))->out(false),
                'branchurl' => (new moodle_url('/mod/spatialforum/post.php', [
                    'id' => $cm->id,
                    'action' => 'branch',
                    'trackid' => $currenttrack->id,
                    'originpostid' => $post->id,
                ]))->out(false),
                'candelete' => $canmoderate && !$post->deleted,
                'deleteurl' => (new moodle_url('/mod/spatialforum/action.php', [
                    'id' => $cm->id,
                    'action' => 'deletepost',
                    'postid' => $post->id,
                    'trackid' => $currenttrack->id,
                    'sesskey' => sesskey(),
                ]))->out(false),
            ];
        }

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

        $childdata = [];
        foreach ($childtracks as $child) {
            $childpostcount = $DB->count_records('spatialforum_posts', ['trackid' => $child->id]);
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
        return [
            'forumname' => format_string($activity->name),
            'cmid' => $cm->id,
            'sesskey' => sesskey(),
            'likeurl' => (new moodle_url('/mod/spatialforum/like.php'))->out(false),
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
            'canreply' => $canreply,
            'newposturl' => (new moodle_url('/mod/spatialforum/post.php', [
                'id' => $cm->id,
                'action' => 'reply',
                'trackid' => $currenttrack->id,
            ]))->out(false),
            'canmoderate' => $canmoderate,
            'tracklocked' => !empty($currenttrack->locked),
            'moderateurl' => $moderateurl->out(false),
            'moderatelabel' => get_string($currenttrack->locked ? 'unlocktrack' : 'locktrack', 'mod_spatialforum'),
            'locked' => !empty($activity->locked) || !empty($currenttrack->locked),
        ];
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
