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


namespace mod_spatialforum\form;

/**
 * Each participant controls only their own activity subscription.
 */
class subscription_form extends \moodleform {
    /**
     * Present opt-in modes, or unsubscribe only when course access has been lost.
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id', $this->_customdata['cmid']);
        $mform->setType('id', PARAM_INT);
        $rootid = $this->_customdata['rootpostid'] ?? 0;
        $mform->addElement('hidden', 'postid', $rootid);
        $mform->setType('postid', PARAM_INT);
        $options = [0 => get_string($rootid ? 'threadmuted' : 'subscriptionoff', 'mod_spatialforum')];
        if ($this->_customdata['eligible']) {
            if ($rootid) {
                $options = [-1 => get_string('threadinherit', 'mod_spatialforum')] + $options;
            }
            $settings = \mod_spatialforum\local\communication::settings();
            foreach ([1 => 'subscriptionimmediate', 2 => 'subscriptiondaily'] as $mode => $label) {
                $text = get_string($label, 'mod_spatialforum');
                $enabled = $settings[$mode === 1 ? 'immediateenabled' : 'dailyenabled'];
                $options[$mode] = $enabled ? $text : get_string('communicationpaused', 'mod_spatialforum', $text);
            }
        }
        $mform->addElement('select', 'mode', get_string('subscriptionmode', 'mod_spatialforum'), $options);
        $mform->setType('mode', PARAM_INT);
        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
