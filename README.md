# Samtalespor (`mod_spatialforum`)

Samtalespor is a Moodle activity module that turns branching discussion into named, colour-coded spatial tracks. The main track stays white, each branch gets its own colour, and the interface keeps both the route back and the complete track map visible.

## Compatibility

- Moodle LMS 5.0 or newer (`requires = 2025041400`)
- Moodle Workplace 5.x, using public Moodle activity, capability, backup and privacy APIs only
- PHP and database requirements follow the installed Moodle 5.x release

This is an MVP release. It has been statically checked, but must be tested on a staging copy of the exact Moodle or Workplace build before production use.

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
- Editable reply subjects prefilled with `Re:` and a live, visually separated parent-post quote
- One like per participant and post, with unlike, per-activity control and a site-wide administrator switch
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

- Version `0.2.0 (staging)` upgrades MVP `2026100500` to `2026102400`. Run the normal Moodle upgrade; do not uninstall the activity to update it.
- Teachers who can edit an activity can disable **Allow likes on posts** in its settings. Administrators can disable **Allow likes site-wide** in the module's site settings. Both switches hide the controls/counts and reject direct submissions, while preserving existing likes.
- Likes use POST, session keys, a separate capability and a unique `(userid, postid)` index. Repeating the same request does not toggle the result.
- Parent quotes use the existing `replytoid` relationship rather than copying message text into the reply. Deleted originals appear as unavailable, including in existing replies.
- The spatial appearance is decorative: post content and controls are never transformed into 3D space. Reduced-motion preferences are respected.
- GitHub Actions installs Moodle 5.0 with PHP 8.3/MariaDB and runs plugin tests. This does not replace visual testing on the target Moodle/Workplace site. See `tests/STAGING.md`.

- All write operations use Moodle capabilities and session keys.
- Post content is rendered through Moodle's text formatting pipeline.
- Test backup/restore and privacy deletion with representative staging data before production rollout.
- Keep the plugin under normal Moodle change control and upgrade it together with the platform.

## License

GNU GPL v3 or later.
