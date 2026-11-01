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

use PHPUnit\Framework\Attributes\CoversFunction;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/upgradelib.php');
require_once($CFG->dirroot . '/mod/spatialforum/db/upgrade.php');

/**

 * Existing activities and posts survive the schema upgrade.

 */
#[CoversFunction('xmldb_spatialforum_upgrade')]
final class upgrade_test extends \advanced_testcase {
    /**
     * Existing activity opt-ins become scope zero and can coexist with an independent thread preference.
     */
    public function test_upgrade_preserves_activity_subscription_and_expands_unique_scope(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $activity = $gen->create_module('spatialforum', ['course' => $course->id]);
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        $rootid = \mod_spatialforum\local\forum::create_post(
            $activity,
            $activity->roottrackid,
            $user->id,
            'Legacy conversation',
            'Legacy text',
            FORMAT_PLAIN
        );
        \mod_spatialforum\local\notifications::set_mode($activity->id, \mod_spatialforum\local\notifications::DAILY);
        $original = $DB->get_record('spatialforum_subscriptions', [], '*', MUST_EXIST);
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('spatialforum_subscriptions');
        $field = new \xmldb_field('rootpostid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'userid');
        $index = new \xmldb_index('forumuserroot', XMLDB_INDEX_UNIQUE, ['spatialforumid', 'userid', 'rootpostid']);
        $oldindex = new \xmldb_index('forumuser', XMLDB_INDEX_UNIQUE, ['spatialforumid', 'userid']);
        $dbman->drop_index($table, $index);
        $dbman->drop_field($table, $field);
        $dbman->add_index($table, $oldindex);
        try {
            set_config('version', 2026103102, 'mod_spatialforum');
            $this->assertTrue(\xmldb_spatialforum_upgrade(2026103102));
            $saved = $DB->get_record('spatialforum_subscriptions', ['id' => $original->id]);
            $this->assertEquals(0, $saved->rootpostid);
            $this->assertEquals($original->mode, $saved->mode);
            $this->assertEquals($original->firstpostid, $saved->firstpostid);
            $this->assertTrue($dbman->index_exists($table, $index));
            $this->assertFalse($dbman->index_exists($table, $oldindex));
            $copy = clone $saved;
            unset($copy->id);
            $copy->rootpostid = $rootid;
            $DB->insert_record('spatialforum_subscriptions', $copy);
            set_config('version', 2026103102, 'mod_spatialforum');
            $this->assertTrue(\xmldb_spatialforum_upgrade(2026103102));
            $this->assertSame(2, $DB->count_records('spatialforum_subscriptions'));
        } finally {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            if ($dbman->index_exists($table, $oldindex)) {
                $dbman->drop_index($table, $oldindex);
            }
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }
    }

    /**
     * Legacy posts keep their content and multiple empty publication keys after upgrade.
     */
    public function test_upgrade_adds_nullable_unique_publication_keys(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('spatialforum', ['course' => $course->id]);
        foreach (['First', 'Second'] as $subject) {
            \mod_spatialforum\local\forum::create_post(
                $activity,
                $activity->roottrackid,
                get_admin()->id,
                $subject,
                'Legacy body',
                FORMAT_PLAIN
            );
        }
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('spatialforum_posts');
        $index = new \xmldb_index('submissionkey', XMLDB_INDEX_UNIQUE, ['submissionkey']);
        $field = new \xmldb_field('submissionkey', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'discussionname');
        $dbman->drop_index($table, $index);
        $dbman->drop_field($table, $field);
        try {
            set_config('version', 2026103100, 'mod_spatialforum');
            $this->assertTrue(\xmldb_spatialforum_upgrade(2026103100));
            $this->assertTrue($dbman->index_exists($table, $index));
            $posts = $DB->get_records('spatialforum_posts', ['submissionkey' => null]);
            $this->assertCount(2, $posts);
            foreach ($posts as $post) {
                $this->assertSame('Legacy body', $post->message);
            }
            set_config('version', 2026103100, 'mod_spatialforum');
            $this->assertTrue(\xmldb_spatialforum_upgrade(2026103100));
        } finally {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }
    }

    /**
     * Existing activities opt out of grading on schema upgrade and repeat upgrades preserve settings.
     */
    public function test_upgrade_adds_ungraded_default_without_creating_grade_items(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('spatialforum', ['course' => $course->id]);
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('spatialforum');
        $field = new \xmldb_field('grade', XMLDB_TYPE_NUMBER, '15, 5', null, XMLDB_NOTNULL, null, '0', 'locked');
        $dbman->drop_field($table, $field);
        try {
            set_config('version', 2026102902, 'mod_spatialforum');
            $this->assertTrue(\xmldb_spatialforum_upgrade(2026102902));
            $this->assertEquals(0, $DB->get_field('spatialforum', 'grade', ['id' => $activity->id]));
            $identity = ['itemmodule' => 'spatialforum', 'iteminstance' => $activity->id];
            $this->assertFalse($DB->record_exists('grade_items', $identity));
            $DB->set_field('spatialforum', 'grade', 100, ['id' => $activity->id]);
            set_config('version', 2026102902, 'mod_spatialforum');
            \xmldb_spatialforum_upgrade(2026102902);
            $this->assertEquals(100, $DB->get_field('spatialforum', 'grade', ['id' => $activity->id]));
        } finally {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
    }

    public function test_upgrade_repairs_names_from_previously_erased_authors(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('spatialforum', ['course' => $course->id]);
        $root = $DB->get_record('spatialforum_tracks', ['id' => $activity->roottrackid]);
        $parent = \mod_spatialforum\local\forum::create_post(
            $activity,
            $root->id,
            get_admin()->id,
            'QA parent',
            'QA text',
            FORMAT_HTML
        );
        $trackid = \mod_spatialforum\local\forum::reply_in_new_track(
            $activity,
            $root,
            $parent,
            get_admin()->id,
            'QA erased title',
            'QA reply',
            'QA text',
            FORMAT_HTML
        );
        $DB->set_field('spatialforum_posts', 'userid', 0, ['trackid' => $trackid]);
        $DB->set_field('spatialforum_posts', 'deleted', 1, ['trackid' => $trackid]);
        set_config('version', 2026102601, 'mod_spatialforum');
        $this->assertTrue(xmldb_spatialforum_upgrade(2026102601));
        $this->assertSame(
            get_string('anonymoustrack', 'mod_spatialforum'),
            $DB->get_field('spatialforum_tracks', 'name', ['id' => $trackid])
        );
        $this->assertSame($activity->name, $DB->get_field('spatialforum_tracks', 'name', ['id' => $root->id]));
        $this->assertEquals(2, $DB->count_records('spatialforum_posts', ['spatialforumid' => $activity->id]));
    }

    public function test_upgrade_from_033_adds_excerpt_storage_without_changing_posts(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('spatialforum', ['course' => $course->id]);
        $postid = \mod_spatialforum\local\forum::create_post(
            $activity,
            $activity->roottrackid,
            get_admin()->id,
            'Before',
            'Unchanged body',
            FORMAT_HTML
        );
        $table = new \xmldb_table('spatialforum_posts');
        $field = new \xmldb_field('replyparts');
        $manager = $DB->get_manager();
        $manager->drop_field($table, $field);
        set_config('version', 2026102503, 'mod_spatialforum');
        try {
            $this->assertTrue(xmldb_spatialforum_upgrade(2026102503));
            $this->assertTrue($manager->field_exists($table, $field));
            $this->assertSame('Unchanged body', $DB->get_field('spatialforum_posts', 'message', ['id' => $postid]));
            $this->assertNull($DB->get_field('spatialforum_posts', 'replyparts', ['id' => $postid]));
        } finally {
            if (!$manager->field_exists($table, $field)) {
                set_config('version', 2026102503, 'mod_spatialforum');
                xmldb_spatialforum_upgrade(2026102503);
            }
        }
    }

    public function test_upgrade_from_mvp_preserves_activity_and_posts(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('spatialforum', ['course' => $course->id]);
        $postid = \mod_spatialforum\local\forum::create_post(
            $activity,
            $activity->roottrackid,
            get_admin()->id,
            'Before upgrade',
            'Existing text',
            FORMAT_HTML
        );
        $manager = $DB->get_manager();
        $table = new \xmldb_table('spatialforum');
        $field = new \xmldb_field('enablelikes');
        $manager->drop_table(new \xmldb_table('spatialforum_likes'));
        $manager->drop_field($table, $field);
        set_config('version', 2026100500, 'mod_spatialforum');
        try {
            $this->assertTrue(xmldb_spatialforum_upgrade(2026100500));
            $upgraded = $DB->get_record('spatialforum', ['id' => $activity->id], '*', MUST_EXIST);
            $this->assertEquals(1, $upgraded->enablelikes);
            $this->assertEquals($activity->roottrackid, $upgraded->roottrackid);
            $this->assertSame('Existing text', $DB->get_field('spatialforum_posts', 'message', ['id' => $postid]));
            $this->assertTrue($manager->table_exists(new \xmldb_table('spatialforum_likes')));
            // A repeat at the new version must not run the migration again.
            $this->assertTrue(xmldb_spatialforum_upgrade((int) get_config('mod_spatialforum', 'version')));
        } finally {
            // Restore the current schema before PHPUnit resets the database.
            if (!$manager->table_exists(new \xmldb_table('spatialforum_likes')) || !$manager->field_exists($table, $field)) {
                set_config('version', 2026100500, 'mod_spatialforum');
                xmldb_spatialforum_upgrade(2026100500);
            }
        }
    }

    public function test_upgrade_from_020_preserves_likes_and_published_quotes(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('spatialforum', ['course' => $course->id]);
        $parent = \mod_spatialforum\local\forum::create_post(
            $activity,
            $activity->roottrackid,
            get_admin()->id,
            'Parent',
            'Text',
            FORMAT_HTML
        );
        $reply = \mod_spatialforum\local\forum::create_post(
            $activity,
            $activity->roottrackid,
            get_admin()->id,
            'Reply',
            'Answer',
            FORMAT_HTML,
            $parent
        );
        $DB->insert_record('spatialforum_likes', (object) [
            'userid' => get_admin()->id, 'postid' => $parent, 'timecreated' => time(),
        ]);
        $manager = $DB->get_manager();
        $manager->drop_field(new \xmldb_table('spatialforum_likes'), new \xmldb_field('reaction'));
        $manager->drop_field(new \xmldb_table('spatialforum_posts'), new \xmldb_field('showparent'));
        set_config('version', 2026102400, 'mod_spatialforum');
        try {
            $this->assertTrue(xmldb_spatialforum_upgrade(2026102400));
            $this->assertEquals(1, $DB->get_field('spatialforum_likes', 'reaction', ['postid' => $parent]));
            $this->assertEquals(1, $DB->get_field('spatialforum_posts', 'showparent', ['id' => $reply]));
            $this->assertEquals(0, $DB->get_field('spatialforum_posts', 'showparent', ['id' => $parent]));
        } finally {
            if (
                !$manager->field_exists(new \xmldb_table('spatialforum_likes'), new \xmldb_field('reaction'))
                    || !$manager->field_exists(new \xmldb_table('spatialforum_posts'), new \xmldb_field('showparent'))
            ) {
                set_config('version', 2026102400, 'mod_spatialforum');
                xmldb_spatialforum_upgrade(2026102400);
            }
        }
    }

    public function test_upgrade_from_021_preserves_existing_read_timestamps(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('spatialforum', ['course' => $course->id]);
        $postid = \mod_spatialforum\local\forum::create_post(
            $activity,
            $activity->roottrackid,
            get_admin()->id,
            'Existing',
            'Existing body',
            FORMAT_HTML
        );
        \mod_spatialforum\local\forum::mark_read([$postid], get_admin()->id);
        $before = $DB->get_record('spatialforum_read', ['postid' => $postid]);
        set_config('version', 2026102401, 'mod_spatialforum');
        $this->assertTrue(xmldb_spatialforum_upgrade(2026102401));
        $after = $DB->get_record('spatialforum_read', ['postid' => $postid]);
        $this->assertEquals($before, $after);
        $this->assertTrue(xmldb_spatialforum_upgrade((int) get_config('mod_spatialforum', 'version')));
    }

    /**
     * Old shared posts survive upgrade and need explicit review before grouped use.
     */
    public function test_group_upgrade_preserves_content_and_requires_review(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('spatialforum', ['course' => $course->id]);
        $id = \mod_spatialforum\local\forum::create_post(
            $activity,
            $activity->roottrackid,
            get_admin()->id,
            'Legacy shared post',
            'Original content',
            FORMAT_HTML
        );
        $dbman = $DB->get_manager();
        $posts = new \xmldb_table('spatialforum_posts');
        $activitytable = new \xmldb_table('spatialforum');
        $groupfield = new \xmldb_field(
            'groupid',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '-1',
            'spatialforumid'
        );
        $readyfield = new \xmldb_field(
            'groupready',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'introformat'
        );
        $index = new \xmldb_index('forumgroup', XMLDB_INDEX_NOTUNIQUE, ['spatialforumid', 'groupid']);
        try {
            $dbman->drop_index($posts, $index);
            $dbman->drop_field($posts, $groupfield);
            $dbman->drop_field($activitytable, $readyfield);
            set_config('version', 2026102800, 'mod_spatialforum');
            $this->assertTrue(xmldb_spatialforum_upgrade(2026102800));
            $this->assertEquals(0, $DB->get_field('spatialforum', 'groupready', ['id' => $activity->id]));
            $post = $DB->get_record('spatialforum_posts', ['id' => $id], '*', MUST_EXIST);
            $this->assertSame('Original content', $post->message);
            $this->assertEquals(-1, $post->groupid);
            $DB->set_field('spatialforum', 'groupready', 1, ['id' => $activity->id]);
            set_config('version', 2026102800, 'mod_spatialforum');
            xmldb_spatialforum_upgrade(2026102800);
            $this->assertEquals(1, $DB->get_field('spatialforum', 'groupready', ['id' => $activity->id]));
        } finally {
            if (!$dbman->field_exists($posts, $groupfield)) {
                $dbman->add_field($posts, $groupfield);
            }
            if (!$dbman->field_exists($activitytable, $readyfield)) {
                $dbman->add_field($activitytable, $readyfield);
            }
            if (!$dbman->index_exists($posts, $index)) {
                $dbman->add_index($posts, $index);
            }
        }
    }

    /**
     * Upgrade creates empty opt-in tables without queuing historical posts, and can be retried.
     */
    public function test_notification_upgrade_preserves_content_and_creates_no_subscriptions(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('spatialforum', ['course' => $course->id]);
        $id = \mod_spatialforum\local\forum::create_post(
            $activity,
            $activity->roottrackid,
            get_admin()->id,
            'Legacy without mail',
            'Legacy body',
            FORMAT_HTML,
            0,
            false,
            '[]',
            -1,
            false
        );
        $dbman = $DB->get_manager();
        try {
            $dbman->drop_table(new \xmldb_table('spatialforum_deliveries'));
            $dbman->drop_table(new \xmldb_table('spatialforum_subscriptions'));
            set_config('version', 2026102900, 'mod_spatialforum');
            $this->assertTrue(xmldb_spatialforum_upgrade(2026102900));
            $this->assertSame(0, $DB->count_records('spatialforum_subscriptions'));
            $this->assertSame(0, $DB->count_records('spatialforum_deliveries'));
            $this->assertSame('Legacy body', $DB->get_field('spatialforum_posts', 'message', ['id' => $id]));
            $this->assertFalse($DB->record_exists('task_adhoc', ['component' => 'mod_spatialforum']));
            $subid = $DB->insert_record('spatialforum_subscriptions', (object) [
                'spatialforumid' => $activity->id, 'userid' => get_admin()->id, 'mode' => 2, 'firstpostid' => $id,
            ]);
            set_config('version', 2026102900, 'mod_spatialforum');
            xmldb_spatialforum_upgrade(2026102900);
            $this->assertEquals(2, $DB->get_field('spatialforum_subscriptions', 'mode', ['id' => $subid]));
        } finally {
            set_config('version', 2026102900, 'mod_spatialforum');
            xmldb_spatialforum_upgrade(2026102900);
        }
    }
}
