<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_spatialforum;

use mod_spatialforum\local\excerpts;
use mod_spatialforum\local\forum;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/** Source-aware, interleaved excerpt replies with real Moodle forms and persistence. */
#[CoversClass(excerpts::class)]
#[CoversClass(forum::class)]
final class excerpts_test extends \advanced_testcase {
    private function fixture(): array {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('spatialforum', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('spatialforum', $activity->id);
        $context = \context_module::instance($cm->id);
        $track = $DB->get_record('spatialforum_tracks', ['id' => $activity->roottrackid]);
        $id = forum::create_post($activity, $track->id, get_admin()->id, 'Original',
            '<p>Målmænd: <strong>Pentz</strong></p><p>Forsvar: &lt;Alves&gt; &amp; venner</p>', FORMAT_HTML);
        $origin = $DB->get_record('spatialforum_posts', ['id' => $id]);
        return [$activity, $track, $origin, $context, $cm];
    }

    public function test_multiple_quotes_are_references_and_preview_contains_only_own_comments(): void {
        global $DB;
        [$activity, $track, $origin, $context, $cm] = $this->fixture();
        $draft = json_encode([
            ['quote' => 'Målmænd: Pentz', 'comment' => 'Min første kommentar'],
            ['quote' => 'Forsvar: <Alves> & venner', 'comment' => '<script>alert(1)</script> Min anden kommentar'],
        ]);
        $newtrack = forum::reply_in_new_track($activity, $track, $origin->id, get_admin()->id,
            'Reply', 'Reply', '', FORMAT_HTML, false, $draft);
        $post = $DB->get_record('spatialforum_posts', ['trackid' => $newtrack]);
        $this->assertEquals($origin->id, $post->replytoid);
        $this->assertStringNotContainsString('Pentz', $post->replyparts);
        $html = excerpts::render($post, $context, $cm->id);
        $this->assertSame(2, substr_count($html, '<blockquote'));
        $this->assertStringContainsString('Målmænd: Pentz', $html);
        $this->assertStringContainsString('&lt;Alves&gt; &amp; venner', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('postid=' . $origin->id, $html);
        $this->assertLessThan(strpos($html, 'Min første kommentar'), strpos($html, 'Målmænd: Pentz'));
        $this->assertLessThan(strpos($html, 'Forsvar:'), strpos($html, 'Min første kommentar'));
        $data = forum::export_view_data($activity, $cm, $track, $context, true);
        $posts = array_column($data['posts'], null, 'id');
        $this->assertStringContainsString('Min første kommentar', $posts[$post->id]['preview']);
        $this->assertStringNotContainsString('Pentz', $posts[$post->id]['preview']);
        $DB->set_field('spatialforum_posts', 'message', 'Changed', ['id' => $origin->id]);
        $html = excerpts::render($post, $context, $cm->id);
        $this->assertStringContainsString(get_string('excerptchanged', 'mod_spatialforum'), $html);
        $this->assertStringNotContainsString('Pentz', $html);
        $DB->set_field('spatialforum_posts', 'deleted', 1, ['id' => $origin->id]);
        $html = excerpts::render($post, $context, $cm->id);
        $this->assertStringContainsString(get_string('originalunavailable', 'mod_spatialforum'), $html);
        $this->assertStringContainsString('Min første kommentar', $html);
    }

    public function test_forged_and_oversized_selections_are_rejected(): void {
        [$activity, $track, $origin, $context] = $this->fixture();
        foreach (['{}', 'null', '[1]', json_encode([['quote' => 'Invented text', 'comment' => 'Hi']]),
                json_encode(array_fill(0, 11, ['quote' => 'Pentz', 'comment' => 'Hi'])),
                json_encode([['quote' => 'Pentz', 'comment' => str_repeat('a', 4001)]])] as $draft) {
            try {
                excerpts::prepare($draft, $origin, $context);
                $this->fail('Invalid quotation accepted');
            } catch (\moodle_exception $e) {
                $this->assertSame('invalidexcerpt', $e->errorcode);
            }
        }
    }

    public function test_form_accepts_comments_without_final_message_and_rejects_empty_reply(): void {
        [$activity, $track, $origin, $context, $cm] = $this->fixture();
        $form = new \mod_spatialforum\form\post_form(null, ['cmid' => $cm->id, 'action' => 'reply',
            'trackid' => $track->id, 'originpostid' => $origin->id, 'origin' => $origin, 'context' => $context]);
        $data = ['subject' => 'Reply', 'message_editor' => ['text' => '', 'format' => FORMAT_HTML],
            'excerptdraft' => json_encode([['quote' => 'Pentz', 'comment' => 'My point']])];
        $this->assertSame([], $form->validation($data, []));
        $data['excerptdraft'] = json_encode([['quote' => 'Pentz', 'comment' => '']]);
        $this->assertArrayHasKey('message_editor', $form->validation($data, []));
        $data['excerptdraft'] = json_encode([['quote' => 'Forged', 'comment' => 'My point']]);
        $this->assertArrayHasKey('message_editor', $form->validation($data, []));
    }

    public function test_privacy_erases_reply_comments_and_source_erasure_hides_quotations(): void {
        global $DB;
        [$activity, $track, $origin, $context, $cm] = $this->fixture();
        $other = $this->getDataGenerator()->create_user();
        $replyid = forum::create_post($activity, $track->id, $other->id, 'Reply', '', FORMAT_HTML,
            $origin->id, false, json_encode([['quote' => 'Pentz', 'comment' => 'Private comment']]));
        $reply = $DB->get_record('spatialforum_posts', ['id' => $replyid]);
        \mod_spatialforum\privacy\provider::delete_data_for_user(
            new \core_privacy\local\request\approved_contextlist(get_admin(), 'mod_spatialforum', [$context->id]));
        $this->assertStringNotContainsString('Pentz', excerpts::render($reply, $context, $cm->id));
        \mod_spatialforum\privacy\provider::delete_data_for_user(
            new \core_privacy\local\request\approved_contextlist($other, 'mod_spatialforum', [$context->id]));
        $this->assertNull($DB->get_field('spatialforum_posts', 'replyparts', ['id' => $replyid]));
    }
}
