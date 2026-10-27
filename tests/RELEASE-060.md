# 0.6.0 audit corrections

Baseline: `c58905f` / 0.5.2. Scope: the eleven prioritised findings from the 27 October 2026 audit. Optional drafts, subscriptions, editing history and undo are separate product work.

| Finding | Implemented behavior | Regression evidence |
| --- | --- | --- |
| F01 | Erasure removes authored track names and post files; export includes names/files. Upgrade repairs earlier erased-author track names. | `audit_fixes_test`, `privacy_test`, `upgrade_test` |
| F02 | Post messages are decoded on restore; activity, post, fragment and track links are remapped. | Real backup/restore in `backup_test` |
| F03 | Editor draft uploads saved to a post filearea, authorised serving, rendering, backup/restore, privacy and deletion; configurable file limits. | File lifecycle and access tests; English browser upload/save/reload/playback of PNG, MP3, MP4 |
| F04 | Audio width fits its message container. | Client CSS and target-browser width checks |
| F05 | Previously read closed cards leave Unread only, including late acknowledgements. | Both shipped AMD source/build in `map_ui_test.cjs` |
| F06 | Disabled colours use neutral reply segments and connectors. | Both AMD source/build with `enablecolors=0` |
| F07 | Moderator forms use POST; GET is rejected. Delete confirmation happens on the server before mutation. | Actual GET denial, QA-track lock/unlock, delete confirmation/cancel; template regressions |
| F08 | Entity-only/zero-width blank content is rejected; sanitised media count as content. | Real form validation in `audit_fixes_test` |
| F09 | Subject/branch-name lengths are checked on the server with Unicode-aware lengths; domain creation also rejects invalid titles. | Form/domain regression tests |
| F10 | Quotes, parent previews, source authors and child counts share batched data. | 101 posts with quotes/parent previews require fewer than 25 export DB reads |
| F11 | Selection action precedes content and stays sticky in the open card; ready state is green and announced. | Source/build selection tests and real long-media-post geometry |

## Verified environment

Moodle 5.2.2+ (Build 20260818), PHP 8.3.36, MariaDB 11.4.13. The existing GitHub workflow also targets Moodle 5.0, but a new remote CI result is not claimed for this local release.

- Final full Moodle regression run: **41 tests, 246 assertions passed**.
- Both JavaScript suites exercise original Mustache plus AMD source/build.
- PHP syntax, reproducible AMD build and Git whitespace checks pass.
- Local logs/screenshots: `../rapporter/samtalespor-0.6.0-verifikation-2026-10-27` relative to the plugin checkout. Database backups are private local files outside this repository.
- Target browser: English QA post 26 contains the three uploaded files. After reload all resolve through `pluginfile.php/.../mod_spatialforum/post/26/`; MP3/MP4 playback advanced without a media error. Post 27 quotes the uploaded-media post. Post 28 is a successfully saved uploaded-image-only post. QA track 16 was locked and unlocked; deletion was cancelled. Existing posts were not deleted.
- At 320 px the real Moodle audio player is 249 px inside a 249 px message area; document width is 320 px. Viewport overrides were reset after testing.

## Limits and rollback

This remains a staging release. Physical devices, screen readers, multi-browser codec coverage and concurrent-user capacity testing are not established by these checks. Native Moodle recording tools follow site configuration; recording/conversion is not a verified new feature. Supported upload formats are explicitly limited to those listed in settings.

Moderation deletes managed files and clears content after confirmation; it does not implement undo. Restore links canonicalise post targets and can omit a prior presentation-only view parameter. Full conversation HTML is still loaded, with list fallback above 150 posts.

Before deployment, back up the database, plugin directory and Moodle data. To roll back, restore a consistent backup; copying older PHP files alone does not reverse the privacy repair. No production or Marketplace publication is part of this release.
