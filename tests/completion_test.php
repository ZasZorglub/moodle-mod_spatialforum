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
 * Contribution counts and the Moodle completion lifecycle.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_spatialforum;

use mod_spatialforum\completion\custom_completion;
use mod_spatialforum\local\forum;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Completion counts live authored posts and distinguishes roots from replies.
 */
#[CoversClass(custom_completion::class)]
final class completion_test extends \advanced_testcase {
    /**
     * Automatic completion changes after posting and deleting, without counting another author.
     */
    public function test_counts_and_recalculation_follow_live_authored_contributions(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $CFG->enablecompletion = true;
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $activity = $gen->create_module('spatialforum', ['course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionposts' => 2,
            'completionreplies' => 1, 'completiondiscussions' => 1]);
        $author = $gen->create_user();
        $other = $gen->create_user();
        $gen->enrol_user($author->id, $course->id, 'student');
        $gen->enrol_user($other->id, $course->id, 'student');
        $cm = get_fast_modinfo($course)->get_cm($activity->cmid);
        $completion = new custom_completion($cm, $author->id);
        $this->assertSame(COMPLETION_INCOMPLETE, $completion->get_state('completionposts'));
        $root = forum::create_post($activity, $activity->roottrackid, $author->id, 'Root', 'Text', FORMAT_PLAIN);
        forum::create_post($activity, $activity->roottrackid, $other->id, 'Other', 'Text', FORMAT_PLAIN);
        $this->assertSame(COMPLETION_COMPLETE, $completion->get_state('completiondiscussions'));
        $this->assertSame(COMPLETION_INCOMPLETE, $completion->get_state('completionposts'));
        $reply = forum::create_post($activity, $activity->roottrackid, $author->id, 'Reply', 'Text', FORMAT_PLAIN, $root);
        $this->assertSame(COMPLETION_COMPLETE, $completion->get_state('completionreplies'));
        $this->assertSame(COMPLETION_COMPLETE, $completion->get_state('completionposts'));
        $info = new \completion_info($course);
        $this->assertEquals(COMPLETION_COMPLETE, $info->get_data($cm, false, $author->id)->completionstate);
        $DB->set_field('spatialforum_posts', 'deleted', 1, ['id' => $reply]);
        custom_completion::refresh($activity->id, $author->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $info->get_data($cm, false, $author->id)->completionstate);
        $this->assertSame(COMPLETION_INCOMPLETE, $completion->get_state('completionreplies'));
        $this->assertStringContainsString('2', $completion->get_custom_rule_descriptions()['completionposts']);
    }
}
