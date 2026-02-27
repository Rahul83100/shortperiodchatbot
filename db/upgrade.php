<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the local_chatbot plugin.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_local_chatbot_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026022503) {

        // Define table local_chatbot_sync to be created.
        $table = new xmldb_table('local_chatbot_sync');

        // Adding fields to table local_chatbot_sync.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('filehash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('syncstatus', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table local_chatbot_sync.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table local_chatbot_sync.
        $table->add_index('filehash_idx', XMLDB_INDEX_NOTUNIQUE, ['filehash']);
        $table->add_index('courseid_idx', XMLDB_INDEX_NOTUNIQUE, ['courseid']);

        // Conditionally launch create table for local_chatbot_sync.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Chatbot savepoint reached.
        upgrade_plugin_savepoint(true, 2026022503, 'local', 'chatbot');
    }

    if ($oldversion < 2026022507) {

        // Define table local_chatbot_sync to be edited.
        $table = new xmldb_table('local_chatbot_sync');

        // Adding fields to table local_chatbot_sync.
        $field = new xmldb_field('pathnamehash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null, 'filehash');

        // Conditionally launch add field pathnamehash.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Adding indexes to table local_chatbot_sync.
        $index = new xmldb_index('pathnamehash_idx', XMLDB_INDEX_UNIQUE, ['pathnamehash']);

        // Conditionally launch add index pathnamehash_idx.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Chatbot savepoint reached.
        upgrade_plugin_savepoint(true, 2026022507, 'local', 'chatbot');
    }

    return true;
}
