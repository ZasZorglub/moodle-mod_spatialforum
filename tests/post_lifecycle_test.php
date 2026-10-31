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
 * Editing, temporary deletion and permanent privacy erasure form one lifecycle.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_spatialforum;

use mod_spatialforum\local\forum;
use mod_spatialforum\local\notifications;
use mod_spatialforum\local\post_lifecycle;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Current permissions, conflict detection, media retention and no historical fan-out.
 */
#[CoversClass(post_lifecycle::class)]
final class post_lifecycle_test extends \advanced_testcase {
    /**
     * A real author, subscriber, activity and post.
     * @param bool $completion
     * @return array
     */
    private function fixture(bool $completion = false): array {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        if ($completion) {
            $CFG->enablecompletion = true;
        }
        $course = $gen->create_course(['enablecompletion' => $completion ? 1 : 0]);
        $activity = $gen->create_module('spatialforum', ['course' => $course->id,
            'completion' => $completion ? COMPLETION_TRACKING_AUTOMATIC : COMPLETION_TRACKING_NONE,
            'completionposts' => $completion ? 1 : 0]);
        $author = $gen->create_user();
        $other = $gen->create_user();
        foreach ([$author, $other] as $user) {
            $gen->enrol_user($user->id, $course->id, 'student');
        }
        $this->setUser($other);
        notifications::set_mode($activity->id, notifications::IMMEDIATE);
        $this->setUser($author);
        $id = forum::create_post($activity, $activity->roottrackid, $author->id, 'Original', '<p>Original body</p>', FORMAT_HTML);
        $cm = get_coursemodule_from_instance('spatialforum', $activity->id);
        $context = \context_module::instance($cm->id);
        $post = $DB->get_record('spatialforum_posts', ['id' => $id]);
        return [$activity, $author, $other, $cm, $context, $post];
    }

    /**
     * Save a real File API editor draft without duplicating notification work or changing thread identity.
     */
    public function test_edit_preserves_thread_and_shows_edited_timestamp(): void {
        global $DB, $CFG;
        [, , , $cm, $context, $post] = $this->fixture();
        require_once($CFG->libdir . '/filelib.php');
        $revision = post_lifecycle::revision($post);
        $prepared = file_prepare_standard_editor(
            clone $post,
            'message',
            \mod_spatialforum\local\media::editor_options($context),
            $context,
            'mod_spatialforum',
            'post',
            $post->id
        );
        $prepared->subject = 'Updated subject';
        $prepared->message_editor['text'] = '<p>Updated body</p>';
        $tasks = $DB->count_records('task_adhoc');
        post_lifecycle::edit($cm, $post->id, $revision, $prepared);
        $saved = $DB->get_record('spatialforum_posts', ['id' => $post->id]);
        $this->assertSame('Updated subject', $saved->subject);
        $this->assertSame('<p>Updated body</p>', $saved->message);
        $this->assertEquals($post->trackid, $saved->trackid);
        $this->assertEquals($post->replytoid, $saved->replytoid);
        $this->assertGreaterThan($post->timecreated, $saved->timemodified);
        $this->assertSame($tasks, $DB->count_records('task_adhoc'));
    }

    /**
     * A stale editor cannot overwrite changes saved from a second tab in the same second.
     */
    public function test_stale_edit_is_rejected(): void {
        global $DB;
        [, , , $cm, , $post] = $this->fixture();
        $revision = post_lifecycle::revision($post);
        $DB->set_field('spatialforum_posts', 'subject', 'Saved from another tab', ['id' => $post->id]);
        $this->expectExceptionMessage(get_string('postchanged', 'mod_spatialforum'));
        post_lifecycle::edit($cm, $post->id, $revision, (object) ['subject' => 'Stale text']);
    }

    /**
     * Viewing and replying do not grant permission to edit another participant's post.
     */
    public function test_other_author_cannot_edit(): void {
        [, , $other, $cm, $context, $post] = $this->fixture();
        $this->setUser($other);
        $this->assertFalse(post_lifecycle::can_edit($post, $context));
        $this->expectExceptionMessage(get_string('cannoteditpost', 'mod_spatialforum'));
        post_lifecycle::edit($cm, $post->id, post_lifecycle::revision($post), (object) []);
    }

    /**
     * Locks apply to existing posts as well as new contributions.
     */
    public function test_locked_track_prevents_edit(): void {
        global $DB;
        [, , , $cm, $context, $post] = $this->fixture();
        $DB->set_field('spatialforum_tracks', 'locked', 1, ['id' => $post->trackid]);
        $this->assertFalse(post_lifecycle::can_edit($post, $context));
        $this->expectExceptionMessage(get_string('cannoteditpost', 'mod_spatialforum'));
        post_lifecycle::edit($cm, $post->id, post_lifecycle::revision($post), (object) []);
    }

    /**
     * Deleted bodies disappear from rendering, media remain available for undo, and old mail is never replayed.
     */
    public function test_moderator_undo_restores_content_without_resending(): void {
        global $DB;
        [$activity, , , $cm, $context, $post] = $this->fixture();
        $file = get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_spatialforum', 'filearea' => 'post',
            'itemid' => $post->id, 'filepath' => '/', 'filename' => 'qa.png',
        ], 'QA media');
        notifications::enqueue_post($post->id);
        $this->assertSame(1, $DB->count_records('spatialforum_deliveries'));
        $childid = forum::create_post($activity, $post->trackid, $post->userid, 'Child', 'Child body', FORMAT_HTML, $post->id);
        $this->setAdminUser();
        post_lifecycle::moderate($cm, $post->id);
        $removed = $DB->get_record('spatialforum_posts', ['id' => $post->id]);
        $this->assertTrue(post_lifecycle::restorable($removed));
        $track = $DB->get_record('spatialforum_tracks', ['id' => $post->trackid]);
        $data = forum::export_view_data($activity, $cm, $track, $context, true);
        $this->assertStringNotContainsString('Original body', json_encode($data));
        $child = array_values(array_filter($data['posts'], static fn($item) => $item['id'] == $childid))[0];
        $this->assertEquals($post->id, $child['parentid']);
        $this->assertSame('QA media', get_file_storage()->get_file_by_id($file->get_id())->get_content());
        $this->assertSame(0, $DB->count_records('spatialforum_deliveries'));
        post_lifecycle::moderate($cm, $post->id, true);
        $restored = $DB->get_record('spatialforum_posts', ['id' => $post->id]);
        $this->assertEquals(0, $restored->deleted);
        $this->assertSame($post->message, $restored->message);
        notifications::enqueue_post($post->id);
        $this->assertSame(0, $DB->count_records('spatialforum_deliveries'));
    }

    /**
     * Expired removals lose their body/media and cannot be restored, even before cron cleanup.
     */
    public function test_expired_deletion_is_purged(): void {
        global $DB;
        [, , , $cm, $context, $post] = $this->fixture();
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_spatialforum', 'filearea' => 'post',
            'itemid' => $post->id, 'filepath' => '/', 'filename' => 'qa.png',
        ], 'QA media');
        $this->setAdminUser();
        post_lifecycle::moderate($cm, $post->id);
        $DB->set_field('spatialforum_posts', 'deletedat', time() - post_lifecycle::RETENTION, ['id' => $post->id]);
        $expired = $DB->get_record('spatialforum_posts', ['id' => $post->id]);
        $this->assertFalse(post_lifecycle::restorable($expired));
        $this->assertSame(1, post_lifecycle::purge());
        $this->assertSame('', $DB->get_field('spatialforum_posts', 'message', ['id' => $post->id]));
        $this->assertCount(0, get_file_storage()->get_area_files($context->id, 'mod_spatialforum', 'post', $post->id, 'id', false));
        $this->expectExceptionMessage(get_string('cannotrestorepost', 'mod_spatialforum'));
        post_lifecycle::moderate($cm, $post->id, true);
    }

    /**
     * Privacy erasure permanently cancels restoration, including an existing moderator window.
     */
    public function test_privacy_erasure_cannot_be_undone(): void {
        global $DB;
        [, $author, , $cm, $context, $post] = $this->fixture();
        $this->setAdminUser();
        post_lifecycle::moderate($cm, $post->id);
        \mod_spatialforum\privacy\provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist(
            $author,
            'mod_spatialforum',
            [$context->id]
        ));
        $erased = $DB->get_record('spatialforum_posts', ['id' => $post->id]);
        $this->assertEquals(0, $erased->userid);
        $this->assertEquals(0, $erased->deletedat);
        $this->assertSame('', $erased->message);
        $this->assertFalse(post_lifecycle::restorable($erased));
        $this->expectExceptionMessage(get_string('cannotrestorepost', 'mod_spatialforum'));
        post_lifecycle::moderate($cm, $post->id, true);
    }

    /**
     * Reversible removal recalculates actual Moodle completion in both directions.
     */
    public function test_completion_follows_deletion_and_restoration(): void {
        [$activity, $author, , $cm, , $post] = $this->fixture(true);
        $course = get_course($activity->course);
        $info = new \completion_info($course);
        $cminfo = get_fast_modinfo($course)->get_cm($cm->id);
        $this->assertEquals(COMPLETION_COMPLETE, $info->get_data($cminfo, false, $author->id)->completionstate);
        $this->setAdminUser();
        post_lifecycle::moderate($cm, $post->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $info->get_data($cminfo, false, $author->id)->completionstate);
        post_lifecycle::moderate($cm, $post->id, true);
        $this->assertEquals(COMPLETION_COMPLETE, $info->get_data($cminfo, false, $author->id)->completionstate);
    }

    /**
     * A learner cannot use the restoration API to reverse a moderator's action.
     */
    public function test_learner_cannot_restore_a_deleted_post(): void {
        [, $author, , $cm, , $post] = $this->fixture();
        $this->setAdminUser();
        post_lifecycle::moderate($cm, $post->id);
        $this->setUser($author);
        $this->expectException(\required_capability_exception::class);
        post_lifecycle::moderate($cm, $post->id, true);
    }
}
