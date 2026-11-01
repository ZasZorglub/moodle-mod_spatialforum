<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Samtalespor spatial discussion activity.
 *
 * @package    mod_spatialforum
 * @copyright  2026 Anders Christian Sloth
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

$ADMIN->add('modsettings', new admin_externalpage(
    'spatialforumcommunication',
    get_string('communicationtitle', 'mod_spatialforum'),
    new moodle_url('/mod/spatialforum/communication.php')
));

$ADMIN->add('modsettings', new admin_externalpage(
    'spatialforumdeliveries',
    get_string('notificationdeliveries', 'mod_spatialforum'),
    new moodle_url('/mod/spatialforum/delivery.php')
));

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'mod_spatialforum/communicationheading',
        get_string('communicationtitle', 'mod_spatialforum'),
        html_writer::link(
            new moodle_url('/mod/spatialforum/communication.php'),
            get_string('communicationconfigure', 'mod_spatialforum')
        )
    ));
    $settings->add(new admin_setting_configmultiselect(
        'mod_spatialforum/allowedtypes',
        get_string('allowedmediatypes', 'mod_spatialforum'),
        get_string('allowedmediatypes_desc', 'mod_spatialforum'),
        \mod_spatialforum\local\media::TYPES,
        array_combine(
            \mod_spatialforum\local\media::TYPES,
            \mod_spatialforum\local\media::TYPES
        )
    ));
    $settings->add(new admin_setting_configselect(
        'mod_spatialforum/maxbytes',
        get_string('maxmediafilebytes', 'mod_spatialforum'),
        '',
        20971520,
        get_max_upload_sizes($CFG->maxbytes)
    ));
    $settings->add(new admin_setting_configcheckbox(
        'mod_spatialforum/enablegrading',
        get_string('enablegrading', 'mod_spatialforum'),
        get_string('enablegrading_desc', 'mod_spatialforum'),
        0
    ));
    $filecounts = [1, 3, 5, 10, 20];
    $settings->add(new admin_setting_configselect(
        'mod_spatialforum/maxfiles',
        get_string('maxmediafiles', 'mod_spatialforum'),
        '',
        10,
        array_combine($filecounts, $filecounts)
    ));
    $totals = [];
    foreach ([1, 5, 10, 20, 50, 100] as $megabytes) {
        $totals[$megabytes * 1048576] = display_size($megabytes * 1048576);
    }
    $settings->add(new admin_setting_configselect(
        'mod_spatialforum/maxpostbytes',
        get_string('maxmediapostbytes', 'mod_spatialforum'),
        '',
        52428800,
        $totals
    ));
    $settings->add(new admin_setting_configcheckbox(
        'mod_spatialforum/allowlikes',
        get_string('allowlikes', 'mod_spatialforum'),
        get_string('allowlikes_desc', 'mod_spatialforum'),
        1
    ));
    $settings->add(new admin_setting_configselect(
        'mod_spatialforum/subscriptionpolicy',
        get_string('subscriptionpolicy', 'mod_spatialforum'),
        get_string('subscriptionpolicy_desc', 'mod_spatialforum'),
        'optin',
        [
            'optin' => get_string('subscriptionpolicy_optin', 'mod_spatialforum'),
            'initial' => get_string('subscriptionpolicy_initial', 'mod_spatialforum'),
            'forced' => get_string('subscriptionpolicy_forced', 'mod_spatialforum'),
            'disabled' => get_string('subscriptionpolicy_disabled', 'mod_spatialforum'),
        ]
    ));
}
