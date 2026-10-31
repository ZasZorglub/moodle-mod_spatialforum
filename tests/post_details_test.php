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
 * Large-view summaries and authenticated fragment details share the same access boundary.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_spatialforum;

use mod_spatialforum\local\forum;
use PHPUnit\Framework\Attributes\CoversFunction;

/**
 * No absent body counts as read, and no deferred request weakens group or context checks.
 */
#[CoversFunction('mod_spatialforum_output_fragment_post_detail')]
final class post_details_test extends \advanced_testcase {
    /**
     * Two enrolled participants with a real parent, reply and embedded file.
     * @param bool $separate
     * @return array
     */
    private function fixture(bool $separate = false): array {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $activity = $gen->create_module('spatialforum', ['course' => $course->id,
            'groupmode' => $separate ? SEPARATEGROUPS : NOGROUPS]);
        $author = $gen->create_user();
        $reader = $gen->create_user();
        foreach ([$author, $reader] as $user) {
            $gen->enrol_user($user->id, $course->id, 'student');
        }
        $groupid = -1;
        if ($separate) {
            foreach ([$author, $reader] as $user) {
                $group = $gen->create_group(['courseid' => $course->id]);
                groups_add_member($group, $user);
                if ($user->id == $author->id) {
                    $groupid = $group->id;
                }
            }
        }
        $this->setUser($author);
        $root = forum::create_post(
            $activity,
            $activity->roottrackid,
            $author->id,
            'Parent subject',
            '<p>Parent body</p><img src="@@PLUGINFILE@@/qa.png" alt="English QA image">',
            FORMAT_HTML,
            0,
            false,
            '[]',
            $groupid,
            false
        );
        $reply = forum::create_post(
            $activity,
            $activity->roottrackid,
            $author->id,
            'Reply subject',
            '<p>Reply body</p>',
            FORMAT_HTML,
            $root,
            true,
            '[]',
            $groupid,
            false
        );
        $cm = get_coursemodule_from_instance('spatialforum', $activity->id);
        $context = \context_module::instance($cm->id);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_spatialforum', 'filearea' => 'post',
            'itemid' => $root, 'filepath' => '/', 'filename' => 'qa.png',
        ], 'QA media');
        $this->setUser($reader);
        $PAGE = new \moodle_page();
        $PAGE->set_context($context);
        $PAGE->set_url('/mod/spatialforum/view.php', ['id' => $cm->id]);
        $track = $DB->get_record('spatialforum_tracks', ['id' => $activity->roottrackid]);
        return [$activity, $reader, $cm, $context, $track, $root, $reply];
    }

    /**
     * A large overview keeps all parent links and searchable own text, with no embedded body/media markup.
     */
    public function test_large_view_defers_details_and_full_page_link_opens_only_requested_body(): void {
        global $DB, $OUTPUT;
        [$activity, , $cm, $context, $track, $root, $reply] = $this->fixture();
        for ($i = 0; $i < 149; $i++) {
            $DB->insert_record('spatialforum_posts', (object) [
                'spatialforumid' => $activity->id, 'trackid' => $track->id, 'userid' => get_admin()->id,
                'subject' => 'QA preview ' . $i, 'message' => '<p>Searchable own text</p>',
                'messageformat' => FORMAT_HTML, 'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        $data = forum::export_view_data($activity, $cm, $track, $context, true, 0);
        $this->assertCount(151, $data['posts']);
        $this->assertCount(151, array_filter($data['posts'], static fn($post) => $post['deferred']));
        $this->assertSame([''], array_values(array_unique(array_column($data['posts'], 'message'))));
        $html = $OUTPUT->render_from_template('mod_spatialforum/main', $data);
        $this->assertStringNotContainsString('/pluginfile.php/', $html);
        $this->assertStringContainsString('Searchable own text', $html);
        $opened = forum::export_view_data($activity, $cm, $track, $context, true, $reply);
        $this->assertCount(150, array_filter($opened['posts'], static fn($post) => $post['deferred']));
        $selected = array_values(array_filter($opened['posts'], static fn($post) => $post['id'] == $reply))[0];
        $this->assertStringContainsString('Reply body', $selected['message']);
        $this->assertEquals($root, $selected['parentid']);
        $this->assertStringContainsString('Parent subject', $selected['relationship']);
    }

    /**
     * On-demand output retains the parent quote, authenticated media links and child navigation.
     */
    public function test_fragment_contains_authorised_content_and_child_links(): void {
        [$activity, , , $context, , $root, $reply] = $this->fixture();
        $html = \mod_spatialforum_output_fragment_post_detail(['context' => $context, 'postid' => $root]);
        $this->assertStringContainsString('Parent body', $html);
        $this->assertStringContainsString('/pluginfile.php/', $html);
        $this->assertStringContainsString('data-open-post="' . $reply . '"', $html);
        $html = \mod_spatialforum_output_fragment_post_detail(['context' => $context, 'postid' => $reply]);
        $this->assertStringContainsString('Reply body', $html);
        $this->assertStringContainsString('Parent body', $html);
        $this->assertStringNotContainsString('data-deferred', $html);
    }

    /**
     * The real fragment service has already started page output when it invokes the plugin callback.
     */
    public function test_core_fragment_service_discovers_callback_and_collects_requirements(): void {
        global $CFG;
        [, , , $context, , $root] = $this->fixture();
        require_once($CFG->libdir . '/external/externallib.php');
        $result = \core_external::get_fragment('mod_spatialforum', 'post_detail', $context->id, [
            ['name' => 'postid', 'value' => (string) $root],
        ]);
        $this->assertStringContainsString('Parent body', $result['html']);
        $this->assertArrayHasKey('javascript', $result);
    }

    /**
     * The transport rechecks course enrolment, including a user who opened the overview earlier.
     */
    public function test_core_fragment_service_denies_revoked_enrolment(): void {
        global $CFG, $DB, $PAGE;
        [$activity, $reader, , $context, , $root] = $this->fixture();
        require_once($CFG->libdir . '/external/externallib.php');
        $manual = $DB->get_record('enrol', ['courseid' => $activity->course, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->unenrol_user($manual, $reader->id);
        $this->setUser($reader);
        $PAGE = new \moodle_page();
        $this->expectException(\moodle_exception::class);
        \core_external::get_fragment('mod_spatialforum', 'post_detail', $context->id, [
            ['name' => 'postid', 'value' => (string) $root],
        ]);
    }

    /**
     * Knowing a post ID in another group must not expose its body or media.
     */
    public function test_fragment_denies_another_group(): void {
        [, , , $context, , $root] = $this->fixture(true);
        $this->expectExceptionMessage(get_string('groupaccessdenied', 'mod_spatialforum'));
        \mod_spatialforum_output_fragment_post_detail(['context' => $context, 'postid' => $root]);
    }

    /**
     * A post is bound to the validated module context, not a client-supplied activity ID.
     */
    public function test_fragment_denies_cross_context_post_ids(): void {
        global $PAGE;
        [$activity, $reader, , , , $root] = $this->fixture();
        $this->setAdminUser();
        $other = $this->getDataGenerator()->create_module('spatialforum', ['course' => $activity->course]);
        $context = \context_module::instance($other->cmid);
        $this->setUser($reader);
        $PAGE = new \moodle_page();
        $this->expectException(\dml_missing_record_exception::class);
        \mod_spatialforum_output_fragment_post_detail(['context' => $context, 'postid' => $root]);
    }

    /**
     * Moderation after loading the overview also revokes a later detail request.
     */
    public function test_fragment_denies_a_post_deleted_after_overview_loading(): void {
        global $PAGE;
        [, $reader, $cm, $context, , $root] = $this->fixture();
        $this->setAdminUser();
        \mod_spatialforum\local\post_lifecycle::moderate($cm, $root);
        $this->setUser($reader);
        $PAGE = new \moodle_page();
        $this->expectException(\dml_missing_record_exception::class);
        \mod_spatialforum_output_fragment_post_detail(['context' => $context, 'postid' => $root]);
    }
}
