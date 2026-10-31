# Author editing and moderator undo — 0.11.2-dev

Code: `70b774662c47bf727eec1a2ffd65e7a3d0c341d3`, verified 31 October 2026.

- [GitHub run 41224022025](https://github.com/ZasZorglub/moodle-mod_spatialforum/actions/runs/41224022025): all four Moodle 5.0/5.2 × MariaDB/PostgreSQL jobs passed the complete workflow. Moodle 5.2/PostgreSQL: **141 tests / 677 assertions**.
- Isolated local Moodle browser: English subject/body edited, **Edited** timestamp displayed, same post/track/parent retained; moderator deletion hid the body and displayed **Undo deletion**; restoration recovered the edited body and kept its parent connection. Evidence screenshots: workspace `rapporter/writing-safety-0.11.1-2026-10-31/{undo-available,post-restored}.png`.
- Local lifecycle regression suite: **9 tests / 34 assertions**. Coverage includes optimistic conflict detection, author ownership, locked tracks, retained media, hiding deleted body data in view exports, unchanged child relationships, no restored-post fan-out, expiration/purge, privacy erasure, completion recalculation, and denied learner restoration. Existing backup/media and privacy tests also pass in CI.
- Installed on the test server after new SQL/data/plugin backups, using a clean archive of the tested commit. CLI reports schema **2026103102** and the retention column present. Browser validation on the shared test server awaits renewed login; the actual UI flow above was verified in an isolated QA installation.
- Release ZIP SHA-256: `c7666a5869e04c134b623f8e5425d38fd93c0037aa41911f0e2bacf4f305d6b4`.

## Contract

Authenticated authors can edit their own live posts while their activity, track, capability and group access allow it. The original record, parent links, replies, reactions and read state remain. A revision hash prevents a stale form from silently overwriting another saved version. Conflicts leave the submitted form visible for copying and review. An edit does not queue another new-post event. Editor draft recovery also covers the edit form, including its revision token.

Moderator removal hides the body, quotes and files immediately from participant views and cancels queued deliveries. The original body/media remain in Moodle for a **seven-day undo window**. Moderators with current group access can restore the same record during that window. The first cron run after expiry removes body, selective reply content and media in batches of at most 250. Cron must run regularly. Restored posts retain their removal timestamp, preventing delayed original fan-out tasks from sending historical mail.

Privacy erasure is permanent, clears the retention marker and uses the same post lock as edit/restore. Earlier destructive deletions, erased posts and expired windows cannot be restored. Backup keeps the original removal timestamp without shifting its deadline; restore does not restart the window. Existing removed-post subject/author tombstones remain until privacy erasure, as before. No revision history, moderator editing of another author's text, configurable edit deadline, or native App verification is claimed.
