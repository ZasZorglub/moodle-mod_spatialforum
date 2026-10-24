<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox('mod_spatialforum/allowlikes',
        get_string('allowlikes', 'mod_spatialforum'),
        get_string('allowlikes_desc', 'mod_spatialforum'), 1));
}
