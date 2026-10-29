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
require_once($CFG->libdir . '/formslib.php');

use mod_spatialforum\local\notifications;

require_login();
if (isguestuser()) {
    throw new moodle_exception('noguest');
}
$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('spatialforum', $id, 0, false, MUST_EXIST);
$eligible = notifications::eligible($cm, $USER);
$context = $eligible ? context_module::instance($cm->id) : context_system::instance();
if ($eligible) {
    require_course_login(get_course($cm->course), false, $cm);
    $PAGE->set_cm($cm);
}
$PAGE->set_context($context);
$PAGE->set_url('/mod/spatialforum/subscription.php', ['id' => $id]);
$PAGE->set_title(get_string('notificationpreferences', 'mod_spatialforum'));
$PAGE->set_heading(get_string('notificationpreferences', 'mod_spatialforum'));
$returnurl = $eligible ? new moodle_url('/mod/spatialforum/view.php', ['id' => $id]) : new moodle_url('/my/');
$form = new \mod_spatialforum\form\subscription_form(null, ['cmid' => $id, 'eligible' => $eligible]);
$subscription = $DB->get_record('spatialforum_subscriptions', ['spatialforumid' => $cm->instance, 'userid' => $USER->id]);
$form->set_data(['mode' => $eligible ? ($subscription->mode ?? 0) : 0]);
if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    require_sesskey();
    notifications::set_mode($cm->instance, (int) $data->mode);
    redirect($returnurl, get_string('subscriptionsaved', 'mod_spatialforum'));
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('notificationpreferences', 'mod_spatialforum'));
echo $OUTPUT->notification(get_string('subscriptionscope', 'mod_spatialforum'), 'info');
$form->display();
echo $OUTPUT->footer();
