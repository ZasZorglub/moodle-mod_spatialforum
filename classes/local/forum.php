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
     * @return int
     */
    public static function create_post(
        stdClass $activity,
        int $trackid,
        int $userid,
        string $subject,
        string $message,
        int $messageformat,
        int $replytoid = 0
    ): int {
        global $DB;

        $now = time();
        return $DB->insert_record('spatialforum_posts', (object) [
            'spatialforumid' => $activity->id,
            'trackid' => $trackid,
            'replytoid' => $replytoid,
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
        ], 'timecreated ASC');
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

        $users = [];
        $userids = array_unique(array_column($posts, 'userid'));
        if ($userids) {
            $users = $DB->get_records_list('user', 'id', $userids, '', 'id,firstname,lastname,picture,imagealt,email');
        }

        $postdata = [];
        foreach ($posts as $post) {
            $user = $users[$post->userid] ?? null;
            $fullname = $user ? fullname($user) : get_string('unknownuser', 'mod_spatialforum');
            $message = $post->deleted
                ? get_string('deletedpost', 'mod_spatialforum')
                : format_text($post->message, $post->messageformat, ['context' => $context]);
            $postdata[] = [
                'id' => $post->id,
                'subject' => format_string($post->subject),
                'message' => $message,
                'author' => $fullname,
                'initials' => self::initials($fullname),
                'time' => userdate($post->timecreated),
                'unread' => isset($unreadids[$post->id]),
                'canreply' => $canreply && !$post->deleted,
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
