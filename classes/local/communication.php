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
 * Site communication policy and safe, plain-text message templates.
 * @package mod_spatialforum
 * @copyright 2026 Anders Christian Sloth
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_spatialforum\local;

/**
 * Site-wide delivery policy and a restricted plain-text template renderer.
 */
class communication {
    /**
     * Settings affecting queued delivery eligibility or timing.
     */
    public const POLICY = ['rootenabled', 'replyenabled', 'immediateenabled', 'dailyenabled',
        'delayminutes', 'digesthour', 'digestminute', 'digesttimezone'];

    /**
     * Whitelisted substitutions; no executable template language is accepted.
     * @return array
     */
    public static function variables(): array {
        $common = ['site', 'course', 'activity', 'recipient', 'count'];
        $post = array_merge($common, ['author', 'subject', 'excerpt', 'posturl']);
        return ['postsubject' => $post, 'postbody' => $post, 'digestsubject' => $common,
            'digestbody' => array_merge($common, ['posts']), 'itembody' => $post];
    }

    /**
     * Defaults preserve existing delivery behaviour, including the former digest-hour setting.
     * @param string|null $lang
     * @return array
     */
    public static function defaults(?string $lang = null): array {
        $hour = get_config('mod_spatialforum', 'digesthour');
        $values = ['rootenabled' => 1, 'replyenabled' => 1, 'immediateenabled' => 1, 'dailyenabled' => 1,
            'delayminutes' => 0, 'digesthour' => $hour === false ? 17 : (int) $hour,
            'digestminute' => 0, 'digesttimezone' => 'recipient'];
        foreach (self::variables() as $key => $variables) {
            $values[$key] = get_string_manager()->get_string('communicationdefault' . $key, 'mod_spatialforum', null, $lang);
        }
        return $values;
    }

    /**
     * Unsaved installations use translated defaults; saved templates are site-wide custom text.
     * @param string|null $lang
     * @return array
     */
    public static function settings(?string $lang = null): array {
        $stored = json_decode(get_config('mod_spatialforum', 'communication') ?: '{}', true);
        return array_intersect_key(is_array($stored) ? $stored : [], self::defaults($lang)) + self::defaults($lang);
    }

    /**
     * Validate both form submissions and programmatic writes.
     * @param array $values
     * @return array Field-specific errors
     */
    public static function validate(array $values): array {
        $errors = [];
        foreach (['rootenabled', 'replyenabled', 'immediateenabled', 'dailyenabled'] as $key) {
            if (!isset($values[$key]) || !in_array((string) $values[$key], ['0', '1'], true)) {
                $errors[$key] = get_string('invaliddata', 'error');
            }
        }
        foreach (['delayminutes' => 1440, 'digesthour' => 23, 'digestminute' => 59] as $key => $max) {
            $number = filter_var($values[$key] ?? null, FILTER_VALIDATE_INT);
            if ($number === false || $number < 0 || $number > $max) {
                $errors[$key] = get_string('communicationinvalidnumber', 'mod_spatialforum', $max);
            }
        }
        if (!in_array($values['digesttimezone'] ?? '', ['recipient', 'site'], true)) {
            $errors['digesttimezone'] = get_string('invaliddata', 'error');
        }
        foreach (self::variables() as $key => $allowed) {
            $value = $values[$key] ?? '';
            $subject = str_ends_with($key, 'subject');
            if (
                !is_string($value) || trim($value) === '' || \core_text::strlen($value) > ($subject ? 200 : 10000)
                    || ($subject && preg_match('/[\r\n]/', $value))
            ) {
                $errors[$key] = get_string('communicationinvalidtemplate', 'mod_spatialforum');
                continue;
            }
            preg_match_all('/\{\{([^{}]*)\}\}/u', $value, $matches);
            if (count($matches[0]) > 100 || ($key === 'digestbody' && substr_count($value, '{{posts}}') > 1)) {
                $errors[$key] = get_string('communicationvariablelimit', 'mod_spatialforum');
                continue;
            }
            $remaining = str_replace($matches[0], '', $value);
            if (array_diff($matches[1], $allowed) || str_contains($remaining, '{{') || str_contains($remaining, '}}')) {
                $errors[$key] = get_string('communicationinvalidvariables', 'mod_spatialforum', implode(', ', $allowed));
            }
        }
        return $errors;
    }

    /**
     * Atomically save site configuration and schedule bounded reconciliation of pending messages.
     * @param array $values
     */
    public static function save(array $values): void {
        global $DB;
        require_capability('moodle/site:config', \context_system::instance());
        if (self::validate($values)) {
            throw new \moodle_exception('communicationinvalidsettings', 'mod_spatialforum');
        }
        $old = self::settings();
        $values = array_intersect_key($values, self::defaults());
        foreach (self::POLICY as $key) {
            if ($key !== 'digesttimezone') {
                $values[$key] = (int) $values[$key];
            }
        }
        $json = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $transaction = $DB->start_delegated_transaction();
        try {
            $previous = get_config('mod_spatialforum', 'communication');
            set_config('communication', $json, 'mod_spatialforum');
            if ($previous !== $json) {
                add_to_config_log('communication', $previous, $json, 'mod_spatialforum');
            }
            if (array_intersect_key($old, array_flip(self::POLICY)) != array_intersect_key($values, array_flip(self::POLICY))) {
                $task = new \mod_spatialforum\task\reconcile_notifications();
                $task->set_component('mod_spatialforum');
                $task->set_custom_data(['lastid' => 0]);
                \core\task\manager::queue_adhoc_task($task, true);
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
    }

    /**
     * Current policy for a post and a participant's chosen delivery mode.
     * @param \stdClass $post
     * @param int $mode
     * @return bool
     */
    public static function allows(\stdClass $post, int $mode): bool {
        $settings = self::settings();
        return ($mode === notifications::IMMEDIATE ? !empty($settings['immediateenabled'])
                : ($mode === notifications::DAILY && !empty($settings['dailyenabled'])))
            && !empty($settings[empty($post->replytoid) ? 'rootenabled' : 'replyenabled']);
    }

    /**
     * One-pass interpolation prevents post content from becoming another template expression.
     * @param string $template
     * @param array $values
     * @return string
     */
    private static function interpolate(string $template, array $values): string {
        $replacements = [];
        foreach ($values as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string) $value;
        }
        return strtr($template, $replacements);
    }

    /**
     * Render approved plain-text templates. Essential post/preferences links are always retained.
     * @param array $settings
     * @param int $mode
     * @param array $common
     * @param array $posts Safe, permission-filtered plain-text values
     * @param string $settingsurl
     * @param string|null $lang
     * @return array
     */
    public static function render(
        array $settings,
        int $mode,
        array $common,
        array $posts,
        string $settingsurl,
        ?string $lang = null
    ): array {
        $common['count'] = count($posts);
        $items = [];
        foreach ($posts as $post) {
            $items[] = \core_text::substr(self::interpolate($settings['itembody'], $post + $common), 0, 4000);
        }
        $daily = $mode === notifications::DAILY;
        $values = $daily ? ['posts' => implode("\n\n", $items)] + $common : reset($posts) + $common;
        $subject = self::interpolate($settings[$daily ? 'digestsubject' : 'postsubject'], $values);
        $subject = \core_text::substr(preg_replace('/[\r\n]+/', ' ', strip_tags($subject)), 0, 200);
        $text = \core_text::substr(self::interpolate($settings[$daily ? 'digestbody' : 'postbody'], $values), 0, 500000);
        foreach ($posts as $post) {
            if (!str_contains($text, $post['posturl'])) {
                $text .= "\n\n" . $post['posturl'];
            }
        }
        $footer = get_string_manager()->get_string('notificationpreferences', 'mod_spatialforum', null, $lang);
        $text .= "\n\n" . $footer . ': ' . $settingsurl;
        return ['subject' => $subject, 'text' => $text, 'html' => format_text(
            $text,
            FORMAT_PLAIN,
            ['filter' => false, 'para' => false]
        )];
    }

    /**
     * Preview/test content is synthetic and never reads participant posts or addresses.
     * @param array $settings
     * @param int $mode
     * @return array
     */
    public static function preview(array $settings, int $mode): array {
        global $SITE;
        $url = new \moodle_url('/mod/spatialforum/view.php', ['id' => 0, 'postid' => 0]);
        $posts = [['author' => 'Example Author', 'subject' => 'An example discussion',
            'excerpt' => 'This is synthetic preview content. No participant data are included.', 'posturl' => $url->out(false)]];
        if ($mode === notifications::DAILY) {
            $posts[] = ['author' => 'Example Participant', 'subject' => 'An example reply',
                'excerpt' => 'A second synthetic contribution for the digest.',
                'posturl' => (new \moodle_url('/mod/spatialforum/view.php', ['id' => 0, 'postid' => 1]))->out(false)];
        }
        return self::render(
            $settings,
            $mode,
            ['site' => html_to_text($SITE->fullname), 'course' => 'Example course',
            'activity' => 'Example spatial forum', 'recipient' => 'Example Recipient'],
            $posts,
            (new \moodle_url('/mod/spatialforum/communication.php'))->out(false)
        );
    }

    /**
     * Send an explicitly requested synthetic test only to the authenticated administrator.
     * @param array $settings
     * @param int $mode
     * @return int|false
     */
    public static function send_test(array $settings, int $mode) {
        global $USER;
        require_capability('moodle/site:config', \context_system::instance());
        if (self::validate($settings) || !in_array($mode, [notifications::IMMEDIATE, notifications::DAILY], true)) {
            throw new \moodle_exception('communicationinvalidsettings', 'mod_spatialforum');
        }
        $rendered = self::preview($settings, $mode);
        $message = new \core\message\message();
        $message->component = 'mod_spatialforum';
        $message->name = 'communicationtests';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $USER;
        $message->subject = '[TEST] ' . $rendered['subject'];
        $message->fullmessage = $rendered['text'];
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $rendered['html'];
        $message->smallmessage = $message->subject;
        $message->notification = 1;
        return message_send($message);
    }
}
