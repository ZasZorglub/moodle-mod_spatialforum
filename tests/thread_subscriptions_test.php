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
 * Conversation follows, overrides and notification privacy.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_spatialforum;

use mod_spatialforum\local\forum;
use mod_spatialforum\local\notifications;
use mod_spatialforum\local\thread_subscriptions;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * An activity subscription and a conversation preference resolve to a single effective delivery.
 */
#[CoversClass(thread_subscriptions::class)]
final class thread_subscriptions_test extends \advanced_testcase {
    /**
     * Two independent top-level conversations on the shared root track.
     * @return array
     */
    private function fixture(): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $activity = $gen->create_module('spatialforum', ['course' => $course->id]);
        $author = $gen->create_user();
        $follower = $gen->create_user();
        foreach ([$author, $follower] as $user) {
            $gen->enrol_user($user->id, $course->id, 'student');
        }
        $this->setUser($author);
        $one = forum::create_post($activity, $activity->roottrackid, $author->id, 'First conversation', 'First root', FORMAT_PLAIN);
        $two = forum::create_post($activity, $activity->roottrackid, $author->id, 'Second', 'Second root', FORMAT_PLAIN);
        return [$activity, $author, $follower, $one, $two];
    }

    /**
     * Replies in new tracks and ordinary same-track replies belong to the same root conversation.
     */
    public function test_thread_only_follow_includes_descendants_and_excludes_other_roots(): void {
        global $DB;
        [$activity, $author, $follower, $one, $two] = $this->fixture();
        $this->setUser($follower);
        notifications::set_mode($activity->id, notifications::IMMEDIATE, $one);
        $this->setUser($author);
        $track = $DB->get_record('spatialforum_tracks', ['id' => $activity->roottrackid]);
        $branch = forum::reply_in_new_track($activity, $track, $one, $author->id, 'Branch', 'Reply', 'Text', FORMAT_PLAIN);
        $reply = forum::create_post($activity, $branch, $author->id, 'More in branch', 'Text', FORMAT_PLAIN);
        $other = forum::create_post($activity, $activity->roottrackid, $author->id, 'Other', 'Text', FORMAT_PLAIN, $two);
        notifications::enqueue_post($reply);
        notifications::enqueue_post($other);
        $this->assertSame(1, $DB->count_records('spatialforum_deliveries'));
        $this->assertTrue($DB->record_exists('spatialforum_deliveries', ['postid' => $reply, 'userid' => $follower->id]));
        $sink = $this->redirectMessages();
        notifications::dispatch(time() + 1);
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertStringContainsString('because you follow this conversation', $message->fullmessage);
        $sink->close();
    }

    /**
     * A per-thread mute overrides whole-activity delivery, including already queued posts.
     */
    public function test_thread_mute_overrides_activity_without_cancelling_unrelated_work(): void {
        global $DB;
        [$activity, $author, $follower, $one, $two] = $this->fixture();
        $this->setUser($follower);
        notifications::set_mode($activity->id, notifications::IMMEDIATE);
        $this->setUser($author);
        $reply = forum::create_post($activity, $activity->roottrackid, $author->id, 'First reply', 'Text', FORMAT_PLAIN, $one);
        $other = forum::create_post($activity, $activity->roottrackid, $author->id, 'Second reply', 'Text', FORMAT_PLAIN, $two);
        notifications::enqueue_post($reply);
        notifications::enqueue_post($other);
        $this->assertSame(2, $DB->count_records('spatialforum_deliveries'));
        $this->setUser($follower);
        notifications::set_mode($activity->id, notifications::OFF, $one);
        $this->assertFalse($DB->record_exists('spatialforum_deliveries', ['postid' => $reply]));
        $this->assertTrue($DB->record_exists('spatialforum_deliveries', ['postid' => $other]));
        notifications::enqueue_post($reply);
        $this->assertFalse($DB->record_exists('spatialforum_deliveries', ['postid' => $reply]));
        notifications::set_mode($activity->id, thread_subscriptions::INHERIT, $one);
        notifications::enqueue_post($reply);
        $this->assertFalse(
            $DB->record_exists('spatialforum_deliveries', ['postid' => $reply]),
            'Returning to inherit does not replay history'
        );
    }

    /**
     * A daily thread override remains daily when the activity frequency changes, without duplicate rows.
     */
    public function test_thread_frequency_is_independent_and_deduplicated(): void {
        global $DB;
        [$activity, $author, $follower, $one] = $this->fixture();
        $this->setUser($follower);
        notifications::set_mode($activity->id, notifications::IMMEDIATE);
        notifications::set_mode($activity->id, notifications::DAILY, $one);
        $this->setUser($author);
        $reply = forum::create_post($activity, $activity->roottrackid, $author->id, 'Daily reply', 'Text', FORMAT_PLAIN, $one);
        notifications::enqueue_post($reply);
        notifications::enqueue_post($reply);
        $this->assertSame(1, $DB->count_records('spatialforum_deliveries'));
        $row = $DB->get_record('spatialforum_deliveries', [], '*', MUST_EXIST);
        $this->assertEquals(notifications::DAILY, $row->mode);
        $due = $row->due;
        $this->setUser($follower);
        notifications::set_mode($activity->id, notifications::DAILY);
        $this->assertEquals($due, $DB->get_field('spatialforum_deliveries', 'due', ['id' => $row->id]));
        notifications::set_mode($activity->id, notifications::IMMEDIATE);
        $this->assertEquals(notifications::DAILY, $DB->get_field('spatialforum_deliveries', 'mode', ['id' => $row->id]));
        $sink = $this->redirectMessages();
        notifications::dispatch((int) $due);
        $this->assertCount(1, $sink->get_messages());
        $sink->close();
    }

    /**
     * Activity-wide Off means all activity notifications, rather than leaving hidden thread follows enabled.
     */
    public function test_activity_off_clears_thread_preferences_and_pending_work(): void {
        global $DB;
        [$activity, $author, $follower, $one] = $this->fixture();
        $this->setUser($follower);
        notifications::set_mode($activity->id, notifications::IMMEDIATE, $one);
        $this->setUser($author);
        $reply = forum::create_post($activity, $activity->roottrackid, $author->id, 'Reply', 'Text', FORMAT_PLAIN, $one);
        notifications::enqueue_post($reply);
        $this->setUser($follower);
        notifications::set_mode($activity->id, notifications::OFF);
        $this->assertFalse($DB->record_exists('spatialforum_subscriptions', ['userid' => $follower->id]));
        $this->assertSame(0, $DB->count_records('spatialforum_deliveries'));
    }

    /**
     * Lost group access prevents subscribing again but still permits cancellation of an existing follow.
     */
    public function test_group_revocation_allows_only_unsubscribe(): void {
        global $DB;
        [$activity, $author, $follower, $one] = $this->fixture();
        $this->setUser($follower);
        notifications::set_mode($activity->id, notifications::IMMEDIATE, $one);
        $this->setAdminUser();
        $group = $this->getDataGenerator()->create_group(['courseid' => $activity->course]);
        groups_add_member($group, $author);
        $DB->set_field('spatialforum_posts', 'groupid', $group->id, ['id' => $one]);
        $DB->set_field('course_modules', 'groupmode', SEPARATEGROUPS, ['id' => $activity->cmid]);
        rebuild_course_cache($activity->course, true);
        $this->setUser($follower);
        try {
            notifications::set_mode($activity->id, notifications::DAILY, $one);
            $this->fail('A hidden conversation must not accept a new follow.');
        } catch (\moodle_exception $e) {
            $this->assertSame('groupaccessdenied', $e->errorcode);
        }
        notifications::set_mode($activity->id, notifications::OFF, $one);
        $this->assertEquals(0, $DB->get_field('spatialforum_subscriptions', 'mode', [
            'spatialforumid' => $activity->id, 'userid' => $follower->id, 'rootpostid' => $one,
        ]));
    }

    /**
     * Privacy erasure includes a preference even when the participant never authored an activity post.
     */
    public function test_privacy_erases_thread_preferences_and_delivery_work(): void {
        global $DB;
        [$activity, $author, $follower, $one] = $this->fixture();
        $this->setUser($follower);
        notifications::set_mode($activity->id, notifications::IMMEDIATE, $one);
        $this->setUser($author);
        $reply = forum::create_post($activity, $activity->roottrackid, $author->id, 'Reply', 'Text', FORMAT_PLAIN, $one);
        notifications::enqueue_post($reply);
        \mod_spatialforum\privacy\provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist(
            $follower,
            'mod_spatialforum',
            [\context_module::instance($activity->cmid)->id]
        ));
        $this->assertFalse($DB->record_exists('spatialforum_subscriptions', ['userid' => $follower->id]));
        $this->assertSame(0, $DB->count_records('spatialforum_deliveries'));
        $this->assertSame('Text', $DB->get_field('spatialforum_posts', 'message', ['id' => $reply]));
    }
}
