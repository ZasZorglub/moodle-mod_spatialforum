<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/** Upgrade existing MVP installations without discarding posts or tracks. */
function xmldb_spatialforum_upgrade($oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();
    if ($oldversion < 2026102400) {
        $table = new xmldb_table('spatialforum');
        $field = new xmldb_field('enablelikes', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL,
            null, '1', 'enablecolors');
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
    return true;
}
