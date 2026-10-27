<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_spatialforum;

use mod_spatialforum\local\forum;
use mod_spatialforum\privacy\provider;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/** Privacy export includes the author's derived track and managed files. */
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
        $trackid = forum::reply_in_new_track($activity, $root, $parent, $author->id,
            'QA authored track', 'QA reply', '<img src="@@PLUGINFILE@@/qa.png" alt="QA image">', FORMAT_HTML);
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
}
