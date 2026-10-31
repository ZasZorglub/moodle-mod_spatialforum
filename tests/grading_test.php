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
 * Whole-activity Gradebook assessment, boundaries and lifecycle tests.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_spatialforum;

use mod_spatialforum\local\grading;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Grades are private, authoritative Gradebook records rather than derived participation scores.
 */
#[CoversClass(grading::class)]
final class grading_test extends \advanced_testcase {
    /**
     * A configured activity cannot bypass the administrator's opt-in.
     */
    public function test_site_opt_in_is_required_before_assessment(): void {
        [$course, $activity, $teacher, $alice] = $this->fixture();
        set_config('enablegrading', 0, 'mod_spatialforum');
        $this->assertFalse(grading::enabled());
        $this->setUser($teacher);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('gradingdisabled', 'mod_spatialforum'));
        grading::assessment($activity->id, $alice->id);
    }
    /**
     * A teacher and two independently enrolled students.
     * @param int $mode
     * @param float $maximum
     * @return array
     */
    private function fixture(int $mode = NOGROUPS, float $maximum = 100): array {
        global $DB;
        $this->resetAfterTest();
        set_config('enablegrading', 1, 'mod_spatialforum');
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $activity = $gen->create_module('spatialforum', ['course' => $course->id, 'grade' => $maximum, 'groupmode' => $mode]);
        $teacher = $gen->create_user(['firstname' => 'Grading', 'lastname' => 'Teacher']);
        $alice = $gen->create_user(['firstname' => 'Grading', 'lastname' => 'Alice']);
        $bob = $gen->create_user(['firstname' => 'Grading', 'lastname' => 'Bob']);
        $gen->enrol_user($teacher->id, $course->id, 'teacher');
        foreach ([$alice, $bob] as $user) {
            $gen->enrol_user($user->id, $course->id, 'student');
        }
        $cm = get_coursemodule_from_instance('spatialforum', $activity->id);
        $context = \context_module::instance($cm->id);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $roleid, $context);
        $this->setUser($teacher);
        return [$course, $activity, $teacher, $alice, $bob, $cm];
    }

    /**
     * Save with a freshly read revision, just as a newly opened assessment form does.
     * @param \stdClass $activity
     * @param \stdClass $user
     * @param string $score
     * @param string $feedback
     */
    private function assess(\stdClass $activity, \stdClass $user, string $score, string $feedback = ''): void {
        $state = grading::assessment($activity->id, $user->id);
        grading::save($activity->id, $user->id, $score, $feedback, $state['token']);
    }

    /**
     * Existing/new activities default to no grade, with no empty grade item created.
     */
    public function test_grading_defaults_to_none(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('spatialforum', ['course' => $course->id]);
        $this->assertEquals(0, $activity->grade);
        $this->assertFalse(grading::item($activity));
    }

    /**
     * Zero, feedback without a grade, and clearing a previous value are distinct states.
     */
    public function test_points_zero_feedback_and_clear_are_gradebook_owned(): void {
        global $DB;
        [, $activity, $teacher, $alice] = $this->fixture();
        $events = $this->redirectEvents();
        $this->assess($activity, $alice, '0', 'Private zero feedback');
        $item = grading::item($activity);
        $record = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $alice->id]);
        $this->assertEquals(0, $record->rawgrade);
        $this->assertEquals(0, $record->finalgrade);
        $this->assertEquals($teacher->id, $record->usermodified);
        $this->assertTrue($DB->record_exists('grade_grades_history', ['itemid' => $item->id, 'userid' => $alice->id]));
        $this->assertNotEmpty(array_filter($events->get_events(), fn($event) => $event instanceof \core\event\user_graded));
        $this->setUser($alice);
        $own = grading::own_result($activity->id);
        $this->assertEquals(0, $own->grade);
        $this->assertStringContainsString('Private zero feedback', $own->str_feedback);
        $this->setUser($teacher);
        $this->assess($activity, $alice, '', 'Feedback without a score');
        $this->setUser($alice);
        $own = grading::own_result($activity->id);
        $this->assertNull($own->grade);
        $this->assertStringContainsString('Feedback without a score', $own->str_feedback);
        $this->setUser($teacher);
        $this->assess($activity, $alice, '', '');
        $record = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $alice->id]);
        $this->assertNull($record->rawgrade);
        $this->assertNull($record->feedback);
    }

    /**
     * Decimal grades are bounded and do not derive from reactions, posts or read state.
     */
    public function test_numeric_validation_and_plain_feedback(): void {
        [, $activity, , $alice] = $this->fixture();
        $item = grading::item($activity);
        $this->assertSame(12.5, grading::parse_score($item, '12,5'));
        foreach (['-1', '101', 'INF', '1e2', '1,000.5', '0.123456'] as $value) {
            try {
                grading::parse_score($item, $value);
                $this->fail('Invalid score accepted: ' . $value);
            } catch (\moodle_exception $e) {
                $this->assertSame('invalidmanualgrade', $e->errorcode);
            }
        }
        $this->assess($activity, $alice, '12.5', '<script>Private</script>');
        $this->setUser($alice);
        $this->assertStringNotContainsString('<script>', grading::own_result($activity->id)->str_feedback);
    }

    /**
     * Scale grades use one-based integer choices and preserve the configured scale.
     */
    public function test_scale_grading_and_scale_usage_callbacks(): void {
        [, $activity, , $alice] = $this->fixture();
        $scale = $this->getDataGenerator()->create_scale([
            'courseid' => $activity->course, 'scale' => 'Needs work,Developing,Confident',
        ]);
        \spatialforum_update_instance((object) ['instance' => $activity->id, 'name' => $activity->name, 'grade' => -$scale->id]);
        $activity->grade = -$scale->id;
        $item = grading::item($activity);
        $this->assertEquals($scale->id, $item->scaleid);
        $this->assertTrue(\spatialforum_scale_used($activity->id, $scale->id));
        $this->assertTrue(\spatialforum_scale_used_anywhere($scale->id));
        foreach (['0', '1.5', '4'] as $value) {
            try {
                grading::parse_score($item, $value);
                $this->fail('Invalid scale score accepted');
            } catch (\moodle_exception $e) {
                $this->assertSame('invalidmanualgrade', $e->errorcode);
            }
        }
        $this->assess($activity, $alice, '3', 'Scale feedback');
        $this->setUser($alice);
        $this->assertStringContainsString('Confident', grading::own_result($activity->id)->str_grade);
    }

    /**
     * A learner cannot view a private peer assessment or enumerate the grading roster.
     */
    public function test_student_cannot_assess_peers(): void {
        [, $activity, , $alice, $bob] = $this->fixture();
        $this->setUser($alice);
        $this->expectException(\required_capability_exception::class);
        grading::assessment($activity->id, $bob->id);
    }

    /**
     * The same grading permission protects roster identity/counts.
     */
    public function test_student_cannot_enumerate_roster(): void {
        [, $activity, , $alice] = $this->fixture();
        $this->setUser($alice);
        $this->expectException(\required_capability_exception::class);
        grading::roster($activity->id);
    }

    /**
     * Visible groups do not grant a teacher authority to grade another group's participants.
     */
    public function test_visible_group_roster_and_direct_assessment_share_boundary(): void {
        [$course, $activity, $teacher, $alice, $bob] = $this->fixture(VISIBLEGROUPS);
        $gen = $this->getDataGenerator();
        $a = $gen->create_group(['courseid' => $course->id]);
        $b = $gen->create_group(['courseid' => $course->id]);
        foreach ([$teacher, $alice] as $user) {
            $gen->create_group_member(['groupid' => $a->id, 'userid' => $user->id]);
        }
        $gen->create_group_member(['groupid' => $b->id, 'userid' => $bob->id]);
        $this->setUser($teacher);
        $roster = grading::roster($activity->id);
        $this->assertSame(1, $roster['total']);
        $this->assertEquals([$alice->id], array_keys($roster['users']));
        $this->assess($activity, $alice, '75');
        $this->expectException(\moodle_exception::class);
        grading::assessment($activity->id, $bob->id);
    }

    /**
     * Group membership revoked after opening the form is checked before saving.
     */
    public function test_group_revocation_prevents_a_stale_form_write(): void {
        [$course, $activity, $teacher, $alice] = $this->fixture(SEPARATEGROUPS);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        foreach ([$teacher, $alice] as $user) {
            $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
        }
        $this->setUser($teacher);
        $state = grading::assessment($activity->id, $alice->id);
        groups_remove_member($group->id, $alice->id);
        $this->expectException(\moodle_exception::class);
        grading::save($activity->id, $alice->id, '50', 'Must not save', $state['token']);
    }

    /**
     * Suspended recipients cannot be graded even when an older form token was valid.
     */
    public function test_suspended_participant_is_rejected(): void {
        global $DB;
        [, $activity, , $alice] = $this->fixture();
        $state = grading::assessment($activity->id, $alice->id);
        $DB->set_field('user', 'suspended', 1, ['id' => $alice->id]);
        $this->expectException(\moodle_exception::class);
        grading::save($activity->id, $alice->id, '50', '', $state['token']);
    }

    /**
     * Both item and individual-grade locks protect all grade/feedback edits.
     */
    public function test_locked_grades_are_read_only(): void {
        [, $activity, , $alice] = $this->fixture();
        $this->assess($activity, $alice, '70', 'Keep locked feedback');
        $item = grading::item($activity);
        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $alice->id]);
        $grade->set_locked(1);
        $state = grading::assessment($activity->id, $alice->id);
        $this->assertSame('gradelocked', $state['reason']);
        $this->expectException(\moodle_exception::class);
        grading::save($activity->id, $alice->id, '10', '', $state['token']);
    }

    /**
     * Gradebook overrides remain the final score and feedback instead of being overwritten here.
     */
    public function test_gradebook_override_is_preserved(): void {
        [, $activity, , $alice] = $this->fixture();
        $this->assess($activity, $alice, '60', 'Original feedback');
        $item = grading::item($activity);
        $item->update_final_grade($alice->id, 88, 'gradebook', 'Gradebook override', FORMAT_PLAIN);
        $state = grading::assessment($activity->id, $alice->id);
        $this->assertSame('gradeoverridden', $state['reason']);
        $this->assertEquals(88, $state['result']->grade);
        $this->assertSame('Gradebook override', $state['result']->feedback);
        $this->expectException(\moodle_exception::class);
        grading::save($activity->id, $alice->id, '20', '', $state['token']);
    }

    /**
     * Hidden item and per-user results never return grade or feedback to the learner.
     */
    public function test_hidden_results_do_not_leak_to_learner(): void {
        [, $activity, $teacher, $alice] = $this->fixture();
        $this->assess($activity, $alice, '85', 'Hidden feedback');
        $item = grading::item($activity);
        $item->set_hidden(1);
        $this->setUser($alice);
        $this->assertNull(grading::own_result($activity->id));
        $this->setUser($teacher);
        $item->set_hidden(0);
        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $alice->id]);
        $grade->set_hidden(1);
        $this->setUser($alice);
        $this->assertNull(grading::own_result($activity->id));
    }

    /**
     * Two open forms cannot silently overwrite each other's newer result.
     */
    public function test_revision_token_rejects_stale_writes(): void {
        [, $activity, , $alice] = $this->fixture();
        $old = grading::assessment($activity->id, $alice->id);
        $this->assess($activity, $alice, '90', 'Newer assessment');
        try {
            grading::save($activity->id, $alice->id, '20', 'Stale assessment', $old['token']);
            $this->fail('Stale form was accepted');
        } catch (\moodle_exception $e) {
            $this->assertSame('gradestale', $e->errorcode);
        }
        $current = grading::assessment($activity->id, $alice->id);
        $this->assertSame('Newer assessment', $current['result']->feedback);
    }

    /**
     * Maintenance/rename callbacks preserve manually stored results rather than re-deriving them.
     */
    public function test_callbacks_and_rename_preserve_manual_grades(): void {
        [, $activity, , $alice] = $this->fixture();
        $this->assess($activity, $alice, '62.5', 'Persisted feedback');
        \spatialforum_update_grades($activity);
        \spatialforum_update_grades($activity, $alice->id, true);
        \spatialforum_update_instance((object) ['instance' => $activity->id, 'name' => 'Renamed graded forum']);
        $item = grading::item($activity);
        $this->assertSame('Renamed graded forum', $item->itemname);
        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $alice->id]);
        $this->assertEquals(62.5, $grade->rawgrade);
        $this->assertSame('Persisted feedback', $grade->feedback);
        $this->expectException(\moodle_exception::class);
        \spatialforum_update_instance((object) ['instance' => $activity->id, 'name' => $activity->name, 'grade' => 50]);
    }

    /**
     * A scale from another course is never accepted as this activity's definition.
     */
    public function test_foreign_scale_is_rejected(): void {
        [, $activity] = $this->fixture();
        $other = $this->getDataGenerator()->create_course();
        $scale = $this->getDataGenerator()->create_scale(['courseid' => $other->id]);
        $this->expectException(\moodle_exception::class);
        \spatialforum_update_instance((object) ['instance' => $activity->id, 'name' => $activity->name, 'grade' => -$scale->id]);
    }

    /**
     * Grade-only user data belong to the core Gradebook privacy subsystem; deletion removes the item.
     */
    public function test_core_privacy_ownership_and_activity_deletion(): void {
        global $DB;
        [, $activity, , $alice] = $this->fixture();
        $this->assess($activity, $alice, '80', 'Grade-only private data');
        $contexts = \core_grades\privacy\provider::get_contexts_for_userid($alice->id)->get_contextids();
        $this->assertNotEmpty($contexts);
        $itemid = grading::item($activity)->id;
        $this->assertTrue(\spatialforum_delete_instance($activity->id));
        $this->assertFalse($DB->record_exists('grade_items', ['id' => $itemid]));
        $this->assertFalse($DB->record_exists('grade_grades', ['itemid' => $itemid]));
    }

    /**
     * Roster pagination does not silently truncate a course at its first fifty participants.
     */
    public function test_roster_pagination(): void {
        [$course, $activity] = $this->fixture();
        $gen = $this->getDataGenerator();
        for ($i = 0; $i < 49; $i++) {
            $user = $gen->create_user();
            $gen->enrol_user($user->id, $course->id, 'student');
        }
        $first = grading::roster($activity->id, 0);
        $second = grading::roster($activity->id, 1);
        $this->assertSame(51, $first['total']);
        $this->assertCount(50, $first['users']);
        $this->assertCount(1, $second['users']);
        $this->assertSame([], array_intersect_key($first['users'], $second['users']));
    }

    /**
     * Course-level grade visibility also withholds the participant's private result here.
     */
    public function test_course_grade_visibility_is_respected(): void {
        global $DB;
        [$course, $activity, , $alice] = $this->fixture();
        $this->assess($activity, $alice, '80', 'Withheld by course policy');
        $DB->set_field('course', 'showgrades', 0, ['id' => $course->id]);
        rebuild_course_cache($course->id, true);
        $this->setUser($alice);
        $this->assertNull(grading::own_result($activity->id));
    }

    /**
     * Course-forced groups, grouping membership and multi-group participation share one boundary.
     */
    public function test_forced_grouping_and_multiple_memberships(): void {
        global $DB;
        [$course, $activity, $teacher, $alice, $bob, $cm] = $this->fixture();
        $gen = $this->getDataGenerator();
        $a = $gen->create_group(['courseid' => $course->id]);
        $b = $gen->create_group(['courseid' => $course->id]);
        $grouping = $gen->create_grouping(['courseid' => $course->id]);
        $gen->create_grouping_group(['groupingid' => $grouping->id, 'groupid' => $a->id]);
        foreach ([$teacher, $alice, $bob] as $user) {
            $gen->create_group_member(['groupid' => $b->id, 'userid' => $user->id]);
        }
        foreach ([$teacher, $alice] as $user) {
            $gen->create_group_member(['groupid' => $a->id, 'userid' => $user->id]);
        }
        $DB->set_field('course', 'groupmodeforce', 1, ['id' => $course->id]);
        $DB->set_field('course', 'groupmode', SEPARATEGROUPS, ['id' => $course->id]);
        $DB->set_field('course_modules', 'groupingid', $grouping->id, ['id' => $cm->id]);
        rebuild_course_cache($course->id, true);
        $this->setUser($teacher);
        $this->assertSame(1, grading::roster($activity->id)['total']);
        $this->assess($activity, $alice, '70');
        $this->expectException(\moodle_exception::class);
        grading::assessment($activity->id, $bob->id);
    }

    /**
     * An explicitly authorised access-all-groups grader can assess ungrouped participants too.
     */
    public function test_access_all_groups_grader_can_assess_ungrouped_participants(): void {
        global $DB;
        [, $activity, $teacher, $alice, $bob, $cm] = $this->fixture(SEPARATEGROUPS);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $roleid, \context_module::instance($cm->id), true);
        $this->setUser($teacher);
        $this->assertSame(2, grading::roster($activity->id)['total']);
        $this->assess($activity, $alice, '50');
        $this->assess($activity, $bob, '60');
        $this->assertEquals(60, grading::assessment($activity->id, $bob->id)['result']->grade);
    }
}
