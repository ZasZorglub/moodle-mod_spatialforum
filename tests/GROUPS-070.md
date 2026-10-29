# 0.7.0-dev group support

Implementation slice S1 from `docs/FEATURE-EXPANSION.md`. The user confirmed whole-activity manual grade/feedback, online App reading/replies and copy-to-new-activity import on 29 October 2026. Those three features and subscriptions are subsequent slices and are not implemented by this group change.

## Implemented

- No groups, separate groups, visible groups and groupings, including effective course-forced group mode.
- Per-conversation audience stored on each post; replies inherit the stored parent's audience. The activity-wide root track remains shared structural data, not an audience.
- Common server-side policy for map/list payloads, routes, parent previews, quotes, unread counts, reactions, posting, track moderation and post files. Visible-group reading grants no cross-group contribution rights. Root-track locking spans audiences and requires access-all-groups in grouped mode; permitted post deletion remains separate.
- New top-level posts use the selected Moodle activity group. The posting form preserves that selection across validation. Shared top-level posting in group mode requires access-all-groups. As in Moodle forum, ordinary participants may reply to shared conversations in visible-groups mode, but not in separate-groups mode.
- Upgrade preserves legacy content, initially marked unreviewed. No-groups activities keep working. Grouped activities with unreviewed legacy audiences deny learner access until a staff member explicitly confirms one audience for all existing posts. The review previews the total and warns about All participants; it requires confirmation, session key, manage-activities and access-all-groups, and is idempotent. Unlinked legacy branches containing posts block audience certification until their conversation links are repaired, preserving content and the review gate. Cancel when legacy conversations need different audiences; per-conversation legacy reassignment is not implemented.
- Backup annotates/remaps audience group IDs. An unresolved restored group is never silently converted to a shared scope; it triggers review. Privacy export includes the post audience. Membership/grouping changes are resolved per new policy instance, without a persistent permission cache.

## Verification

Local environment: isolated Moodle 5.2.2+, PHP 8.3.36, PostgreSQL 16.15. Main Moodle source and the plugin are mounted read-only into the QA container; standard plugin subtrees are copied into disposable mounts. Test data use a separate PostgreSQL container and temporary Moodle data. The original test-server database/data are not used.

Tests exercise separate/visible groups, two independent learners, a multi-group learner, an ungrouped learner, guest access, access-all-groups, grouping restrictions, removed membership, forced course mode, unread state, denied replies/reactions/files, filtered quoted parents, explicit/idempotent legacy review, group mapping during real backup/restore, and schema upgrade preserving content/review state. The complete local run passed **54 tests / 308 assertions**; both JavaScript suites, PHP syntax and Moodle coding standard (0 errors / 0 warnings) also passed. The exact results are recorded in the local evidence folder `../rapporter/samtalespor-0.7.0-grupper-2026-10-29/` and the CI run for the committed code.

UI JavaScript suites and Moodle coding-standard/PHP checks are also required. Do not treat test results from 0.6.1 as evidence for this code. Remote CI targets Moodle 5.0/5.2 and MariaDB/PostgreSQL.

## Limits before release

This is an alpha development build. A complete real-browser teacher/learner walkthrough of the group selector and legacy-review form, physical-device/screen-reader checks and concurrency testing remain open. No Marketplace or test-server deployment is implied. New notification, grading, import and App integrations must use this audience boundary and add their own recipient/identity/capability checks.

Back up database, Moodle data and plugin code before deployment. To roll back a schema change, restore a consistent backup; do not just replace PHP files. The original 0.6.1-rc.1 ZIP is retained as a prior candidate.
