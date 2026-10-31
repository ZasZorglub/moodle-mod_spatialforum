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
$rootid = optional_param('postid', 0, PARAM_INT);
if ($rootid && $eligible) {
    $post = $DB->get_record('spatialforum_posts', ['id' => $rootid, 'spatialforumid' => $cm->instance]);
    $eligible = $post && \mod_spatialforum\local\group_access::for_activity($cm->instance)->can_view($post);
    if ($eligible) {
        $rootid = \mod_spatialforum\local\thread_subscriptions::root($post);
    }
}
$context = $eligible ? context_module::instance($cm->id) : context_system::instance();
if ($eligible) {
    require_course_login(get_course($cm->course), false, $cm);
    $PAGE->set_cm($cm);
}
$PAGE->set_context($context);
$PAGE->set_url('/mod/spatialforum/subscription.php', ['id' => $id, 'postid' => $rootid]);
$PAGE->set_title(get_string('notificationpreferences', 'mod_spatialforum'));
$PAGE->set_heading(get_string('notificationpreferences', 'mod_spatialforum'));
$returnurl = $eligible ? new moodle_url('/mod/spatialforum/view.php', ['id' => $id]) : new moodle_url('/my/');
if ($rootid && $eligible) {
    $returnurl->param('postid', $rootid);
    $returnurl->set_anchor('post-' . $rootid);
}
$form = new \mod_spatialforum\form\subscription_form(null, ['cmid' => $id, 'eligible' => $eligible, 'rootpostid' => $rootid]);
$subscription = $DB->get_record('spatialforum_subscriptions', [
    'spatialforumid' => $cm->instance, 'userid' => $USER->id, 'rootpostid' => $rootid,
]);
$form->set_data(['mode' => $eligible ? ($subscription->mode ?? ($rootid ? -1 : 0)) : 0]);
if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($data = $form->get_data()) {
    require_sesskey();
    notifications::set_mode($cm->instance, (int) $data->mode, $rootid);
    redirect($returnurl, get_string('subscriptionsaved', 'mod_spatialforum'));
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('notificationpreferences', 'mod_spatialforum'));
if ($rootid && $eligible) {
    $root = $DB->get_record('spatialforum_posts', ['id' => $rootid], '*', MUST_EXIST);
    $url = new moodle_url('/mod/spatialforum/view.php', ['id' => $id, 'postid' => $rootid]);
    $url->set_anchor('post-' . $rootid);
    echo html_writer::tag('p', html_writer::link($url, format_string($root->subject)));
}
echo $OUTPUT->notification(get_string($rootid ? 'threadsubscriptionscope' : 'subscriptionscope', 'mod_spatialforum'), 'info');
$form->display();
echo $OUTPUT->footer();
