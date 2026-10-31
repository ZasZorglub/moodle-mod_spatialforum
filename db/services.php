<?php
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
