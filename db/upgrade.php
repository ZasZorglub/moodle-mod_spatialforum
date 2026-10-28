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
    return true;
}
