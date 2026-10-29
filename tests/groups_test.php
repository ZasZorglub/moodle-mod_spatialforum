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
use mod_spatialforum\local\group_access;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Group privacy is enforced for rendering, writes, reads and files.
 */
#[CoversClass(group_access::class)]
#[CoversClass(forum::class)]
final class groups_test extends \advanced_testcase {
    /**
     * Create two independent groups, learners, a shared post and two private posts.
     *
     * @param int $mode
     * @return array
     */
    private function fixture(int $mode = SEPARATEGROUPS): array {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $a = $gen->create_group(['courseid' => $course->id, 'name' => 'Group A']);
        $b = $gen->create_group(['courseid' => $course->id, 'name' => 'Group B']);
        $alice = $gen->create_user();
        $bob = $gen->create_user();
        foreach ([$alice, $bob] as $user) {
            $gen->enrol_user($user->id, $course->id, 'student');
        }
        $gen->create_group_member(['groupid' => $a->id, 'userid' => $alice->id]);
        $gen->create_group_member(['groupid' => $b->id, 'userid' => $bob->id]);
        $activity = $gen->create_module('spatialforum', ['course' => $course->id, 'groupmode' => $mode]);
        $cm = get_coursemodule_from_instance('spatialforum', $activity->id);
        $context = \context_module::instance($cm->id);
        $root = $DB->get_record('spatialforum_tracks', ['id' => $activity->roottrackid]);
        $pa = forum::create_post(
            $activity,
            $root->id,
            $alice->id,
            'Private A marker',
            'A content',
            FORMAT_HTML,
            0,
            false,
            '[]',
            $a->id
        );
        $pb = forum::create_post(
            $activity,
            $root->id,
            $bob->id,
            'Private B marker',
            'B content',
            FORMAT_HTML,
            0,
            false,
            '[]',
            $b->id
        );
        $shared = forum::create_post(
            $activity,
            $root->id,
            get_admin()->id,
            'Shared marker',
            'Shared',
            FORMAT_HTML,
            0,
            false,
            '[]',
            -1
        );
        $pa = $DB->get_record('spatialforum_posts', ['id' => $pa]);
        $pb = $DB->get_record('spatialforum_posts', ['id' => $pb]);
        return [$course, $activity, $cm, $context, $root, $a, $b, $alice, $bob, $pa, $pb, $shared];
    }

    /**
     * A rejected group operation cannot silently succeed.
     *
     * @param callable $operation
     */
    private function denied(callable $operation): void {
        try {
            $operation();
            $this->fail('Cross-group operation was accepted');
        } catch (\moodle_exception $e) {
            $this->assertSame('groupaccessdenied', $e->errorcode);
        }
    }

    /**
     * Separate groups hide post payloads, route labels, counters and direct file access.
     */
    public function test_separate_groups_hide_content_and_deny_direct_writes_and_files(): void {
        global $DB, $PAGE;
        [$course, $activity, $cm, $context, $root, $a, $b, $alice, $bob, $pa, $pb, $shared] = $this->fixture();
        forum::reply_in_new_track(
            $activity,
            $root,
            $pb->id,
            $bob->id,
            'Private B branch',
            'Private B reply',
            'B reply',
            FORMAT_HTML
        );
        $this->setUser($alice);
        $data = forum::export_view_data($activity, $cm, $root, $context, true);
        $this->assertCount(2, $data['posts']);
        $this->assertStringNotContainsString('Private B', json_encode($data));
        $this->assertEquals([$shared], forum::get_unread_postids($activity->id, $alice->id));
        $policy = new group_access($activity, $cm);
        $this->assertTrue($policy->can_view($pa));
        $this->assertFalse($policy->can_view($pb));
        $this->denied(fn() => forum::mark_read([$pb->id], $alice->id));
        $this->denied(fn() => forum::set_reaction($activity, $pb, $alice->id, 1));
        $this->denied(fn() => forum::reply_in_new_track(
            $activity,
            $root,
            $pb->id,
            $alice->id,
            'Forbidden',
            'Forbidden',
            'Forbidden',
            FORMAT_HTML
        ));
        get_file_storage()->create_file_from_string(['contextid' => $context->id, 'component' => 'mod_spatialforum',
            'filearea' => 'post', 'itemid' => $pb->id, 'filepath' => '/', 'filename' => 'private.png'], 'QA bytes');
        $PAGE = new \moodle_page();
        $this->assertFalse(\spatialforum_pluginfile($course, $cm, $context, 'post', [$pb->id, 'private.png'], false));
        $this->assertFalse($DB->record_exists('spatialforum_read', ['userid' => $alice->id, 'postid' => $pb->id]));
    }

    /**
     * Visible-group readers can read and mark read, but cannot reply or react across groups.
     */
    public function test_visible_groups_reading_does_not_grant_contribution(): void {
        global $DB;
        [, $activity, $cm, $context, $root, $a, $b, $alice, $bob, $pa, $pb] = $this->fixture(VISIBLEGROUPS);
        $this->setUser($alice);
        $policy = new group_access($activity, $cm);
        $this->assertTrue($policy->can_view($pb));
        $this->assertFalse($policy->can_write($b->id));
        $data = forum::export_view_data($activity, $cm, $root, $context, true);
        $post = array_values(array_filter($data['posts'], fn($item) => $item['id'] == $pb->id))[0];
        $this->assertFalse($post['canreply']);
        $this->assertFalse($post['canlike']);
        forum::mark_read([$pb->id], $alice->id);
        $this->assertTrue($DB->record_exists('spatialforum_read', ['userid' => $alice->id, 'postid' => $pb->id]));
        $this->denied(fn() => forum::set_reaction($activity, $pb, $alice->id, 1));
        $this->denied(fn() => forum::create_post(
            $activity,
            $root->id,
            $alice->id,
            'Forbidden B post',
            'Content',
            FORMAT_HTML,
            0,
            false,
            '[]',
            $b->id
        ));
    }

    /**
     * Replies inherit the stored scope; clients cannot choose a different audience.
     */
    public function test_replies_inherit_scope_and_multi_group_members_can_participate(): void {
        global $DB;
        [, $activity, $cm, , $root, $a, $b, $alice, , $pa, $pb] = $this->fixture();
        $this->getDataGenerator()->create_group_member(['groupid' => $b->id, 'userid' => $alice->id]);
        $this->setUser($alice);
        $id = forum::create_post(
            $activity,
            $root->id,
            $alice->id,
            'Scoped reply',
            'Answer',
            FORMAT_HTML,
            $pb->id,
            false,
            '[]',
            $a->id
        );
        $this->assertEquals($b->id, $DB->get_field('spatialforum_posts', 'groupid', ['id' => $id]));
        $this->assertTrue((new group_access($activity, $cm))->can_write($b->id));
    }

    /**
     * Ungrouped learners and guests see only shared conversations; old grouped data is fail-closed.
     */
    public function test_legacy_review_and_ungrouped_users(): void {
        global $DB;
        [$course, $activity, $cm, , $root, , , $alice, , $pa, $pb] = $this->fixture();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        $this->assertFalse((new group_access($activity, $cm))->can_view($pa));
        $this->setGuestUser();
        $this->assertFalse((new group_access($activity, $cm))->can_view($pb));
        $DB->set_field('spatialforum', 'groupready', 0, ['id' => $activity->id]);
        $activity->groupready = 0;
        $this->setUser($alice);
        $policy = new group_access($activity, $cm);
        $this->assertTrue($policy->pending());
        $this->assertFalse($policy->can_view($pa));
        $this->setAdminUser();
        $policy = new group_access($activity, $cm);
        $this->assertTrue($policy->can_view($pb));
        $this->assertFalse($policy->can_write($pb->groupid));
    }

    /**
     * A grouping excludes other course groups, even for an access-all-groups teacher.
     */
    public function test_grouping_and_membership_changes_are_rechecked(): void {
        global $DB;
        [$course, $activity, $cm, , , $a, $b, $alice, , $pa, $pb] = $this->fixture();
        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id]);
        groups_assign_grouping($grouping->id, $a->id);
        $cm->groupingid = $grouping->id;
        $this->setAdminUser();
        $this->assertFalse((new group_access($activity, $cm))->can_view($pb));
        $this->setUser($alice);
        $this->assertTrue((new group_access($activity, $cm))->can_view($pa));
        groups_remove_member($a->id, $alice->id);
        $this->assertFalse((new group_access($activity, $cm))->can_view($pa));
        $DB->set_field('course', 'groupmodeforce', 1, ['id' => $course->id]);
        $DB->set_field('course', 'groupmode', SEPARATEGROUPS, ['id' => $course->id]);
        $cm->groupmode = NOGROUPS;
        $this->assertTrue((new group_access($activity, $cm))->uses_groups());
    }

    /**
     * Audience review requires staff permissions, valid scope and explicit confirmation.
     */
    public function test_legacy_review_is_explicit_authorised_and_idempotent(): void {
        global $DB;
        [, $activity, , , , $a, $b, $alice, , $pa] = $this->fixture();
        $DB->set_field('spatialforum', 'groupready', 0, ['id' => $activity->id]);
        $this->denied(fn() => group_access::review_legacy($activity->id, $a->id, false));
        $this->denied(fn() => group_access::review_legacy($activity->id, 999999, true));
        $this->setUser($alice);
        try {
            group_access::review_legacy($activity->id, $a->id, true);
            $this->fail('Learner performed an audience review');
        } catch (\required_capability_exception $e) {
            $this->assertFalse((bool) $DB->get_field('spatialforum', 'groupready', ['id' => $activity->id]));
        }
        $this->setAdminUser();
        group_access::review_legacy($activity->id, $a->id, true);
        $this->assertEquals(1, $DB->get_field('spatialforum', 'groupready', ['id' => $activity->id]));
        $this->assertEquals([$a->id], array_values(array_unique($DB->get_fieldset_select(
            'spatialforum_posts',
            'groupid',
            'spatialforumid = ?',
            [$activity->id]
        ))));
        group_access::review_legacy($activity->id, $b->id, true);
        $this->assertEquals($a->id, $DB->get_field('spatialforum_posts', 'groupid', ['id' => $pa->id]));
    }

    /**
     * Forged cross-group quoted parents are unavailable rather than leaking their source.
     */
    public function test_quoted_parent_is_filtered_independently_of_the_reply(): void {
        [, $activity, $cm, $context, , , , $alice, , $pa, $pb] = $this->fixture();
        $this->setUser($alice);
        $pa->replytoid = $pb->id;
        $pa->replyparts = json_encode([['start' => 0, 'length' => 9, 'hash' => hash('sha256', 'B content'),
            'comment' => 'Visible own comment']]);
        $html = \mod_spatialforum\local\excerpts::render($pa, $context, $cm->id);
        $this->assertStringContainsString('Visible own comment', $html);
        $this->assertStringNotContainsString('Private B marker', $html);
        $this->assertStringNotContainsString('B content', $html);
        $this->assertFalse(forum::export_reply_context($pb, $context, $cm->id)['available']);
    }

    /**
     * Match Moodle forum: shared replies work in visible groups but shared starts require staff.
     */
    public function test_shared_reply_policy_depends_on_group_mode(): void {
        global $DB;
        [, $activity, $cm, , $root, , , $alice, , , , $shared] = $this->fixture(VISIBLEGROUPS);
        $this->setUser($alice);
        $policy = new group_access($activity, $cm);
        $this->assertTrue($policy->can_write(-1));
        $this->assertFalse($policy->can_start(-1));
        $id = forum::create_post($activity, $root->id, $alice->id, 'Shared reply', 'Reply', FORMAT_HTML, $shared);
        $this->assertEquals(-1, $DB->get_field('spatialforum_posts', 'groupid', ['id' => $id]));
        $this->denied(fn() => forum::create_post(
            $activity,
            $root->id,
            $alice->id,
            'Shared start',
            'Content',
            FORMAT_HTML,
            0,
            false,
            '[]',
            -1
        ));
        $DB->set_field('course_modules', 'groupmode', SEPARATEGROUPS, ['id' => $cm->id]);
        $this->denied(fn() => forum::create_post(
            $activity,
            $root->id,
            $alice->id,
            'Forbidden shared reply',
            'Reply',
            FORMAT_HTML,
            $shared
        ));
    }

    /**
     * Existing unlinked tracks remain readable when the activity has no group mode.
     */
    public function test_no_groups_preserves_legacy_unlinked_tracks(): void {
        global $DB;
        [, $activity, $cm, $context, $root] = $this->fixture(NOGROUPS);
        $id = forum::create_track($activity, $root, 0, 'Legacy unlinked track');
        $track = $DB->get_record('spatialforum_tracks', ['id' => $id], '*', MUST_EXIST);
        $data = forum::export_view_data($activity, $cm, $track, $context);
        $this->assertSame('Legacy unlinked track', $data['trackname']);
    }

    /**
     * Group review cannot certify unresolved legacy branches containing posts.
     */
    public function test_legacy_review_rejects_unresolved_conversation_structure(): void {
        global $DB;
        [, $activity, $cm, , $root] = $this->fixture(NOGROUPS);
        $id = forum::create_track($activity, $root, 0, 'Legacy unlinked track');
        forum::create_post($activity, $id, get_admin()->id, 'Legacy content', 'Keep this content', FORMAT_HTML);
        $DB->set_field('spatialforum', 'groupready', 0, ['id' => $activity->id]);
        try {
            group_access::review_legacy($activity->id, -1, true);
            $this->fail('An unresolved branch was certified');
        } catch (\moodle_exception $e) {
            $this->assertSame('groupreviewstructure', $e->errorcode);
        }
        $this->assertEquals(0, $DB->get_field('spatialforum', 'groupready', ['id' => $activity->id]));
        $this->assertSame('Keep this content', $DB->get_field('spatialforum_posts', 'message', ['trackid' => $id]));
    }
}
