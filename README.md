# Samtalespor (`mod_spatialforum`)

Samtalespor is a Moodle activity module that turns branching discussion into named, colour-coded spatial tracks. The main track stays white, each branch gets its own colour, and the interface keeps both the route back and the complete track map visible.

## Compatibility and release status

- Development build: **0.9.0-dev** (alpha); not approved or published on Moodle Marketplace.
- Declared installation minimum: Moodle LMS 5.0 (`requires = 2025041400`). This is a minimum-version gate, not proof that every later version has been tested.
- The previous release was tested on Moodle LMS 5.2.2+, PHP 8.3 and MariaDB 11.4. Current release results and database coverage are recorded in [docs/RELEASE-CANDIDATE.md](docs/RELEASE-CANDIDATE.md).
- Moodle Workplace compatibility has not been verified and is not claimed for this release.
- No additional plugins, external service accounts or paid service subscriptions are required. Administrator installation requires no Node.js or Composer commands.

For setup and use, see [the user guide](https://github.com/ZasZorglub/moodle-mod_spatialforum-support/blob/main/USER-GUIDE.md). For support and issue reporting, see [the support policy](SUPPORT.md).

## 0.11.0 contribution completion

Automatic activity completion can require a minimum total number of contributions, replies and/or new top-level posts. Zero disables an individual rule; all selected requirements must be met. Deleted posts and other participants' contributions do not count. Replies include contributions in child tracks. Posting, moderation deletion and individual post erasure refresh the participant's Moodle completion state. Existing activities default to no contribution requirements. Rule changes follow Moodle's standard completion-unlock/reset workflow; Gradebook completion remains a separate core Moodle condition.

Administrator opt-in at **Site administration → Plugins → Activity modules → Spatial forum → Enable grading** is required before grading interfaces appear. Individual activities must also configure a grade. Disabling the site switch preserves existing Gradebook data; Moodle Gradebook permissions continue to govern the central gradebook.

## 0.10.0 standard-forum import

Teachers with course activity-management/import permissions can use **Import a standard forum** in the course navigation. Preview reports the source counts, supported media, exclusions and blockers before explicit confirmation. Supported source types are General and Each person posts one discussion. The importer creates a new hidden activity, preserves discussion titles, post subjects, authors, dates, parent relationships, group scope and supported files, and remaps links inside the source. The source remains unchanged.

Moodle cron copies and rewrites up to 100 posts per task. Incomplete copies cannot be read, edited or backed up. Review the completed hidden copy before publishing through normal Moodle settings. Changed sources, private/timed material, unsupported files, invalid trees and broader read permissions block import. Failed jobs can resume the same unchanged snapshot or discard only the incomplete target. No subscriptions, historical notifications, reads, ratings, grades or completion are imported. This is a one-time copy, not ongoing synchronisation. See [import verification and limits](tests/IMPORT-0100.md).

## 0.9.0 whole-activity grading

Editing teachers can enable points or a course/site scale in the normal activity **Grade** settings. New and upgraded activities default to None. **Assess participants** opens a paginated, group-filtered roster and one manual score/private-feedback form per active eligible participant. In both separate and visible groups, this plugin requires a shared group within the configured grouping unless the grader has access-all-groups. Learners use **Your grade and feedback** to see their permitted own result. Grades and feedback never appear in conversation cards.

Moodle Gradebook is the only grade/feedback store. Zero is distinct from unset/cleared; feedback without a score is supported. Save rechecks permissions, enrolment, groups, locks/overrides and the form revision. Maintenance callbacks preserve manual grades. Gradebook locks, overrides, calculations, adjustments and externally formatted feedback are protected; manage those cases in Gradebook. Hidden item/user results and disabled course-grade visibility withhold both score and feedback here. Core Gradebook has its own course permissions; the plugin does not narrow access separately granted there.

Changing grading type/maximum is blocked while scores or feedback exist. Core backup/restore preserves authorised user grades, scale mappings, visibility, locks and overrides; backups without users retain grading configuration without participant assessments. The core Gradebook privacy provider owns grade/history export and erasure. No plugin-specific grade email is introduced; core Moodle messaging settings remain in control. See [grading verification](tests/GRADING-090.md).

## 0.8.1 administrator communication setup

Open **Site administration → Plugins → Activity modules → Spatial forum communication**. A site administrator controls notifications for top-level posts and replies, enables individual messages/daily digests, sets an individual-message delay (0–1440 minutes), and chooses a daily hour/minute in each recipient's Moodle timezone or the site's timezone. Rules apply to existing and new activities, while subscriptions remain opt-in and Moodle processor preferences remain authoritative.

Edit the plain-text subjects/bodies and repeated digest-item template with the displayed variables. Unknown/incomplete variables, multiline subjects and invalid times are rejected. Post links and the preferences link are automatically retained; content substitutions cannot execute HTML or template code. Saved custom text is shared across languages. Preview uses synthetic data; **Send test to my account** sends the unsaved example only to the current administrator. Preview/test do not save settings, and saving never sends a test message. Moodle acceptance does not prove inbox receipt.

Policy changes enqueue bounded reconciliation of pending deliveries. Disallowed messages are cancelled when processed, schedules are recalculated from publication time, explicit-failure backoff is preserved, and accepted/failed/uncertain rows are not replayed. Dispatch rechecks enabled types and postponed schedules even before reconciliation finishes. Text is rendered at dispatch. Configuration changes are recorded in Moodle's configuration log. These site settings are not copied inside activity backups. See [communication verification](tests/COMMUNICATION-081.md).

## 0.8.0 notifications and daily digests

Use **Notification preferences** below the conversation to choose Off, Each new post or Daily digest for this activity. New users and existing activities start unsubscribed. Notifications cover only new posts from other participants that the recipient can currently access; they do not mark posts as read. Normal Moodle notification preferences control channels. Uploaded media remain in Moodle behind activity access checks.

Moodle cron must run regularly: a post creates a transactional fan-out task, then the scheduled notification task hands due work to Moodle Message API. Daily cutoffs default to 17:00 in the recipient's Moodle timezone, configurable in module site settings. Large digests are split into batches of at most 100 posts. Unsubscribe cancels plugin delivery work; already accepted messages cannot be recalled. Imports can explicitly suppress notifications; restores never copy subscriptions or delivery queues and require fresh opt-in.

Administrators can inspect **Spatial forum notification delivery** under the activity-plugin settings. Accepted means handed to Moodle, not verified inbox delivery. Explicit failures retry up to five times with backoff. Ambiguous dispatches require administrator review and are not automatically resent. Delivery metadata expire after 30 days. See [notification verification](tests/NOTIFICATIONS-080.md) for observed evidence and limits.

## 0.6.0 media and audit corrections

Posts support Moodle-managed PNG/JPEG images, MP3 audio, MP4 video and VTT captions. Uploaded files belong to the post, use authenticated pluginfile URLs, and follow post backup/restore, privacy export/erasure and activity deletion. External URLs remain external. Site settings control accepted formats, file count, per-file size and combined uploaded size per post. Defaults are 10 files, 20 MB per file (subject to Moodle/course limits) and 50 MB per post. Media-only posts are accepted; visually empty HTML is rejected.

Privacy erasure anonymises child-track names derived from the author's first post. Upgrade also repairs names left by earlier erasures. Restore remaps links inside post messages, including post/hash links. Quote rendering batches source posts and authors, instead of querying per quote. Very large conversations still load their full HTML; this is not pagination.

Unread only removes a previously read card when moving to the next post, colours respect the activity setting, audio players fit narrow content areas, and the active Reply to selection action stays at the top of the open post while scrolling. Moderator actions require POST and a session key; deletion has a server confirmation page before files/content are removed. The thread layout and native reading fallback remain intact.

See [tests/RELEASE-060.md](tests/RELEASE-060.md) for audit IDs, test evidence and limits.

## Threaded reading and reply experience

Posts unfold in their original place in the thread. The complete map and its connecting wires stay visible; the selected column widens for readable text and neighbouring cards reflow without overlaps. Long map cards scroll internally to keep the surrounding thread in view. In List, the opened post retains its hierarchical indentation and uses the full content height. Map and List are explicit choices; small available widths default to List. Search, Unread only and Next unread help navigate the loaded conversation. Copy link, reload, browser history and cancellation preserve the selected post; per-tab session storage keeps only presentation preferences and overview offsets, not post content.

When text is selected inside a post, **Reply to selection** becomes green, high-contrast and visibly emphasized; the button exposes its enabled state to assistive technology.

The activity uses Moodle's introduction once and a compact toolbar. Reading-route emphasis preserves text contrast. New posts have their own heading and Create post action. Ordinary replies start with the message editor and a compact, expandable original; passage quoting and whole-parent quoting are optional disclosures. Removing a quote restores logical keyboard focus. The Marketplace package ships English labels. The Danish translation is retained in the source repository for later AMOS submission. See `tests/UX-050.md` for acceptance and verification boundaries.

Build the dependency-free AMD wrappers with `node scripts/build-amd.mjs`. The group-support development build adds post audiences and a legacy-audience review gate. See [group verification](tests/GROUPS-070.md) before upgrading.

## 0.4.0 selective replies

Select text in an opened post and choose **Reply to selection**. The reply form opens with that passage and a comment field. Select further passages in the original above the form and choose **Add selected text** to build an interleaved quote/comment reply. Remove any unwanted blocks before submitting. Up to ten blocks, each with a 4,000-character quotation/comment limit, form one reply and one automatically linked track. The regular message editor provides an optional closing comment. Plain Reply and optional whole-parent quoting remain available.

Selections are checked against the source on the server. Stored blocks contain the reply author's comments, source offsets and a fingerprint; no copy of another author's text is stored. The visible quotation links to the source and its current author. A changed source displays a notice instead of a potentially incorrect passage; deleting or privacy-erasing a source hides its quotations. Previews contain only the reply author's own text. Backup/restore and Privacy API export/deletion include the new nullable `replyparts` field. Selection/composer interactions require JavaScript; ordinary replies remain available without it.

## Conversation map

The map uses a white background. Main-track columns occupy 50% and reply-depth columns 25% of the visible plugin width, including their connector gutters. Columns have minimum widths of 320/240 pixels for small screens; extra depth scrolls horizontally instead of compressing cards. A continuous reply chain retains its colour; children at a fork receive palette colours. Wires match the destination segment and attach to card-summary edges. Main posts stay white with a neutral outline. Colours are derived for the map; stored track colours and backup data are unchanged. Very large maps (over 150 posts) retain the list fallback.

Version 0.3.1 adds explicit creation dates/times on closed and opened cards, and an Edited timestamp when an undeleted post's modification time is later than its creation time. Times follow the viewer's Moodle timezone/locale; quoted originals show the same metadata. Reading and reacting do not change a post's edit time. This is timestamp presentation, not a new post-editing workflow or revision history.

- The complete activity is a connected map of posts. Opening a card unfolds its content inside the map; only one post stays open. Close or Escape collapses it and restores the previous overview position. A highlighted route links back through ancestors.
- Closed cards show two lines of the post's own text, with explicit Read/Unread labels. Previewing the map changes no read state. Opening a post sends an authenticated, session-key protected POST for that post only; the UI shows Read only after server confirmation. Each user's timestamps are independent. Existing timestamps are preserved during upgrade.
- Participants simply choose Reply. The server automatically creates the linked child track and uses the editable reply subject as its internal name. There is no branch-name decision or duplicate Start track here action.
- Native details and a Mark as read form work without JavaScript. Dense maps reflow into the same hierarchy as a list; text and controls remain untransformed. Reduced-motion preferences are respected.
- The map currently loads the whole activity. Validate performance with representative course data; more than 150 posts use the list layout. This release does not introduce pagination or virtualisation.

## Install

1. Unpack the archive as `<moodleroot>/mod/spatialforum`.
2. Visit **Site administration → Notifications**, or run:

   ```sh
   php admin/cli/upgrade.php
   ```

3. Add **Samtalespor** as an activity in a course.

## Included

- Main track plus named nested tracks
- Per-track colours and persistent route/map navigation
- Controlled 3D depth and reduced-motion support
- Replies, unread markers and branch creation from a post
- Editable reply subjects prefilled with `Re:` and an optional, visually separated parent-post quote
- One like or dislike per participant and post, with switching/removal, per-activity control and a site-wide administrator switch
- Track locking and moderator post removal
- Moodle capabilities for view, reply, branching and moderation
- Activity completion on view
- No/separate/visible groups and groupings, with a shared server-side audience policy
- Opt-in activity notifications: each new post or daily digest through Moodle
- Manual whole-activity points/scales and private participant feedback in Moodle Gradebook
- Course backup and restore
- Privacy API metadata, export and deletion
- English language pack; Danish translation retained separately in source for AMOS
- Responsive layout for desktop, tablet and mobile

## Current MVP limits

The [feature expansion plan](docs/FEATURE-EXPANSION.md) records implemented development slices and the remaining App work.

- No separate attachment list, per-post ratings or activity completion based on posting
- No Moodle App/mobile service implementation
- No ongoing synchronisation with the standard Moodle forum activity; import supports the restricted subset described above
- 3D is a progressive visual layer; core navigation remains conventional links for accessibility

## Security and operations

- Version `0.9.0-dev` upgrades earlier versions to `2026102903`. Existing activities keep their content and require explicit audience review before learners can use group mode. Activities without groups continue normally. Only a user with manage-activities and access-all-groups can confirm the audience for all legacy posts. Cancel if different conversations require different audiences; this first review workflow assigns one audience to the entire legacy activity. New replies inherit their stored parent audience. See [tests/GROUPS-070.md](tests/GROUPS-070.md).
- Teachers who can edit an activity can disable **Allow like/dislike on posts** in its settings. Administrators can disable **Allow like/dislike site-wide** in the module's site settings. Both switches hide both controls/counts and reject direct submissions, while preserving existing reactions.
- Reactions use POST, session keys, a separate capability and a unique `(userid, postid)` index. Each user chooses like, dislike or neither. Repeating the same request does not toggle the result.
- Authors opt in to a parent quote with **Include the previous post as a quote** (off by default). The reply relationship is preserved independently of this choice. Quotes read the original live rather than copying message text. Deleted originals appear as unavailable, including across track boundaries.
- Each Reply to an existing post starts a new child track on submission. Two replies to the same post create separate sibling tracks; further replies create another child track. The server names the track from the reply subject. Cancelling creates nothing. New top-level posts use the main track. Replying requires both reply and branch capabilities, an unlocked conversation and room below the configured maximum track depth.
- The spatial appearance is decorative: post content and controls are never transformed into 3D space. Reduced-motion preferences are respected.
- GitHub Actions defines Moodle 5.0/5.2 checks with PHP 8.3 on MariaDB and PostgreSQL. A workflow definition alone is not a passing run. This does not replace visual testing on the target Moodle/Workplace site. See `tests/STAGING.md`.
- `npm ci --prefix tests/javascript --ignore-scripts` and `npm test --prefix tests/javascript` run DOM checks against the real Mustache template and both AMD source/build. These exercise unfolding, read persistence/failures, keyboard close, responsive fallback and cyclic imported references; they do not replace real-browser layout testing.

- All write operations use Moodle capabilities and session keys.
- Post content is rendered through Moodle's text formatting pipeline.
- Test backup/restore and privacy deletion with representative staging data before production rollout.
- Keep the plugin under normal Moodle change control and upgrade it together with the platform.

## License

GNU GPL v3 or later.
