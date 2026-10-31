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
 * Publication retries must not duplicate posts, branches or notification tasks.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_spatialforum;

use mod_spatialforum\local\forum;
use mod_spatialforum\local\submissions;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Authenticated publication keys share the post transaction and current access rules.
 */
#[CoversClass(submissions::class)]
final class submissions_test extends \advanced_testcase {
    /**
     * An enrolled author and a real activity, independently of other test cases.
     * @param int $groupmode
     * @return array
     */
    private function fixture(int $groupmode = NOGROUPS): array {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $activity = $gen->create_module('spatialforum', ['course' => $course->id, 'groupmode' => $groupmode]);
        $author = $gen->create_user();
        $gen->enrol_user($author->id, $course->id, 'student');
        $this->setUser($author);
        $cm = get_coursemodule_from_instance('spatialforum', $activity->id);
        return [$course, $activity, $author, $cm];
    }

    /**
     * A retry returns the original branch, without executing publication or fan-out twice.
     */
    public function test_repeated_submission_creates_one_branch_post_and_task(): void {
        global $DB;
        [, $activity, $author, $cm] = $this->fixture();
        $root = forum::create_post($activity, $activity->roottrackid, $author->id, 'Root', 'Text', FORMAT_PLAIN);
        $track = $DB->get_record('spatialforum_tracks', ['id' => $activity->roottrackid]);
        $calls = 0;
        $create = function () use ($activity, $author, $root, $track, &$calls, $DB) {
            $calls++;
            $id = forum::reply_in_new_track($activity, $track, $root, $author->id, 'Branch', 'Reply', 'Text', FORMAT_PLAIN);
            return $DB->get_field('spatialforum_posts', 'id', ['trackid' => $id], MUST_EXIST);
        };
        $token = bin2hex(random_bytes(32));
        $id = submissions::save($cm, $token, $create);
        $this->assertSame($id, submissions::save($cm, $token, $create));
        $this->assertSame(1, $calls);
        $this->assertSame(2, $DB->count_records('spatialforum_posts'));
        $this->assertSame(2, $DB->count_records('spatialforum_tracks'));
        $this->assertSame(2, $DB->count_records('task_adhoc', ['classname' => '\\mod_spatialforum\\task\\fanout_post']));
        $key = $DB->get_field('spatialforum_posts', 'submissionkey', ['id' => $id]);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $key);
    }

    /**
     * Keys are scoped to both the actor and activity, rather than trusting raw browser tokens.
     */
    public function test_token_is_scoped_to_author_and_activity(): void {
        global $USER, $PAGE;
        [$course, $activity, , $cm] = $this->fixture();
        $token = str_repeat('a', 64);
        $create = function () use ($activity) {
            global $USER;
            return forum::create_post($activity, $activity->roottrackid, $USER->id, 'Post', 'Text', FORMAT_PLAIN);
        };
        $one = submissions::save($cm, $token, $create);
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($other->id, $course->id, 'student');
        $this->setUser($other);
        $PAGE = new \moodle_page();
        $two = submissions::save($cm, $token, $create);
        $this->assertNotEquals($one, $two);
        $this->setAdminUser();
        $second = $this->getDataGenerator()->create_module('spatialforum', ['course' => $course->id]);
        $this->setUser($other);
        $PAGE = new \moodle_page();
        $othercm = get_coursemodule_from_instance('spatialforum', $second->id);
        $three = submissions::save($othercm, $token, function () use ($second, $USER) {
            return forum::create_post($second, $second->roottrackid, $USER->id, 'Post', 'Text', FORMAT_PLAIN);
        });
        $this->assertNotContains($three, [$one, $two]);
    }

    /**
     * A failed publication rolls back the post and its task and permits the same request to retry.
     */
    public function test_failure_rolls_back_and_allows_retry(): void {
        global $DB;
        $this->preventResetByRollback();
        [, $activity, $author, $cm] = $this->fixture();
        $create = function () use ($activity, $author) {
            return forum::create_post($activity, $activity->roottrackid, $author->id, 'Post', 'Text', FORMAT_PLAIN);
        };
        $token = str_repeat('b', 64);
        try {
            submissions::save($cm, $token, function () use ($create) {
                $create();
                throw new \RuntimeException('QA publication failure');
            });
            $this->fail('The transaction must propagate publication failures.');
        } catch (\RuntimeException $e) {
            $this->assertSame('QA publication failure', $e->getMessage());
        }
        $this->assertSame(0, $DB->count_records('spatialforum_posts'));
        $this->assertSame(0, $DB->count_records('task_adhoc', ['classname' => '\\mod_spatialforum\\task\\fanout_post']));
        $this->assertGreaterThan(0, submissions::save($cm, $token, $create));
        $this->assertSame(1, $DB->count_records('spatialforum_posts'));
    }

    /**
     * Deleted posts cannot be revived by replaying their publication token.
     */
    public function test_deleted_post_cannot_be_replayed(): void {
        global $DB;
        [, $activity, $author, $cm] = $this->fixture();
        $token = str_repeat('c', 64);
        $create = function () use ($activity, $author) {
            return forum::create_post($activity, $activity->roottrackid, $author->id, 'Post', 'Text', FORMAT_PLAIN);
        };
        $id = submissions::save($cm, $token, $create);
        $DB->set_field('spatialforum_posts', 'deleted', 1, ['id' => $id]);
        $this->expectExceptionMessage(get_string('invalidsubmission', 'mod_spatialforum'));
        submissions::save($cm, $token, $create);
    }

    /**
     * A forged or missing browser key is rejected before publication.
     */
    public function test_invalid_key_never_executes_publication(): void {
        [, , , $cm] = $this->fixture();
        $this->expectExceptionMessage(get_string('invalidsubmission', 'mod_spatialforum'));
        submissions::save($cm, 'not-a-key', function () {
            $this->fail('Invalid requests must not invoke publication.');
        });
    }

    /**
     * Replays still require the participant's current group access, not just their earlier access.
     */
    public function test_group_revocation_rejects_replay(): void {
        [$course, $activity, $author, $cm] = $this->fixture(SEPARATEGROUPS);
        $this->setAdminUser();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_add_member($group, $author);
        $this->setUser($author);
        $token = str_repeat('d', 64);
        $create = function () use ($activity, $author, $group) {
            return forum::create_post(
                $activity,
                $activity->roottrackid,
                $author->id,
                'Group post',
                'Text',
                FORMAT_PLAIN,
                0,
                false,
                '[]',
                $group->id
            );
        };
        submissions::save($cm, $token, $create);
        groups_remove_member($group, $author);
        $this->expectExceptionMessage(get_string('groupaccessdenied', 'mod_spatialforum'));
        submissions::save($cm, $token, $create);
    }
}
