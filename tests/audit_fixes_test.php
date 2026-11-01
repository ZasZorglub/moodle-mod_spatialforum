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

use mod_spatialforum\form\post_form;
use mod_spatialforum\local\excerpts;
use mod_spatialforum\local\forum;
use mod_spatialforum\local\media;
use mod_spatialforum\privacy\provider;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**

 * Regressions from the 0.5.2 audit: media lifecycle, validation, erasure and bounded export.

 */
#[CoversClass(media::class)]
#[CoversClass(post_form::class)]
#[CoversClass(provider::class)]
#[CoversClass(forum::class)]
final class audit_fixes_test extends \advanced_testcase {
    /**
     * Create a course, participant, activity and first post.
     */
    private function fixture(): array {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('spatialforum', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('spatialforum', $activity->id);
        $context = \context_module::instance($cm->id);
        $PAGE->set_context($context);
        $PAGE->set_url('/mod/spatialforum/view.php', ['id' => $cm->id]);
        return [$course, $activity, $cm, $context];
    }

    /**
     * Create an uploaded file in the current user draft area.
     */
    private function draft(string $name, string $contents, int $draftid = 0): int {
        global $USER, $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $draftid = $draftid ?: file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id, 'component' => 'user',
            'filearea' => 'draft', 'itemid' => $draftid, 'filepath' => '/', 'filename' => $name,
        ], $contents);
        return $draftid;
    }

    public function test_visible_content_and_unicode_title_validation(): void {
        [$course, $activity, $cm, $context] = $this->fixture();
        $form = new post_form(null, ['cmid' => $cm->id, 'action' => 'reply',
            'trackid' => $activity->roottrackid, 'originpostid' => 0, 'context' => $context]);
        foreach (['<p>&nbsp;</p>', '<p>&#160;</p>', "<p>\u{200B}</p>", '<p><br></p>'] as $blank) {
            $errors = $form->validation(['subject' => 'QA empty content',
                'message_editor' => ['text' => $blank, 'format' => FORMAT_HTML]], []);
            $this->assertArrayHasKey('message_editor', $errors);
        }
        foreach (
            ['<img src="https://example.test/image.png" alt="QA image">',
                '<audio controls src="https://example.test/sample.mp3"></audio>',
                '<video controls><source src="https://example.test/sample.mp4"></video>'] as $html
        ) {
            $this->assertSame([], $form->validation(['subject' => 'QA media without caption',
                'message_editor' => ['text' => $html, 'format' => FORMAT_HTML]], []));
        }
        $data = ['subject' => str_repeat('ø', 255), 'message_editor' => ['text' => 'QA text', 'format' => FORMAT_HTML]];
        $this->assertSame([], $form->validation($data, []));
        $data['subject'] .= 'ø';
        $this->assertArrayHasKey('subject', $form->validation($data, []));
    }

    public function test_domain_rejects_long_subject_before_database_insert(): void {
        [$course, $activity] = $this->fixture();
        $this->expectException(\moodle_exception::class);
        forum::create_post($activity, $activity->roottrackid, get_admin()->id, str_repeat('x', 256), 'QA', FORMAT_HTML);
    }

    public function test_draft_media_are_saved_rendered_and_removed_with_activity(): void {
        global $CFG, $DB, $USER;
        [$course, $activity, $cm, $context] = $this->fixture();
        $draftid = $this->draft('qa.png', 'QA image content');
        $this->draft('qa.mp3', 'QA audio content', $draftid);
        $this->draft('qa.mp4', 'QA video content', $draftid);
        $usercontextid = \context_user::instance($USER->id)->id;
        $base = $CFG->wwwroot . '/draftfile.php/' . $usercontextid . '/user/draft/' . $draftid;
        $html = '<img src="' . $base . '/qa.png" alt="QA image">' .
            '<audio controls src="' . $base . '/qa.mp3"></audio><video controls src="' . $base . '/qa.mp4"></video>';
        $postid = forum::create_post($activity, $activity->roottrackid, $USER->id, 'QA media only', $html, FORMAT_HTML);
        media::save($postid, ['text' => $html, 'format' => FORMAT_HTML, 'itemid' => $draftid], $context);
        $post = $DB->get_record('spatialforum_posts', ['id' => $postid]);
        $this->assertStringNotContainsString('/draftfile.php/', $post->message);
        $this->assertStringContainsString('@@PLUGINFILE@@/qa.png', $post->message);
        $files = get_file_storage()->get_area_files($context->id, 'mod_spatialforum', 'post', $postid, 'id', false);
        $this->assertCount(3, $files);
        $rendered = excerpts::render($post, $context, $cm->id);
        $this->assertStringContainsString('/mod_spatialforum/post/' . $postid . '/qa.mp3', $rendered);
        $this->assertStringContainsString('/mod_spatialforum/post/' . $postid . '/qa.mp4', $rendered);
        $this->assertStringNotContainsString('@@PLUGINFILE@@', $rendered);
        spatialforum_delete_instance($activity->id);
        $this->assertCount(0, get_file_storage()->get_area_files($context->id, 'mod_spatialforum', 'post'));
    }

    public function test_draft_limits_are_enforced_on_server(): void {
        [$course, $activity, $cm, $context] = $this->fixture();
        $options = media::editor_options($context);
        $this->assertTrue($options['autosave']);
        $bad = $this->draft('qa.php', '<?php echo "QA";');
        $this->assertNotNull(media::validate_draft($bad, $options));
        $draftid = $this->draft('qa.png', str_repeat('x', 12));
        $options['maxbytes'] = 10;
        $this->assertNotNull(media::validate_draft($draftid, $options));
        $options['maxbytes'] = 100;
        $options['areamaxbytes'] = 20;
        $this->assertNull(media::validate_draft($draftid, $options));
        $this->draft('other.png', str_repeat('y', 12), $draftid);
        $this->assertNotNull(media::validate_draft($draftid, $options));
        $options['areamaxbytes'] = 100;
        $options['maxfiles'] = 1;
        $this->assertNotNull(media::validate_draft($draftid, $options));
        $options['maxfiles'] = 10;
        $options['accepted_types'] = ['.mp3'];
        $this->assertNotNull(media::validate_draft($draftid, $options));
    }

    public function test_file_size_setting_respects_course_and_site_limits(): void {
        global $CFG, $COURSE;
        [$course, $activity, $cm, $context] = $this->fixture();
        $CFG->maxbytes = 1024;
        $COURSE->maxbytes = 512;
        set_config('maxbytes', 0, 'mod_spatialforum');
        $this->assertEquals(512, media::editor_options($context)['maxbytes']);
        set_config('maxbytes', 256, 'mod_spatialforum');
        $this->assertEquals(256, media::editor_options($context)['maxbytes']);
    }

    public function test_privacy_erases_derived_track_names_and_post_files_only_for_author(): void {
        global $DB;
        [$course, $activity, $cm, $context] = $this->fixture();
        $author = $this->getDataGenerator()->create_user();
        $root = $DB->get_record('spatialforum_tracks', ['id' => $activity->roottrackid]);
        $parent = forum::create_post($activity, $root->id, get_admin()->id, 'QA parent', 'QA text', FORMAT_HTML);
        $trackid = forum::reply_in_new_track(
            $activity,
            $root,
            $parent,
            $author->id,
            'QA author private title',
            'QA author private title',
            'QA authored text',
            FORMAT_HTML
        );
        $post = $DB->get_record('spatialforum_posts', ['trackid' => $trackid]);
        get_file_storage()->create_file_from_string(['contextid' => $context->id, 'component' => 'mod_spatialforum',
            'filearea' => 'post', 'itemid' => $post->id, 'filepath' => '/', 'filename' => 'qa.png'], 'QA');
        provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist(
            $author,
            'mod_spatialforum',
            [$context->id]
        ));
        $this->assertSame(
            get_string('anonymoustrack', 'mod_spatialforum'),
            $DB->get_field('spatialforum_tracks', 'name', ['id' => $trackid])
        );
        $this->assertSame($activity->name, $DB->get_field('spatialforum_tracks', 'name', ['id' => $root->id]));
        $this->assertCount(0, get_file_storage()->get_area_files($context->id, 'mod_spatialforum', 'post', $post->id));
        $this->assertSame('QA parent', $DB->get_field('spatialforum_posts', 'subject', ['id' => $parent]));
        $this->assertEquals(0, $DB->get_field('spatialforum_posts', 'userid', ['id' => $post->id]));
    }

    public function test_whole_context_erasure_removes_names_and_files(): void {
        global $DB;
        [$course, $activity, $cm, $context] = $this->fixture();
        $root = $DB->get_record('spatialforum_tracks', ['id' => $activity->roottrackid]);
        $parent = forum::create_post($activity, $root->id, get_admin()->id, 'QA parent', 'QA', FORMAT_HTML);
        $child = forum::reply_in_new_track(
            $activity,
            $root,
            $parent,
            get_admin()->id,
            'QA private name',
            'QA reply',
            'QA reply',
            FORMAT_HTML
        );
        get_file_storage()->create_file_from_string(['contextid' => $context->id, 'component' => 'mod_spatialforum',
            'filearea' => 'post', 'itemid' => $parent, 'filepath' => '/', 'filename' => 'qa.png'], 'QA');
        provider::delete_data_for_all_users_in_context($context);
        $this->assertSame(0, $DB->count_records('spatialforum_posts', ['spatialforumid' => $activity->id]));
        $this->assertSame(
            get_string('anonymoustrack', 'mod_spatialforum'),
            $DB->get_field('spatialforum_tracks', 'name', ['id' => $child])
        );
        $this->assertCount(0, get_file_storage()->get_area_files($context->id, 'mod_spatialforum', 'post'));
    }

    public function test_quote_export_database_reads_do_not_grow_per_post(): void {
        global $DB;
        [$course, $activity, $cm, $context] = $this->fixture();
        $root = $DB->get_record('spatialforum_tracks', ['id' => $activity->roottrackid]);
        $parent = forum::create_post($activity, $root->id, get_admin()->id, 'QA source', 'QA source text', FORMAT_HTML);
        for ($i = 0; $i < 100; $i++) {
            forum::create_post(
                $activity,
                $root->id,
                get_admin()->id,
                'QA quote ' . $i,
                'QA body',
                FORMAT_HTML,
                $parent,
                true,
                json_encode([['quote' => 'QA source', 'comment' => 'QA comment']])
            );
        }
        forum::export_view_data($activity, $cm, $root, $context, true);
        $before = $DB->perf_get_reads();
        $data = forum::export_view_data($activity, $cm, $root, $context, true);
        $this->assertCount(101, $data['posts']);
        $this->assertLessThan(25, $DB->perf_get_reads() - $before, 'Quote and parent authors must be batched.');
        $this->assertStringContainsString('QA comment', $data['posts'][1]['message']);
    }

    public function test_pluginfile_rejects_wrong_context_cross_activity_and_deleted_posts(): void {
        global $DB;
        [$course, $activity, $cm, $context] = $this->fixture();
        $other = $this->getDataGenerator()->create_module('spatialforum', ['course' => $course->id]);
        $postid = forum::create_post($other, $other->roottrackid, get_admin()->id, 'QA other activity', 'QA', FORMAT_HTML);
        $this->assertFalse(spatialforum_pluginfile($course, $cm, $context, 'post', [$postid, 'qa.png'], false));
        $this->assertFalse(spatialforum_pluginfile(
            $course,
            $cm,
            \context_module::instance($other->cmid),
            'post',
            [$postid, 'qa.png'],
            false
        ));
        $ownid = forum::create_post($activity, $activity->roottrackid, get_admin()->id, 'QA deleted', 'QA', FORMAT_HTML);
        $DB->set_field('spatialforum_posts', 'deleted', 1, ['id' => $ownid]);
        $this->assertFalse(spatialforum_pluginfile($course, $cm, $context, 'post', [$ownid, 'qa.png'], false));
    }

    public function test_existing_file_is_not_served_without_view_capability(): void {
        global $DB;
        [$course, $activity, $cm, $context] = $this->fixture();
        $postid = forum::create_post($activity, $activity->roottrackid, get_admin()->id, 'QA access', 'QA', FORMAT_HTML);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_spatialforum', 'filearea' => 'post',
            'itemid' => $postid, 'filepath' => '/', 'filename' => 'qa.png',
        ], 'QA private file');
        $student = $this->getDataGenerator()->create_user();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $roleid);
        assign_capability('mod/spatialforum:view', CAP_PROHIBIT, $roleid, $context->id, true);
        $this->setUser($student);
        $this->assertFalse(has_capability('mod/spatialforum:view', $context));
        $this->assertFalse(spatialforum_pluginfile($course, $cm, $context, 'post', [$postid, 'qa.png'], false));
    }
}
