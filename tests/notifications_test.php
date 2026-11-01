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
use mod_spatialforum\local\notifications;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/failing_notifications.php');
require_once(__DIR__ . '/fixtures/uncertain_notifications.php');

/**
 * The source post/outbox, audience checks and Moodle Message API form one lifecycle.
 */
#[CoversClass(notifications::class)]
#[CoversClass(\mod_spatialforum\task\fanout_post::class)]
#[CoversClass(\mod_spatialforum\task\send_notifications::class)]
final class notifications_test extends \advanced_testcase {
    /**
     * Test that administrator policy controls defaults and forced opt-out behavior.
     */
    public function test_admin_subscription_policy(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        set_config('subscriptionpolicy', 'forced', 'mod_spatialforum');
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $activity = $gen->create_module('spatialforum', ['course' => $course->id]);
        $participant = $gen->create_user();
        $gen->enrol_user($participant->id, $course->id, 'student');
        $this->setUser($participant);
        $this->assertSame(notifications::IMMEDIATE, notifications::default_mode());
        notifications::ensure_activity_subscription($activity->id, $USER->id);
        $this->assertTrue($DB->record_exists('spatialforum_subscriptions', ['spatialforumid' => $activity->id,
            'userid' => $USER->id, 'rootpostid' => 0]));
        $this->expectException(\moodle_exception::class);
        notifications::set_mode($activity->id, notifications::OFF);
    }
    /**
     * An author and two independent recipients in one course.
     *
     * @param int $groupmode
     * @return array
     */
    private function fixture(int $groupmode = NOGROUPS): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $activity = $gen->create_module('spatialforum', ['course' => $course->id, 'groupmode' => $groupmode]);
        $author = $gen->create_user(['firstname' => 'QA', 'lastname' => 'Author']);
        $alice = $gen->create_user(['firstname' => 'QA', 'lastname' => 'Alice']);
        $bob = $gen->create_user(['firstname' => 'QA', 'lastname' => 'Bob']);
        foreach ([$author, $alice, $bob] as $user) {
            $gen->enrol_user($user->id, $course->id, 'student');
        }
        return [$course, $activity, $author, $alice, $bob];
    }

    /**
     * Publish through the same domain API as the web form, then execute the fan-out task.
     *
     * @param \stdClass $activity
     * @param \stdClass $author
     * @param int $groupid
     * @return int
     */
    private function publish(\stdClass $activity, \stdClass $author, int $groupid = -1): int {
        $this->setUser($author);
        $id = forum::create_post(
            $activity,
            $activity->roottrackid,
            $author->id,
            'QA notification subject',
            '<p>QA notification body</p>',
            FORMAT_HTML,
            0,
            false,
            '[]',
            $groupid
        );
        $task = new \mod_spatialforum\task\fanout_post();
        $task->set_custom_data(['postid' => $id]);
        $task->execute();
        return $id;
    }

    /**
     * Delivery uses Moodle messages, authenticating links, excluding authors and older posts.
     */
    public function test_opt_in_new_posts_only_and_idempotent_delivery(): void {
        global $DB;
        [, $activity, $author, $alice] = $this->fixture();
        $old = $this->publish($activity, $author);
        $this->setUser($alice);
        notifications::set_mode($activity->id, notifications::IMMEDIATE);
        $this->setUser($author);
        notifications::set_mode($activity->id, notifications::IMMEDIATE);
        notifications::enqueue_post($old);
        $this->assertSame(0, $DB->count_records('spatialforum_deliveries'));
        $id = $this->publish($activity, $author);
        notifications::enqueue_post($id);
        $this->assertSame(1, $DB->count_records('spatialforum_deliveries'));
        $sink = $this->redirectMessages();
        $result = notifications::dispatch(time() + 1);
        $messages = $sink->get_messages();
        $this->assertSame(1, $result['accepted']);
        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertEquals($alice->id, $message->useridto);
        $this->assertStringContainsString('QA notification body', $message->fullmessage);
        $this->assertStringContainsString('postid=' . $id, $message->fullmessage);
        $this->assertStringContainsString('subscription.php', $message->fullmessage);
        $this->assertSame(0, $DB->count_records('spatialforum_read'));
        notifications::enqueue_post($id);
        notifications::dispatch(time() + 2);
        $this->assertCount(1, $sink->get_messages());
        $sink->close();
    }

    /**
     * Due daily posts form one message; not-yet-due posts remain queued.
     */
    public function test_digest_batches_only_due_posts(): void {
        global $DB;
        [, $activity, $author, $alice] = $this->fixture();
        $this->setUser($alice);
        notifications::set_mode($activity->id, notifications::DAILY);
        $one = $this->publish($activity, $author);
        $two = $this->publish($activity, $author);
        $rows = $DB->get_records('spatialforum_deliveries', [], 'id');
        $due = (int) reset($rows)->due;
        $sink = $this->redirectMessages();
        notifications::dispatch($due - 1);
        $this->assertCount(0, $sink->get_messages());
        notifications::dispatch($due);
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertStringContainsString('postid=' . $one, $message->fullmessage);
        $this->assertStringContainsString('postid=' . $two, $message->fullmessage);
        $this->assertSame(2, $DB->count_records('spatialforum_deliveries', ['status' => notifications::ACCEPTED]));
        $sink->close();
    }

    /**
     * Daily cutoffs follow recipient local time across both DST transitions.
     */
    public function test_daily_cutoff_handles_recipient_timezone_and_dst(): void {
        $this->resetAfterTest();
        $user = (object) ['timezone' => 'Europe/Copenhagen'];
        $this->assertEquals(
            strtotime('2026-03-29 15:00:00 UTC'),
            notifications::due(notifications::DAILY, $user, strtotime('2026-03-28 16:30:00 UTC'))
        );
        $this->assertEquals(
            strtotime('2026-10-25 16:00:00 UTC'),
            notifications::due(notifications::DAILY, $user, strtotime('2026-10-24 16:30:00 UTC'))
        );
        set_config('digesthour', 8, 'mod_spatialforum');
        $this->assertEquals(
            strtotime('2026-10-29 07:00:00 UTC'),
            notifications::due(notifications::DAILY, $user, strtotime('2026-10-29 06:00:00 UTC'))
        );
    }

    /**
     * Revoked group access is rechecked at delivery, not just at fan-out.
     */
    public function test_group_membership_revocation_prevents_delivery(): void {
        global $DB;
        [$course, $activity, $author, $alice, $bob] = $this->fixture(SEPARATEGROUPS);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        foreach ([$author, $alice] as $user) {
            $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
        }
        foreach ([$alice, $bob] as $user) {
            $this->setUser($user);
            notifications::set_mode($activity->id, notifications::IMMEDIATE);
        }
        $this->publish($activity, $author, $group->id);
        $this->assertSame(1, $DB->count_records('spatialforum_deliveries'));
        groups_remove_member($group->id, $alice->id);
        $sink = $this->redirectMessages();
        notifications::dispatch(time() + 1);
        $this->assertCount(0, $sink->get_messages());
        $this->assertSame(0, $DB->count_records('spatialforum_deliveries'));
        $sink->close();
    }

    /**
     * Unsubscribe cancels pending work; resubscribe cannot replay an older post.
     */
    public function test_unsubscribe_and_resubscribe_do_not_replay_history(): void {
        global $DB;
        [, $activity, $author, $alice] = $this->fixture();
        $this->setUser($alice);
        notifications::set_mode($activity->id, notifications::IMMEDIATE);
        $id = $this->publish($activity, $author);
        $this->setUser($alice);
        notifications::set_mode($activity->id, notifications::OFF);
        $this->assertSame(0, $DB->count_records('spatialforum_deliveries'));
        notifications::set_mode($activity->id, notifications::DAILY);
        notifications::enqueue_post($id);
        $this->assertSame(0, $DB->count_records('spatialforum_deliveries'));
    }

    /**
     * Revoked enrolment still allows an authenticated user to cancel their own preference.
     */
    public function test_unenrolment_revokes_delivery_and_allows_unsubscribe(): void {
        global $DB;
        [$course, $activity, $author, $alice] = $this->fixture();
        $this->setUser($alice);
        notifications::set_mode($activity->id, notifications::IMMEDIATE);
        $this->publish($activity, $author);
        $manual = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->unenrol_user($manual, $alice->id);
        $sink = $this->redirectMessages();
        notifications::dispatch(time() + 1);
        $this->assertCount(0, $sink->get_messages());
        $this->setUser($alice);
        notifications::set_mode($activity->id, notifications::OFF);
        $this->assertFalse($DB->record_exists('spatialforum_subscriptions', ['userid' => $alice->id]));
        $sink->close();
    }

    /**
     * Suspension or a hidden activity prevents delivery even after a row was queued.
     */
    public function test_suspension_and_activity_visibility_are_rechecked(): void {
        global $DB;
        [, $activity, $author, $alice, $bob] = $this->fixture();
        foreach ([$alice, $bob] as $user) {
            $this->setUser($user);
            notifications::set_mode($activity->id, notifications::IMMEDIATE);
        }
        $this->publish($activity, $author);
        $DB->set_field('user', 'suspended', 1, ['id' => $alice->id]);
        $sink = $this->redirectMessages();
        notifications::dispatch(time() + 1);
        $this->assertCount(1, $sink->get_messages());
        $this->publish($activity, $author);
        $cm = get_coursemodule_from_instance('spatialforum', $activity->id);
        set_coursemodule_visible($cm->id, 0);
        rebuild_course_cache($cm->course, true);
        notifications::dispatch(time() + 1);
        $this->assertCount(1, $sink->get_messages());
        $sink->close();
    }

    /**
     * An explicit send failure retries with backoff and a bounded attempt count.
     */
    public function test_explicit_failure_retries_and_stops_after_five_attempts(): void {
        global $DB;
        [, $activity, $author, $alice] = $this->fixture();
        $this->setUser($alice);
        notifications::set_mode($activity->id, notifications::IMMEDIATE);
        $this->publish($activity, $author);
        $clock = time() + 1;
        for ($i = 0; $i < 5; $i++) {
            failing_notifications::dispatch($clock);
            $row = $DB->get_record('spatialforum_deliveries', [], '*', MUST_EXIST);
            $this->assertGreaterThan($clock, (int) $row->due);
            $clock = (int) $row->due;
        }
        $this->assertEquals(notifications::FAILED, $row->status);
        $this->assertEquals(5, $row->attempts);
    }

    /**
     * Ambiguous exceptions require operator review and cannot duplicate automatically.
     */
    public function test_uncertain_delivery_is_not_automatically_retried(): void {
        global $DB;
        [, $activity, $author, $alice] = $this->fixture();
        $this->setUser($alice);
        notifications::set_mode($activity->id, notifications::IMMEDIATE);
        $this->publish($activity, $author);
        try {
            uncertain_notifications::dispatch(time() + 1);
            $this->fail('Expected a transport exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('QA transport interrupted', $e->getMessage());
        }
        $this->assertDebuggingCalled();
        $row = $DB->get_record('spatialforum_deliveries', [], '*', MUST_EXIST);
        $this->assertEquals(notifications::UNCERTAIN, $row->status);
        $sink = $this->redirectMessages();
        notifications::dispatch(time() + DAYSECS);
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }

    /**
     * New publication and its fan-out task roll back together; imports can suppress fan-out.
     */
    public function test_post_transaction_and_notification_suppression(): void {
        global $DB;
        $this->preventResetByRollback();
        [, $activity, $author] = $this->fixture();
        $this->setUser($author);
        $transaction = $DB->start_delegated_transaction();
        $id = forum::create_post($activity, $activity->roottrackid, $author->id, 'Rollback', 'Body', FORMAT_HTML);
        $this->assertTrue($DB->record_exists('task_adhoc', ['component' => 'mod_spatialforum']));
        try {
            $transaction->rollback(new \RuntimeException('QA rollback'));
        } catch (\RuntimeException $e) {
            $this->assertSame('QA rollback', $e->getMessage());
        }
        $this->assertFalse($DB->record_exists('spatialforum_posts', ['id' => $id]));
        $this->assertFalse($DB->record_exists('task_adhoc', ['component' => 'mod_spatialforum']));
        forum::create_post(
            $activity,
            $activity->roottrackid,
            $author->id,
            'Silent import',
            'Imported body',
            FORMAT_HTML,
            0,
            false,
            '[]',
            -1,
            false
        );
        $this->assertFalse($DB->record_exists('task_adhoc', ['component' => 'mod_spatialforum']));
    }

    /**
     * Guests cannot opt in, and thirty-day-old delivery metadata are removed.
     */
    public function test_guest_denial_and_delivery_retention(): void {
        global $DB;
        [, $activity, $author, $alice] = $this->fixture();
        $this->setUser($alice);
        notifications::set_mode($activity->id, notifications::IMMEDIATE);
        $this->publish($activity, $author);
        $sink = $this->redirectMessages();
        notifications::dispatch(time() + notifications::RETENTION + 1);
        $this->assertSame(0, $DB->count_records('spatialforum_deliveries'));
        $this->assertCount(0, $sink->get_messages());
        $this->setGuestUser();
        $this->expectException(\moodle_exception::class);
        notifications::set_mode($activity->id, notifications::IMMEDIATE);
    }

    /**
     * Changing mode reschedules pending posts without changing the subscription's history boundary.
     */
    public function test_mode_changes_reschedule_pending_work_without_replay(): void {
        global $DB;
        [, $activity, $author, $alice] = $this->fixture();
        $this->setUser($alice);
        notifications::set_mode($activity->id, notifications::IMMEDIATE);
        $this->publish($activity, $author);
        $this->setUser($alice);
        notifications::set_mode($activity->id, notifications::DAILY);
        $row = $DB->get_record('spatialforum_deliveries', [], '*', MUST_EXIST);
        $this->assertEquals(notifications::DAILY, $row->mode);
        $this->assertGreaterThan(time(), (int) $row->due);
        notifications::set_mode($activity->id, notifications::IMMEDIATE);
        $sink = $this->redirectMessages();
        notifications::dispatch(time() + 1);
        $this->assertCount(1, $sink->get_messages());
        notifications::set_mode($activity->id, notifications::DAILY);
        notifications::dispatch(time() + DAYSECS);
        $this->assertCount(1, $sink->get_messages());
        $sink->close();
    }

    /**
     * Tombstoned posts are cancelled and activity deletion removes both new data tables.
     */
    public function test_deleted_content_and_activity_cleanup(): void {
        global $DB;
        [, $activity, $author, $alice] = $this->fixture();
        $this->setUser($alice);
        notifications::set_mode($activity->id, notifications::IMMEDIATE);
        $id = $this->publish($activity, $author);
        $DB->set_field('spatialforum_posts', 'deleted', 1, ['id' => $id]);
        $sink = $this->redirectMessages();
        notifications::dispatch(time() + 1);
        $this->assertCount(0, $sink->get_messages());
        $this->assertSame(0, $DB->count_records('spatialforum_deliveries'));
        $this->publish($activity, $author);
        $this->setAdminUser();
        \spatialforum_delete_instance($activity->id);
        $this->assertSame(0, $DB->count_records('spatialforum_subscriptions'));
        $this->assertSame(0, $DB->count_records('spatialforum_deliveries'));
        $sink->close();
    }


    /**
     * Hiding a course revokes delivery even if its individual activity is visible.
     */
    public function test_hidden_course_prevents_delivery(): void {
        global $DB;
        [$course, $activity, $author, $alice] = $this->fixture();
        $this->setUser($alice);
        notifications::set_mode($activity->id, notifications::IMMEDIATE);
        $this->publish($activity, $author);
        $DB->set_field('course', 'visible', 0, ['id' => $course->id]);
        $sink = $this->redirectMessages();
        notifications::dispatch(time() + 1);
        $this->assertCount(0, $sink->get_messages());
        $this->assertSame(0, $DB->count_records('spatialforum_deliveries'));
        $sink->close();
    }
}
