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
 * Builds the spatial forum backup structure.
 *
 * @package mod_spatialforum
 */
class backup_spatialforum_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines activity data included in backup.
     *
     * @return backup_nested_element
     */
    protected function define_structure(): backup_nested_element {
        global $DB;
        if ($DB->get_field('spatialforum', 'importstate', ['id' => $this->get_task()->get_activityid()])) {
            throw new moodle_exception('importinprogress', 'mod_spatialforum');
        }
        // Notification preferences and delivery queues are not copied: restored activities require fresh opt-in.
        $userinfo = $this->get_setting_value('userinfo');

        $spatialforum = new backup_nested_element('spatialforum', ['id'], [
            'name', 'intro', 'introformat', 'roottrackid', 'groupready', 'maxdepth',
            'enablecolors', 'enablelikes', 'locked', 'grade', 'timemodified',
            'completionposts', 'completionreplies', 'completiondiscussions',
        ]);
        $tracks = new backup_nested_element('tracks');
        $track = new backup_nested_element('track', ['id'], [
            'parenttrackid', 'originpostid', 'name', 'colour', 'locked', 'timecreated', 'timemodified',
        ]);
        $posts = new backup_nested_element('posts');
        $post = new backup_nested_element('post', ['id'], [
            'groupid', 'trackid', 'replytoid', 'showparent', 'replyparts', 'importedattachments', 'discussionname', 'userid',
            'subject', 'message', 'messageformat', 'deleted', 'deletedat',
            'timecreated', 'timemodified',
        ]);
        $reads = new backup_nested_element('reads');
        $read = new backup_nested_element('read', ['id'], ['userid', 'postid', 'timeread']);
        $likes = new backup_nested_element('likes');
        $like = new backup_nested_element('like', ['id'], ['userid', 'postid', 'reaction', 'timecreated']);

        $spatialforum->add_child($tracks);
        $tracks->add_child($track);
        $spatialforum->add_child($posts);
        $posts->add_child($post);
        $spatialforum->add_child($reads);
        $reads->add_child($read);
        $spatialforum->add_child($likes);
        $likes->add_child($like);

        $spatialforum->set_source_table('spatialforum', ['id' => backup::VAR_ACTIVITYID]);
        $track->set_source_table('spatialforum_tracks', ['spatialforumid' => backup::VAR_PARENTID], 'timecreated ASC, id ASC');

        if ($userinfo) {
            $like->set_source_sql(
                'SELECT l.* FROM {spatialforum_likes} l
                   JOIN {spatialforum_posts} p ON p.id = l.postid
                  WHERE p.spatialforumid = ?',
                [backup::VAR_PARENTID]
            );
            $post->set_source_table('spatialforum_posts', ['spatialforumid' => backup::VAR_PARENTID], 'timecreated ASC, id ASC');
            $read->set_source_sql(
                'SELECT r.*
                   FROM {spatialforum_read} r
                   JOIN {spatialforum_posts} p ON p.id = r.postid
                  WHERE p.spatialforumid = ?',
                [backup::VAR_PARENTID]
            );
        }

        $post->annotate_ids('user', 'userid');
        $post->annotate_ids('group', 'groupid');
        $spatialforum->annotate_ids('scale', 'grade');
        $read->annotate_ids('user', 'userid');
        $like->annotate_ids('user', 'userid');
        $spatialforum->annotate_files('mod_spatialforum', 'intro', null);
        $post->annotate_files('mod_spatialforum', 'post', 'id');

        return $this->prepare_activity_structure($spatialforum);
    }
}
