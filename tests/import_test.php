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
 * Read-only previews and immutable, permission-aware historical forum copies.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_spatialforum;

use mod_spatialforum\local\forum_import;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Source integrity, topology, files, idempotency and recovery are exercised through the domain API.
 */
#[CoversClass(forum_import::class)]
#[CoversClass(\mod_spatialforum\task\import_forum::class)]
final class import_test extends \advanced_testcase {
    /**
     * A supported source with a root and two reply levels.
     * @param int $mode
     * @return array
     */
    private function fixture(int $mode = NOGROUPS): array {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $source = $gen->create_module('forum', ['course' => $course->id, 'type' => 'general', 'groupmode' => $mode]);
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $course->id, 'student');
        $forumgen = $gen->get_plugin_generator('mod_forum');
        $discussion = $forumgen->create_discussion(['forum' => $source->id, 'course' => $course->id,
            'userid' => $user->id, 'name' => 'Source discussion', 'subject' => 'Source root', 'message' => '<p>Source text</p>']);
        $DB->set_field('forum_posts', 'subject', 'Source root', ['id' => $discussion->firstpost]);
        $reply = $forumgen->create_post(['discussion' => $discussion->id, 'parent' => $discussion->firstpost,
            'userid' => $user->id, 'subject' => 'Source reply', 'message' => 'Reply text', 'messageformat' => FORMAT_PLAIN]);
        $child = $forumgen->create_post(['discussion' => $discussion->id, 'parent' => $reply->id,
            'userid' => $user->id, 'subject' => 'Source child', 'message' => 'Nested text', 'messageformat' => FORMAT_PLAIN]);
        $cm = get_coursemodule_from_instance('forum', $source->id);
        return [$course, $source, $cm, $user, $discussion, $reply, $child];
    }

    /**
     * Complete bounded pages exactly as queued task executions do, with a finite test guard.
     * @param int $jobid
     */
    private function finish(int $jobid): void {
        for ($i = 0; $i < 30; $i++) {
            if ((int) forum_import::job($jobid)->status === forum_import::COMPLETE) {
                return;
            }
            $task = new \mod_spatialforum\task\import_forum();
            $task->set_custom_data(['importid' => $jobid]);
            $task->execute();
        }
        $this->fail('Import did not finish its bounded pages');
    }

    /**
     * Preview writes no job/target and a repeated confirmation returns the one existing copy.
     */
    public function test_preview_and_idempotent_copy_preserve_source_and_thread(): void {
        global $DB;
        [, $source, $cm, $user, $discussion, $reply, $child] = $this->fixture();
        $before = $DB->get_records('forum_posts', ['discussion' => $discussion->id]);
        $preview = forum_import::preview($cm->id);
        $this->assertSame([], $preview['issues']);
        $this->assertSame(3, $preview['posts']);
        $this->assertSame(3, $preview['maxdepth']);
        $this->assertSame(0, $DB->count_records('spatialforum_imports'));
        $this->assertSame(0, $DB->count_records('spatialforum'));
        $jobid = forum_import::confirm($cm->id, $preview['fingerprint'], 'Hidden source copy', true);
        $this->assertSame($jobid, forum_import::confirm($cm->id, $preview['fingerprint'], 'Hidden source copy', true));
        $job = forum_import::job($jobid);
        $target = $DB->get_record('spatialforum', ['id' => $job->targetid]);
        $this->assertFalse(\mod_spatialforum\local\group_access::for_activity($target->id)->can_start(-1));
        $this->finish($jobid);
        $target = $DB->get_record('spatialforum', ['id' => $job->targetid]);
        $targetcm = get_coursemodule_from_instance('spatialforum', $target->id);
        $this->assertEquals(0, $targetcm->visible);
        $this->assertEquals(0, $target->importstate);
        $this->assertEquals(0, $target->grade);
        $this->assertEquals($before, $DB->get_records('forum_posts', ['discussion' => $discussion->id]));
        $maps = $DB->get_records('spatialforum_import_map', ['importid' => $jobid], '', 'sourcepostid, targetpostid');
        $newreply = $DB->get_record('spatialforum_posts', ['id' => $maps[$reply->id]->targetpostid]);
        $newchild = $DB->get_record('spatialforum_posts', ['id' => $maps[$child->id]->targetpostid]);
        $newroot = $DB->get_record('spatialforum_posts', ['id' => $maps[$discussion->firstpost]->targetpostid]);
        $this->assertSame('Source discussion', $newroot->discussionname);
        $this->assertSame('Source root', $newroot->subject);
        $this->assertEquals($maps[$discussion->firstpost]->targetpostid, $newreply->replytoid);
        $this->assertEquals($newreply->id, $newchild->replytoid);
        $this->assertEquals($user->id, $newchild->userid);
        $this->assertEquals($child->created, $newchild->timecreated);
        $this->assertEquals(FORMAT_PLAIN, $newchild->messageformat);
        $this->assertSame(0, $DB->count_records('spatialforum_deliveries'));
        $this->assertSame(0, $DB->count_records('spatialforum_subscriptions'));
        $this->assertSame(0, $DB->count_records('spatialforum_read'));
        $this->assertFalse($DB->record_exists('task_adhoc', ['classname' => '\\mod_spatialforum\\task\\fanout_post']));
        forum_import::execute($jobid);
        $this->assertSame(3, $DB->count_records('spatialforum_posts', ['spatialforumid' => $target->id]));
        $this->assertTrue($DB->record_exists('forum', ['id' => $source->id]));
    }

    /**
     * Changed sources are rejected before creating a target.
     */
    public function test_stale_preview_cannot_create_copy(): void {
        global $DB;
        [, , $cm, , , $reply] = $this->fixture();
        $preview = forum_import::preview($cm->id);
        $DB->set_field('forum_posts', 'message', 'Changed without relying on timestamp resolution', ['id' => $reply->id]);
        $this->expectException(\moodle_exception::class);
        forum_import::confirm($cm->id, $preview['fingerprint'], 'Stale copy', true);
    }

    /**
     * Restricted sources and invalid parent graphs never become ordinary public posts.
     */
    public function test_preflight_detects_restrictions_and_structure(): void {
        global $DB;
        [, $source, $cm, $user, $discussion, $reply] = $this->fixture();
        $DB->set_field('forum', 'type', 'qanda', ['id' => $source->id]);
        $DB->set_field('forum_posts', 'privatereplyto', $user->id, ['id' => $reply->id]);
        $DB->set_field('forum_discussions', 'timeend', time() + 1000, ['id' => $discussion->id]);
        $DB->set_field('forum_posts', 'parent', $reply->id, ['id' => $reply->id]);
        $issues = forum_import::preview($cm->id)['issues'];
        foreach (['type', 'restricted', 'timed', 'structure'] as $issue) {
            $this->assertContains($issue, $issues);
        }
        $this->assertSame(0, $DB->count_records('spatialforum'));
    }

    /**
     * A student cannot inspect a privileged whole-forum import preview.
     */
    public function test_student_is_denied_before_preview(): void {
        [, , $cm, $user] = $this->fixture();
        $this->setUser($user);
        $this->expectException(\required_capability_exception::class);
        forum_import::preview($cm->id);
    }

    /**
     * Inline/attachment filename collisions, media rendering and internal read links are remapped.
     */
    public function test_files_media_and_links_are_independent_copies(): void {
        global $DB, $CFG;
        [, , $cm, $user, $discussion, $reply] = $this->fixture();
        $context = \context_module::instance($cm->id);
        $fs = get_file_storage();
        $areas = ['post' => ['same.png'], 'attachment' => ['same.png', 'sound.mp3', 'film.mp4', 'captions.vtt']];
        foreach ($areas as $area => $names) {
            foreach ($names as $name) {
                $fs->create_file_from_string(
                    ['contextid' => $context->id, 'component' => 'mod_forum', 'filearea' => $area,
                    'itemid' => $discussion->firstpost, 'filepath' => '/', 'filename' => $name, 'userid' => $user->id],
                    'Synthetic ' . $area . $name
                );
            }
        }
        $message = '<img src="@@PLUGINFILE@@/same.png" alt="Inline"> <a href="' . $CFG->wwwroot
            . '/mod/forum/discuss.php?d=' . $discussion->id . '#p' . $reply->id . '">Reply link</a>';
        $DB->set_field('forum_posts', 'message', $message, ['id' => $discussion->firstpost]);
        $preview = forum_import::preview($cm->id);
        $this->assertSame([], $preview['issues']);
        $this->assertSame(5, $preview['files']);
        $jobid = forum_import::confirm($cm->id, $preview['fingerprint'], 'Media copy', true);
        $this->finish($jobid);
        $targetid = forum_import::job($jobid)->targetid;
        $targetcm = get_coursemodule_from_instance('spatialforum', $targetid);
        $newcontext = \context_module::instance($targetcm->id);
        $map = $DB->get_record('spatialforum_import_map', ['importid' => $jobid, 'sourcepostid' => $discussion->firstpost]);
        $post = $DB->get_record('spatialforum_posts', ['id' => $map->targetpostid]);
        $this->assertStringContainsString('@@PLUGINFILE@@/imported-inline/same.png', $post->message);
        $this->assertStringContainsString('/mod/spatialforum/view.php?id=' . $targetcm->id, $post->message);
        $this->assertCount(5, $fs->get_area_files($newcontext->id, 'mod_spatialforum', 'post', $post->id, 'id', false));
        $html = \mod_spatialforum\local\excerpts::render($post, $newcontext, $targetcm->id);
        foreach (['imported-inline/same.png', 'imported-attachments/same.png', '<audio', '<video', 'captions.vtt'] as $part) {
            $this->assertStringContainsString($part, $html);
        }
        $this->assertCount(4, $fs->get_area_files($context->id, 'mod_forum', 'attachment', $discussion->firstpost, 'id', false));
    }

    /**
     * Unsupported and missing files block the complete copy rather than being omitted.
     */
    public function test_unsupported_and_missing_files_block_copy(): void {
        global $DB;
        [, , $cm, , $discussion] = $this->fixture();
        get_file_storage()->create_file_from_string(['contextid' => \context_module::instance($cm->id)->id,
            'component' => 'mod_forum', 'filearea' => 'attachment', 'itemid' => $discussion->firstpost,
            'filepath' => '/', 'filename' => 'unsupported.pdf'], 'Synthetic PDF');
        $DB->set_field('forum_posts', 'message', '<img src="@@PLUGINFILE@@/missing.png">', ['id' => $discussion->firstpost]);
        $issues = forum_import::preview($cm->id)['issues'];
        $this->assertContains('files', $issues);
        $this->assertContains('missingfile', $issues);
    }

    /**
     * More than one copy page completes without a hidden fifty/one-hundred-post truncation.
     */
    public function test_large_copy_uses_continuations(): void {
        [, , $cm, $user, $discussion] = $this->fixture();
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_forum');
        for ($i = 0; $i < 103; $i++) {
            $gen->create_post(['discussion' => $discussion->id, 'parent' => $discussion->firstpost, 'userid' => $user->id]);
        }
        $preview = forum_import::preview($cm->id);
        $jobid = forum_import::confirm($cm->id, $preview['fingerprint'], 'Large copy', true);
        forum_import::execute($jobid);
        $this->assertEquals(100, forum_import::job($jobid)->copiedposts);
        $this->finish($jobid);
        $this->assertEquals(106, forum_import::job($jobid)->copiedposts);
    }

    /**
     * Mid-copy source change fails closed; discard removes only the incomplete target.
     */
    public function test_source_change_after_confirmation_requires_discard(): void {
        global $DB, $CFG;
        [, $source, $cm, , , $reply] = $this->fixture();
        $preview = forum_import::preview($cm->id);
        $jobid = forum_import::confirm($cm->id, $preview['fingerprint'], 'Changed source copy', true);
        $targetid = forum_import::job($jobid)->targetid;
        $DB->set_field('forum_posts', 'message', 'A newer version', ['id' => $reply->id]);
        try {
            forum_import::execute($jobid);
            $this->fail('Changed source accepted');
        } catch (\moodle_exception $e) {
            $this->assertSame('importpreviewchanged', $e->errorcode);
        }
        $this->assertEquals(forum_import::FAILED, forum_import::job($jobid)->status);
        $this->assertEquals(1, $DB->get_field('spatialforum', 'importstate', ['id' => $targetid]));
        $recyclebinsettings = $CFG->forced_plugin_settings['tool_recyclebin'] ?? [];
        forum_import::recover($jobid, true);
        $this->assertFalse($DB->record_exists('spatialforum', ['id' => $targetid]));
        $this->assertFalse($DB->record_exists('spatialforum_imports', ['id' => $jobid]));
        $this->assertFalse($DB->record_exists('spatialforum_import_map', ['importid' => $jobid]));
        $this->assertSame($recyclebinsettings, $CFG->forced_plugin_settings['tool_recyclebin']);
        $this->assertTrue($DB->record_exists('forum', ['id' => $source->id]));
        forum_import::execute($jobid);
    }

    /**
     * Source module overrides cannot silently grant the copy broader participant visibility.
     */
    public function test_read_permission_mismatch_is_a_blocker(): void {
        global $DB;
        [, , $cm] = $this->fixture();
        $role = $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('mod/forum:viewdiscussion', CAP_PROHIBIT, $role, \context_module::instance($cm->id), true);
        $this->assertContains('permissions', forum_import::preview($cm->id)['issues']);
    }

    /**
     * Group audiences and grouping restrictions survive, including after an explicit retry.
     */
    public function test_grouped_copy_can_resume_without_duplicates(): void {
        global $DB;
        [$course, , $cm, , $discussion] = $this->fixture(SEPARATEGROUPS);
        $gen = $this->getDataGenerator();
        $group = $gen->create_group(['courseid' => $course->id]);
        $grouping = $gen->create_grouping(['courseid' => $course->id]);
        $gen->create_grouping_group(['groupingid' => $grouping->id, 'groupid' => $group->id]);
        $DB->set_field('course_modules', 'groupingid', $grouping->id, ['id' => $cm->id]);
        $DB->set_field('forum_discussions', 'groupid', $group->id, ['id' => $discussion->id]);
        rebuild_course_cache($course->id, true);
        $preview = forum_import::preview($cm->id);
        $this->assertSame([], $preview['issues']);
        $jobid = forum_import::confirm($cm->id, $preview['fingerprint'], 'Grouped resume', true);
        forum_import::execute($jobid);
        $DB->set_field('spatialforum_imports', 'status', forum_import::FAILED, ['id' => $jobid]);
        forum_import::recover($jobid);
        $this->finish($jobid);
        $targetid = forum_import::job($jobid)->targetid;
        $targetcm = get_coursemodule_from_instance('spatialforum', $targetid);
        $this->assertEquals(SEPARATEGROUPS, $targetcm->groupmode);
        $this->assertEquals($grouping->id, $targetcm->groupingid);
        $this->assertSame(3, $DB->count_records('spatialforum_posts', ['spatialforumid' => $targetid, 'groupid' => $group->id]));
        $this->assertSame(0, $DB->count_records('spatialforum_deliveries'));
    }

    /**
     * Erasure discovers unprocessed authors and prevents their pending data being reintroduced.
     */
    public function test_pending_context_erasure_cancels_copy_and_removes_backreferences(): void {
        global $DB;
        [, , $cm, $author] = $this->fixture();
        $preview = forum_import::preview($cm->id);
        $jobid = forum_import::confirm($cm->id, $preview['fingerprint'], 'Privacy pending', true);
        $targetcm = get_coursemodule_from_instance('spatialforum', forum_import::job($jobid)->targetid);
        $context = \context_module::instance($targetcm->id);
        \mod_spatialforum\privacy\provider::delete_data_for_all_users_in_context($context);
        $this->assertEquals(forum_import::CANCELLED, forum_import::job($jobid)->status);
        $this->assertSame(0, $DB->count_records('spatialforum_import_map', ['importid' => $jobid]));
        forum_import::execute($jobid);
        $this->assertSame(0, $DB->count_records('spatialforum_posts'));
    }

    /**
     * Mail dispatch bookkeeping is not a content revision; external destinations stay external.
     */
    public function test_mail_bookkeeping_and_external_links_do_not_change_content(): void {
        global $DB, $CFG;
        [, , $cm, , $discussion, $reply] = $this->fixture();
        $message = '<a href="https://external.example/mod/forum/discuss.php?d=' . $discussion->id . '">External</a>'
            . '<a href="' . $CFG->wwwroot . '/mod/forum/discuss.php?d=' . $discussion->id
            . '&amp;parent=' . $reply->id . '">Internal</a>';
        $DB->set_field('forum_posts', 'message', $message, ['id' => $discussion->firstpost]);
        $preview = forum_import::preview($cm->id);
        $DB->set_field('forum_posts', 'mailed', 1, ['id' => $reply->id]);
        $this->assertSame($preview['fingerprint'], forum_import::preview($cm->id)['fingerprint']);
        $jobid = forum_import::confirm($cm->id, $preview['fingerprint'], 'Link copy', true);
        $this->finish($jobid);
        $map = $DB->get_record('spatialforum_import_map', ['importid' => $jobid, 'sourcepostid' => $discussion->firstpost]);
        $newmessage = $DB->get_field('spatialforum_posts', 'message', ['id' => $map->targetpostid]);
        $this->assertStringContainsString('https://external.example/mod/forum/discuss.php?d=' . $discussion->id, $newmessage);
        $this->assertStringContainsString('/mod/spatialforum/view.php?id=', $newmessage);
        $this->assertStringNotContainsString('&amp;parent=', $newmessage);
    }
}
