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

/**
 * Privacy provider for spatial forum user data.
 *
 * @package mod_spatialforum
 */
class provider implements \core_privacy\local\metadata\provider, core_userlist_provider, plugin_provider {
    /**
     * {@inheritDoc}
     */
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
            'discussionname' => 'privacy:metadata:spatialforum_posts:discussionname',
            'message' => 'privacy:metadata:spatialforum_posts:message',
            'groupid' => 'privacy:metadata:spatialforum_posts:groupid',
            'showparent' => 'privacy:metadata:spatialforum_posts:showparent',
            'replyparts' => 'privacy:metadata:spatialforum_posts:replyparts',
            'timecreated' => 'privacy:metadata:spatialforum_posts:timecreated',
        ], 'privacy:metadata:spatialforum_posts');
        $collection->add_database_table('spatialforum_read', [
            'userid' => 'privacy:metadata:spatialforum_read:userid',
            'postid' => 'privacy:metadata:spatialforum_read:postid',
            'timeread' => 'privacy:metadata:spatialforum_read:timeread',
        ], 'privacy:metadata:spatialforum_read');
        $collection->add_database_table('spatialforum_tracks', [
            'originpostid' => 'privacy:metadata:spatialforum_tracks:originpostid',
            'name' => 'privacy:metadata:spatialforum_tracks:name',
        ], 'privacy:metadata:spatialforum_tracks');
        $collection->add_database_table('spatialforum_subscriptions', [
            'userid' => 'privacy:metadata:spatialforum_subscriptions:userid',
            'mode' => 'privacy:metadata:spatialforum_subscriptions:mode',
            'firstpostid' => 'privacy:metadata:spatialforum_subscriptions:firstpostid',
            'timecreated' => 'privacy:metadata:spatialforum_subscriptions:timecreated',
            'timemodified' => 'privacy:metadata:spatialforum_subscriptions:timemodified',
        ], 'privacy:metadata:spatialforum_subscriptions');
        $collection->add_database_table('spatialforum_deliveries', [
            'userid' => 'privacy:metadata:spatialforum_deliveries:userid',
            'postid' => 'privacy:metadata:spatialforum_deliveries:postid',
            'mode' => 'privacy:metadata:spatialforum_deliveries:mode',
            'due' => 'privacy:metadata:spatialforum_deliveries:due',
            'status' => 'privacy:metadata:spatialforum_deliveries:status',
            'attempts' => 'privacy:metadata:spatialforum_deliveries:attempts',
            'messageid' => 'privacy:metadata:spatialforum_deliveries:messageid',
            'timecreated' => 'privacy:metadata:spatialforum_deliveries:timecreated',
            'timemodified' => 'privacy:metadata:spatialforum_deliveries:timemodified',
        ], 'privacy:metadata:spatialforum_deliveries');
        $collection->link_subsystem('core_message', 'privacy:metadata:messages');
        $collection->link_subsystem('core', 'privacy:metadata:tasks');
        $collection->link_subsystem('core_files', 'privacy:metadata:files');
        $collection->link_subsystem('core_grades', 'privacy:metadata:grades');
        $collection->add_database_table('spatialforum_imports', [
            'userid' => 'privacy:metadata:imports:userid', 'sourcecmid' => 'privacy:metadata:imports:sourcecmid',
            'targetid' => 'privacy:metadata:imports:targetid', 'requestkey' => 'privacy:metadata:imports:requestkey',
            'status' => 'privacy:metadata:imports:status', 'timecreated' => 'privacy:metadata:imports:timecreated',
        ], 'privacy:metadata:imports');
        $collection->add_database_table('spatialforum_import_map', [
            'sourcepostid' => 'privacy:metadata:importmap:sourcepostid',
            'targetpostid' => 'privacy:metadata:importmap:targetpostid',
        ], 'privacy:metadata:importmap');
        return $collection;
    }

    /**

     * {@inheritDoc}

     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            'SELECT ctx.id FROM {context} ctx
            JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :level
            JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            JOIN {spatialforum_imports} i ON i.targetid = cm.instance
            WHERE i.userid = :owner OR EXISTS (SELECT 1 FROM {forum_posts} p
                JOIN {forum_discussions} d ON d.id = p.discussion
                JOIN {course_modules} scm ON scm.instance = d.forum
                JOIN {modules} sm ON sm.id = scm.module AND sm.name = :smod
                JOIN {spatialforum_imports} si ON si.sourcecmid = scm.id AND si.targetid = cm.instance
                WHERE p.userid = :author)',
            ['level' => CONTEXT_MODULE, 'modname' => 'spatialforum', 'smod' => 'forum',
            'owner' => $userid,
            'author' => $userid]
        );
        $sourcecontexts = $DB->get_fieldset_sql(
            'SELECT DISTINCT targetcm.id
               FROM {course_modules} targetcm
               JOIN {modules} targetm ON targetm.id = targetcm.module AND targetm.name = :targetmod
               JOIN {spatialforum_imports} i ON i.targetid = targetcm.instance
               JOIN {course_modules} sourcecm ON sourcecm.id = i.sourcecmid
               JOIN {modules} sourcem ON sourcem.id = sourcecm.module AND sourcem.name = :sourcemod
               JOIN {forum_discussions} d ON d.forum = sourcecm.instance
               JOIN {forum_posts} p ON p.discussion = d.id
              WHERE p.userid = :userid',
            ['targetmod' => 'spatialforum', 'sourcemod' => 'forum', 'userid' => $userid]
        );
        foreach ($sourcecontexts as $cmid) {
            $contextlist->add_from_sql(
                'SELECT ctx.id FROM {context} ctx JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  WHERE ctx.contextlevel = :level AND cm.id = :cmid',
                ['level' => CONTEXT_MODULE, 'cmid' => $cmid]
            );
        }
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
                                WHERE lp.spatialforumid = sf.id AND l.userid = :likeuserid)
                    OR EXISTS (SELECT 1 FROM {spatialforum_subscriptions} s
                                WHERE s.spatialforumid = sf.id AND s.userid = :subuserid)
                    OR EXISTS (SELECT 1 FROM {spatialforum_deliveries} d
                                WHERE d.spatialforumid = sf.id AND d.userid = :deliveryuserid)";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'spatialforum',
            'postuserid' => $userid,
            'readuserid' => $userid,
            'likeuserid' => $userid,
            'subuserid' => $userid,
            'deliveryuserid' => $userid,
        ]);
        return $contextlist;
    }

    /**

     * {@inheritDoc}

     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('spatialforum', $context->instanceid);
        if (!$cm) {
            return;
        }
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {spatialforum_imports} WHERE targetid = :targetid',
            ['targetid' => $cm->instance]
        );
        $userlist->add_from_sql('userid', 'SELECT p.userid FROM {forum_posts} p
            JOIN {spatialforum_import_map} im ON im.sourcepostid = p.id
            JOIN {spatialforum_imports} i ON i.id = im.importid WHERE i.targetid = :targetid', ['targetid' => $cm->instance]);
        foreach (['spatialforum_subscriptions', 'spatialforum_deliveries'] as $table) {
            $userlist->add_from_sql(
                'userid',
                "SELECT userid FROM {{$table}} WHERE spatialforumid = :forumid",
                ['forumid' => $cm->instance]
            );
        }
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {spatialforum_posts} WHERE spatialforumid = :forumid',
            ['forumid' => $cm->instance]
        );
        $userlist->add_from_sql(
            'userid',
            'SELECT l.userid FROM {spatialforum_likes} l
               JOIN {spatialforum_posts} p ON p.id = l.postid
              WHERE p.spatialforumid = :forumid',
            ['forumid' => $cm->instance]
        );
        $userlist->add_from_sql(
            'userid',
            'SELECT r.userid
               FROM {spatialforum_read} r
               JOIN {spatialforum_posts} p ON p.id = r.postid
              WHERE p.spatialforumid = :forumid',
            ['forumid' => $cm->instance]
        );
    }

    /**

     * {@inheritDoc}

     */
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
            $posts = $DB->get_records(
                'spatialforum_posts',
                [
                    'spatialforumid' => $cm->instance,
                    'userid' => $userid,
                ],
                'timecreated ASC',
                'id,groupid,trackid,replytoid,showparent,replyparts,subject,discussionname,' .
                    'message,messageformat,timecreated,timemodified'
            );
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
                ['forumid' => $cm->instance, 'userid' => $userid]
            );
            $data = (object) [
                'imports' => array_values($DB->get_records(
                    'spatialforum_imports',
                    ['targetid' => $cm->instance, 'userid' => $userid]
                )),
                'importmaps' => array_values($DB->get_records_sql('SELECT im.* FROM {spatialforum_import_map} im
                    JOIN {spatialforum_imports} i ON i.id = im.importid
                    LEFT JOIN {forum_posts} sp ON sp.id = im.sourcepostid
                    LEFT JOIN {spatialforum_posts} tp ON tp.id = im.targetpostid
                    WHERE i.targetid = ? AND (sp.userid = ? OR tp.userid = ?)', [$cm->instance, $userid, $userid])),
                'subscriptions' => array_values($DB->get_records('spatialforum_subscriptions', [
                    'spatialforumid' => $cm->instance, 'userid' => $userid,
                ])),
                'deliveries' => array_values($DB->get_records('spatialforum_deliveries', [
                    'spatialforumid' => $cm->instance, 'userid' => $userid,
                ])),
                'likes' => array_values($likes),
                'posts' => array_values($posts),
                'readstate' => array_values($reads),
                'tracks' => array_values(self::authored_tracks($cm->instance, $userid)),
            ];
            foreach ($posts as $post) {
                $area = [get_string('posts', 'mod_spatialforum'), (string) $post->id];
                $post->message = writer::with_context($context)->rewrite_pluginfile_urls(
                    $area,
                    'mod_spatialforum',
                    'post',
                    $post->id,
                    $post->message
                );
                writer::with_context($context)->export_area_files($area, 'mod_spatialforum', 'post', $post->id);
            }
            writer::with_context($context)->export_data([], $data);
        }
    }

    /**

     * {@inheritDoc}

     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('spatialforum', $context->instanceid);
        if (!$cm) {
            return;
        }
        $DB->delete_records('spatialforum_subscriptions', ['spatialforumid' => $cm->instance]);
        \mod_spatialforum\local\forum_import::erase_user_data($cm->instance);
        $DB->delete_records('spatialforum_deliveries', ['spatialforumid' => $cm->instance]);
        $postids = $DB->get_fieldset_select('spatialforum_posts', 'id', 'spatialforumid = ?', [$cm->instance]);
        if ($postids) {
            $DB->delete_records_list('spatialforum_likes', 'postid', $postids);
            $DB->delete_records_list('spatialforum_read', 'postid', $postids);
        }
        $DB->delete_records('spatialforum_posts', ['spatialforumid' => $cm->instance]);
        get_file_storage()->delete_area_files($context->id, 'mod_spatialforum', 'post');
        $DB->set_field_select(
            'spatialforum_tracks',
            'name',
            get_string('anonymoustrack', 'mod_spatialforum'),
            'spatialforumid = ? AND parenttrackid <> 0',
            [$cm->instance]
        );
        $DB->set_field('spatialforum_tracks', 'originpostid', 0, ['spatialforumid' => $cm->instance]);
    }

    /**

     * {@inheritDoc}

     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_user_in_context($contextlist->get_user()->id, $context);
        }
    }

    /**

     * {@inheritDoc}

     */
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
        $DB->delete_records('spatialforum_subscriptions', ['spatialforumid' => $cm->instance, 'userid' => $userid]);
        \mod_spatialforum\local\forum_import::erase_user_data($cm->instance, $userid);
        $DB->delete_records('spatialforum_deliveries', ['spatialforumid' => $cm->instance, 'userid' => $userid]);
        $postids = $DB->get_fieldset_select(
            'spatialforum_posts',
            'id',
            'spatialforumid = ? AND userid = ?',
            [$cm->instance, $userid]
        );
        $likeids = $DB->get_fieldset_sql(
            'SELECT l.id FROM {spatialforum_likes} l
               JOIN {spatialforum_posts} p ON p.id = l.postid
              WHERE l.userid = :userid AND p.spatialforumid = :forumid',
            ['userid' => $userid, 'forumid' => $cm->instance]
        );
        if ($likeids) {
            $DB->delete_records_list('spatialforum_likes', 'id', $likeids);
        }
        if ($postids) {
            $DB->delete_records_list('spatialforum_deliveries', 'postid', $postids);
            $tracks = self::authored_tracks($cm->instance, $userid);
            foreach ($tracks as $track) {
                $DB->set_field(
                    'spatialforum_tracks',
                    'name',
                    get_string('anonymoustrack', 'mod_spatialforum'),
                    ['id' => $track->id]
                );
            }
            foreach ($postids as $postid) {
                \mod_spatialforum\local\media::delete((int) $postid, $context);
            }
            [$insql, $params] = $DB->get_in_or_equal($postids, SQL_PARAMS_NAMED);
            $DB->execute("UPDATE {spatialforum_posts}
                            SET userid = 0, subject = '', discussionname = NULL, message = '', replyparts = NULL, deleted = 1
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

    /**

     * A child track is authored by its first post's author, not the parent post's author.

     */
    private static function authored_tracks(int $forumid, int $userid): array {
        global $DB;
        return $DB->get_records_sql(
            'SELECT t.id, t.name FROM {spatialforum_tracks} t
            JOIN {spatialforum_posts} p ON p.trackid = t.id
            WHERE t.spatialforumid = :forumid AND t.parenttrackid <> 0 AND p.userid = :userid
              AND NOT EXISTS (SELECT 1 FROM {spatialforum_posts} earlier
                              WHERE earlier.trackid = t.id AND earlier.id < p.id)',
            ['forumid' => $forumid, 'userid' => $userid]
        );
    }
}
