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


/**

 * Upgrade existing MVP installations without discarding posts or tracks.

 */
function xmldb_spatialforum_upgrade($oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();
    if ($oldversion < 2026102400) {
        $table = new xmldb_table('spatialforum');
        $field = new xmldb_field(
            'enablelikes',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'enablecolors'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('spatialforum_likes');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('postid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('post', XMLDB_KEY_FOREIGN, ['postid'], 'spatialforum_posts', ['id']);
        $table->add_index('userpost', XMLDB_INDEX_UNIQUE, ['userid', 'postid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 2026102400, 'spatialforum');
    }
    if ($oldversion < 2026102401) {
        $table = new xmldb_table('spatialforum_likes');
        $field = new xmldb_field(
            'reaction',
            XMLDB_TYPE_INTEGER,
            '2',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'postid'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('spatialforum_posts');
        $field = new xmldb_field(
            'showparent',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'replytoid'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            // Preserve quotes already published before authors could choose.
            $DB->set_field_select('spatialforum_posts', 'showparent', 1, 'replytoid <> 0');
        }
        upgrade_mod_savepoint(true, 2026102401, 'spatialforum');
    }
    if ($oldversion < 2026102500) {
        // Display/read behaviour changes only; existing schema and read timestamps are preserved.
        upgrade_mod_savepoint(true, 2026102500, 'spatialforum');
    }
    if ($oldversion < 2026102501) {
        // Timestamps already exist; only their presentation changes.
        upgrade_mod_savepoint(true, 2026102501, 'spatialforum');
    }
    if ($oldversion < 2026102503) {
        // White scrolling map; no schema or stored user-data changes.
        upgrade_mod_savepoint(true, 2026102503, 'spatialforum');
    }
    if ($oldversion < 2026102504) {
        $table = new xmldb_table('spatialforum_posts');
        $field = new xmldb_field('replyparts', XMLDB_TYPE_TEXT, null, null, null, null, null, 'showparent');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026102504, 'spatialforum');
    }
    if ($oldversion < 2026102505) {
        // UX release only: preserve all existing posts, quotations, reactions and read timestamps.
        upgrade_mod_savepoint(true, 2026102505, 'spatialforum');
    }
    if ($oldversion < 2026102600) {
        // Restore inline threaded reading; no schema or stored user-data changes.
        upgrade_mod_savepoint(true, 2026102600, 'spatialforum');
    }
    if ($oldversion < 2026102601) {
        // Make the enabled selection-reply action visually prominent; no schema changes.
        upgrade_mod_savepoint(true, 2026102601, 'spatialforum');
    }
    if ($oldversion < 2026102700) {
        // Repair names left behind by earlier Privacy API erasures, preserving the track tree.
        $ids = $DB->get_fieldset_sql('SELECT t.id FROM {spatialforum_tracks} t
            JOIN {spatialforum_posts} p ON p.trackid = t.id
            WHERE t.parenttrackid <> 0 AND p.userid = 0 AND p.deleted = 1
              AND NOT EXISTS (SELECT 1 FROM {spatialforum_posts} earlier
                              WHERE earlier.trackid = t.id AND earlier.id < p.id)');
        if ($ids) {
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $DB->set_field_select(
                'spatialforum_tracks',
                'name',
                get_string('anonymoustrack', 'mod_spatialforum'),
                "id $insql",
                $params
            );
        }
        upgrade_mod_savepoint(true, 2026102700, 'spatialforum');
    }
    if ($oldversion < 2026102900) {
        $table = new xmldb_table('spatialforum_posts');
        $field = new xmldb_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '-1', 'spatialforumid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $index = new xmldb_index('forumgroup', XMLDB_INDEX_NOTUNIQUE, ['spatialforumid', 'groupid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        $table = new xmldb_table('spatialforum');
        $field = new xmldb_field('groupready', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'introformat');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->set_field('spatialforum', 'groupready', 0);
        }
        upgrade_mod_savepoint(true, 2026102900, 'spatialforum');
    }
    if ($oldversion < 2026102901) {
        $table = new xmldb_table('spatialforum_subscriptions');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('spatialforumid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('mode', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('firstpostid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('forum', XMLDB_KEY_FOREIGN, ['spatialforumid'], 'spatialforum', ['id']);
        $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('forumuser', XMLDB_INDEX_UNIQUE, ['spatialforumid', 'userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        $table = new xmldb_table('spatialforum_deliveries');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('spatialforumid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('postid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('mode', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('due', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('attempts', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('messageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('forum', XMLDB_KEY_FOREIGN, ['spatialforumid'], 'spatialforum', ['id']);
        $table->add_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('post', XMLDB_KEY_FOREIGN, ['postid'], 'spatialforum_posts', ['id']);
        $table->add_index('userpost', XMLDB_INDEX_UNIQUE, ['userid', 'postid']);
        $table->add_index('dispatch', XMLDB_INDEX_NOTUNIQUE, ['status', 'due']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 2026102901, 'spatialforum');
    }
    if ($oldversion < 2026102902) {
        // Communication configuration uses plugin config; register the new test-message provider.
        upgrade_mod_savepoint(true, 2026102902, 'spatialforum');
    }
    if ($oldversion < 2026102903) {
        $table = new xmldb_table('spatialforum');
        $field = new xmldb_field('grade', XMLDB_TYPE_NUMBER, '15, 5', null, XMLDB_NOTNULL, null, '0', 'locked');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026102903, 'spatialforum');
    }
    if ($oldversion < 2026102904) {
        $posttable = new xmldb_table('spatialforum_posts');
        $attachmentfield = new xmldb_field(
            'importedattachments',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'replyparts'
        );
        if (!$dbman->field_exists($posttable, $attachmentfield)) {
            $dbman->add_field($posttable, $attachmentfield);
        }
        $titlefield = new xmldb_field('discussionname', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'subject');
        if (!$dbman->field_exists($posttable, $titlefield)) {
            $dbman->add_field($posttable, $titlefield);
        }
        $field = new xmldb_field('importstate', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'grade');
        $activitytable = new xmldb_table('spatialforum');
        if (!$dbman->field_exists($activitytable, $field)) {
            $dbman->add_field($activitytable, $field);
        }
        $table = new xmldb_table('spatialforum_imports');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $integers = [
            'sourcecmid', 'courseid', 'targetid', 'userid', 'totalposts', 'copiedposts', 'timecreated', 'timemodified',
        ];
        foreach ($integers as $name) {
            $table->add_field($name, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        }
        foreach (['requestkey', 'fingerprint'] as $name) {
            $table->add_field($name, XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        }
        $table->add_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('errorcode', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('target', XMLDB_KEY_FOREIGN_UNIQUE, ['targetid'], 'spatialforum', ['id']);
        $table->add_index('requestkey', XMLDB_INDEX_UNIQUE, ['requestkey']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        $table = new xmldb_table('spatialforum_import_map');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        foreach (['importid', 'sourcepostid', 'sourceparentid', 'discussionid', 'targetpostid'] as $name) {
            $table->add_field($name, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        }
        $table->add_field('depth', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('rewritten', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('import', XMLDB_KEY_FOREIGN, ['importid'], 'spatialforum_imports', ['id']);
        $table->add_index('sourcepost', XMLDB_INDEX_UNIQUE, ['importid', 'sourcepostid']);
        $table->add_index('targetpost', XMLDB_INDEX_NOTUNIQUE, ['targetpostid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 2026102904, 'spatialforum');
    }
    if ($oldversion < 2026103100) {
        $table = new xmldb_table('spatialforum');
        foreach (['completionposts', 'completionreplies', 'completiondiscussions'] as $name) {
            $field = new xmldb_field($name, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2026103100, 'spatialforum');
    }
    if ($oldversion < 2026103101) {
        $table = new xmldb_table('spatialforum_posts');
        $field = new xmldb_field('submissionkey', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'discussionname');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $index = new xmldb_index('submissionkey', XMLDB_INDEX_UNIQUE, ['submissionkey']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        upgrade_mod_savepoint(true, 2026103101, 'spatialforum');
    }
    if ($oldversion < 2026103102) {
        $table = new xmldb_table('spatialforum_posts');
        $field = new xmldb_field('deletedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'deleted');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $index = new xmldb_index('deletedtime', XMLDB_INDEX_NOTUNIQUE, ['deleted', 'deletedat']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        upgrade_mod_savepoint(true, 2026103102, 'spatialforum');
    }
    if ($oldversion < 2026103103) {
        $table = new xmldb_table('spatialforum_subscriptions');
        $field = new xmldb_field('rootpostid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $oldindex = new xmldb_index('forumuser', XMLDB_INDEX_UNIQUE, ['spatialforumid', 'userid']);
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }
        $index = new xmldb_index('forumuserroot', XMLDB_INDEX_UNIQUE, ['spatialforumid', 'userid', 'rootpostid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        upgrade_mod_savepoint(true, 2026103103, 'spatialforum');
    }
    if ($oldversion < 2026103104) {
        // No schema changes: invalidate caches for deferred rendering and its new partial template.
        upgrade_mod_savepoint(true, 2026103104, 'spatialforum');
    }
    if ($oldversion < 2026103105) {
        upgrade_mod_savepoint(true, 2026103105, 'spatialforum');
    }
    if ($oldversion < 2026110100) {
        upgrade_mod_savepoint(true, 2026110100, 'spatialforum');
    }
    if ($oldversion < 2026110101) {
        // No schema changes: invalidate caches for the optional compact presentation.
        upgrade_mod_savepoint(true, 2026110101, 'spatialforum');
    }
    return true;
}
