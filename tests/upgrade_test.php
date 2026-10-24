<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_spatialforum;

use PHPUnit\Framework\Attributes\CoversFunction;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/upgradelib.php');
require_once($CFG->dirroot . '/mod/spatialforum/db/upgrade.php');

/** Existing activities and posts survive the schema upgrade. */
#[CoversFunction('xmldb_spatialforum_upgrade')]
final class upgrade_test extends \advanced_testcase {
    public function test_upgrade_from_mvp_preserves_activity_and_posts(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('spatialforum', ['course' => $course->id]);
        $postid = \mod_spatialforum\local\forum::create_post($activity, $activity->roottrackid,
            get_admin()->id, 'Before upgrade', 'Existing text', FORMAT_HTML);
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
            $this->assertTrue(xmldb_spatialforum_upgrade(2026102401));
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
        $parent = \mod_spatialforum\local\forum::create_post($activity, $activity->roottrackid,
            get_admin()->id, 'Parent', 'Text', FORMAT_HTML);
        $reply = \mod_spatialforum\local\forum::create_post($activity, $activity->roottrackid,
            get_admin()->id, 'Reply', 'Answer', FORMAT_HTML, $parent);
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
            if (!$manager->field_exists(new \xmldb_table('spatialforum_likes'), new \xmldb_field('reaction'))
                    || !$manager->field_exists(new \xmldb_table('spatialforum_posts'), new \xmldb_field('showparent'))) {
                set_config('version', 2026102400, 'mod_spatialforum');
                xmldb_spatialforum_upgrade(2026102400);
            }
        }
    }
}
