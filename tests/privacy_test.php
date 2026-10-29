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

/**

 * Privacy export includes the author's derived track and managed files.

 */
#[CoversClass(provider::class)]
final class privacy_test extends \core_privacy\tests\provider_testcase {
    public function test_export_includes_authored_track_and_embedded_files(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('spatialforum', ['course' => $course->id]);
        $author = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($author->id, $course->id, 'student');
        $root = $DB->get_record('spatialforum_tracks', ['id' => $activity->roottrackid]);
        $parent = forum::create_post($activity, $root->id, get_admin()->id, 'QA parent', 'QA parent text', FORMAT_HTML);
        $trackid = forum::reply_in_new_track(
            $activity,
            $root,
            $parent,
            $author->id,
            'QA authored track',
            'QA reply',
            '<img src="@@PLUGINFILE@@/qa.png" alt="QA image">',
            FORMAT_HTML
        );
        $post = $DB->get_record('spatialforum_posts', ['trackid' => $trackid]);
        $context = \context_module::instance($activity->cmid);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_spatialforum', 'filearea' => 'post',
            'itemid' => $post->id, 'filepath' => '/', 'filename' => 'qa.png', 'userid' => $author->id,
        ], 'QA privacy file');
        $this->export_context_data_for_user($author->id, $context, 'mod_spatialforum');
        $writer = \core_privacy\local\request\writer::with_context($context);
        $data = $writer->get_data([]);
        $this->assertCount(1, $data->posts);
        $this->assertSame('QA authored track', $data->tracks[0]->name);
        $this->assertStringNotContainsString('@@PLUGINFILE@@', $data->posts[0]->message);
        $this->assertStringContainsString('qa.png', $data->posts[0]->message);
        $this->assertCount(1, (array) $writer->get_files([get_string('posts', 'mod_spatialforum'), (string) $post->id]));
    }

    /**
     * Subscription-only users are discoverable, exported and erased along with queued deliveries.
     */
    public function test_subscription_metadata_has_a_complete_privacy_lifecycle(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('spatialforum', ['course' => $course->id]);
        $author = $this->getDataGenerator()->create_user();
        $subscriber = $this->getDataGenerator()->create_user();
        foreach ([$author, $subscriber] as $user) {
            $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        }
        $this->setUser($subscriber);
        \mod_spatialforum\local\notifications::set_mode($activity->id, \mod_spatialforum\local\notifications::DAILY);
        $context = \context_module::instance($activity->cmid);
        $this->assertContains((int) $context->id, array_map(
            'intval',
            provider::get_contexts_for_userid($subscriber->id)->get_contextids()
        ));
        $users = new \core_privacy\local\request\userlist($context, 'mod_spatialforum');
        provider::get_users_in_context($users);
        $this->assertContains((int) $subscriber->id, array_map('intval', $users->get_userids()));
        $this->setUser($author);
        $postid = forum::create_post($activity, $activity->roottrackid, $author->id, 'Private email QA', 'QA body', FORMAT_HTML);
        \mod_spatialforum\local\notifications::enqueue_post($postid);
        $this->export_context_data_for_user($subscriber->id, $context, 'mod_spatialforum');
        $data = \core_privacy\local\request\writer::with_context($context)->get_data([]);
        $this->assertCount(1, $data->subscriptions);
        $this->assertCount(1, $data->deliveries);
        $this->assertEquals($postid, $data->deliveries[0]->postid);
        provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist(
            $subscriber,
            'mod_spatialforum',
            [$context->id]
        ));
        $this->assertFalse($DB->record_exists('spatialforum_subscriptions', ['userid' => $subscriber->id]));
        $this->assertFalse($DB->record_exists('spatialforum_deliveries', ['userid' => $subscriber->id]));
        $this->assertSame('QA body', $DB->get_field('spatialforum_posts', 'message', ['id' => $postid]));
    }

    /**
     * Author erasure cancels notifications to other recipients before removing source content.
     */
    public function test_erasing_author_cancels_queued_post_notifications(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('spatialforum', ['course' => $course->id]);
        $author = $this->getDataGenerator()->create_user();
        $subscriber = $this->getDataGenerator()->create_user();
        foreach ([$author, $subscriber] as $user) {
            $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        }
        $this->setUser($subscriber);
        \mod_spatialforum\local\notifications::set_mode($activity->id, \mod_spatialforum\local\notifications::IMMEDIATE);
        $this->setUser($author);
        $id = forum::create_post($activity, $activity->roottrackid, $author->id, 'Erase me', 'QA body', FORMAT_HTML);
        \mod_spatialforum\local\notifications::enqueue_post($id);
        provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist(
            $author,
            'mod_spatialforum',
            [\context_module::instance($activity->cmid)->id]
        ));
        $this->assertSame(0, $DB->count_records('spatialforum_deliveries'));
        $this->assertSame(1, $DB->count_records('spatialforum_subscriptions'));
    }
}
