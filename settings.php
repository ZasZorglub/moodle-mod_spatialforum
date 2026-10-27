<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configmultiselect('mod_spatialforum/allowedtypes',
        get_string('allowedmediatypes', 'mod_spatialforum'), get_string('allowedmediatypes_desc', 'mod_spatialforum'),
        \mod_spatialforum\local\media::TYPES, array_combine(
            \mod_spatialforum\local\media::TYPES, \mod_spatialforum\local\media::TYPES)));
    $settings->add(new admin_setting_configselect('mod_spatialforum/maxbytes',
        get_string('maxmediafilebytes', 'mod_spatialforum'), '', 20971520, get_max_upload_sizes($CFG->maxbytes)));
    $filecounts = [1, 3, 5, 10, 20];
    $settings->add(new admin_setting_configselect('mod_spatialforum/maxfiles',
        get_string('maxmediafiles', 'mod_spatialforum'), '', 10, array_combine($filecounts, $filecounts)));
    $totals = [];
    foreach ([1, 5, 10, 20, 50, 100] as $megabytes) {
        $totals[$megabytes * 1048576] = display_size($megabytes * 1048576);
    }
    $settings->add(new admin_setting_configselect('mod_spatialforum/maxpostbytes',
        get_string('maxmediapostbytes', 'mod_spatialforum'), '', 52428800, $totals));
    $settings->add(new admin_setting_configcheckbox('mod_spatialforum/allowlikes',
        get_string('allowlikes', 'mod_spatialforum'),
        get_string('allowlikes_desc', 'mod_spatialforum'), 1));
}
