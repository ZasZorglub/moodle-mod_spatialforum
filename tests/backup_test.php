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


namespace mod_spatialforum;

use mod_spatialforum\local\forum;
use mod_spatialforum\privacy\provider;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/phpunit/classes/restore_date_testcase.php');

/**

 * Backup/restore preserves reply, track and like relationships.

 */
#[CoversClass(\backup_spatialforum_activity_structure_step::class)]
#[CoversClass(\restore_spatialforum_activity_structure_step::class)]
final class backup_test extends \restore_date_testcase {
    public function test_restore_remaps_post_links_and_embedded_media_files(): void {
        global $DB, $CFG;
        [$course, $activity] = $this->create_course_and_module('spatialforum');
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $cm = get_coursemodule_from_instance('spatialforum', $activity->id);
        $context = \context_module::instance($cm->id);
        $postid = forum::create_post($activity, $activity->roottrackid, $user->id, 'QA media backup', 'QA', FORMAT_HTML);
        foreach (['qa.png', 'qa.mp3', 'qa.mp4'] as $filename) {
            get_file_storage()->create_file_from_string([
                'contextid' => $context->id, 'component' => 'mod_spatialforum', 'filearea' => 'post',
                'itemid' => $postid, 'filepath' => '/', 'filename' => $filename,
            ], 'QA file ' . $filename);
        }
        $base = $CFG->wwwroot . '/mod/spatialforum/view.php?id=' . $cm->id;
        $html = '<p><a href="' . $base . '">QA activity</a> ' .
            '<a href="' . $base . '&amp;postid=' . $postid . '">QA post</a> ' .
            '<a href="' . $base . '&amp;trackid=' . $activity->roottrackid . '#post-' . $postid . '">QA hash</a></p>' .
            '<img src="@@PLUGINFILE@@/qa.png" alt="QA image">' .
            '<audio controls src="@@PLUGINFILE@@/qa.mp3"></audio><video controls src="@@PLUGINFILE@@/qa.mp4"></video>';
        $DB->set_field('spatialforum_posts', 'message', $html, ['id' => $postid]);
        $newcourseid = $this->backup_and_restore($course);
        $new = $DB->get_record('spatialforum', ['course' => $newcourseid], '*', MUST_EXIST);
        $newcm = get_coursemodule_from_instance('spatialforum', $new->id);
        $newcontext = \context_module::instance($newcm->id);
        $post = $DB->get_record('spatialforum_posts', ['spatialforumid' => $new->id], '*', MUST_EXIST);
        $this->assertStringNotContainsString('$@SPATIALFORUM', $post->message);
        $this->assertStringContainsString('view.php?id=' . $newcm->id, $post->message);
        $decoded = html_entity_decode($post->message, ENT_QUOTES | ENT_HTML5);
        $this->assertStringContainsString('view.php?id=' . $newcm->id . '&postid=' . $post->id, $decoded);
        $this->assertStringNotContainsString('view.php?id=' . $cm->id, $decoded);
        $files = get_file_storage()->get_area_files($newcontext->id, 'mod_spatialforum', 'post', $post->id, 'id', false);
        $this->assertCount(3, $files);
        $rendered = \mod_spatialforum\local\excerpts::render($post, $newcontext, $newcm->id);
        $this->assertStringContainsString('/mod_spatialforum/post/' . $post->id . '/qa.mp4', $rendered);
    }

    public function test_restore_remaps_quotes_likes_and_privacy_tombstones(): void {
        global $DB;

        [$course, $activity] = $this->create_course_and_module('spatialforum', ['enablelikes' => 0]);
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($other->id, $course->id, 'student');
        $root = $DB->get_record('spatialforum_tracks', ['id' => $activity->roottrackid]);
        $first = forum::create_post($activity, $root->id, $user->id, 'Original', 'Parent', FORMAT_HTML);
        $reply = forum::create_post(
            $activity,
            $root->id,
            $other->id,
            'Reply',
            'Answer',
            FORMAT_HTML,
            $first,
            true,
            json_encode([['quote' => 'Parent', 'comment' => 'Note']])
        );
        $replyparts = $DB->get_field('spatialforum_posts', 'replyparts', ['id' => $reply]);
        $replycreated = $DB->get_field('spatialforum_posts', 'timecreated', ['id' => $reply]);
        $DB->set_field('spatialforum_posts', 'timemodified', $replycreated + 3600, ['id' => $reply]);
        $branchid = forum::create_track($activity, $root, $reply, 'Child track');
        forum::create_post(
            $activity,
            $branchid,
            $other->id,
            'Child',
            'Branch body',
            FORMAT_HTML,
            $reply,
            false,
            json_encode([['quote' => 'Answer', 'comment' => 'Child note']])
        );
        // Likes remain in backups even while the activity hides them.
        $DB->insert_record('spatialforum_likes', (object) [
            'userid' => $other->id, 'postid' => $reply, 'reaction' => -1, 'timecreated' => time(),
        ]);
        $context = \context_module::instance($activity->cmid);
        provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist(
            $user,
            'mod_spatialforum',
            [$context->id]
        ));

        $newcourseid = $this->backup_and_restore($course);
        $new = $DB->get_record('spatialforum', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertEquals(0, $new->enablelikes);
        $this->assertNotEquals($activity->roottrackid, $new->roottrackid);
        $this->assertSame(3, $DB->count_records('spatialforum_posts', ['spatialforumid' => $new->id]));
        $newreply = $DB->get_record('spatialforum_posts', ['spatialforumid' => $new->id, 'subject' => 'Reply'], '*', MUST_EXIST);
        $parent = $DB->get_record('spatialforum_posts', ['id' => $newreply->replytoid], '*', MUST_EXIST);
        $this->assertEquals(1, $newreply->showparent);
        $this->assertSame($replyparts, $newreply->replyparts);
        $this->assertEquals(3600, $newreply->timemodified - $newreply->timecreated);
        $this->assertEquals($new->id, $parent->spatialforumid);
        $this->assertEquals(1, $parent->deleted);
        $this->assertSame('', $parent->message);
        $newchild = $DB->get_record('spatialforum_tracks', ['spatialforumid' => $new->id, 'name' => 'Child track']);
        $this->assertEquals($new->roottrackid, $newchild->parenttrackid);
        $this->assertEquals($newreply->id, $newchild->originpostid);
        $this->assertTrue($DB->record_exists('spatialforum_likes', [
            'postid' => $newreply->id, 'userid' => $other->id, 'reaction' => -1,
        ]));
        $newchildpost = $DB->get_record('spatialforum_posts', ['trackid' => $newchild->id], '*', MUST_EXIST);
        $this->assertEquals(0, $newchildpost->showparent);
        $this->assertEquals($newreply->id, $newchildpost->replytoid);
        $newcm = get_coursemodule_from_instance('spatialforum', $new->id);
        $newcontext = \context_module::instance($newcm->id);
        $this->assertStringContainsString('Answer', \mod_spatialforum\local\excerpts::render(
            $newchildpost,
            $newcontext,
            $newcm->id
        ));
        $this->assertStringContainsString('Child note', $newchildpost->replyparts);
        $this->assertStringNotContainsString('Parent', \mod_spatialforum\local\excerpts::render(
            $newreply,
            $newcontext,
            $newcm->id
        ));
    }
}
