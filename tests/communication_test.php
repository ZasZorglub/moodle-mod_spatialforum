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
 * Administrator policy, safe templates, scheduling and actual Message API integration.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_spatialforum;

use mod_spatialforum\local\communication;
use mod_spatialforum\local\forum;
use mod_spatialforum\local\notifications;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Site policy is enforced consistently by web administration, the queue and message rendering.
 */
#[CoversClass(communication::class)]
#[CoversClass(\mod_spatialforum\task\reconcile_notifications::class)]
final class communication_test extends \advanced_testcase {
    /**
     * Enrolled author and subscriber with a pending post.
     * @param int $mode
     * @return array
     */
    private function fixture(int $mode = notifications::IMMEDIATE): array {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['fullname' => 'Communication course']);
        $activity = $gen->create_module('spatialforum', ['course' => $course->id, 'name' => 'Communication forum']);
        $author = $gen->create_user(['firstname' => 'Example', 'lastname' => 'Author']);
        $recipient = $gen->create_user(['firstname' => 'Example', 'lastname' => 'Recipient', 'lang' => 'en']);
        foreach ([$author, $recipient] as $user) {
            $gen->enrol_user($user->id, $course->id, 'student');
        }
        $this->setUser($recipient);
        notifications::set_mode($activity->id, $mode);
        $this->setUser($author);
        $postid = forum::create_post(
            $activity,
            $activity->roottrackid,
            $author->id,
            'An original subject',
            '<p>An original contribution</p>',
            FORMAT_HTML
        );
        notifications::enqueue_post($postid);
        return [$activity, $author, $recipient, $postid];
    }

    /**
     * Save as site administrator through the public policy API.
     * @param array $overrides
     */
    private function policy(array $overrides): void {
        $this->setAdminUser();
        communication::save($overrides + communication::settings());
    }

    /**
     * Legacy hour and opt-in behaviour survive the configuration-only upgrade.
     */
    public function test_unsaved_defaults_preserve_existing_policy(): void {
        $this->resetAfterTest();
        set_config('digesthour', 8, 'mod_spatialforum');
        $settings = communication::settings();
        $this->assertSame(8, $settings['digesthour']);
        $this->assertSame(1, $settings['rootenabled']);
        $this->assertSame(1, $settings['replyenabled']);
        $this->assertSame(0, $settings['delayminutes']);
        $this->assertSame([], communication::validate($settings));
    }

    /**
     * Malformed expressions, unsafe subject lines and invalid times are rejected before saving.
     */
    public function test_invalid_templates_and_schedules_are_rejected(): void {
        $this->resetAfterTest();
        $defaults = communication::defaults();
        $cases = ['postbody' => '{{password}}', 'digestbody' => '{{posts}', 'postsubject' => "Header\nInjected",
            'itembody' => '', 'delayminutes' => '-1', 'digesthour' => 24, 'digestminute' => 60,
            'digesttimezone' => 'Invalid/Zone', 'dailyenabled' => 2];
        foreach ($cases as $key => $value) {
            $this->assertArrayHasKey($key, communication::validate([$key => $value] + $defaults));
        }
        $this->assertArrayHasKey('digestbody', communication::validate(['digestbody' => '{{posts}}{{posts}}'] + $defaults));
        $this->assertArrayHasKey('postbody', communication::validate(['postbody' => str_repeat('{{excerpt}}', 101)] + $defaults));
        $this->setAdminUser();
        $this->expectException(\moodle_exception::class);
        communication::save(['delayminutes' => '1.5'] + $defaults);
    }

    /**
     * A participant cannot change site policy, even with valid form data.
     */
    public function test_participant_cannot_save_policy(): void {
        [, , $recipient] = $this->fixture();
        $this->setUser($recipient);
        $this->expectException(\required_capability_exception::class);
        communication::save(communication::settings());
    }

    /**
     * A participant cannot use the test endpoint to send arbitrary messages.
     */
    public function test_participant_cannot_send_test(): void {
        [, , $recipient] = $this->fixture();
        $this->setUser($recipient);
        $this->expectException(\required_capability_exception::class);
        communication::send_test(communication::settings(), notifications::IMMEDIATE);
    }

    /**
     * Save logs configuration, queues reconciliation, and never sends posts by itself.
     */
    public function test_save_is_audited_and_does_not_send(): void {
        global $DB;
        $this->fixture();
        $sink = $this->redirectMessages();
        $this->policy(['delayminutes' => 15]);
        $this->assertSame(15, communication::settings()['delayminutes']);
        $this->assertTrue($DB->record_exists('config_log', ['plugin' => 'mod_spatialforum', 'name' => 'communication']));
        $this->assertTrue($DB->record_exists('task_adhoc', ['classname' => '\\mod_spatialforum\\task\\reconcile_notifications']));
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }

    /**
     * Templates are one-pass, escaped, bounded and retain indispensable authenticated links.
     */
    public function test_render_preserves_links_and_escapes_content(): void {
        $this->resetAfterTest();
        $settings = communication::defaults();
        $settings['postsubject'] = '{{subject}}';
        $settings['postbody'] = '{{excerpt}}';
        $post = ['author' => 'Author', 'subject' => "<b>Subject</b>\r\nInjected",
            'excerpt' => '<script>alert(1)</script> {{recipient}}', 'posturl' => 'https://example.org/private-post'];
        $message = communication::render(
            $settings,
            1,
            ['recipient' => 'Should not replace'],
            [$post],
            'https://example.org/preferences'
        );
        $this->assertSame('Subject Injected', $message['subject']);
        $this->assertStringContainsString('{{recipient}}', $message['text']);
        $this->assertStringNotContainsString('<script>', $message['html']);
        $this->assertStringContainsString('https://example.org/private-post', $message['text']);
        $this->assertStringContainsString('https://example.org/preferences', $message['text']);
    }

    /**
     * Actual notifications use saved templates and recipient/activity/course substitutions.
     */
    public function test_custom_text_reaches_message_api(): void {
        $this->fixture();
        $this->policy(['postsubject' => 'Update from {{activity}}',
            'postbody' => 'Hello {{recipient}}: {{author}} / {{subject}} / {{excerpt}} in {{course}}']);
        $sink = $this->redirectMessages();
        notifications::dispatch(time() + 1);
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame('Update from Communication forum', $messages[0]->subject);
        $this->assertStringContainsString(
            'Hello Example Recipient: Example Author / An original subject',
            $messages[0]->fullmessage
        );
        $this->assertStringContainsString('An original contribution in Communication course', $messages[0]->fullmessage);
        $sink->close();
    }

    /**
     * Disabled roots are excluded at both fan-out and dispatch; subscriptions remain opted in.
     */
    public function test_disabling_roots_cancels_queued_roots_and_prevents_new_fanout(): void {
        global $DB;
        [$activity, $author] = $this->fixture();
        $this->policy(['rootenabled' => 0]);
        $sink = $this->redirectMessages();
        notifications::dispatch(time() + 1);
        $this->assertCount(0, $sink->get_messages());
        $this->assertSame(0, $DB->count_records('spatialforum_deliveries'));
        $this->assertSame(1, $DB->count_records('spatialforum_subscriptions'));
        $this->setUser($author);
        $id = forum::create_post($activity, $activity->roottrackid, $author->id, 'Another root', 'Text', FORMAT_PLAIN);
        notifications::enqueue_post($id);
        $this->assertSame(0, $DB->count_records('spatialforum_deliveries'));
        $this->policy(['rootenabled' => 1]);
        notifications::dispatch(time() + 2);
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }

    /**
     * Reply switches and delivery-mode switches affect only their own categories.
     */
    public function test_independent_type_and_mode_switches(): void {
        $this->resetAfterTest();
        $root = (object) ['replytoid' => 0];
        $reply = (object) ['replytoid' => 42];
        $this->policy(['replyenabled' => 0, 'dailyenabled' => 0]);
        $this->assertTrue(communication::allows($root, notifications::IMMEDIATE));
        $this->assertFalse(communication::allows($reply, notifications::IMMEDIATE));
        $this->assertFalse(communication::allows($root, notifications::DAILY));
        $this->assertFalse(communication::allows($root, notifications::OFF));
    }

    /**
     * Publication delay is honoured even before asynchronous reconciliation completes.
     */
    public function test_changed_delay_prevents_premature_delivery_and_reconciles_queue(): void {
        global $DB;
        [, , , $postid] = $this->fixture();
        $this->policy(['delayminutes' => 30]);
        $sink = $this->redirectMessages();
        notifications::dispatch(time() + 1);
        $this->assertCount(0, $sink->get_messages());
        $post = $DB->get_record('spatialforum_posts', ['id' => $postid]);
        $row = $DB->get_record('spatialforum_deliveries', ['postid' => $postid]);
        $this->assertEquals($post->timecreated + 1800, $row->due);
        $this->policy(['delayminutes' => 5]);
        $task = new \mod_spatialforum\task\reconcile_notifications();
        $task->set_custom_data(['lastid' => 0]);
        $task->execute();
        $row = $DB->get_record('spatialforum_deliveries', ['postid' => $postid]);
        $this->assertEquals($post->timecreated + 300, $row->due);
        notifications::dispatch($row->due);
        $this->assertCount(1, $sink->get_messages());
        $sink->close();
    }

    /**
     * Reconciliation never retries uncertain sends or shortens an explicit-failure backoff.
     */
    public function test_reconciliation_preserves_backoff_and_uncertainty(): void {
        global $DB;
        [, , , $postid] = $this->fixture();
        $row = $DB->get_record('spatialforum_deliveries', ['postid' => $postid]);
        $row->attempts = 2;
        $row->due = time() + 3600;
        $DB->update_record('spatialforum_deliveries', $row);
        notifications::reconcile();
        $this->assertEquals($row->due, $DB->get_field('spatialforum_deliveries', 'due', ['id' => $row->id]));
        $DB->set_field('spatialforum_deliveries', 'status', notifications::UNCERTAIN, ['id' => $row->id]);
        $this->policy(['immediateenabled' => 0]);
        notifications::reconcile();
        $this->assertEquals(notifications::UNCERTAIN, $DB->get_field('spatialforum_deliveries', 'status', ['id' => $row->id]));
    }

    /**
     * Site timezone overrides recipient timezone; configured minutes are exact across DST.
     */
    public function test_digest_time_and_timezone_are_configurable(): void {
        $this->resetAfterTest();
        $this->policy(['digesthour' => 8, 'digestminute' => 35, 'digesttimezone' => 'site']);
        set_config('timezone', 'Europe/Copenhagen');
        $user = (object) ['timezone' => 'America/New_York'];
        $this->assertEquals(
            strtotime('2026-03-29 06:35:00 UTC'),
            notifications::due(notifications::DAILY, $user, strtotime('2026-03-28 20:00:00 UTC'))
        );
    }

    /**
     * Synthetic previews and self-test do not persist unsaved templates or read participant data.
     */
    public function test_test_message_is_only_for_current_admin_without_saving(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $settings = communication::settings();
        $settings['digestsubject'] = 'Unsaved {{count}} contributions';
        $sink = $this->redirectMessages();
        $id = communication::send_test($settings, notifications::DAILY);
        $this->assertNotFalse($id);
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals(get_admin()->id, $messages[0]->useridto);
        $this->assertSame('[TEST] Unsaved 2 contributions', $messages[0]->subject);
        $this->assertStringContainsString('synthetic', $messages[0]->fullmessage);
        $this->assertFalse(get_config('mod_spatialforum', 'communication'));
        $sink->close();
    }

    /**
     * Digest header and repeated item templates reach the actual Message API together.
     */
    public function test_custom_digest_uses_each_authorised_post(): void {
        [$activity, $author, , $first] = $this->fixture(notifications::DAILY);
        $this->setUser($author);
        $second = forum::create_post(
            $activity,
            $activity->roottrackid,
            $author->id,
            'A second subject',
            'A second contribution',
            FORMAT_PLAIN
        );
        notifications::enqueue_post($second);
        $this->policy(['digestsubject' => '{{course}} / {{count}}',
            'digestbody' => 'Summary for {{recipient}}: {{posts}}', 'itembody' => 'Item: {{subject}}']);
        $sink = $this->redirectMessages();
        notifications::dispatch(time() + DAYSECS);
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertSame('Communication course / 2', $messages[0]->subject);
        $this->assertStringContainsString('Item: An original subject', $messages[0]->fullmessage);
        $this->assertStringContainsString('Item: A second subject', $messages[0]->fullmessage);
        foreach ([$first, $second] as $id) {
            $this->assertStringContainsString('postid=' . $id, $messages[0]->fullmessage);
        }
        $sink->close();
    }

    /**
     * Large queues are reconciled in pages, with continuation and no replay of completed rows.
     */
    public function test_reconciliation_pages_large_pending_queues(): void {
        global $DB;
        [, , , $postid] = $this->fixture();
        $template = $DB->get_record('spatialforum_deliveries', ['postid' => $postid]);
        $post = $DB->get_record('spatialforum_posts', ['id' => $postid]);
        unset($post->id, $template->id);
        for ($i = 0; $i < 250; $i++) {
            $template->postid = $DB->insert_record('spatialforum_posts', $post);
            $DB->insert_record('spatialforum_deliveries', $template);
        }
        $this->policy(['delayminutes' => 20]);
        $task = new \mod_spatialforum\task\reconcile_notifications();
        $task->set_custom_data(['lastid' => 0]);
        $task->execute();
        $due = $post->timecreated + 1200;
        $this->assertSame(250, $DB->count_records('spatialforum_deliveries', ['due' => $due]));
        $tasks = $DB->get_records('task_adhoc', ['classname' => '\\mod_spatialforum\\task\\reconcile_notifications']);
        $continuations = array_filter($tasks, fn($queued) => json_decode($queued->customdata)->lastid > 0);
        $this->assertCount(1, $continuations);
        $task->set_custom_data(json_decode(reset($continuations)->customdata));
        $task->execute();
        $this->assertSame(251, $DB->count_records('spatialforum_deliveries', ['due' => $due]));
    }
}
