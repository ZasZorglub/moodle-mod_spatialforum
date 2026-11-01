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

 * Moodle-managed files embedded in a post.

 */
class media {
    /**
     * Supported formats, including optional video captions.
     */
    public const TYPES = ['.png', '.jpg', '.jpeg', '.mp3', '.mp4', '.vtt'];

    /**

     * Editor and draft-saving limits share one server-side contract.

     */
    public static function editor_options(\context_module $context): array {
        global $CFG, $COURSE;
        require_once($CFG->libdir . '/filelib.php');
        $configured = get_config('mod_spatialforum', 'allowedtypes');
        $types = $configured === false ? self::TYPES : array_intersect(self::TYPES, explode(',', $configured));
        $maxbytes = get_config('mod_spatialforum', 'maxbytes');
        return [
            'autosave' => true,
            'context' => $context, 'trusttext' => false, 'subdirs' => true,
            'maxfiles' => max(1, (int) (get_config('mod_spatialforum', 'maxfiles') ?: 10)),
            'maxbytes' => get_max_upload_file_size(
                $CFG->maxbytes,
                $COURSE->maxbytes ?? 0,
                $maxbytes === false ? 20971520 : max(0, (int) $maxbytes)
            ),
            'areamaxbytes' => max(1, (int) (get_config('mod_spatialforum', 'maxpostbytes') ?: 52428800)),
            'accepted_types' => array_values($types ?: self::TYPES),
            'removeorphaneddrafts' => true,
        ];
    }

    /**

     * Validate actual files in this user's draft, including direct submissions.

     */
    public static function validate_draft(int $draftid, array $options): ?string {
        global $USER;
        if (!$draftid) {
            return null;
        }
        $context = \context_user::instance($USER->id);
        $files = get_file_storage()->get_area_files($context->id, 'user', 'draft', $draftid, 'id', false);
        $total = 0;
        if (count($files) > $options['maxfiles']) {
            return get_string('mediafilelimit', 'mod_spatialforum', $options['maxfiles']);
        }
        foreach ($files as $file) {
            $extension = '.' . strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            if (!in_array($extension, $options['accepted_types'], true)) {
                return get_string('mediafiletype', 'mod_spatialforum');
            }
            if ($options['maxbytes'] && $file->get_filesize() > $options['maxbytes']) {
                return get_string('mediafilesize', 'mod_spatialforum', display_size($options['maxbytes']));
            }
            $total += $file->get_filesize();
        }
        return $total > $options['areamaxbytes']
            ? get_string('mediapostsize', 'mod_spatialforum', display_size($options['areamaxbytes'])) : null;
    }

    /**

     * Empty entities/zero-width text are not content; sanitised media are.

     */
    public static function has_content(string $html): bool {
        $clean = clean_text($html, FORMAT_HTML);
        $text = html_entity_decode(strip_tags($clean), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_replace('/[\s\p{Z}\x{200B}-\x{200D}\x{FEFF}]+/u', '', $text) !== '') {
            return true;
        }
        return (bool) preg_match('~<(?:img|audio|video|source)\b[^>]*\bsrc\s*=\s*["\'][^"\']+~i', $clean);
    }

    /**

     * Publish a validated editor draft after its post ID has been allocated.

     */
    public static function save(int $postid, array $editor, \context_module $context): void {
        global $DB, $CFG, $USER;
        require_once($CFG->libdir . '/filelib.php');
        $cm = get_coursemodule_from_id('spatialforum', $context->instanceid, 0, false, MUST_EXIST);
        $DB->get_record('spatialforum_posts', ['id' => $postid, 'spatialforumid' => $cm->instance,
            'userid' => $USER->id, 'deleted' => 0], '*', MUST_EXIST);
        $options = self::editor_options($context);
        if ($error = self::validate_draft((int) ($editor['itemid'] ?? 0), $options)) {
            throw new \moodle_exception('invalidmedia', 'mod_spatialforum', '', $error);
        }
        $data = file_postupdate_standard_editor(
            (object) ['message_editor' => $editor],
            'message',
            $options,
            $context,
            'mod_spatialforum',
            'post',
            $postid
        );
        $DB->update_record('spatialforum_posts', (object) [
            'id' => $postid, 'message' => $data->message, 'messageformat' => $data->messageformat,
        ]);
    }

    /**

     * URL placeholders are resolved only within the post's activity context.

     */
    public static function message(\stdClass $post, \context $context): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        return file_rewrite_pluginfile_urls(
            $post->message,
            'pluginfile.php',
            $context->id,
            'mod_spatialforum',
            'post',
            $post->id ?? 0
        );
    }

    /**

     * Remove one post's files on moderation or privacy erasure.

     */
    public static function delete(int $postid, \context $context): void {
        get_file_storage()->delete_area_files($context->id, 'mod_spatialforum', 'post', $postid);
    }

    /**
     * Display copied forum attachments without changing the source message's text format.
     * @param \stdClass $post
     * @param \context $context
     * @return string
     */
    public static function imported_attachments(\stdClass $post, \context $context): string {
        if (empty($post->importedattachments)) {
            return '';
        }
        $html = '';
        foreach (get_file_storage()->get_area_files($context->id, 'mod_spatialforum', 'post', $post->id, 'id', false) as $file) {
            if (!str_starts_with($file->get_filepath(), '/imported-attachments/')) {
                continue;
            }
            $url = \moodle_url::make_pluginfile_url(
                $context->id,
                'mod_spatialforum',
                'post',
                $post->id,
                $file->get_filepath(),
                $file->get_filename()
            );
            $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            $label = $file->get_filename();
            if (in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
                $content = \html_writer::empty_tag('img', ['src' => $url, 'alt' => $label, 'class' => 'img-fluid']);
            } else if (in_array($extension, ['mp3', 'mp4'], true)) {
                $content = \html_writer::tag(
                    $extension === 'mp3' ? 'audio' : 'video',
                    \html_writer::link($url, s($label)),
                    ['src' => $url, 'controls' => 'controls', 'style' => 'max-width:100%']
                );
            } else {
                $content = \html_writer::link($url, s($label));
            }
            $html .= \html_writer::tag('div', $content, ['class' => 'spatialforum-imported-attachment']);
        }
        return $html;
    }
}
