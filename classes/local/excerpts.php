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


namespace mod_spatialforum\local;

/**

 * Selective quotations reference the source; they never store another author's text.

 */
class excerpts {
    /**
     * Stable whitespace for browser selections and formatted Moodle content.
     */
    public static function normalise(string $text): string {
        return trim(preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $text)));
    }

    /**

     * Plain text from the author's own content, excluding quotations.

     */
    public static function source(\stdClass $post, \context $context): string {
        $parts = json_decode($post->replyparts ?? '[]', true) ?: [];
        $comments = array_column($parts, 'comment');
        $html = format_text($post->message, $post->messageformat, ['context' => $context, 'filter' => false]);
        $html = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $html);
        $html = preg_replace('~<(?:br\b[^>]*|/(?:p|div|li|h[1-6]|tr|blockquote))\s*>~i', ' ', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return self::normalise(implode(' ', $comments) . ' ' . $text);
    }

    /**

     * Validate a submitted draft and turn selections into source offsets and fingerprints.

     */
    public static function prepare(string $draft, \stdClass $origin, \context $context): string {
        if (strlen($draft) > 100000 || $origin->deleted) {
            throw new \moodle_exception('invalidexcerpt', 'mod_spatialforum');
        }
        $items = json_decode($draft ?: '[]', true);
        if (
            !str_starts_with(ltrim($draft ?: '[]'), '[') || !is_array($items) ||
                !array_is_list($items) || count($items) > 10
        ) {
            throw new \moodle_exception('invalidexcerpt', 'mod_spatialforum');
        }
        group_access::for_activity($origin->spatialforumid)->require_post($origin, true);
        $source = self::source($origin, $context);
        $parts = [];
        foreach ($items as $item) {
            if (!is_array($item) || !is_string($item['quote'] ?? null) || !is_string($item['comment'] ?? null)) {
                throw new \moodle_exception('invalidexcerpt', 'mod_spatialforum');
            }
            $quote = self::normalise($item['quote']);
            $comment = trim($item['comment']);
            $start = strpos($source, $quote);
            if (
                $quote === '' || $start === false || \core_text::strlen($quote) > 4000 ||
                    \core_text::strlen($comment) > 4000
            ) {
                throw new \moodle_exception('invalidexcerpt', 'mod_spatialforum');
            }
            $parts[] = ['start' => $start, 'length' => strlen($quote), 'hash' => hash('sha256', $source),
                'comment' => $comment];
        }
        return json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**

     * Render source-aware quotes interleaved with the reply author's comments.

     */
    public static function render(
        \stdClass $post,
        \context $context,
        int $cmid,
        ?array $posts = null,
        ?array $users = null,
        ?group_access $access = null
    ): string {
        global $DB;
        $access = $access ?? group_access::for_activity($post->spatialforumid);
        $access->require_post($post);
        $html = '';
        $parts = json_decode($post->replyparts ?? '[]', true) ?: [];
        if ($parts) {
            $origin = $posts !== null ? ($posts[$post->replytoid] ?? false)
                : $DB->get_record('spatialforum_posts', ['id' => $post->replytoid,
                    'spatialforumid' => $post->spatialforumid, 'deleted' => 0]);
            if ($origin && ($origin->deleted || $origin->spatialforumid != $post->spatialforumid || !$access->can_view($origin))) {
                $origin = false;
            }
            $source = $origin ? self::source($origin, $context) : '';
            $user = $origin ? ($users !== null ? ($users[$origin->userid] ?? false)
                : $DB->get_record('user', ['id' => $origin->userid])) : false;
            foreach ($parts as $part) {
                $quote = get_string('originalunavailable', 'mod_spatialforum');
                $label = get_string('excerptquote', 'mod_spatialforum');
                if ($origin) {
                    $url = new \moodle_url('/mod/spatialforum/view.php', ['id' => $cmid, 'postid' => $origin->id]);
                    $label = \html_writer::link($url, s($user ? fullname($user) :
                        get_string('unknownuser', 'mod_spatialforum')) . ' · ' . s($origin->subject));
                    $valid = isset($part['hash'], $part['start'], $part['length']) &&
                        hash_equals(hash('sha256', $source), (string) $part['hash']) &&
                        $part['start'] >= 0 && $part['length'] > 0 && $part['start'] + $part['length'] <= strlen($source);
                    $quote = $valid ? substr($source, $part['start'], $part['length']) :
                        get_string('excerptchanged', 'mod_spatialforum');
                }
                $html .= \html_writer::tag(
                    'blockquote',
                    \html_writer::div($label, 'spatialforum-excerpt-source') .
                    \html_writer::div(s($quote), 'spatialforum-excerpt-text'),
                    ['class' => 'spatialforum-excerpt']
                );
                $html .= \html_writer::div(nl2br(s($part['comment'] ?? '')), 'spatialforum-excerpt-comment');
            }
        }
        return $html . format_text(media::message($post, $context), $post->messageformat, ['context' => $context]);
    }
}
