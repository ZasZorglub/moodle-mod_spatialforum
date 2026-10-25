<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_spatialforum;

use mod_spatialforum\form\post_form;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/** Form semantics for new posts, ordinary replies and selective replies. */
#[CoversClass(post_form::class)]
final class ux_test extends \advanced_testcase {
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
        $this->assertLessThan(strpos($html, 'data-excerpt-composer'), strpos($html, 'id_message_editor'));
        $this->assertStringContainsString('spatialforum-quote-options', $html);
        $this->assertStringContainsString('name="sfview"', $html);
        $this->assertStringContainsString('value="list"', $html);
        $this->assertDoesNotMatchRegularExpression('/data-excerpt-composer\s+open/', $html);
    }

    public function test_selection_reply_opens_composer_and_labels_closing_comment(): void {
        $html = $this->render_form(19, true);
        $this->assertStringContainsString(get_string('closingcomment', 'mod_spatialforum'), $html);
        $this->assertMatchesRegularExpression('/data-excerpt-composer\s+open/', $html);
    }
}
