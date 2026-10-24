<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_spatialforum\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for spatial forum user data.
 *
 * @package mod_spatialforum
 */
class provider implements
    \core_privacy\local\metadata\provider,
    plugin_provider,
    core_userlist_provider {

    /** @inheritDoc */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('spatialforum_likes', [
            'userid' => 'privacy:metadata:spatialforum_likes:userid',
            'postid' => 'privacy:metadata:spatialforum_likes:postid',
            'reaction' => 'privacy:metadata:spatialforum_likes:reaction',
            'timecreated' => 'privacy:metadata:spatialforum_likes:timecreated',
        ], 'privacy:metadata:spatialforum_likes');
        $collection->add_database_table('spatialforum_posts', [
            'userid' => 'privacy:metadata:spatialforum_posts:userid',
            'subject' => 'privacy:metadata:spatialforum_posts:subject',
            'message' => 'privacy:metadata:spatialforum_posts:message',
            'showparent' => 'privacy:metadata:spatialforum_posts:showparent',
            'timecreated' => 'privacy:metadata:spatialforum_posts:timecreated',
        ], 'privacy:metadata:spatialforum_posts');
        $collection->add_database_table('spatialforum_read', [
            'userid' => 'privacy:metadata:spatialforum_read:userid',
            'postid' => 'privacy:metadata:spatialforum_read:postid',
            'timeread' => 'privacy:metadata:spatialforum_read:timeread',
        ], 'privacy:metadata:spatialforum_read');
        $collection->add_database_table('spatialforum_tracks', [
            'originpostid' => 'privacy:metadata:spatialforum_tracks:originpostid',
        ], 'privacy:metadata:spatialforum_tracks');
        return $collection;
    }

    /** @inheritDoc */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {spatialforum} sf ON sf.id = cm.instance
                 WHERE EXISTS (SELECT 1 FROM {spatialforum_posts} p
                                WHERE p.spatialforumid = sf.id AND p.userid = :postuserid)
                    OR EXISTS (SELECT 1 FROM {spatialforum_read} r
                                 JOIN {spatialforum_posts} rp ON rp.id = r.postid
                                WHERE rp.spatialforumid = sf.id AND r.userid = :readuserid)
                    OR EXISTS (SELECT 1 FROM {spatialforum_likes} l
                                 JOIN {spatialforum_posts} lp ON lp.id = l.postid
                                WHERE lp.spatialforumid = sf.id AND l.userid = :likeuserid)";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'spatialforum',
            'postuserid' => $userid,
            'readuserid' => $userid,
            'likeuserid' => $userid,
        ]);
        return $contextlist;
    }

    /** @inheritDoc */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('spatialforum', $context->instanceid);
        if (!$cm) {
            return;
        }
        $userlist->add_from_sql('userid',
            'SELECT userid FROM {spatialforum_posts} WHERE spatialforumid = :forumid',
            ['forumid' => $cm->instance]);
        $userlist->add_from_sql('userid',
            'SELECT l.userid FROM {spatialforum_likes} l
               JOIN {spatialforum_posts} p ON p.id = l.postid
              WHERE p.spatialforumid = :forumid',
            ['forumid' => $cm->instance]);
        $userlist->add_from_sql('userid',
            'SELECT r.userid
               FROM {spatialforum_read} r
               JOIN {spatialforum_posts} p ON p.id = r.postid
              WHERE p.spatialforumid = :forumid',
            ['forumid' => $cm->instance]);
    }

    /** @inheritDoc */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('spatialforum', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $posts = $DB->get_records('spatialforum_posts', [
                'spatialforumid' => $cm->instance,
                'userid' => $userid,
            ], 'timecreated ASC', 'id,trackid,subject,message,messageformat,timecreated,timemodified');
            $reads = $DB->get_records_sql(
                'SELECT r.*
                   FROM {spatialforum_read} r
                   JOIN {spatialforum_posts} p ON p.id = r.postid
                  WHERE p.spatialforumid = :forumid AND r.userid = :userid',
                ['forumid' => $cm->instance, 'userid' => $userid]
            );
            $likes = $DB->get_records_sql(
                'SELECT l.* FROM {spatialforum_likes} l
                   JOIN {spatialforum_posts} p ON p.id = l.postid
                  WHERE p.spatialforumid = :forumid AND l.userid = :userid',
                ['forumid' => $cm->instance, 'userid' => $userid]);
            $data = (object) [
                'likes' => array_values($likes),
                'posts' => array_values($posts),
                'readstate' => array_values($reads),
            ];
            writer::with_context($context)->export_data([], $data);
        }
    }

    /** @inheritDoc */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('spatialforum', $context->instanceid);
        if (!$cm) {
            return;
        }
        $postids = $DB->get_fieldset_select('spatialforum_posts', 'id', 'spatialforumid = ?', [$cm->instance]);
        if ($postids) {
            $DB->delete_records_list('spatialforum_likes', 'postid', $postids);
            $DB->delete_records_list('spatialforum_read', 'postid', $postids);
        }
        $DB->delete_records('spatialforum_posts', ['spatialforumid' => $cm->instance]);
        $DB->set_field('spatialforum_tracks', 'originpostid', 0, ['spatialforumid' => $cm->instance]);
    }

    /** @inheritDoc */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_user_in_context($contextlist->get_user()->id, $context);
        }
    }

    /** @inheritDoc */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        foreach ($userlist->get_userids() as $userid) {
            self::delete_user_in_context($userid, $userlist->get_context());
        }
    }

    /**
     * Removes one user's content and read state in one activity.
     *
     * Posts are retained only as empty tombstones to preserve branches and replies.
     *
     * @param int $userid
     * @param context $context
     */
    private static function delete_user_in_context(int $userid, context $context): void {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('spatialforum', $context->instanceid);
        if (!$cm) {
            return;
        }
        $postids = $DB->get_fieldset_select('spatialforum_posts', 'id',
            'spatialforumid = ? AND userid = ?', [$cm->instance, $userid]);
        $likeids = $DB->get_fieldset_sql(
            'SELECT l.id FROM {spatialforum_likes} l
               JOIN {spatialforum_posts} p ON p.id = l.postid
              WHERE l.userid = :userid AND p.spatialforumid = :forumid',
            ['userid' => $userid, 'forumid' => $cm->instance]);
        if ($likeids) {
            $DB->delete_records_list('spatialforum_likes', 'id', $likeids);
        }
        if ($postids) {
            [$insql, $params] = $DB->get_in_or_equal($postids, SQL_PARAMS_NAMED);
            $DB->execute("UPDATE {spatialforum_posts}
                            SET userid = 0, subject = '', message = '', deleted = 1
                          WHERE id {$insql}", $params);
        }
        $readids = $DB->get_fieldset_sql(
            'SELECT r.id
               FROM {spatialforum_read} r
               JOIN {spatialforum_posts} p ON p.id = r.postid
              WHERE r.userid = :userid AND p.spatialforumid = :forumid',
            ['userid' => $userid, 'forumid' => $cm->instance]
        );
        if ($readids) {
            $DB->delete_records_list('spatialforum_read', 'id', $readids);
        }
    }
}
