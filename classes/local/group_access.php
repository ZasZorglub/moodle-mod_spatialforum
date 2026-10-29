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


namespace mod_spatialforum\local;

/**
 * Shared group boundary for every transport. Activity capabilities remain caller requirements.
 */
class group_access {
    /** @var \stdClass Activity configuration. */
    private \stdClass $activity;
    /** @var \stdClass Course module. */
    private \stdClass $cm;
    /** @var int Effective group mode, including course-forced mode. */
    private int $mode;
    /** @var bool Whether the actor may access every group. */
    private bool $all;
    /** @var array Visible group identifiers. */
    private array $visible = [];
    /** @var array Groups in which the actor can participate. */
    private array $own = [];

    /**
     * Resolve group membership once for a batch, without persistent permission caches.
     *
     * @param \stdClass $activity
     * @param \stdClass $cm
     * @param int $userid
     */
    public function __construct(\stdClass $activity, \stdClass $cm, int $userid = 0) {
        global $USER, $CFG;
        require_once($CFG->libdir . '/grouplib.php');
        $userid = $userid ?: (int) $USER->id;
        $this->activity = $activity;
        $this->cm = $cm;
        $this->mode = (int) groups_get_activity_groupmode($cm);
        $this->all = has_capability('moodle/site:accessallgroups', \context_module::instance($cm->id), $userid);
        if ($this->mode !== NOGROUPS) {
            $this->visible = array_keys(groups_get_activity_allowed_groups($cm, $userid) ?: []);
            $this->own = array_keys(groups_get_all_groups($cm->course, $userid, $cm->groupingid) ?: []);
        }
    }

    /**
     * Construct the policy for a stored activity, including an explicitly named recipient.
     *
     * @param int $forumid
     * @param int $userid
     * @return self
     */
    public static function for_activity(int $forumid, int $userid = 0): self {
        global $DB;
        $activity = $DB->get_record('spatialforum', ['id' => $forumid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('spatialforum', $forumid, 0, false, MUST_EXIST);
        return new self($activity, $cm, $userid);
    }

    /**
     * Whether audience isolation applies to this activity.
     *
     * @return bool
     */
    public function uses_groups(): bool {
        return $this->mode !== NOGROUPS;
    }

    /**
     * Existing conversations require an explicit audience review before grouped use.
     *
     * @return bool
     */
    public function pending(): bool {
        return $this->mode !== NOGROUPS && empty($this->activity->groupready);
    }

    /**
     * Grouped content is never made shared because a group was deleted or unmapped.
     *
     * @param \stdClass $post
     * @return bool
     */
    public function can_view(\stdClass $post): bool {
        if (!empty($this->activity->importstate)) {
            return false;
        }
        if ((int) $post->spatialforumid !== (int) $this->activity->id) {
            return false;
        }
        if ($this->mode === NOGROUPS) {
            return true;
        }
        if ($this->pending()) {
            return $this->all;
        }
        $groupid = (int) ($post->groupid ?? -1);
        return $groupid === -1 || in_array($groupid, $this->visible, true);
    }

    /**
     * Visible groups permit reading other groups, not contributing to them.
     *
     * @param int $groupid
     * @return bool
     */
    public function can_write(int $groupid): bool {
        if (!empty($this->activity->importstate)) {
            return false;
        }
        if ($this->pending()) {
            return false;
        }
        if ($this->mode === NOGROUPS) {
            return true;
        }
        if ($groupid === -1) {
            return $this->all || $this->mode === VISIBLEGROUPS;
        }
        return in_array($groupid, $this->visible, true)
            && ($this->all || in_array($groupid, $this->own, true));
    }

    /**
     * Starting a shared conversation requires access-all-groups, unlike a visible shared reply.
     *
     * @param int $groupid
     * @return bool
     */
    public function can_start(int $groupid): bool {
        return $this->can_write($groupid)
            && ($this->mode === NOGROUPS || $groupid !== -1 || $this->all);
    }

    /**
     * Deny direct requests before content or metadata is returned.
     *
     * @param \stdClass $post
     * @param bool $write
     */
    public function require_post(\stdClass $post, bool $write = false): void {
        if (!$this->can_view($post) || ($write && !$this->can_write((int) ($post->groupid ?? -1)))) {
            throw new \moodle_exception('groupaccessdenied', 'mod_spatialforum');
        }
    }

    /**
     * Root track operations span every audience and require access-all-groups.
     *
     * @param \stdClass $track
     * @param bool $write
     */
    public function require_track(\stdClass $track, bool $write = false): void {
        global $DB;
        if (!empty($this->activity->importstate)) {
            throw new \moodle_exception('importinprogress', 'mod_spatialforum');
        }
        if ((int) $track->spatialforumid !== (int) $this->activity->id) {
            throw new \moodle_exception('groupaccessdenied', 'mod_spatialforum');
        }
        if ($this->mode === NOGROUPS) {
            return;
        }
        if ((int) $track->id === (int) $this->activity->roottrackid) {
            if (($write && $this->mode !== NOGROUPS && !$this->all) || ($this->pending() && !$this->all)) {
                throw new \moodle_exception('groupaccessdenied', 'mod_spatialforum');
            }
            return;
        }
        $origin = $DB->get_record('spatialforum_posts', ['id' => $track->originpostid,
            'spatialforumid' => $this->activity->id]);
        if (!$origin) {
            throw new \moodle_exception('groupaccessdenied', 'mod_spatialforum');
        }
        $this->require_post($origin, $write);
    }

    /**
     * Explicitly resolve the audience for a new top-level contribution.
     *
     * @return int
     */
    public function selected_group(): int {
        return $this->mode === NOGROUPS ? -1 : ((int) groups_get_activity_group($this->cm, true) ?: -1);
    }

    /**
     * SQL filter for unread counts and other batch readers.
     *
     * @param string $alias Trusted internal column prefix.
     * @return array
     */
    public function sql(string $alias = 'p'): array {
        global $DB;
        if (!empty($this->activity->importstate)) {
            return ['1=0', []];
        }
        if ($this->mode === NOGROUPS || ($this->pending() && $this->all)) {
            return ['1=1', []];
        }
        if ($this->pending()) {
            return ['1=0', []];
        }
        [$in, $params] = $DB->get_in_or_equal([-1, ...$this->visible], SQL_PARAMS_NAMED, 'sfgroup');
        return ["{$alias}.groupid {$in}", $params];
    }

    /**
     * Assign one deliberately selected audience to the legacy activity, once.
     *
     * @param int $forumid
     * @param int $groupid
     * @param bool $confirmed
     */
    public static function review_legacy(int $forumid, int $groupid, bool $confirmed): void {
        global $DB;
        $activity = $DB->get_record('spatialforum', ['id' => $forumid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('spatialforum', $forumid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        require_capability('moodle/course:manageactivities', $context);
        require_capability('moodle/site:accessallgroups', $context);
        $groups = groups_get_all_groups($cm->course, 0, $cm->groupingid) ?: [];
        if (!$confirmed || ($groupid !== -1 && !isset($groups[$groupid]))) {
            throw new \moodle_exception('groupaccessdenied', 'mod_spatialforum');
        }
        $lock = \core\lock\lock_config::get_lock_factory('mod_spatialforum')->get_lock('groupreview:' . $forumid, 10);
        if (!$lock) {
            throw new \moodle_exception('groupreviewbusy', 'mod_spatialforum');
        }
        try {
            if (
                !$DB->get_field('spatialforum', 'groupready', ['id' => $forumid])
                && $DB->record_exists_sql(
                    "SELECT t.id FROM {spatialforum_tracks} t
                    WHERE t.spatialforumid = :forumid AND t.id <> :rootid
                      AND NOT EXISTS (SELECT p.id FROM {spatialforum_posts} p
                                       WHERE p.id = t.originpostid AND p.spatialforumid = t.spatialforumid)
                      AND EXISTS (SELECT p2.id FROM {spatialforum_posts} p2 WHERE p2.trackid = t.id)",
                    ['forumid' => $forumid, 'rootid' => $activity->roottrackid]
                )
            ) {
                throw new \moodle_exception('groupreviewstructure', 'mod_spatialforum');
            }
            $transaction = $DB->start_delegated_transaction();
            if (!$DB->get_field('spatialforum', 'groupready', ['id' => $forumid])) {
                $DB->set_field('spatialforum_posts', 'groupid', $groupid, ['spatialforumid' => $forumid]);
                $DB->set_field('spatialforum', 'groupready', 1, ['id' => $forumid]);
            }
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }
    }
}
