# 0.10.0-dev standard-forum import

Baseline: `ce647c7d4eaaa7f778024d300aea074a659eeec5` / `0.9.0-dev`. Implements S4: preview and copy to a new hidden activity in the same course. Dedicated Moodle App support remains S5.

## Contract

- General and Each person posts one discussion sources only. Whole-forum access requires course import/add-instance/manage-activities permissions, source read access and access-all-groups when grouped. Source module role assignments are unsupported; effective role permissions must not broaden read/group access in the new activity. Group mode, grouping and availability are retained.
- Preview creates no activity, job, files, reads or delivery state. Confirmation requires a checkbox, session key and current SHA-256 content/policy fingerprint. Repeated identical confirmation by the same actor returns the existing job. The source is never modified.
- Preserve original discussion title separately from root subject, author, creation/modification time, text format and parent graph. Replies become child tracks. Invalid/missing/cyclic parents, depth over 12, erased authors, private/deleted posts, timed/locked discussions and unsupported forum types block conversion.
- Copy PNG/JPEG/MP3/MP4/VTT through Moodle File API with separate inline/attachment paths. Apply current site/course/plugin file limits; unsupported or missing inline files block conversion. Images/audio/video attachments render as media; VTT attachments remain authenticated links. Same-source activity/discussion/post/file links are remapped. External and other-forum links retain their destinations.
- No source grades, ratings, subscriptions, read/mail state, pins, completion or future standard-forum posting constraints are imported. Historical copying bypasses normal publication/fan-out; no backfill messages are queued. Target grading defaults to None.
- Actor-bound adhoc tasks process at most 100 posts per copy/rewrite page, check the immutable source again, and use a job lock and per-post transactions. Incomplete content has an independent access/edit/backup gate, even if core visibility is changed. Completed copies remain hidden for teacher review and normal publication.
- Failed jobs require explicit retry against the unchanged snapshot or discard of the identified hidden target. Discard suppresses recycle-bin backup only for this request, restoring the previous request configuration in `finally`; a partial import must never become a publishable restored activity. Source changes require discard and fresh preview. Deleting a target removes its maps/job; later queued tasks safely no-op.
- Privacy discovery/export includes job owners and source/copy authors. Erasure removes source back-references and cancels incomplete work so it cannot reintroduce erased posts. Completed backups retain title/media flags and files, but exclude job/mapping metadata. Pending imports cannot be backed up.

## Verification

Isolated Moodle 5.2.2+, PHP 8.3.36, PostgreSQL 16.15. Original testserver containers/data remain untouched. Synthetic English-only teacher/learner data and an isolated Mailpit processor are used.

Evidence folder: `../rapporter/samtalespor-0.10.0-import-2026-10-29/` relative to the repository parent. It contains upgrade, regression, coding-standard, actual adhoc-worker logs, browser screenshots and package/CI identity evidence.

Browser: teacher selected source and target name, preview showed 1 discussion / 3 posts / depth 3; unchecked confirmation was rejected; checked confirmation created a hidden target. Actual Moodle CLI task runner completed copy and rewrite continuations. Progress showed 3/3 and still hidden; review opened the three-level map and original-author posts in place.

Local baseline verification passed 123 tests / 598 assertions on Moodle 5.2 with PostgreSQL. GitHub run [40926460908](https://github.com/ZasZorglub/moodle-mod_spatialforum/actions/runs/40926460908) for `ae8513e` passed Moodle 5.2 on both databases but failed both Moodle 5.0 jobs: `cmactions::delete()` was unavailable when discarding a partial import. The compatibility fix checks the actual method and falls back to `course_delete_module()` on Moodle 5.0. The existing regression now also checks removal of job/maps and restoration of request-local recycle-bin configuration. Final CI must pass the complete 5.0/5.2 × MariaDB/PostgreSQL matrix before this fix is reported as verified.

## Limits

Alpha development release. No existing-testserver or Marketplace deployment is implied. Import is a one-time copy; no merge, cross-course conversion or ongoing synchronisation. Preview/confirmation and each task verify the full source; only copy/rewrite batches are bounded. Large-course memory, query cost, concurrent source editing and crash/worker-kill behaviour need representative performance testing. Full-source fingerprint checks do not lock standard-forum editing. File rendering tests establish copied bytes/markup, not cross-browser codec playback or physical-device behaviour. Moodle App, accessibility and broad operational release gates remain open.
