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

/** Spatialforum external service declarations. */
defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_spatialforum_get_forum' => [
        'classname' => 'mod_spatialforum\\external\\get_forum',
        'methodname' => 'execute', 'description' => 'Read the threaded Spatialforum overview for Moodle App clients.',
        'type' => 'read', 'ajax' => true, 'capabilities' => 'mod/spatialforum:view',
    ],
    'mod_spatialforum_create_post' => [
        'classname' => 'mod_spatialforum\\external\\create_post',
        'methodname' => 'execute', 'description' => 'Create a Spatialforum post or reply from a Moodle App client.',
        'type' => 'write', 'ajax' => true, 'capabilities' => 'mod/spatialforum:reply',
    ],
    'mod_spatialforum_set_subscription' => [
        'classname' => 'mod_spatialforum\\external\\set_subscription',
        'methodname' => 'execute', 'description' => 'Set an activity or conversation notification preference.',
        'type' => 'write', 'ajax' => true, 'capabilities' => 'mod/spatialforum:view',
    ],
];
