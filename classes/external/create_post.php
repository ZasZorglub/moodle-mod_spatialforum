<?php
// phpcs:ignoreFile
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// @package    mod_spatialforum
// @copyright  2026 Anders Christian Sloth
// @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/** Create a post or reply from Moodle App clients. */
namespace mod_spatialforum\external;

use context_module;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

/** Moodle App publication service. */
class create_post extends external_api {
    /** Define input parameters. */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT), 'subject' => new external_value(PARAM_TEXT),
            'message' => new external_value(PARAM_RAW), 'format' => new external_value(PARAM_INT, VALUE_DEFAULT, FORMAT_HTML),
            'parentid' => new external_value(PARAM_INT, VALUE_DEFAULT, 0),
        ]);
    }
    /** Publish a text post or reply. */
    public static function execute(
        int $cmid,
        string $subject,
        string $message,
        int $format = FORMAT_HTML,
        int $parentid = 0
    ): array {
        global $DB, $USER;
        $data = self::validate_parameters(
            self::execute_parameters(), compact('cmid', 'subject', 'message', 'format', 'parentid')
        );
        $cm = get_coursemodule_from_id('spatialforum', $data['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/spatialforum:reply', $context);
        $activity = $DB->get_record('spatialforum', ['id' => $cm->instance], '*', MUST_EXIST);
        $trackid = (int) $activity->roottrackid;
        if ($data['parentid']) {
            $parent = $DB->get_record(
                'spatialforum_posts',
                ['id' => $data['parentid'], 'spatialforumid' => $activity->id, 'deleted' => 0],
                '*',
                MUST_EXIST
            );
            \mod_spatialforum\local\group_access::for_activity($activity->id)->require_post($parent, true);
            $trackid = (int) $parent->trackid;
        }
        $postid = \mod_spatialforum\local\forum::create_post(
            $activity,
            $trackid,
            $USER->id,
            $data['subject'],
            $data['message'],
            $data['format'],
            $data['parentid']
        );
        return ['postid' => (int) $postid];
    }
    /** Define the response structure. */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(['postid' => new external_value(PARAM_INT)]);
    }
}
