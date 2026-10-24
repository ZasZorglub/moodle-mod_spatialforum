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
}
