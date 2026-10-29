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


namespace mod_spatialforum;

use mod_spatialforum\local\notifications;

/**
 * Controlled ambiguous transport interruption for recovery integration tests.
 */
class uncertain_notifications extends notifications {
    /**
     * Throw after dispatch has been claimed.
     *
     * @param \stdClass $cm
     * @param \stdClass $user
     * @param array $posts
     * @param int $mode
     */
    protected static function send(\stdClass $cm, \stdClass $user, array $posts, int $mode) {
        throw new \RuntimeException('QA transport interrupted');
    }
}
