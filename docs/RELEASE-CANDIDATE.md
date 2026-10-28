# 0.6.1-rc.1 release candidate

Prepared on 28 October 2026. This is a release candidate, not a Marketplace approval or a production deployment.

## Changes

- Added the full GNU GPL v3 license and explicit copyright/license headers across source files.
- Corrected Moodle coding-standard findings, expanded PHPDoc descriptions and removed a redundant identical English string key. Function-only files no longer contain unnecessary top-level guards; request entry points retain their access controls.
- Added a deterministic ZIP builder, archive checks and an English-only release package. Danish is retained in Git for later AMOS submission; no existing installation's language files are deleted by this preparation.
- Published a separate public issue tracker and English user guide: https://github.com/ZasZorglub/moodle-mod_spatialforum-support
- Narrowed compatibility claims: minimum install version remains Moodle 5.0; Workplace is unverified. Added a Moodle 5.0/5.2 x MariaDB/PostgreSQL CI matrix, coding-standard and metadata checks.

## Verification

The test environments are disposable local Docker containers. The existing Moodle test-server source was mounted read-only for copying; its database and data directories were not used by these tests. Additional non-core plugins were removed from the isolated copies.

- Moodle LMS 5.2.2+, PHP 8.3.36, PostgreSQL 16: clean Moodle/plugin installation from the release archive; 41 tests / 249 assertions passed.
- Moodle coding standard (`moodlehq/moodle-cs` 3.7.0): zero errors and zero warnings.
- Both JavaScript suites passed against source/build and the real templates.
- Moodle LMS 5.2.2+, PHP 8.3.36, MariaDB 11.4.13: clean installation; 41 tests / 249 assertions passed.
- Moodle's own ZIP validator passed. Its expected warnings identify RC maturity and an existing writable target directory; no validation errors remain. The builder writes explicit directory entries, as required by this validator.
- PostgreSQL schema check passed. PHP syntax checks and Git whitespace checks passed.
- Executable-token review found only the declared version/maturity change, equivalent `elseif` formatting, interface ordering and language-key sorting/removal of identical duplicates; the other PHP changes are comments/formatting or removal of unnecessary function-only guards.
- Remote CI is started by the release commit push. Consult GitHub Actions for the completed matrix result; this note does not claim a result before completion.

Local evidence logs are stored outside the source repository in `../rapporter/samtalespor-0.6.1-rc.1-2026-10-28/`. Earlier media/browser results belong to 0.6.0 and are recorded in `tests/RELEASE-060.md`; they are not a fresh cross-browser or physical-device run for this candidate.

## Before submission or stable release

- Check completed remote CI results for the final commit.
- Upload the final ZIP through Moodle's built-in plugin-installation UI as an additional manual check; CLI extraction/installation does not prove that UI flow.
- Review the public listing description, license, support terms, compatibility selection and screenshots. Use synthetic English examples without student data. Submit only after the owner approves the final listing.
- Physical devices, screen readers, multi-browser codecs and concurrent-user/load testing remain unverified. Do not claim these have passed or market the candidate as broadly proven stable.
- AMOS submission is separate and follows plugin approval. Existing screenshots and browser evidence must be reviewed before public publication.
