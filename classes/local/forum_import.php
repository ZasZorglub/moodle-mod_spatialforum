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
 * Preview and resumable copy of supported standard-forum content.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_spatialforum\local;

/**
 * Source stays read-only; incomplete copies are inaccessible and never send notifications.
 */
class forum_import {
    /** Copy stage. */
    public const COPYING = 0;
    /** Rewrite source references after every post has a target identifier. */
    public const REWRITING = 1;
    /** Requires explicit retry or discard. */
    public const FAILED = 2;
    /** Ready for teacher review, still hidden. */
    public const COMPLETE = 3;
    /** Import cancelled by erasure or discard. */
    public const CANCELLED = 4;

    /**
     * Authorise a whole-forum copy before returning source counts or metadata.
     * @param int $sourcecmid
     * @return array
     */
    private static function source(int $sourcecmid): array {
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        if (!isloggedin() || isguestuser() || !empty($USER->deleted) || !empty($USER->suspended)) {
            throw new \moodle_exception('importdenied', 'mod_spatialforum');
        }
        $cm = get_coursemodule_from_id('forum', $sourcecmid, 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        $context = \context_module::instance($cm->id);
        foreach (['mod/spatialforum:import', 'moodle/course:manageactivities', 'mod/spatialforum:addinstance'] as $cap) {
            require_capability($cap, \context_course::instance($course->id));
        }
        require_capability('mod/forum:viewdiscussion', $context);
        if (
            !get_fast_modinfo($course)->get_cm($cm->id)->uservisible
                || (!$course->visible && !has_capability('moodle/course:viewhiddencourses', \context_course::instance($course->id)))
                || ((int) groups_get_activity_groupmode($cm) !== NOGROUPS
                    && !has_capability('moodle/site:accessallgroups', $context))
        ) {
            throw new \moodle_exception('importdenied', 'mod_spatialforum');
        }
        return [$DB->get_record('forum', ['id' => $cm->instance], '*', MUST_EXIST), $cm, $course, $context];
    }

    /**
     * Deduplicated blocker codes, with no private post body copied to an error log.
     * @param array $issues
     * @param string $code
     */
    private static function issue(array &$issues, string $code): void {
        $issues[$code] = $code;
    }

    /**
     * Site/course media limits apply equally to historical copied files.
     * @param \stdClass $course
     * @param \context_module $context
     * @return array
     */
    private static function limits(\stdClass $course, \context_module $context): array {
        global $CFG;
        $options = media::editor_options($context);
        $bytes = get_config('mod_spatialforum', 'maxbytes');
        $options['maxbytes'] = get_max_upload_file_size(
            $CFG->maxbytes,
            $course->maxbytes,
            $bytes === false ? 20971520 : max(0, (int) $bytes)
        );
        return $options;
    }

    /**
     * Preview does not create activities, jobs, maps, files, read markers or notifications.
     * Full-source verification streams bodies; only the parent graph stays in memory.
     * @param int $sourcecmid
     * @return array
     */
    public static function preview(int $sourcecmid): array {
        global $DB;
        [$forum, $cm, $course, $context] = self::source($sourcecmid);
        $issues = [];
        $hash = hash_init('sha256');
        hash_update($hash, json_encode([$forum, $cm, $course->groupmode, $course->groupmodeforce, $course->maxbytes]));
        // All standard Moodle forum types share the same discussion/post tables.
        // Their presentation rules are converted into the spatial thread model.
        if ($forum->lockdiscussionafter || $forum->cutoffdate) {
            self::issue($issues, 'timed');
        }
        if ($DB->record_exists('role_assignments', ['contextid' => $context->id])) {
            self::issue($issues, 'permissions');
        }
        // A copied activity must not broaden an inherited role's read/group access.
        $coursecontext = \context_course::instance($course->id);
        foreach ($DB->get_fieldset_select('role', 'id', '1=1', []) as $roleid) {
            foreach (
                ['mod/forum:viewdiscussion' => 'mod/spatialforum:view',
                    'moodle/site:accessallgroups' => 'moodle/site:accessallgroups'] as $sourcecap => $targetcap
            ) {
                $sourcepermission = role_context_capabilities($roleid, $context, $sourcecap)[$sourcecap] ?? 0;
                $targetpermission = role_context_capabilities($roleid, $coursecontext, $targetcap)[$targetcap] ?? 0;
                hash_update($hash, json_encode([$roleid, $sourcecap, $sourcepermission, $targetpermission]));
                if (
                    ((int) $targetpermission === CAP_ALLOW && (int) $sourcepermission !== CAP_ALLOW)
                        || ((int) $sourcepermission === CAP_PROHIBIT && (int) $targetpermission !== CAP_PROHIBIT)
                ) {
                    self::issue($issues, 'permissions');
                }
            }
        }
        $discussions = $DB->get_records('forum_discussions', ['forum' => $forum->id], 'id');
        foreach ($discussions as $discussion) {
            hash_update($hash, json_encode($discussion));
            if ($discussion->timestart || $discussion->timeend || $discussion->timelocked) {
                self::issue($issues, 'timed');
            }
            if (
                $discussion->groupid != -1 && (!$discussion->groupid || !$DB->record_exists(
                    'groups',
                    ['id' => $discussion->groupid, 'courseid' => $course->id]
                ))
            ) {
                self::issue($issues, 'groups');
            }
            if (
                $discussion->groupid > 0 && $cm->groupingid && !$DB->record_exists(
                    'groupings_groups',
                    ['groupingid' => $cm->groupingid, 'groupid' => $discussion->groupid]
                )
            ) {
                self::issue($issues, 'groups');
            }
        }
        $options = self::limits($course, $context);
        hash_update($hash, json_encode($options));
        $graph = [];
        $files = 0;
        $rs = $DB->get_recordset_sql('SELECT p.*, u.deleted AS authordeleted FROM {forum_posts} p
            JOIN {forum_discussions} d ON d.id = p.discussion
            LEFT JOIN {user} u ON u.id = p.userid WHERE d.forum = ? ORDER BY p.id', [$forum->id]);
        foreach ($rs as $post) {
            hash_update($hash, json_encode([$post->id, $post->discussion, $post->parent, $post->userid, $post->created,
                $post->modified, $post->subject, $post->message, $post->messageformat, $post->deleted,
                $post->privatereplyto, $post->authordeleted]));
            $graph[$post->id] = ['parent' => (int) $post->parent, 'discussion' => (int) $post->discussion];
            if ($post->deleted || $post->privatereplyto || $post->authordeleted === null || $post->authordeleted) {
                self::issue($issues, 'restricted');
            }
            if (
                trim($post->subject) === '' || !in_array(
                    (int) $post->messageformat,
                    [(int) FORMAT_MOODLE, (int) FORMAT_HTML, (int) FORMAT_PLAIN, (int) FORMAT_MARKDOWN],
                    true
                )
            ) {
                self::issue($issues, 'format');
            }
            if (str_contains($post->message, 'draftfile.php')) {
                self::issue($issues, 'missingfile');
            }
            $files += self::check_files($context->id, (int) $post->id, $options, $hash, $issues);
            preg_match_all('~@@PLUGINFILE@@/([^\s"\'<>\)]+)~u', $post->message, $references);
            foreach ($references[1] as $reference) {
                $path = rawurldecode(parse_url('https://example.org/' . html_entity_decode($reference), PHP_URL_PATH));
                if (
                    !get_file_storage()->get_file(
                        $context->id,
                        'mod_forum',
                        'post',
                        $post->id,
                        dirname($path) === '/' ? '/' : dirname($path) . '/',
                        basename($path)
                    )
                ) {
                    self::issue($issues, 'missingfile');
                }
            }
        }
        $rs->close();
        $files += self::check_files($context->id, 0, $options, $hash, $issues, true);
        $depths = [];
        foreach ($graph as $id => $node) {
            $path = [];
            $cursor = $id;
            while (!isset($depths[$cursor])) {
                if (!isset($graph[$cursor]) || isset($path[$cursor]) || count($path) >= 12) {
                    self::issue($issues, 'structure');
                    break;
                }
                $entry = $graph[$cursor];
                $path[$cursor] = $cursor;
                if (!$entry['parent']) {
                    if ((int) ($discussions[$entry['discussion']]->firstpost ?? 0) !== (int) $cursor) {
                        self::issue($issues, 'structure');
                    }
                    $depths[$cursor] = 1;
                    break;
                }
                if (($graph[$entry['parent']]['discussion'] ?? 0) !== $entry['discussion']) {
                    self::issue($issues, 'structure');
                    break;
                }
                $cursor = $entry['parent'];
            }
            $depth = $depths[$cursor] ?? 12;
            foreach (array_reverse($path) as $ancestor) {
                if (!isset($depths[$ancestor])) {
                    $depths[$ancestor] = ++$depth;
                }
            }
        }
        foreach ($discussions as $discussion) {
            if (!isset($graph[$discussion->firstpost]) || $graph[$discussion->firstpost]['parent']) {
                self::issue($issues, 'structure');
            }
        }
        $maximum = $depths ? max($depths) : 1;
        if ($maximum > 12) {
            self::issue($issues, 'structure');
        }
        return ['source' => $forum, 'cm' => $cm, 'course' => $course, 'fingerprint' => hash_final($hash),
            'discussions' => count($discussions), 'posts' => count($graph), 'files' => $files, 'maxdepth' => $maximum,
            'issues' => array_values($issues), 'graph' => $graph, 'depths' => $depths];
    }

    /**
     * Validate and fingerprint every source file, including stored attachments.
     * @param int $contextid
     * @param int $postid
     * @param array $options
     * @param mixed $hash
     * @param array $issues
     * @param bool $intro
     * @return int
     */
    private static function check_files(
        int $contextid,
        int $postid,
        array $options,
        $hash,
        array &$issues,
        bool $intro = false
    ): int {
        $count = 0;
        $bytes = 0;
        foreach ($intro ? ['intro'] : ['post', 'attachment'] as $area) {
            foreach (get_file_storage()->get_area_files($contextid, 'mod_forum', $area, $postid, 'id', false) as $file) {
                $count++;
                $bytes += $file->get_filesize();
                hash_update($hash, json_encode([$area, $postid, $file->get_filepath(), $file->get_filename(),
                    $file->get_filesize(), $file->get_contenthash(), $file->get_status(), $file->get_timemodified(),
                    $file->get_userid(), $file->get_mimetype(), $file->get_timecreated()]));
                $extension = '.' . strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
                if (
                    !in_array($extension, $options['accepted_types'], true) || $file->get_status()
                        || ($options['maxbytes'] && $file->get_filesize() > $options['maxbytes'])
                ) {
                    self::issue($issues, 'files');
                }
            }
        }
        if ($count > $options['maxfiles'] || $bytes > $options['areamaxbytes']) {
            self::issue($issues, 'files');
        }
        return $count;
    }

    /**
     * Confirm the exact preview once. A repeated request returns the existing job/copy.
     * @param int $sourcecmid
     * @param string $fingerprint
     * @param string $name
     * @param bool $confirmed
     * @return int Job ID
     */
    public static function confirm(int $sourcecmid, string $fingerprint, string $name, bool $confirmed): int {
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot . '/course/modlib.php');
        $preview = self::preview($sourcecmid);
        $name = trim($name);
        if (
            !$confirmed || $preview['issues'] || $name === '' || \core_text::strlen($name) > 255
                || !hash_equals($preview['fingerprint'], $fingerprint)
        ) {
            throw new \moodle_exception('importpreviewchanged', 'mod_spatialforum');
        }
        $key = hash('sha256', json_encode([$USER->id, $sourcecmid, $fingerprint, $name]));
        $lock = self::lock('request:' . $key);
        try {
            if ($id = $DB->get_field('spatialforum_imports', 'id', ['requestkey' => $key])) {
                return (int) $id;
            }
            $transaction = $DB->start_delegated_transaction();
            try {
                $source = $preview['source'];
                $cm = $preview['cm'];
                $info = add_moduleinfo((object) [
                    'modulename' => 'spatialforum', 'module' => $DB->get_field('modules', 'id', ['name' => 'spatialforum']),
                    'section' => $DB->get_field('course_sections', 'section', ['id' => $cm->section]),
                    'name' => $name, 'intro' => $source->intro, 'introformat' => $source->introformat,
                    'visible' => 0, 'visibleoncoursepage' => 1, 'groupmode' => $cm->groupmode, 'groupingid' => $cm->groupingid,
                    'availability' => $cm->availability, 'completion' => 0, 'grade' => 0, 'locked' => 1, 'importstate' => 1,
                    'maxdepth' => max(3, $preview['maxdepth']), 'enablecolors' => 1, 'enablelikes' => 1,
                ], $preview['course']);
                $jobid = $DB->insert_record('spatialforum_imports', (object) [
                    'sourcecmid' => $sourcecmid, 'courseid' => $cm->course, 'targetid' => $info->instance,
                    'userid' => $USER->id, 'requestkey' => $key, 'fingerprint' => $fingerprint,
                    'status' => self::COPYING, 'totalposts' => $preview['posts'], 'copiedposts' => 0,
                    'errorcode' => '', 'timecreated' => time(), 'timemodified' => time(),
                ]);
                foreach ($preview['graph'] as $postid => $node) {
                    $DB->insert_record('spatialforum_import_map', (object) [
                        'importid' => $jobid, 'sourcepostid' => $postid, 'sourceparentid' => $node['parent'],
                        'discussionid' => $node['discussion'], 'depth' => $preview['depths'][$postid],
                        'targetpostid' => 0, 'rewritten' => 0,
                    ]);
                }
                $targetcm = get_coursemodule_from_instance('spatialforum', $info->instance, 0, false, MUST_EXIST);
                self::copy_files(\context_module::instance($cm->id)->id, \context_module::instance($targetcm->id)->id, 0, 0, true);
                self::queue($jobid, (int) $USER->id);
                $transaction->allow_commit();
                return (int) $jobid;
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Schedule one idempotent bounded copy/rewrite page under the authorising user.
     * @param int $jobid
     * @param int $userid
     */
    public static function queue(int $jobid, int $userid): void {
        $task = new \mod_spatialforum\task\import_forum();
        $task->set_component('mod_spatialforum');
        $task->set_userid($userid);
        $task->set_custom_data(['importid' => $jobid]);
        // The executing task itself must not prevent its continuation from being queued.
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * A course importer can inspect an existing job without exposing its source post content.
     * @param int $jobid
     * @return \stdClass
     */
    public static function job(int $jobid): \stdClass {
        global $DB;
        $job = $DB->get_record('spatialforum_imports', ['id' => $jobid], '*', MUST_EXIST);
        require_capability('mod/spatialforum:import', \context_course::instance($job->courseid));
        require_capability('moodle/course:manageactivities', \context_course::instance($job->courseid));
        $cm = get_coursemodule_from_instance('spatialforum', $job->targetid, 0, false, MUST_EXIST);
        require_capability('mod/spatialforum:view', \context_module::instance($cm->id));
        return $job;
    }

    /**
     * Process at most 100 posts. Source integrity is rechecked before each page and completion.
     * @param int $jobid
     */
    public static function execute(int $jobid): void {
        global $DB, $USER;
        $lock = self::lock('job:' . $jobid);
        try {
            $job = $DB->get_record('spatialforum_imports', ['id' => $jobid]);
            if (!$job) {
                return;
            }
            if (!in_array((int) $job->status, [self::COPYING, self::REWRITING], true)) {
                return;
            }
            try {
                self::job($jobid);
                if (!$job->userid || (int) $USER->id !== (int) $job->userid) {
                    throw new \moodle_exception('importdenied', 'mod_spatialforum');
                }
                $preview = self::preview($job->sourcecmid);
                if ($preview['issues'] || !hash_equals($job->fingerprint, $preview['fingerprint'])) {
                    throw new \moodle_exception('importpreviewchanged', 'mod_spatialforum');
                }
                $target = $DB->get_record('spatialforum', ['id' => $job->targetid, 'importstate' => 1], '*', MUST_EXIST);
                $targetcm = get_coursemodule_from_instance('spatialforum', $target->id, 0, false, MUST_EXIST);
                $targetcontext = \context_module::instance($targetcm->id);
                if (
                    (int) groups_get_activity_groupmode($targetcm) !== (int) groups_get_activity_groupmode($preview['cm'])
                        || (int) $targetcm->groupingid !== (int) $preview['cm']->groupingid
                        || json_decode($targetcm->availability ?? '') != json_decode($preview['cm']->availability ?? '')
                        || $DB->record_exists('role_assignments', ['contextid' => $targetcontext->id])
                        || $DB->record_exists_select(
                            'role_capabilities',
                            'contextid = ? AND capability IN (?, ?)',
                            [$targetcontext->id, 'mod/spatialforum:view', 'moodle/site:accessallgroups']
                        )
                ) {
                    throw new \moodle_exception('importinconsistent', 'mod_spatialforum');
                }
                set_coursemodule_visible($targetcm->id, 0);
                $sourcecontext = \context_module::instance($preview['cm']->id);
                $where = ['importid' => $jobid]
                    + ((int) $job->status === self::COPYING ? ['targetpostid' => 0] : ['rewritten' => 0]);
                $maps = $DB->get_records('spatialforum_import_map', $where, 'depth, sourcepostid', '*', 0, 100);
                foreach ($maps as $map) {
                    $post = $DB->get_record('forum_posts', ['id' => $map->sourcepostid], '*', MUST_EXIST);
                    $transaction = $DB->start_delegated_transaction();
                    try {
                        if ((int) $job->status === self::COPYING) {
                            $parent = $map->sourceparentid ? $DB->get_record(
                                'spatialforum_import_map',
                                ['importid' => $jobid, 'sourcepostid' => $map->sourceparentid],
                                '*',
                                MUST_EXIST
                            ) : null;
                            $trackid = $target->roottrackid;
                            if ($parent) {
                                $parentpost = $DB->get_record(
                                    'spatialforum_posts',
                                    ['id' => $parent->targetpostid],
                                    '*',
                                    MUST_EXIST
                                );
                                $trackid = $DB->insert_record('spatialforum_tracks', (object) [
                                    'spatialforumid' => $target->id, 'parenttrackid' => $parentpost->trackid,
                                    'originpostid' => $parentpost->id, 'name' => $post->subject, 'colour' => '#ffffff',
                                    'locked' => 0, 'timecreated' => $post->created, 'timemodified' => $post->modified,
                                ]);
                            }
                            $discussion = $DB->get_record('forum_discussions', ['id' => $post->discussion], '*', MUST_EXIST);
                            // Administrative historical copy: no author impersonation, events, reads or fan-out.
                            $map->targetpostid = $DB->insert_record('spatialforum_posts', (object) [
                                'spatialforumid' => $target->id, 'trackid' => $trackid, 'groupid' => $discussion->groupid,
                                'replytoid' => $parent ? $parent->targetpostid : 0, 'showparent' => 0, 'replyparts' => null,
                                'userid' => $post->userid, 'subject' => $post->subject,
                                'discussionname' => $parent ? null : $discussion->name,
                                'message' => self::inline_prefix($post->message), 'messageformat' => $post->messageformat,
                                'deleted' => 0, 'timecreated' => $post->created, 'timemodified' => $post->modified,
                            ]);
                            if (self::copy_files($sourcecontext->id, $targetcontext->id, $post->id, $map->targetpostid)) {
                                $DB->set_field('spatialforum_posts', 'importedattachments', 1, ['id' => $map->targetpostid]);
                            }
                        } else {
                            $copy = $DB->get_record('spatialforum_posts', ['id' => $map->targetpostid,
                                'spatialforumid' => $target->id, 'userid' => $post->userid, 'deleted' => 0], '*', MUST_EXIST);
                            $copy->message = self::rewrite(self::inline_prefix($post->message), $job, $targetcm, $targetcontext);
                            $DB->update_record('spatialforum_posts', $copy);
                            $map->rewritten = 1;
                        }
                        $DB->update_record('spatialforum_import_map', $map);
                        $transaction->allow_commit();
                    } catch (\Throwable $e) {
                        $transaction->rollback($e);
                    }
                }
                $job->copiedposts = $DB->count_records_select(
                    'spatialforum_import_map',
                    'importid = ? AND targetpostid > 0',
                    [$jobid]
                );
                if (!$maps) {
                    if ((int) $job->status === self::COPYING) {
                        $job->status = self::REWRITING;
                    } else {
                        if (
                            $job->copiedposts != $job->totalposts
                                || $DB->count_records('spatialforum_posts', ['spatialforumid' => $target->id]) != $job->totalposts
                        ) {
                            throw new \moodle_exception('importinconsistent', 'mod_spatialforum');
                        }
                        $DB->set_field('spatialforum', 'locked', 0, ['id' => $target->id]);
                        $DB->set_field(
                            'spatialforum',
                            'intro',
                            self::rewrite($preview['source']->intro, $job, $targetcm, $targetcontext),
                            ['id' => $target->id]
                        );
                        $DB->set_field('spatialforum', 'importstate', 0, ['id' => $target->id]);
                        $job->status = self::COMPLETE;
                    }
                }
                $job->timemodified = time();
                $DB->update_record('spatialforum_imports', $job);
                if ((int) $job->status !== self::COMPLETE) {
                    self::queue($jobid, (int) $job->userid);
                }
            } catch (\Throwable $e) {
                $job->status = self::FAILED;
                $job->errorcode = $e instanceof \moodle_exception ? $e->errorcode : 'importfailed';
                $job->timemodified = time();
                $DB->update_record('spatialforum_imports', $job);
                throw $e;
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * Retry a failed immutable snapshot, or discard only the explicitly identified hidden copy.
     * @param int $jobid
     * @param bool $discard
     */
    public static function recover(int $jobid, bool $discard = false): void {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/course/lib.php');
        $lock = self::lock('job:' . $jobid);
        try {
            $job = self::job($jobid);
            if (!in_array((int) $job->status, [self::FAILED, self::COPYING, self::REWRITING, self::CANCELLED], true)) {
                throw new \moodle_exception('importdenied', 'mod_spatialforum');
            }
            $cm = get_coursemodule_from_instance('spatialforum', $job->targetid, 0, false, MUST_EXIST);
            $target = $DB->get_record('spatialforum', ['id' => $job->targetid, 'importstate' => 1], '*', MUST_EXIST);
            if ($cm->visible) {
                throw new \moodle_exception('importdenied', 'mod_spatialforum');
            }
            if ($discard) {
                // An incomplete copy must never enter the recycle bin as a publishable backup.
                $forced = $CFG->forced_plugin_settings['tool_recyclebin'] ?? [];
                $CFG->forced_plugin_settings['tool_recyclebin']['coursebinenable'] = 0;
                try {
                    $actions = \core_courseformat\formatactions::cm($cm->course);
                    // The actions class predates its delete method, which was added in Moodle 5.2.
                    if (method_exists($actions, 'delete')) {
                        $actions->delete($cm->id);
                    } else {
                        course_delete_module($cm->id);
                    }
                } finally {
                    $CFG->forced_plugin_settings['tool_recyclebin'] = $forced;
                }
                return;
            }
            if ((int) $job->status === self::CANCELLED) {
                throw new \moodle_exception('importdenied', 'mod_spatialforum');
            }
            $preview = self::preview($job->sourcecmid);
            if ($preview['issues'] || !hash_equals($job->fingerprint, $preview['fingerprint'])) {
                throw new \moodle_exception('importpreviewchanged', 'mod_spatialforum');
            }
            $job->userid = $USER->id;
            $job->status = $DB->record_exists('spatialforum_import_map', ['importid' => $jobid, 'targetpostid' => 0])
                ? self::COPYING : self::REWRITING;
            $job->errorcode = '';
            $DB->update_record('spatialforum_imports', $job);
            self::queue($jobid, (int) $USER->id);
        } finally {
            $lock->release();
        }
    }

    /**
     * Prefix inline placeholders without altering the original message format.
     * @param string $message
     * @return string
     */
    private static function inline_prefix(string $message): string {
        return str_replace('@@PLUGINFILE@@/', '@@PLUGINFILE@@/imported-inline/', $message);
    }

    /**
     * Copy File API objects with distinct attachment/inline paths, preserving names and content.
     * @param int $sourcecontext
     * @param int $targetcontext
     * @param int $sourcepost
     * @param int $targetpost
     * @param bool $intro
     * @return int Number of attachments copied
     */
    private static function copy_files(
        int $sourcecontext,
        int $targetcontext,
        int $sourcepost,
        int $targetpost,
        bool $intro = false
    ): int {
        $fs = get_file_storage();
        $attachments = 0;
        foreach ($intro ? ['intro'] : ['post', 'attachment'] as $area) {
            foreach ($fs->get_area_files($sourcecontext, 'mod_forum', $area, $sourcepost, 'id', false) as $file) {
                $attachments += $area === 'attachment' ? 1 : 0;
                $prefix = $area === 'post' ? '/imported-inline' : '/imported-attachments';
                $fs->create_file_from_storedfile([
                    'contextid' => $targetcontext, 'component' => 'mod_spatialforum', 'filearea' => $intro ? 'intro' : 'post',
                    'itemid' => $targetpost, 'filepath' => $intro ? $file->get_filepath() : $prefix . $file->get_filepath(),
                    'filename' => $file->get_filename(), 'userid' => $file->get_userid(),
                    'timecreated' => $file->get_timecreated(), 'timemodified' => $file->get_timemodified(),
                ], $file);
            }
        }
        return $attachments;
    }

    /**
     * Rewrite only same-site read/file references into this job's already mapped posts.
     * @param string $message
     * @param \stdClass $job
     * @param \stdClass $targetcm
     * @param \context_module $targetcontext
     * @return string
     */
    private static function rewrite(string $message, \stdClass $job, \stdClass $targetcm, \context_module $targetcontext): string {
        global $DB, $CFG;
        $base = parse_url($CFG->wwwroot);
        $basepath = rtrim($base['path'] ?? '', '/');
        $sourcecontext = \context_module::instance($job->sourcecmid);
        return preg_replace_callback(
            '~https?://[^\s"\'<>\)\]]+|(?<![\w:/])/(?:[^\s"\'<>\)\]]+)~u',
            function ($match) use ($job, $targetcm, $targetcontext, $sourcecontext, $base, $basepath, $DB) {
                $uri = html_entity_decode($match[0], ENT_QUOTES | ENT_HTML5);
                $parts = parse_url($uri);
                if (
                    !$parts || (isset($parts['host']) && (strtolower($parts['host']) !== strtolower($base['host'])
                        || ($parts['port'] ?? null) != ($base['port'] ?? null)))
                ) {
                    return $match[0];
                }
                parse_str($parts['query'] ?? '', $query);
                $path = $parts['path'] ?? '';
                $url = null;
                if ($path === $basepath . '/mod/forum/view.php' && (int) ($query['id'] ?? 0) === (int) $job->sourcecmid) {
                    $url = new \moodle_url('/mod/spatialforum/view.php', ['id' => $targetcm->id]);
                } else if ($path === $basepath . '/mod/forum/discuss.php' && !empty($query['d'])) {
                    $discussion = $DB->get_record('forum_discussions', ['id' => (int) $query['d']]);
                    $postid = $discussion->firstpost ?? 0;
                    if (preg_match('/^p(\d+)$/', $parts['fragment'] ?? '', $anchor)) {
                        $postid = (int) $anchor[1];
                    } else if (!empty($query['parent'])) {
                        $postid = (int) $query['parent'];
                    }
                    $map = $DB->get_record('spatialforum_import_map', ['importid' => $job->id,
                        'sourcepostid' => $postid, 'discussionid' => (int) $query['d']]);
                    if ($map) {
                        $url = new \moodle_url(
                            '/mod/spatialforum/view.php',
                            ['id' => $targetcm->id, 'postid' => $map->targetpostid],
                            'post-' . $map->targetpostid
                        );
                    }
                } else {
                    $file = $path === $basepath . '/pluginfile.php' ? ($query['file'] ?? '')
                        : substr($path, strlen($basepath . '/pluginfile.php'));
                    if (
                        str_starts_with($path, $basepath . '/pluginfile.php') && preg_match(
                            '~^/' . $sourcecontext->id . '/mod_forum/intro/(?:0/)?(.*)$~',
                            rawurldecode($file),
                            $intro
                        )
                    ) {
                        $fullpath = '/' . $intro[1];
                        $url = \moodle_url::make_pluginfile_url(
                            $targetcontext->id,
                            'mod_spatialforum',
                            'intro',
                            null,
                            dirname($fullpath) . '/',
                            basename($fullpath)
                        );
                    } else if (
                        str_starts_with($path, $basepath . '/pluginfile.php') && preg_match(
                            '~^/' . $sourcecontext->id . '/mod_forum/(post|attachment)/(\d+)(/.*)$~',
                            rawurldecode($file),
                            $ref
                        )
                    ) {
                        $map = $DB->get_record('spatialforum_import_map', ['importid' => $job->id, 'sourcepostid' => $ref[2]]);
                        if ($map) {
                            $prefix = $ref[1] === 'post' ? '/imported-inline' : '/imported-attachments';
                            $fullpath = $prefix . $ref[3];
                            $url = \moodle_url::make_pluginfile_url(
                                $targetcontext->id,
                                'mod_spatialforum',
                                'post',
                                $map->targetpostid,
                                dirname($fullpath) . '/',
                                basename($fullpath)
                            );
                        }
                    }
                }
                return $url ? (str_contains($match[0], '&amp;') ? s($url->out(false)) : $url->out(false)) : $match[0];
            },
            $message
        );
    }

    /**
     * One import lock domain for confirmation, page execution and recovery.
     * @param string $key
     * @return \core\lock\lock
     */
    private static function lock(string $key): \core\lock\lock {
        $lock = \core\lock\lock_config::get_lock_factory('mod_spatialforum')->get_lock('import:' . $key, 10);
        if (!$lock) {
            throw new \moodle_exception('importbusy', 'mod_spatialforum');
        }
        return $lock;
    }

    /**
     * Erase owner identity and source back-references for a scoped Privacy API request.
     * @param int $forumid
     * @param int|null $userid Null erases all import metadata user references in the context
     */
    public static function erase_user_data(int $forumid, ?int $userid = null): void {
        global $DB;
        foreach ($DB->get_records('spatialforum_imports', ['targetid' => $forumid]) as $job) {
            if ($userid === null || (int) $job->userid === $userid) {
                $job->userid = 0;
                $job->requestkey = bin2hex(random_bytes(32));
            }
            if ((int) $job->status !== self::COMPLETE) {
                $job->status = self::CANCELLED;
            }
            $DB->update_record('spatialforum_imports', $job);
            if ($userid === null) {
                $DB->delete_records('spatialforum_import_map', ['importid' => $job->id]);
            } else {
                $DB->delete_records_select(
                    'spatialforum_import_map',
                    'importid = ? AND (
                    sourcepostid IN (SELECT id FROM {forum_posts} WHERE userid = ?)
                    OR targetpostid IN (SELECT id FROM {spatialforum_posts} WHERE spatialforumid = ? AND userid = ?))',
                    [$job->id, $userid, $forumid, $userid]
                );
            }
        }
    }
}
