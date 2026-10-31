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

/** Set activity or conversation notifications from Moodle App clients. */
namespace mod_spatialforum\external;

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

/** Moodle App subscription service. */
class set_subscription extends external_api {
    /** Define input parameters. */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'forumid' => new external_value(PARAM_INT), 'mode' => new external_value(PARAM_INT),
            'postid' => new external_value(PARAM_INT, VALUE_DEFAULT, 0),
        ]);
    }
    /** Save the authenticated participant's preference. */
    public static function execute(int $forumid, int $mode, int $postid = 0): array {
        $data = self::validate_parameters(self::execute_parameters(), compact('forumid', 'mode', 'postid'));
        $cm = get_coursemodule_from_instance('spatialforum', $data['forumid'], 0, false, MUST_EXIST);
        self::validate_context(\context_module::instance($cm->id));
        \mod_spatialforum\local\notifications::set_mode($data['forumid'], $data['mode'], $data['postid']);
        return ['saved' => 1];
    }
    /** Define the response structure. */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(['saved' => new external_value(PARAM_BOOL)]);
    }
}
