<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/** Test instances with the same defaults as the activity form. */
class mod_spatialforum_generator extends testing_module_generator {
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) ((array) $record + ['maxdepth' => 8, 'enablecolors' => 1, 'enablelikes' => 1, 'locked' => 0]);
        return parent::create_instance($record, (array) $options);
    }
}
