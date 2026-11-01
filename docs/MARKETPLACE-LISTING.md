# Moodle Marketplace listing draft — owner review required

All public copy, screenshots and captions for this submission must be in English and use synthetic data only.

## Name

Spatial forum

## Short description

A better forum for meaningful discussion: easier navigation, clearer conversation overview and a user experience designed around how people actually read, follow and contribute to branching conversations.

## Full description

Spatial forum is a Moodle activity module built to make online discussion easier to follow. It is a better forum experience for conversations that branch into connected tracks: opening a post keeps it inside the surrounding thread, so participants retain context while reading and replying. Users can switch between Map and List, enable Compact view, search the conversation and move through unread contributions without flattening the hierarchy.

Designed with the user experience in mind, Spatial forum makes the structure of a conversation visible instead of hiding it behind a long, flat list. Participants can see where a reply belongs, understand the route through a discussion and return to the wider overview without losing their place.

Participants create top-level posts or reply in connected child tracks. Moodle-managed images, MP3 audio, MP4 video and VTT caption files can be included through the editor, subject to site and course limits. Teachers can configure track depth, reactions, group mode, completion based on contributions, and an optional whole-activity grade with private feedback.

Participants can follow the whole activity or individual conversations and choose individual notifications or daily digests. Administrators control subscription policy, notification types, timing and plain-text message templates. Delivery uses Moodle cron and the Message API; the site's processor configuration remains authoritative.

The module supports no groups, separate groups, visible groups and groupings, with server-side access checks across posts, files and services. It also includes course backup/restore, Privacy API export and deletion, standard-forum preview/copy import, a JSON interchange export, and dedicated authenticated web services for online Moodle App clients.

No external service, API key or additional plugin is required. The complete conversation stays available in List view when Map is unsuitable; conversations above 150 posts automatically use List. Native Moodle App presentation, offline App use, physical-device screen-reader coverage, high-concurrency load and Moodle Workplace compatibility are not claimed for this release.

## Submission fields

- Component: `mod_spatialforum`
- Planned release: `0.14.0-rc.1` / `2026110101`
- Planned maturity: release candidate
- Pricing: Free
- License: GNU GPL v3 or later
- Dependencies: None outside Moodle LMS
- Supported Moodle versions: Moodle 5.0–5.3, subject to the final CI matrix
- Source: https://github.com/ZasZorglub/moodle-mod_spatialforum
- Public issue tracker: https://github.com/ZasZorglub/moodle-mod_spatialforum-support/issues
- Documentation: include `README.md` and `docs/USER-GUIDE.md` in the submitted public source repository
- Support: community issue tracker; no guaranteed response time or service-level agreement

Select only Moodle versions covered by the final green CI matrix and release evidence. The source repository must be publicly accessible before submission; an unauthenticated check currently returns 404.

## English screenshot set

Prepared with the responsive Boost layout, English UI and synthetic users/course content. The images contain no tunnel hostname, session key or personal data.

1. `01-conversation-map.png` — complete branching map with several visible tracks.
2. `02-open-post-in-context.png` — one opened post while the surrounding thread remains visible.
3. `03-compact-list-view.png` — Compact view enabled in the hierarchical List.
4. `04-reply-with-media.png` — English reply editor with image, multimedia, audio and video controls.
5. `05-notification-preferences.png` — participant notification mode with delivery and access guidance.
6. `06-teacher-settings.png` — English administrator settings for media limits, optional grading, reactions and subscription policy.

Each caption must describe only visible, verified behaviour. Do not show the test tunnel hostname in public material.

## Submission boundary

This file prepares content but does not authorise Marketplace submission. Before submission, the owner must approve the final ZIP, compatibility selections, screenshots, listing copy, support terms and the free pricing choice. Marketplace upload is an external submission and requires separate final approval.
