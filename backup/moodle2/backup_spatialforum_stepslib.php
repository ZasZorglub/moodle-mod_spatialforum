<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

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
        $userinfo = $this->get_setting_value('userinfo');

        $spatialforum = new backup_nested_element('spatialforum', ['id'], [
            'name', 'intro', 'introformat', 'roottrackid', 'maxdepth', 'enablecolors', 'enablelikes', 'locked', 'timemodified',
        ]);
        $tracks = new backup_nested_element('tracks');
        $track = new backup_nested_element('track', ['id'], [
            'parenttrackid', 'originpostid', 'name', 'colour', 'locked', 'timecreated', 'timemodified',
        ]);
        $posts = new backup_nested_element('posts');
        $post = new backup_nested_element('post', ['id'], [
            'trackid', 'replytoid', 'userid', 'subject', 'message', 'messageformat', 'deleted',
            'timecreated', 'timemodified',
        ]);
        $reads = new backup_nested_element('reads');
        $read = new backup_nested_element('read', ['id'], ['userid', 'postid', 'timeread']);
        $likes = new backup_nested_element('likes');
        $like = new backup_nested_element('like', ['id'], ['userid', 'postid', 'timecreated']);

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
                  WHERE p.spatialforumid = ?', [backup::VAR_PARENTID]);
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
        $read->annotate_ids('user', 'userid');
        $like->annotate_ids('user', 'userid');
        $spatialforum->annotate_files('mod_spatialforum', 'intro', null);

        return $this->prepare_activity_structure($spatialforum);
    }
}
