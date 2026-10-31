<?php
namespace mod_spatialforum\external;

defined('MOODLE_INTERNAL') || die();

use context_module;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

class get_forum extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(['cmid' => new external_value(PARAM_INT)]);
    }
    public static function execute(int $cmid): array {
        global $DB;
        $cmid = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid])['cmid'];
        $cm = get_coursemodule_from_id('spatialforum', $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/spatialforum:view', $context);
        $activity = $DB->get_record('spatialforum', ['id' => $cm->instance], '*', MUST_EXIST);
        $access = \mod_spatialforum\local\group_access::for_activity($activity->id);
        $posts = [];
        foreach ($DB->get_records('spatialforum_posts', ['spatialforumid' => $activity->id, 'deleted' => 0], 'id ASC') as $post) {
            if (!$access->can_view($post)) {
                continue;
            }
            $posts[] = [
                'id' => (int) $post->id, 'parentid' => (int) $post->replytoid, 'trackid' => (int) $post->trackid,
                'userid' => (int) $post->userid, 'subject' => (string) $post->subject,
                'message' => (string) $post->message, 'format' => (int) $post->messageformat,
                'timecreated' => (int) $post->timecreated, 'timemodified' => (int) $post->timemodified,
            ];
        }
        return ['id' => (int) $activity->id, 'cmid' => (int) $cm->id, 'name' => (string) $activity->name, 'posts' => $posts];
    }
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT), 'cmid' => new external_value(PARAM_INT), 'name' => new external_value(PARAM_TEXT),
            'posts' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT), 'parentid' => new external_value(PARAM_INT), 'trackid' => new external_value(PARAM_INT),
                'userid' => new external_value(PARAM_INT), 'subject' => new external_value(PARAM_TEXT), 'message' => new external_value(PARAM_RAW),
                'format' => new external_value(PARAM_INT), 'timecreated' => new external_value(PARAM_INT), 'timemodified' => new external_value(PARAM_INT),
            ])),
        ]);
    }
}
