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

use mod_spatialforum\form\post_form;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**

 * Form semantics for new posts, ordinary replies and selective replies.

 */
#[CoversClass(post_form::class)]
final class ux_test extends \advanced_testcase {
    /**
     * Render the reply form for the requested parent post.
     */
    private function render_form(int $originpostid, bool $excerptmode = false): string {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/mod/spatialforum/post.php', ['id' => 42]);
        $form = new post_form(null, ['cmid' => 42, 'action' => 'reply', 'trackid' => 1,
            'originpostid' => $originpostid, 'excerptmode' => $excerptmode, 'sfview' => 'list']);
        ob_start();
        $form->display();
        return ob_get_clean();
    }

    public function test_new_post_has_a_distinct_submit_label(): void {
        $html = $this->render_form(0);
        $this->assertStringContainsString(get_string('createpost', 'mod_spatialforum'), $html);
        $this->assertStringNotContainsString('data-excerpt-composer', $html);
    }

    public function test_regular_reply_starts_with_message_and_keeps_quotes_optional(): void {
        $html = $this->render_form(19);
        $this->assertLessThan(strpos($html, 'name="showparent"'), strpos($html, 'id_message_editor'));
        $this->assertStringNotContainsString('spatialforum-quote-options', $html);
        $this->assertStringContainsString('name="sfview"', $html);
        $this->assertStringContainsString('value="list"', $html);
        $this->assertStringNotContainsString('data-excerpt-composer', $html);
    }

    public function test_selection_reply_opens_composer_and_labels_closing_comment(): void {
        $html = $this->render_form(19, true);
        $this->assertStringContainsString('name="showparent"', $html);
        $this->assertStringNotContainsString('data-excerpt-composer', $html);
    }
}
