<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_spatialforum;

use mod_spatialforum\local\forum;
use mod_spatialforum\privacy\provider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/phpunit/classes/restore_date_testcase.php');

/** Backup/restore preserves reply, track and like relationships.
 * @covers \backup_spatialforum_activity_structure_step
 * @covers \restore_spatialforum_activity_structure_step
 */
final class backup_test extends \restore_date_testcase {
    public function test_restore_remaps_quotes_likes_and_privacy_tombstones(): void {
        global $DB;

        [$course, $activity] = $this->create_course_and_module('spatialforum', ['enablelikes' => 0]);
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($other->id, $course->id, 'student');
        $root = $DB->get_record('spatialforum_tracks', ['id' => $activity->roottrackid]);
        $first = forum::create_post($activity, $root->id, $user->id, 'Original', 'Parent', FORMAT_HTML);
        $reply = forum::create_post($activity, $root->id, $other->id, 'Reply', 'Answer', FORMAT_HTML, $first);
        $branchid = forum::create_track($activity, $root, $reply, 'Child track');
        forum::create_post($activity, $branchid, $other->id, 'Child', 'Branch body', FORMAT_HTML);
        // Likes remain in backups even while the activity hides them.
        $DB->insert_record('spatialforum_likes', (object) [
            'userid' => $other->id, 'postid' => $reply, 'timecreated' => time(),
        ]);
        $context = \context_module::instance($activity->cmid);
        provider::delete_data_for_user(new \core_privacy\local\request\approved_contextlist(
            $user, 'mod_spatialforum', [$context->id]));

        $newcourseid = $this->backup_and_restore($course);
        $new = $DB->get_record('spatialforum', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertEquals(0, $new->enablelikes);
        $this->assertNotEquals($activity->roottrackid, $new->roottrackid);
        $this->assertSame(3, $DB->count_records('spatialforum_posts', ['spatialforumid' => $new->id]));
        $newreply = $DB->get_record('spatialforum_posts', ['spatialforumid' => $new->id, 'subject' => 'Reply'], '*', MUST_EXIST);
        $parent = $DB->get_record('spatialforum_posts', ['id' => $newreply->replytoid], '*', MUST_EXIST);
        $this->assertEquals($new->id, $parent->spatialforumid);
        $this->assertEquals(1, $parent->deleted);
        $this->assertSame('', $parent->message);
        $newchild = $DB->get_record('spatialforum_tracks', ['spatialforumid' => $new->id, 'name' => 'Child track']);
        $this->assertEquals($new->roottrackid, $newchild->parenttrackid);
        $this->assertEquals($newreply->id, $newchild->originpostid);
        $this->assertTrue($DB->record_exists('spatialforum_likes', ['postid' => $newreply->id, 'userid' => $other->id]));
    }
}
