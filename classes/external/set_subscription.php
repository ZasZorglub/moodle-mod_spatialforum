<?php
namespace mod_spatialforum\external;

defined('MOODLE_INTERNAL') || die();

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

class set_subscription extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'forumid' => new external_value(PARAM_INT), 'mode' => new external_value(PARAM_INT),
            'postid' => new external_value(PARAM_INT, VALUE_DEFAULT, 0),
        ]);
    }
    public static function execute(int $forumid, int $mode, int $postid = 0): array {
        $data = self::validate_parameters(self::execute_parameters(), compact('forumid', 'mode', 'postid'));
        $cm = get_coursemodule_from_instance('spatialforum', $data['forumid'], 0, false, MUST_EXIST);
        self::validate_context(\context_module::instance($cm->id));
        \mod_spatialforum\local\notifications::set_mode($data['forumid'], $data['mode'], $data['postid']);
        return ['saved' => 1];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(['saved' => new external_value(PARAM_BOOL)]);
    }
}
