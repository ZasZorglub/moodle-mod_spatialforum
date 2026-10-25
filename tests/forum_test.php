<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_spatialforum;

use mod_spatialforum\local\forum;
use mod_spatialforum\privacy\provider;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/** Regression tests using a real Moodle database and capability system. */
#[CoversClass(forum::class)]
#[CoversClass(provider::class)]
final class forum_test extends \advanced_testcase {
    /** Create an enrolled participant, activity, root track and first post. */
    private function fixture(): array {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $activity = $this->getDataGenerator()->create_module('spatialforum', ['course' => $course->id]);
        $context = \context_module::instance($activity->cmid);
        $cm = get_coursemodule_from_id('spatialforum', $activity->cmid);
        $track = $DB->get_record('spatialforum_tracks', ['id' => $activity->roottrackid], '*', MUST_EXIST);
        $id = forum::create_post($activity, $track->id, $user->id, 'Original subject',
            '<p>Original body</p>', FORMAT_HTML);
        $post = $DB->get_record('spatialforum_posts', ['id' => $id], '*', MUST_EXIST);
        $this->setUser($user);
        return [$activity, $track, $post, $user, $context, $cm, $course];
    }

    public function test_reply_subject_remains_editable_and_is_bounded(): void {
        $this->assertSame('Re: Original subject', forum::reply_subject('Original subject'));
        $this->assertSame('Re: Original subject', forum::reply_subject('Re: Original subject'));
        $this->assertSame('RE: Subject', forum::reply_subject(' RE: Subject '));
        $this->assertSame(255, \core_text::strlen(forum::reply_subject(str_repeat('æ', 255))));
    }

    public function test_reply_context_disappears_after_original_is_erased(): void {
        global $DB;

        [$activity, $track, $post, $user, $context, $cm] = $this->fixture();
        forum::create_post($activity, $track->id, $user->id, 'Custom reply title', 'My reply', FORMAT_HTML, $post->id, true);
        $data = forum::export_view_data($activity, $cm, $track, $context);
        $this->assertSame('Custom reply title', $data['posts'][1]['subject']);
        $this->assertStringContainsString('Original body', $data['posts'][1]['replycontext'][0]['message']);
        $DB->set_field('spatialforum_posts', 'deleted', 1, ['id' => $post->id]);
        $DB->set_field('spatialforum_posts', 'message', '', ['id' => $post->id]);
        $data = forum::export_view_data($activity, $cm, $track, $context);
        $this->assertSame([['available' => false]], $data['posts'][1]['replycontext']);
    }

    public function test_likes_are_idempotent_and_scoped_to_one_user(): void {
        global $DB;

        [$activity, $track, $post, $user, $context, $cm] = $this->fixture();
        $other = $this->getDataGenerator()->create_user();
        forum::set_like($activity, $post, $user->id, true);
        forum::set_like($activity, $post, $user->id, true);
        forum::set_like($activity, $post, $other->id, true);
        $data = forum::export_view_data($activity, $cm, $track, $context);
        $this->assertEquals(2, $data['posts'][0]['likecount']);
        $this->assertTrue($data['posts'][0]['liked']);
        forum::set_like($activity, $post, $user->id, false);
        forum::set_like($activity, $post, $user->id, false);
        $this->assertSame(1, $DB->count_records('spatialforum_likes', ['postid' => $post->id]));
        $this->assertTrue($DB->record_exists('spatialforum_likes', ['postid' => $post->id, 'userid' => $other->id]));
    }

    public function test_quotes_are_opt_in_without_losing_reply_relationships(): void {
        global $DB;

        [$activity, $track, $post, $user, $context, $cm] = $this->fixture();
        $reply = forum::create_post($activity, $track->id, $user->id, 'Without quote', 'Answer', FORMAT_HTML, $post->id);
        $data = forum::export_view_data($activity, $cm, $track, $context);
        $this->assertSame([], $data['posts'][1]['replycontext']);
        $this->assertEquals($post->id, $DB->get_field('spatialforum_posts', 'replytoid', ['id' => $reply]));
        // Opt-in quotes also work across a track boundary, without copying the original text.
        $childid = forum::create_track($activity, $track, $post->id, 'Branch');
        forum::create_post($activity, $childid, $user->id, 'With quote', 'Answer', FORMAT_HTML, $post->id, true);
        $child = $DB->get_record('spatialforum_tracks', ['id' => $childid], '*', MUST_EXIST);
        $data = forum::export_view_data($activity, $cm, $child, $context);
        $this->assertStringContainsString('Original body', $data['posts'][0]['replycontext'][0]['message']);
    }

    public function test_like_dislike_switching_removal_and_invalid_reactions(): void {
        global $DB;

        [$activity, $track, $post, $user, $context, $cm] = $this->fixture();
        $other = $this->getDataGenerator()->create_user();
        forum::set_reaction($activity, $post, $other->id, 1);
        forum::set_reaction($activity, $post, $user->id, 1);
        forum::set_reaction($activity, $post, $user->id, -1);
        forum::set_reaction($activity, $post, $user->id, -1);
        $data = forum::export_view_data($activity, $cm, $track, $context);
        $this->assertEquals(1, $data['posts'][0]['likecount']);
        $this->assertEquals(1, $data['posts'][0]['dislikecount']);
        $this->assertFalse($data['posts'][0]['liked']);
        $this->assertTrue($data['posts'][0]['disliked']);
        $this->assertSame(0, $data['posts'][0]['nextdislike']);
        $this->assertSame(2, $DB->count_records('spatialforum_likes', ['postid' => $post->id]));
        forum::set_reaction($activity, $post, $user->id, 1);
        $data = forum::export_view_data($activity, $cm, $track, $context);
        $this->assertEquals(2, $data['posts'][0]['likecount']);
        $this->assertEquals(0, $data['posts'][0]['dislikecount']);
        forum::set_reaction($activity, $post, $user->id, 0);
        forum::set_reaction($activity, $post, $user->id, 0);
        $this->assertSame(1, $DB->count_records('spatialforum_likes', ['postid' => $post->id]));
        $this->expectException(\moodle_exception::class);
        forum::set_reaction($activity, $post, $user->id, 2);
    }

    public function test_every_reply_creates_a_separate_track_with_a_return_path(): void {
        global $DB;

        [$activity, $root, $post, $user, $context, $cm] = $this->fixture();
        $firstid = forum::reply_in_new_track($activity, $root, $post->id, $user->id,
            'First reply track', 'Re: Original', 'First answer', FORMAT_HTML);
        $secondid = forum::reply_in_new_track($activity, $root, $post->id, $user->id,
            'Second reply track', 'Different subject', 'Second answer', FORMAT_HTML, true);
        $this->assertNotEquals($firstid, $secondid);
        foreach ([$firstid, $secondid] as $id) {
            $track = $DB->get_record('spatialforum_tracks', ['id' => $id], '*', MUST_EXIST);
            $reply = $DB->get_record('spatialforum_posts', ['trackid' => $id], '*', MUST_EXIST);
            $this->assertEquals($root->id, $track->parenttrackid);
            $this->assertEquals($post->id, $track->originpostid);
            $this->assertEquals($post->id, $reply->replytoid);
            $this->assertNotEquals('#ffffff', $track->colour);
        }
        $second = $DB->get_record('spatialforum_tracks', ['id' => $secondid], '*', MUST_EXIST);
        $secondpost = $DB->get_record('spatialforum_posts', ['trackid' => $secondid], '*', MUST_EXIST);
        $data = forum::export_view_data($activity, $cm, $second, $context);
        $this->assertStringContainsString('Original body', $data['posts'][0]['replycontext'][0]['message']);
        $thirdid = forum::reply_in_new_track($activity, $second, $secondpost->id, $user->id,
            'Reply to reply', 'Re: Different subject', 'Third answer', FORMAT_HTML);
        $this->assertEquals([$root->id, $secondid, $thirdid],
            array_column(forum::get_path($thirdid, $activity->id), 'id'));
        $this->assertSame(1, $DB->count_records('spatialforum_posts', ['trackid' => $root->id]));
    }

    public function test_new_reply_tracks_respect_locks_depth_and_origin_scope(): void {
        global $DB;

        [$activity, $track, $post, $user, $context, $cm] = $this->fixture();
        foreach (['locked', 'depth', 'deleted', 'wrongtrack'] as $case) {
            $testtrack = clone $track;
            $testactivity = clone $activity;
            if ($case === 'locked') {
                $testtrack->locked = 1;
            } else if ($case === 'depth') {
                $testactivity->maxdepth = 1;
                $data = forum::export_view_data($testactivity, $cm, $track, $context);
                $this->assertFalse($data['posts'][0]['canreply']);
            } else if ($case === 'deleted') {
                $DB->set_field('spatialforum_posts', 'deleted', 1, ['id' => $post->id]);
            } else {
                $testtrack->id = -1;
            }
            try {
                forum::reply_in_new_track($testactivity, $testtrack, $post->id, $user->id,
                    'Rejected', 'Subject', 'Answer', FORMAT_HTML);
                $this->fail('Invalid reply accepted: ' . $case);
            } catch (\moodle_exception $e) {
                $this->assertSame(1, $DB->count_records('spatialforum_tracks', ['spatialforumid' => $activity->id]));
                $this->assertSame(1, $DB->count_records('spatialforum_posts', ['spatialforumid' => $activity->id]));
            }
            $DB->set_field('spatialforum_posts', 'deleted', 0, ['id' => $post->id]);
        }
        $role = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        assign_capability('mod/spatialforum:startbranch', CAP_PROHIBIT, $role->id, $context->id);
        reload_all_capabilities();
        $data = forum::export_view_data($activity, $cm, $track, $context);
        $this->assertFalse($data['posts'][0]['canreply']);
        $this->assertTrue($data['canreply']); // A new top-level post does not create a track.
    }

    public function test_likes_can_be_disabled_at_both_levels_without_losing_data(): void {
        global $DB;

        [$activity, $track, $post, $user, $context, $cm] = $this->fixture();
        forum::set_like($activity, $post, $user->id, true);
        foreach (['activity', 'site'] as $scope) {
            $activity->enablelikes = $scope === 'activity' ? 0 : 1;
            set_config('allowlikes', $scope === 'site' ? 0 : 1, 'mod_spatialforum');
            $data = forum::export_view_data($activity, $cm, $track, $context);
            $this->assertFalse($data['posts'][0]['showlikes']);
            try {
                forum::set_reaction($activity, $post, $user->id, -1);
                $this->fail('Disabled likes accepted a direct request');
            } catch (\moodle_exception $e) {
                $this->assertSame('likesdisabled', $e->errorcode);
            }
            $this->assertSame(1, $DB->count_records('spatialforum_likes'));
        }
    }

    public function test_role_and_lock_controls_and_fifth_reply_link(): void {
        [$activity, $track, $post, $user, $context, $cm, $course] = $this->fixture();
        for ($i = 2; $i <= 8; $i++) {
            forum::create_post($activity, $track->id, $user->id, 'Post ' . $i, str_repeat('Long body ', 200), FORMAT_HTML);
        }
        $data = forum::export_view_data($activity, $cm, $track, $context);
        $this->assertTrue($data['posts'][4]['canreply']);
        $this->assertStringContainsString('originpostid=' . $data['posts'][4]['id'], $data['posts'][4]['replyurl']);
        $this->assertTrue($data['posts'][0]['canlike']);
        $this->assertFalse($data['canmoderate']);
        $track->locked = 1;
        $locked = forum::export_view_data($activity, $cm, $track, $context);
        $this->assertFalse($locked['posts'][4]['canreply']);
        $this->assertFalse($locked['posts'][4]['canbranch']);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);
        $this->assertTrue(has_capability('moodle/course:manageactivities', $context));
        $this->assertTrue(has_capability('mod/spatialforum:moderate', $context));
        $this->setGuestUser();
        $guest = forum::export_view_data($activity, $cm, $track, $context);
        $this->assertFalse($guest['posts'][0]['canlike']);
    }

    public function test_privacy_covers_users_who_only_liked_a_post(): void {
        global $DB;

        [$activity, $track, $post, $user, $context] = $this->fixture();
        $other = $this->getDataGenerator()->create_user();
        forum::set_reaction($activity, $post, $other->id, -1);
        $contexts = provider::get_contexts_for_userid($other->id)->get_contextids();
        $this->assertContains((int) $context->id, array_map('intval', $contexts));
        $userlist = new \core_privacy\local\request\userlist($context, 'mod_spatialforum');
        provider::get_users_in_context($userlist);
        $this->assertContains((int) $other->id, array_map('intval', $userlist->get_userids()));
        $approved = new \core_privacy\local\request\approved_contextlist($other, 'mod_spatialforum', [$context->id]);
        provider::delete_data_for_user($approved);
        $this->assertFalse($DB->record_exists('spatialforum_likes', ['userid' => $other->id]));
        $this->assertTrue($DB->record_exists('spatialforum_posts', ['id' => $post->id, 'userid' => $user->id]));
        forum::set_like($activity, $post, $user->id, true);
        provider::delete_data_for_all_users_in_context($context);
        $this->assertSame(0, $DB->count_records('spatialforum_likes'));
    }

    public function test_map_previews_do_not_mark_posts_read(): void {
        global $DB;

        [$activity, $root, $original, $user, $context, $cm] = $this->fixture();
        $author = $this->getDataGenerator()->create_user();
        $parentid = forum::create_post($activity, $root->id, $author->id,
            'Other post', '<p>First line</p><p><strong>Second line</strong></p>', FORMAT_HTML);
        $childid = forum::reply_in_new_track($activity, $root, $parentid, $author->id,
            'Automatic', 'Child post', '<p>The actual answer</p>', FORMAT_HTML, true);
        $data = forum::export_view_data($activity, $cm, $root, $context, true);
        $this->assertCount(3, $data['posts']);
        $this->assertSame(2, $data['readcount']);
        $this->assertSame(0, $DB->count_records('spatialforum_read'));
        $posts = array_column($data['posts'], null, 'id');
        $parent = $posts[$parentid];
        $this->assertStringContainsString('First line', $parent['preview']);
        $this->assertStringContainsString('Second line', $parent['preview']);
        $this->assertStringNotContainsString('<', $parent['preview']);
        $childpostid = $DB->get_field('spatialforum_posts', 'id', ['trackid' => $childid], MUST_EXIST);
        $this->assertEquals($parentid, $posts[$childpostid]['parentid']);
        $this->assertSame('The actual answer', $posts[$childpostid]['preview']);
        $this->assertCount(1, $posts[$childpostid]['replycontext']);
        $this->setGuestUser();
        $guest = forum::export_view_data($activity, $cm, $root, $context, true);
        $this->assertSame(0, $guest['readcount']);
        $this->assertFalse($guest['posts'][0]['cantrackread']);
        $this->assertSame(0, $DB->count_records('spatialforum_read'));
    }

    public function test_read_marks_are_per_user_per_post_and_idempotent(): void {
        global $DB;

        [$activity, $root, $post, $user] = $this->fixture();
        $other = $this->getDataGenerator()->create_user();
        $secondid = forum::create_post($activity, $root->id, $user->id, 'Second', 'Still unread', FORMAT_HTML);
        forum::mark_read([$post->id], $user->id);
        forum::mark_read([$post->id], $other->id);
        $this->assertSame(2, $DB->count_records('spatialforum_read', ['postid' => $post->id]));
        $conditions = ['postid' => $post->id, 'userid' => $other->id];
        $DB->set_field('spatialforum_read', 'timeread', 123, $conditions);
        forum::mark_read([$post->id, $post->id], $other->id);
        $this->assertEquals(123, $DB->get_field('spatialforum_read', 'timeread', $conditions));
        $unread = array_map('intval', forum::get_unread_postids($activity->id, $other->id));
        $this->assertNotContains((int) $post->id, $unread);
        $this->assertContains($secondid, $unread);
        $this->assertSame(0, $DB->count_records('spatialforum_read', ['postid' => $secondid]));
        forum::mark_read([$post->id], 0);
        $this->assertSame(2, $DB->count_records('spatialforum_read'));
    }

    public function test_whole_map_uses_each_posts_track_permissions_and_reactions(): void {
        global $DB;

        [$activity, $root, $post, $user, $context, $cm] = $this->fixture();
        $childid = forum::reply_in_new_track($activity, $root, $post->id, $user->id,
            'Child', 'Child title', 'Answer', FORMAT_HTML);
        $child = $DB->get_record('spatialforum_posts', ['trackid' => $childid], '*', MUST_EXIST);
        forum::set_reaction($activity, $child, $user->id, -1);
        $DB->set_field('spatialforum_tracks', 'locked', 1, ['id' => $childid]);
        $posts = array_column(forum::export_view_data($activity, $cm, $root, $context, true)['posts'], null, 'id');
        $this->assertFalse($posts[$child->id]['canreply']);
        $this->assertTrue($posts[$post->id]['canreply']);
        $this->assertEquals(1, $posts[$child->id]['dislikecount']);
        $DB->set_field('spatialforum_tracks', 'locked', 0, ['id' => $childid]);
        $posts = array_column(forum::export_view_data($activity, $cm, $root, $context, true)['posts'], null, 'id');
        $this->assertTrue($posts[$child->id]['canreply']);
        $this->assertStringContainsString('trackid=' . $childid, $posts[$child->id]['replyurl']);
        $this->assertEquals($child->id, $posts[$post->id]['children'][0]['id']);
    }

    public function test_automatic_reply_form_has_no_branch_name_choice(): void {
        global $PAGE;

        [$activity, $root, $post, $user, $context, $cm] = $this->fixture();
        $PAGE->set_context($context);
        $PAGE->set_url('/mod/spatialforum/post.php', ['id' => $cm->id]);
        $form = new \mod_spatialforum\form\post_form(null, [
            'cmid' => $cm->id, 'action' => 'reply', 'trackid' => $root->id,
            'originpostid' => $post->id, 'newtrack' => true,
        ]);
        $errors = $form->validation([
            'action' => 'reply', 'subject' => 'My reply',
            'message_editor' => ['text' => 'Actual answer', 'format' => FORMAT_HTML],
        ], []);
        $this->assertArrayNotHasKey('branchname', $errors);
        ob_start();
        $form->display();
        $html = ob_get_clean();
        $this->assertStringNotContainsString('name="branchname"', $html);
        $this->assertStringContainsString('name="showparent"', $html);
    }

    public function test_actual_template_renders_all_posts_as_unfolding_cards(): void {
        global $OUTPUT;

        [$activity, $root, $post, $user, $context, $cm] = $this->fixture();
        forum::reply_in_new_track($activity, $root, $post->id, $user->id,
            'Child', 'A reply', 'Answer preview', FORMAT_HTML);
        $data = forum::export_view_data($activity, $cm, $root, $context, true);
        $html = $OUTPUT->render_from_template('mod_spatialforum/main', $data);
        $this->assertSame(2, substr_count($html, '<details '));
        $this->assertSame(2, $data['postcount']);
        $this->assertStringContainsString('/mod/spatialforum/read.php', $data['readurl']);
        $this->assertStringContainsString('data-read-url="' . s($data['readurl']) . '"', $html);
        $this->assertStringContainsString('data-parent-id="' . $post->id . '"', $html);
        $this->assertStringContainsString('Answer preview', $html);
        $this->assertStringNotContainsString('Start track here', $html);
        $this->assertStringNotContainsString('data-track-link', $html);
    }
}
