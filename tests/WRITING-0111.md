# Writing safety — 0.11.1-dev

Verified 31 October 2026 at code commit `60ac5bf2b781be9bd78f4fa65320057d3fde2ff2`.

- [GitHub matrix run 41222335573](https://github.com/ZasZorglub/moodle-mod_spatialforum/actions/runs/41222335573): all four Moodle 5.0/5.2 × MariaDB/PostgreSQL jobs passed, including syntax, Moodle coding standard, metadata, AMD builds, DOM regressions, packaging, upgrade savepoints and the complete PHP suite. The Moodle 5.2/PostgreSQL log reports **132 tests / 642 assertions**.
- Real local browser: write English subject/body in TinyMCE, reopen the same form in the same tab, explicitly restore both fields, publish, and reopen the original form. Successful publication cleared the tab draft. Screenshot in the workspace evidence folder: `rapporter/writing-safety-0.11.1-2026-10-31/draft-restored.png`.
- Reopening used Moodle's form-change-checker API to mark only the synthetic QA form submitted before reloading; no post was written at that point. This avoids the tool cancelling the leave-form warning. An offline POST reached `ERR_INTERNET_DISCONNECTED`, but the browser tool refused its internal error page; recovery after that particular fault is **not externally verified**. Automated tests cover draft retention during uncertain submission.
- Test-server backup includes SQL, Moodle data and the previous plugin. CLI upgrade and cache purge succeeded; installed schema is **2026103101**, and the publication-key column is present. Deployment uses checksum comparison because reproducible ZIPs share timestamps with same-day builds.

## Behaviour and limits

The editor enables Moodle TinyMCE server autosave. The plugin also keeps a recoverable form snapshot in **sessionStorage in the same browser tab**, keyed by current user, activity, operation, track and parent. It includes subject, body, branch name, quote choice, File API draft reference and publication token. It is not cross-device draft synchronisation. Drafts expire after 24 hours; expired entries are removed when the form is reopened. Closing the tab normally removes sessionStorage; browser session restoration is browser-dependent.

The snapshot is saved on ordinary field changes, once per second for TinyMCE content, and before submission/pagehide where browser events run. Success clears only the matching draft token; Cancel clears the current draft. Storage errors produce a visible message. The final second of typing, sudden process termination, unsupported editors, storage restrictions and expired Moodle draft files cannot be guaranteed. Media use Moodle's temporary File API area; no local media bytes are copied. TinyMCE and the plain textarea have automated coverage; physical iPhone/VoiceOver and alternative rich-text editors remain unverified.

Publication keys are scoped to authenticated actor/activity, serialized with a Moodle lock, and persisted under a unique nullable index in the same transaction as post, track, media and fan-out task creation. Retries return the existing post after current capability/group/deletion checks. Tests cover branch/post/task deduplication, actor/activity isolation, rollback and retry, malformed tokens, revoked groups, upgrade, backup exclusion of keys, and privacy erasure. This does not promise exactly-once downstream email delivery.

## Remaining accepted work

Author editing and moderator undo, thread subscriptions/automatic following, device accessibility, large-conversation measurements and conditional incremental loading, administrator subscription policies, wider import/export, and the dedicated online Moodle App handler remain separate unfinished slices. This release does not claim those features.
