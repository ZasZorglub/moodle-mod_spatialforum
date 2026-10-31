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
 * Contribution-based activity completion.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_spatialforum\completion;

/**
 * Counts live contributions for the requested participant, never other authors.
 */
class custom_completion extends \core_completion\activity_custom_completion {
    /**
     * Evaluate a configured rule using current stored contributions.
     * @param string $rule
     * @return int
     */
    public function get_state(string $rule): int {
        global $DB;
        $this->validate_rule($rule);
        $activity = $DB->get_record('spatialforum', ['id' => $this->cm->instance], '*', MUST_EXIST);
        if (!empty($activity->importstate)) {
            return COMPLETION_INCOMPLETE;
        }
        $where = 'spatialforumid = :forumid AND userid = :userid AND deleted = 0';
        $params = ['forumid' => $activity->id, 'userid' => $this->userid];
        if ($rule !== 'completionposts') {
            $params['root'] = $activity->roottrackid;
            $where .= $rule === 'completiondiscussions'
                ? ' AND replytoid = 0 AND trackid = :root'
                : ' AND (replytoid <> 0 OR trackid <> :root)';
        }
        $count = $DB->count_records_select('spatialforum_posts', $where, $params);
        return $count >= (int) $activity->$rule ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Supported optional requirements.
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionposts', 'completionreplies', 'completiondiscussions'];
    }

    /**
     * Human-readable requirements shown by Moodle.
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        $descriptions = [];
        foreach (self::get_defined_custom_rules() as $rule) {
            $value = $this->cm->customdata['customcompletionrules'][$rule] ?? 0;
            $descriptions[$rule] = get_string('completiondetail:' . $rule, 'mod_spatialforum', $value);
        }
        return $descriptions;
    }

    /**
     * Moodle displays completion requirements in this order.
     * @return array
     */
    public function get_sort_order(): array {
        return ['completionview', 'completiondiscussions', 'completionreplies', 'completionposts',
            'completionusegrade', 'completionpassgrade'];
    }

    /**
     * Refresh a participant after a successful contribution or moderation change.
     * @param int $forumid
     * @param int $userid
     */
    public static function refresh(int $forumid, int $userid): void {
        if ($userid <= 0) {
            return;
        }
        $cm = get_coursemodule_from_instance('spatialforum', $forumid, 0, false, MUST_EXIST);
        $completion = new \completion_info(get_course($cm->course));
        if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $userid);
        }
    }
}
