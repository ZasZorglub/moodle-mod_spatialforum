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


/**

 * Test instances with the same defaults as the activity form.

 */
class mod_spatialforum_generator extends testing_module_generator {
    /**
     * Create a spatial forum activity and its root track.
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) ((array) $record + ['maxdepth' => 8, 'enablecolors' => 1, 'enablelikes' => 1, 'locked' => 0]);
        return parent::create_instance($record, (array) $options);
    }
}
