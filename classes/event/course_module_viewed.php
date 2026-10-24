<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_spatialforum\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Course module viewed event.
 *
 * @package mod_spatialforum
 */
class course_module_viewed extends \core\event\course_module_viewed {
    /**
     * Initialises event data.
     */
    protected function init(): void {
        $this->data['objecttable'] = 'spatialforum';
        parent::init();
    }
}

