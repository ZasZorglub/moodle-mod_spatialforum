# 0.14.0-rc.1 release candidate

Prepared on 1 November 2026. This is a candidate for Moodle Marketplace review, not an approval or public release.

## Candidate identity

- Component: `mod_spatialforum`
- Release: `0.14.0-rc.1`
- Build: `2026110101`
- Supported declaration: Moodle 5.0–5.3
- Package: deterministic English-only ZIP produced by `scripts/package-release.py`
- Pricing decision: Free initial listing

## Candidate changes

- Added an optional **Compact view** that reduces closed-card spacing, preview height and metadata height while preserving the complete thread hierarchy, post elements and connections.
- Stored display density only in per-tab session storage; no post content or personal data is added to browser preference storage.
- Updated English Marketplace copy, screenshot plan, user guide and current feature/limitation statements.
- Extended the CI matrix to Moodle 5.3 and PostgreSQL 17 while retaining Moodle 5.0/5.2 and MariaDB coverage.
- Declared RC maturity and Moodle 5.0–5.3 support in `version.php`.

## Notification verification without SMTP

The PHPUnit suites use Moodle's message redirection sink, not a real mailbox. They verify Message API acceptance and the generated recipient, subject, body and authenticated links without sending external mail. Existing focused cases cover:

- individual notifications and daily digests
- activity and conversation-level preferences
- administrator templates, timing, type switches and preview/test restrictions
- source explanations, no self-notification and no historical replay
- current enrolment, suspension, visibility and group-access rechecks
- unsubscribe, forced/disabled policy behaviour and pending-work cancellation
- idempotent fan-out, bounded retries, uncertain delivery and reconciliation
- digest timezone/DST handling and batching

This proves plugin-side generation and handoff to Moodle's Message API. It does not prove SMTP delivery, spam handling, push delivery or receipt in an external inbox. Those remain the Moodle site's operational responsibility.

## Automated release gates

GitHub Actions installs the plugin and runs PHP lint, Moodle coding standards, metadata validation, upgrade savepoints, JavaScript DOM regressions, deterministic ZIP validation and the complete plugin PHPUnit suite on:

- Moodle 5.0 / MariaDB and PostgreSQL
- Moodle 5.2 / MariaDB and PostgreSQL
- Moodle 5.3 / MariaDB and PostgreSQL 17

Only a completed green run for the final commit counts as release evidence. A workflow definition or an earlier commit is not sufficient.

## Marketplace submission blockers and open evidence

- The source repository currently returns `404` to an unauthenticated request. It must be made public before submission; this requires an explicit owner action.
- Capture and approve the six English screenshots listed in `MARKETPLACE-LISTING.md` using synthetic data only.
- Install the final ZIP through Moodle's web plugin installer and verify the upgrade on the shared test server with developer debugging enabled.
- Complete an own-browser smoke test of Map, List, Compact view, reply, media, notification preferences, grading settings and import/export.
- Native Moodle App rendering, offline App use, physical-device screen readers, high-concurrency load and Moodle Workplace remain unverified and are not claimed.
- Marketplace upload and submission require separate final owner approval.

## Rollback

Before test-server installation, back up the database, Moodle data and existing plugin directory. The `2026110101` upgrade changes no schema; it invalidates caches for the new presentation. Roll back only by restoring the consistent pre-upgrade backups. Copying old PHP files alone is not a supported database rollback procedure.
