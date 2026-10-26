# Samtalespor (`mod_spatialforum`)

Samtalespor is a Moodle activity module that turns branching discussion into named, colour-coded spatial tracks. The main track stays white, each branch gets its own colour, and the interface keeps both the route back and the complete track map visible.

## Compatibility

- Moodle LMS 5.0 or newer (`requires = 2025041400`)
- Moodle Workplace 5.x, using public Moodle activity, capability, backup and privacy APIs only
- PHP and database requirements follow the installed Moodle 5.x release

This is a staging release with automated Moodle 5.0 regression tests. It must also be tested on a staging copy of the exact Moodle or Workplace build before production use.

## 0.5.1 threaded reading and reply experience

Posts unfold in their original place in the thread. The complete map and its connecting wires stay visible; the selected column widens for readable text and neighbouring cards reflow without overlaps. Long map cards scroll internally to keep the surrounding thread in view. In List, the opened post retains its hierarchical indentation and uses the full content height. Map and List are explicit choices; small available widths default to List. Search, Unread only and Next unread help navigate the loaded conversation. Copy link, reload, browser history and cancellation preserve the selected post; per-tab session storage keeps only presentation preferences and overview offsets, not post content.

The activity uses Moodle's introduction once and a compact toolbar. Reading-route emphasis preserves text contrast. New posts have their own heading and Create post action. Ordinary replies start with the message editor and a compact, expandable original; passage quoting and whole-parent quoting are optional disclosures. Removing a quote restores logical keyboard focus. All UI labels are available in Danish and English. See `tests/UX-050.md` for acceptance and verification boundaries.

Build the dependency-free AMD wrappers with `node scripts/build-amd.mjs`. This release changes no database schema or permission rules.

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
- Course backup and restore
- Privacy API metadata, export and deletion
- Danish and English language packs
- Responsive layout for desktop, tablet and mobile

## Current MVP limits

- No subscriptions, notifications or digests
- No attachments, groups, grading or activity completion based on posting
- No Moodle App/mobile service implementation
- No import or synchronization with the standard Moodle forum activity
- 3D is a progressive visual layer; core navigation remains conventional links for accessibility

## Security and operations

- Version `0.5.1 (staging)` upgrades MVP or earlier staging versions to `2026102600`. Run the normal Moodle upgrade; do not uninstall the activity to update it. Existing likes, published quotes and read timestamps are preserved.
- Teachers who can edit an activity can disable **Allow like/dislike on posts** in its settings. Administrators can disable **Allow like/dislike site-wide** in the module's site settings. Both switches hide both controls/counts and reject direct submissions, while preserving existing reactions.
- Reactions use POST, session keys, a separate capability and a unique `(userid, postid)` index. Each user chooses like, dislike or neither. Repeating the same request does not toggle the result.
- Authors opt in to a parent quote with **Include the previous post as a quote** (off by default). The reply relationship is preserved independently of this choice. Quotes read the original live rather than copying message text. Deleted originals appear as unavailable, including across track boundaries.
- Each Reply to an existing post starts a new child track on submission. Two replies to the same post create separate sibling tracks; further replies create another child track. The server names the track from the reply subject. Cancelling creates nothing. New top-level posts use the main track. Replying requires both reply and branch capabilities, an unlocked conversation and room below the configured maximum track depth.
- The spatial appearance is decorative: post content and controls are never transformed into 3D space. Reduced-motion preferences are respected.
- GitHub Actions installs Moodle 5.0 with PHP 8.3/MariaDB and runs plugin tests. This does not replace visual testing on the target Moodle/Workplace site. See `tests/STAGING.md`.
- `npm ci --prefix tests/javascript --ignore-scripts` and `npm test --prefix tests/javascript` run DOM checks against the real Mustache template and both AMD source/build. These exercise unfolding, read persistence/failures, keyboard close, responsive fallback and cyclic imported references; they do not replace real-browser layout testing.

- All write operations use Moodle capabilities and session keys.
- Post content is rendered through Moodle's text formatting pipeline.
- Test backup/restore and privacy deletion with representative staging data before production rollout.
- Keep the plugin under normal Moodle change control and upgrade it together with the platform.

## License

GNU GPL v3 or later.
