# 0.8.0-dev activity subscriptions and daily digests

Baseline: `b3f66e7` / `0.7.0-dev`. This is implementation slice S2; grading, standard-forum import and dedicated Moodle App support remain subsequent work.

## Behaviour and boundaries

- Authenticated active participants opt in per activity: Off, Each new post, Daily digest. There is no automatic subscription or historic-post backfill. Only the current user's preference is changed. Authenticated unsubscribe remains available after course access is revoked.
- Publication and its fan-out task are committed/rolled back together. The posting request never loops through recipients or sends email. An explicitly silent creation option supports future import. The fan-out task pages subscribers and records an idempotent recipient/post key.
- Eligibility is checked both during fan-out and immediately before delivery: active enrolment, user suspension/deletion, course visibility, activity capability/availability and current group access. Unsubscribe serialises with fan-out/delivery through the same recipient lock. Deleted/erased post content is cancelled. Read markers are unchanged.
- Notification text contains a bounded excerpt of the author's own contribution, without copied parent quotations or media attachments. Authenticated post/settings links are included. Message processors/preferences remain under Moodle control.
- Daily cutoffs use the recipient's Moodle timezone and the configured hour (default 17). DST transitions are covered. The next cron run processes due rows; there is no promise of exact wall-clock delivery. Digests split at 100 posts; a task handles at most 1000 candidate rows and 100 messages. Remaining work stays pending for later runs.
- Explicit provider refusal retries up to five times with exponential backoff. An exception/crash after claiming dispatch leaves Uncertain state, requiring administrator review instead of automatic resend. There is no exactly-once SMTP claim. Accepted records mean Message API acceptance, not inbox delivery. The admin-only delivery report exposes aggregate status counts, no message bodies. Delivery metadata expire after 30 days, including stale pending/failed/uncertain rows; operators should investigate before expiry.
- Privacy discovery/export/deletion includes subscription-only participants and delivery metadata. Author erasure cancels queued references for their posts. The core messaging subsystem owns already accepted messages; already delivered email cannot be recalled.
- Backup/restore intentionally excludes subscription preferences and delivery queues. A restored activity requires fresh opt-in and never replays notifications. Activity deletion clears both tables. Upgrade creates empty tables without auto-subscribing existing users or scheduling history.

## Observed evidence

Local environment: isolated Moodle 5.2.2+, PHP 8.3.36, PostgreSQL 16.15 and Mailpit 1.10. Source is mounted read-only; standard plugin subtrees and all main/test data are isolated. The existing test-server database/data are not used. All test users, posts and mail addresses are synthetic. SMTP is routed exclusively to local Mailpit.

The full local suite passed: 72 tests, 384 assertions. Moodle PHPCS reported zero errors and warnings; PHP syntax and both JavaScript regression suites passed. Automated coverage includes opt-in/history boundaries, authored-post exclusion, idempotent fan-out/dispatch, daily batching/cutoffs, DST, revoked group/enrolment access, hidden activity/course, suspension, unsubscribe/resubscribe, mode changes, explicit failures/backoff, uncertainty, transaction rollback, silent creation, retention, privacy, restore and schema upgrade. Final logs and committed CI results live in `../rapporter/samtalespor-0.8.0-abonnementer-2026-10-29/`.

Own browser, English UI: a real enrolled participant selected Each new post, saved it, re-opened the form, switched to Daily digest, saved it, and later unsubscribed through the same form. New notifications appeared in Moodle without marking posts as read. Reopening preferences confirmed Off. The participant was denied access to the administrator delivery report. Positive administrator-browser rendering remains unverified. Screenshots: `digest-preferences.png` and `unsubscribed-preferences.png` in the evidence folder.

Real CLI task/SMTP test: one immediate post produced one Mailpit message. Two digest posts produced no additional mail before their due time, then one digest containing both source post links. Only the synthetic queue cutoff was advanced to exercise due delivery; this is not an observed overnight wait. After browser unsubscribe, another post/fan-out/task run left the mailbox count unchanged. Final mailbox counts: 1 / 1 / 2 / 2. Full message text and authenticated links were verified through Mailpit's API. Synthetic `example.org` addresses were used because Moodle deliberately suppresses `.invalid` recipients. No external inbox or real participant received a test message.

## Release limits

This is an alpha development build, not a Marketplace or existing-test-server deployment. Remote CI must finish for the committed source on Moodle 5.0/5.2 and MariaDB/PostgreSQL. Mailpit capture proves SMTP delivery to the isolated test server, not deliverability at a customer's mail provider. Physical devices, screen readers, concurrent-user load, large-recipient task throughput and externally monitored long-running cron operation remain unverified. There is no weekly/thread-specific/forced subscription, email reply, mobile push proof or copied subscription preference on restore.

Before deploying, back up database, Moodle data and plugin code. Apply the Moodle upgrade, confirm task registration and cron, use synthetic opt-in accounts and verify the real site's mail processor before rollout. A consistent backup is required for schema rollback; replacing older PHP alone is insufficient.
