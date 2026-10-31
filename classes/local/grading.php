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
 * Whole-activity assessment backed exclusively by Moodle Gradebook.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_spatialforum\local;

/**
 * Permission-aware Gradebook assessment without a second grade/feedback store.
 */
class grading {
    /**
     * Grading requires explicit site administrator opt-in.
     * @return bool
     */
    public static function enabled(): bool {
        return get_config('mod_spatialforum', 'enablegrading') === '1';
    }
    /**
     * Load the Moodle grade classes and one activity.
     * @param int $forumid
     * @return array
     */
    private static function activity(int $forumid): array {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        $activity = $DB->get_record('spatialforum', ['id' => $forumid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('spatialforum', $forumid, 0, false, MUST_EXIST);
        return [$activity, $cm, \context_module::instance($cm->id)];
    }

    /**
     * Grade item lookup never creates or resets grades during reads.
     * @param \stdClass $activity
     * @return \grade_item|false
     */
    public static function item(\stdClass $activity) {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        return \grade_item::fetch(['courseid' => $activity->course, 'itemtype' => 'mod',
            'itemmodule' => 'spatialforum', 'iteminstance' => $activity->id, 'itemnumber' => 0]);
    }

    /**
     * Validate configuration; existing manual results cannot be silently reinterpreted or discarded.
     * @param \stdClass $activity
     * @param \stdClass|null $existing
     */
    public static function validate_definition(\stdClass $activity, ?\stdClass $existing = null): void {
        global $DB;
        $grade = $activity->grade ?? ($existing->grade ?? 0);
        if (!is_numeric($grade) || !is_finite((float) $grade) || $grade > 10000) {
            throw new \moodle_exception('invalidgradedefinition', 'mod_spatialforum');
        }
        if ($grade < 0) {
            $scale = $DB->get_record('scale', ['id' => -(int) $grade]);
            if (
                (float) $grade !== (float) (int) $grade || !$scale
                    || ($scale->courseid && (int) $scale->courseid !== (int) $activity->course)
                    || count(explode(',', $scale->scale)) < 2
            ) {
                throw new \moodle_exception('invalidgradedefinition', 'mod_spatialforum');
            }
        }
        if ($existing && (float) $grade !== (float) $existing->grade) {
            $item = self::item($existing);
            if (
                $item && $DB->record_exists_select(
                    'grade_grades',
                    'itemid = ? AND (rawgrade IS NOT NULL OR finalgrade IS NOT NULL OR feedback IS NOT NULL)',
                    [$item->id]
                )
            ) {
                throw new \moodle_exception('gradedefinitionhasresults', 'mod_spatialforum');
            }
        }
    }

    /**
     * Require a current actor who can view the course/activity, including availability restrictions.
     * @param \stdClass $cm
     * @param \context_module $context
     */
    private static function require_view(\stdClass $cm, \context_module $context): void {
        global $USER;
        if (!self::enabled()) {
            throw new \moodle_exception('gradingdisabled', 'mod_spatialforum');
        }
        if (!isloggedin() || isguestuser() || !empty($USER->suspended) || !empty($USER->deleted)) {
            throw new \moodle_exception('gradeaccessdenied', 'mod_spatialforum');
        }
        require_capability('mod/spatialforum:view', $context);
        $course = get_course($cm->course);
        if (
            (!$course->visible && !has_capability('moodle/course:viewhiddencourses', \context_course::instance($course->id)))
                || !get_fast_modinfo($cm->course)->get_cm($cm->id)->uservisible
        ) {
            throw new \moodle_exception('gradeaccessdenied', 'mod_spatialforum');
        }
    }

    /**
     * Grading in visible groups still requires membership, unless access-all-groups is granted.
     * @param \stdClass $cm
     * @return array|null Null means all participants; an empty array means none
     */
    public static function allowed_groups(\stdClass $cm): ?array {
        global $CFG, $USER;
        require_once($CFG->libdir . '/grouplib.php');
        if (
            (int) groups_get_activity_groupmode($cm) === NOGROUPS
                || has_capability('moodle/site:accessallgroups', \context_module::instance($cm->id))
        ) {
            return null;
        }
        return array_map('intval', array_keys(groups_get_all_groups($cm->course, $USER->id, $cm->groupingid) ?: []));
    }

    /**
     * Check the target before returning any private grade, feedback or identity.
     * @param \stdClass $cm
     * @param \context_module $context
     * @param int $userid
     * @return \stdClass
     */
    private static function require_participant(\stdClass $cm, \context_module $context, int $userid): \stdClass {
        global $DB;
        self::require_view($cm, $context);
        require_capability('mod/spatialforum:grade', $context);
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0]);
        if (!$user || !is_enrolled($context, $user, 'mod/spatialforum:receivegrade', true)) {
            throw new \moodle_exception('gradeaccessdenied', 'mod_spatialforum');
        }
        $groups = self::allowed_groups($cm);
        if ($groups !== null) {
            $member = array_map('intval', array_keys(groups_get_all_groups($cm->course, $userid, $cm->groupingid) ?: []));
            if (!array_intersect($groups, $member)) {
                throw new \moodle_exception('gradeaccessdenied', 'mod_spatialforum');
            }
        }
        return $user;
    }

    /**
     * Read a grader's authorised target and a revision token for optimistic concurrency checks.
     * @param int $forumid
     * @param int $userid
     * @return array
     */
    public static function assessment(int $forumid, int $userid): array {
        [$activity, $cm, $context] = self::activity($forumid);
        $user = self::require_participant($cm, $context, $userid);
        $report = grade_get_grades($activity->course, 'mod', 'spatialforum', $activity->id, $userid);
        $item = self::item($activity);
        if (empty($activity->grade) || !$item || (int) $item->gradetype === GRADE_TYPE_NONE) {
            throw new \moodle_exception('gradingdisabled', 'mod_spatialforum');
        }
        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
        $reason = '';
        if (
            $item->is_locked($userid) || ($item->locktime && $item->locktime <= time())
                || ($grade && $grade->locktime && $grade->locktime <= time())
        ) {
            $reason = 'gradelocked';
        } else if ($grade && $grade->is_overridden()) {
            $reason = 'gradeoverridden';
        } else if (
            $item->is_calculated() || (float) $item->multfactor !== 1.0 || (float) $item->plusfactor !== 0.0
                || ($grade && !empty($grade->deductedmark))
                || ($grade && !empty($grade->feedback) && (int) $grade->feedbackformat !== (int) FORMAT_PLAIN)
        ) {
            $reason = 'gradeexternal';
        }
        $snapshot = [$activity->grade, $item->id, $item->gradetype, $item->grademax, $item->scaleid,
            $item->locked, $item->locktime, $item->calculation, $item->multfactor, $item->plusfactor,
            $grade ? (array) $grade : null];
        return ['user' => $user, 'item' => $item, 'grade' => $grade, 'result' => $report->items[0]->grades[$userid],
            'reason' => $reason, 'token' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR))];
    }

    /**
     * Validate a nullable manual score; zero is a real points grade and scale values start at one.
     * @param \grade_item $item
     * @param string $value
     * @return float|null
     */
    public static function parse_score(\grade_item $item, string $value): ?float {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^(?:\d+(?:[.,]\d{1,5})?|[.,]\d{1,5})$/D', $value)) {
            throw new \moodle_exception('invalidmanualgrade', 'mod_spatialforum');
        }
        $number = (float) str_replace(',', '.', $value);
        if (
            $number < $item->grademin || $number > $item->grademax
                || ((int) $item->gradetype === GRADE_TYPE_SCALE && $number !== floor($number))
        ) {
            throw new \moodle_exception('invalidmanualgrade', 'mod_spatialforum');
        }
        return $number;
    }

    /**
     * Save only after fresh capability, active-enrolment, group, protection and revision checks.
     * @param int $forumid
     * @param int $userid
     * @param string $value Empty means clear, never zero
     * @param string $feedback
     * @param string $expectedtoken
     */
    public static function save(int $forumid, int $userid, string $value, string $feedback, string $expectedtoken): void {
        global $DB, $USER;
        $lock = \core\lock\lock_config::get_lock_factory('mod_spatialforum')->get_lock('grade:' . $forumid . ':' . $userid, 10);
        if (!$lock) {
            throw new \moodle_exception('gradebusy', 'mod_spatialforum');
        }
        try {
            $state = self::assessment($forumid, $userid);
            if ($state['reason']) {
                throw new \moodle_exception($state['reason'], 'mod_spatialforum');
            }
            if (!hash_equals($state['token'], $expectedtoken)) {
                throw new \moodle_exception('gradestale', 'mod_spatialforum');
            }
            $number = self::parse_score($state['item'], $value);
            if (\core_text::strlen($feedback) > 10000) {
                throw new \moodle_exception('gradefeedbacklimit', 'mod_spatialforum');
            }
            $item = $state['item'];
            $transaction = $DB->start_delegated_transaction();
            try {
                $status = grade_update('mod/spatialforum', $item->courseid, 'mod', 'spatialforum', $forumid, 0, (object) [
                    'userid' => $userid, 'rawgrade' => $number, 'feedback' => $feedback,
                    'feedbackformat' => FORMAT_PLAIN, 'usermodified' => $USER->id, 'dategraded' => time(),
                ]);
                $fresh = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
                if ($status !== GRADE_UPDATE_OK || !$fresh || $fresh->is_locked() || $fresh->is_overridden()) {
                    throw new \moodle_exception('gradesavefailed', 'mod_spatialforum');
                }
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Own result only, filtered before returning any hidden grade or feedback.
     * @param int $forumid
     * @return \stdClass|null Null means unavailable
     */
    public static function own_result(int $forumid): ?\stdClass {
        global $USER;
        [$activity, $cm, $context] = self::activity($forumid);
        self::require_view($cm, $context);
        require_capability('moodle/grade:view', $context);
        if (
            !is_enrolled($context, $USER, 'mod/spatialforum:receivegrade', true) || empty($activity->grade)
                || !get_course($cm->course)->showgrades
        ) {
            return null;
        }
        $item = self::item($activity);
        $grade = $item ? \grade_grade::fetch(['itemid' => $item->id, 'userid' => $USER->id]) : false;
        if (!$item || (int) $item->gradetype === GRADE_TYPE_NONE || $item->is_hidden() || ($grade && $grade->is_hidden())) {
            return null;
        }
        $report = grade_get_grades($activity->course, 'mod', 'spatialforum', $activity->id, $USER->id);
        $result = $report->items[0]->grades[$USER->id] ?? null;
        return $result && !$result->hidden ? $result : null;
    }

    /**
     * Page only eligible, authorised participants; counts and names share the same SQL filter.
     * @param int $forumid
     * @param int $page
     * @param int $groupid
     * @return array
     */
    public static function roster(int $forumid, int $page = 0, int $groupid = 0): array {
        global $DB;
        [$activity, $cm, $context] = self::activity($forumid);
        self::require_view($cm, $context);
        require_capability('mod/spatialforum:grade', $context);
        if (empty($activity->grade)) {
            throw new \moodle_exception('gradingdisabled', 'mod_spatialforum');
        }
        $groups = self::allowed_groups($cm);
        $available = groups_get_all_groups($cm->course, 0, $cm->groupingid) ?: [];
        $permitted = $groups === null ? $available : array_intersect_key($available, array_flip($groups));
        if ($groupid && (!isset($available[$groupid]) || ($groups !== null && !in_array($groupid, $groups, true)))) {
            throw new \moodle_exception('gradeaccessdenied', 'mod_spatialforum');
        }
        if ($groupid) {
            $groups = [$groupid];
        }
        [$enrolled, $params] = get_enrolled_sql($context, 'mod/spatialforum:receivegrade', 0, true);
        $where = 'u.deleted = 0 AND u.suspended = 0';
        if ($groups !== null) {
            if (!$groups) {
                $where .= ' AND 1=0';
            } else {
                [$in, $groupvalues] = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED, 'sfgradegroup');
                $params += $groupvalues;
                $where .= " AND EXISTS (SELECT 1 FROM {groups_members} gm WHERE gm.userid = u.id AND gm.groupid $in)";
            }
        }
        $from = "{user} u JOIN ($enrolled) enrolled ON enrolled.id = u.id";
        $total = $DB->count_records_sql("SELECT COUNT(1) FROM $from WHERE $where", $params);
        $users = $DB->get_records_sql(
            "SELECT u.* FROM $from WHERE $where ORDER BY u.lastname, u.firstname, u.id",
            $params,
            max(0, $page) * 50,
            50
        );
        $report = grade_get_grades($activity->course, 'mod', 'spatialforum', $forumid, array_keys($users));
        return ['users' => $users, 'total' => $total, 'grades' => $report->items[0]->grades ?? [],
            'groups' => $permitted];
    }
}
