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


require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
admin_externalpage_setup('spatialforumdeliveries');
require_capability('moodle/site:config', context_system::instance());
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('notificationdeliveries', 'mod_spatialforum'));
echo $OUTPUT->notification(get_string('notificationdeliveries_desc', 'mod_spatialforum'), 'info');
$counts = $DB->get_records_sql('SELECT status, COUNT(1) AS total FROM {spatialforum_deliveries} GROUP BY status');
$table = new html_table();
$table->head = [get_string('status'), get_string('total')];
foreach ([0 => 'deliverypending', 1 => 'deliveryaccepted', 2 => 'deliveryfailed', 3 => 'deliveryuncertain'] as $status => $label) {
    $table->data[] = [get_string($label, 'mod_spatialforum'), (int) ($counts[$status]->total ?? 0)];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
