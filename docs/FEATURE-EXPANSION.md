# Feature expansion: groups, subscriptions, grading, import and Moodle App

Prepared 29 October 2026. **Implementation plan; these features are not implemented by this document.**

## Baseline and product intent

Source baseline: `2d83ccfe0397e61bc207fa40cff9a96fc3892ca6`, branch `feat/replies-likes-navigation`, release `0.6.1-rc.1` / `2026102800`. The working tree was clean when inspected. The user's request is to address all five omissions; the exact first-version choices below are pending answers, not approved requirements.

Keep the complete branching discussion understandable when reading, replying, receiving notifications or changing device. Preserve the in-place threaded web map, hierarchical List, linked selective quotations and Moodle-managed post media. Test content and UI verification use English.

Observed source evidence:

| Area | Current evidence | Consequence |
| --- | --- | --- |
| Groups | `lib.php::spatialforum_supports()` does not declare groups/groupings; `db/install.xml` has no conversation group ownership. | Adding a group selector alone would not isolate data. |
| Reads and files | `classes/local/forum.php::export_view_data()` queries posts by activity/track; unread IDs are activity-wide. `read.php` and `lib.php::spatialforum_pluginfile()` enforce activity access. | All must gain the same conversation visibility policy before group support is advertised. This is a future requirement, not evidence of leakage in today's no-groups product. |
| Notifications | No `db/messages.php`, `db/tasks.php`, subscriptions or delivery state in the current tree/schema. | Add an actual queued Message API delivery lifecycle, not an email link or UI-only subscription. |
| Grades | No grade callbacks/grade support in `lib.php` or grading configuration in `mod_form.php`. | A Gradebook integration and teacher workflow are needed. Existing reactions are not grades. |
| App | No `db/mobile.php` or mobile output handlers. | A responsive browser layout does not prove native Moodle App integration. |
| Import | No standard-forum importer. Existing backup/restore handles this plugin's own activity. | Standard-forum conversion is a separate data-mapping workflow. |

## Decisions requested

| Decision | Recommended first version | Alternatives and impact | Status |
| --- | --- | --- | --- |
| Assessment | One teacher-assigned grade and feedback per participant for the whole activity. | Individual-post ratings require aggregation/rating permissions and an additional assessment workflow; both significantly expand this slice. | Awaiting user answer. |
| App connectivity | Online reading and replying inside the Moodle App. | Offline reading/writing adds local content retention, outbox synchronisation, stale permissions and conflict handling. | Awaiting user answer. |
| Standard-forum import | Preview and copy into a new activity in the same course; preserve the source. | Merging needs collision/recovery policies; ongoing synchronisation needs ownership and update/deletion reconciliation. | Awaiting user answer. |

These recommendations are proposals, not silent acceptance. Group implementation planning can continue independently. Do not implement the affected assessment, offline-storage or import-mutation behaviour until its decision is resolved.

## Delivery order and completion evidence

| Slice | Outcome | Depends on | Required evidence before declaring complete |
| --- | --- | --- | --- |
| S1 — Groups and shared access policy | No groups, separate groups, visible groups and groupings use Moodle's group rules throughout the discussion. | Existing conversation model and explicit legacy-content mapping. | Two independent learners in different groups, multi-group learner, ungrouped learner, guest and teachers with/without access-all-groups; denied direct URLs/files/quotes/counts as well as UI; upgrade and backup/restore. |
| S2 — Subscriptions and daily digests | A participant can subscribe, choose immediate or daily delivery and unsubscribe. | S1, Message API and Task API. | Actual Message API capture, isolated email processor/mailbox delivery, scheduler/digest cutoffs, permission revocation, unsubscribe and retry tests. |
| S3 — Grading | Teacher can assess participation and learner can see the permitted result in Moodle Gradebook. | S1 and assessment decision. | Correct capability/group boundaries, zero versus ungraded, points/scales, locked/overridden grades, feedback, restore and delete lifecycle. |
| S4 — Standard-forum import | Teacher can inspect and import supported content into a new hidden activity, then review and expose the result. | S1, import decision and notification suppression from S2. | Real source discussion/media/author/group/link mapping, dry-run without writes, repeated request, interrupted job, changed source, unsupported-content report and source integrity checks. |
| S5 — Dedicated Moodle App | A participant reads the same hierarchy and posts/replies through a registered App handler. | S1–S3 domain contracts, App connectivity decision; import is sequenced earlier to settle converted-content handling. | Handler discovery and actual rendering in the Moodle App, authorised reads/writes/files, account separation, revoked access, real device and interrupted-request behaviour. REST tests alone are insufficient. |

No release date or effort estimate is asserted before the decisions and first migration prototype. Keep `0.6.1-rc.1` recoverable; assign new development/release versions only when runtime/schema changes are implemented. Do not label any slice complete because its configuration file exists.

## S1 — Groups: first implementation slice

Use Moodle Groups API and the effective course/activity group mode, including forced course mode, grouping restrictions and `moodle/site:accessallgroups`. In visible-groups mode, visibility and permission to contribute are distinct: viewing another group's discussion must not automatically permit writing to it. Do not infer a group from a user's first membership; a user can belong to multiple groups.

Introduce one group scope per top-level conversation, inherited by every reply, child track and selective quote. The current activity-wide root track is shared by multiple top-level posts, so placing a group ID only on that root track would be incorrect. Prototype explicit conversation identity/scope, then choose the smallest consistent schema change. An ID passed by the browser never overrides the stored parent conversation's scope. Cross-conversation quotations must respect the source's visibility.

Create a common server-side access/query boundary for web requests, rendering, files, future tasks, imports and services. Apply it before reading payloads or returning any derived data: parent previews, route labels, author names, search results, unread/reaction counts, quote text, child counts and files. Enforce write access for new posts, replies, reactions, read markers and moderation. UI filtering is not the boundary. Keep Moodle capability checks and session-key/token rules appropriate to each transport.

Migration must not assign old posts to a guessed group, clone them into every group, or silently broaden their audience. Existing activities remain no-groups by default. Before enabling or forcing a group mode on populated activities, define and preview the treatment of existing shared conversations and unresolved/orphaned references; do not activate a partially classified group view. Any shared/all-participants scope must be explicit. Test both course-forced mode and later group/grouping/membership changes.

Affected layers: `db/install.xml`, `db/upgrade.php`, `lib.php`, `mod_form.php`, `classes/local/forum.php`, `classes/local/excerpts.php`, media serving, `view.php`, `post.php`, `read.php`, `like.php`, `action.php`, forms/templates, backup/restore, privacy and tests. Declare group feature flags only with the complete access lifecycle. Restore must remap group IDs and fail safely on unresolved scope; never turn an unresolved private group into a shared conversation.

## S2 — Subscription and delivery contract

Proposed first scope: activity-level opt-in, immediate or daily digest, and unsubscribe. Do not automatically subscribe existing users or send historical posts after upgrading. Subscription changes use authenticated POST and a session key on the web; a learner controls only their own subscription. Staff management/forced subscription and thread-specific subscriptions are separate later decisions.

Publish new-post events and queue IDs after the post transaction commits. Store references and delivery state, not duplicate message bodies. Register notification providers in `db/messages.php`, dispatch through Moodle Message API and honour configured processors/preferences. Define scheduled/adhoc tasks rather than sending mail during the posting request. Use a unique delivery identity and task locks; track claims, attempts, retry/backoff and cleanup. Do not promise exactly-once SMTP delivery: a crash between provider acceptance and local acknowledgement needs an explicit recovery policy.

Recheck enrolment, suspension, visibility, group membership, subscription and post existence immediately before dispatch. Erased/deleted content must not survive in queued plaintext. Do not mark a post as read merely because a notification was queued. Use bounded digest batches and recipient-timezone/day boundaries, including DST. Imports and restores do not fan out old content as new mail. Permission failures remove/skip ineligible deliveries; processor failures remain observable and retryable. Capture messages in isolated tests; do not send unsolicited mail to real course participants.

Subscriptions and delivery metadata require Privacy API coverage, context/user discovery and cleanup on activity/user deletion. Restore may preserve an authorised subscription preference when user data is included, but must not replay delivery queues or resend history. Record the chosen backup behaviour before implementation.

## S3 — Grading contract, subject to the assessment decision

Recommended v1: optional manual whole-activity points or scale and feedback per participant, using Moodle Gradebook as the authority. Default to no grading for existing activities. Provide grade-item creation/update/deletion and the normal activity configuration/UI lifecycle. A teacher needs the grading capability and access to the participant's group. Learners can retrieve only their permitted own result; feedback and grades never enter the public conversation map.

Never derive grades from likes, unread state or post counts without a separate explicit product decision. Preserve the difference between zero, unset and cleared grades. Respect Gradebook overrides, locked items/grades, hidden results and configuration changes; a callback invoked by Moodle must not regenerate manual grades from empty local data. Avoid two competing grade/feedback stores. Include event/audit visibility, supported scale validation and tests of user data/grade backup semantics. If per-post assessment is selected, redesign this slice around Rating API aggregation and permissions before building its UI.

## S4 — Import contract, subject to the import decision

Recommended v1: teacher preview/copy within the same Moodle course into a new hidden activity. Require source access, activity-creation/import capability, group access and explicit confirmation of the concrete preview before mutation. The source is never edited/deleted. Restrict the first supported source types to those whose semantics can be represented and tested.

Map source discussion roots and post parent links into connected top-level conversations and reply tracks. Preserve authors and timestamps, source groups/grouping intent, text format and supported embedded media; rewrite links/file references to copied File API objects. Keep source-to-target IDs in an import-job mapping for traceability and idempotent retries. Do not reuse source read state, subscriptions, ratings or mail state as if equivalent; preview explicitly reports what is excluded.

Preflight detects private replies (`privatereplyto`), Q&A visibility, timed discussions, missing authors/groups, missing/deleted parents, excessive depth, attachments outside the current PNG/JPEG/MP3/MP4/VTT contract, file limits and source changes. Reject unsupported restricted material or preserve its policy in a separately designed feature; never publish it as an ordinary post. Unsupported files/content are a visible blocker, not silently omitted or copied with weaker restrictions. A selective import must be deliberate and reflected in the preview/counts.

Snapshot/check source identity and revision between preview and execution; stale previews require refresh. Long imports use bounded jobs, keep the incomplete target unavailable, suppress notifications, and have recovery/cleanup for database and copied-file failures. Repeated confirmation for the same job returns its existing result rather than making a second copy. Publish only after an authorised teacher reviews the reconciled result. No hardcoded tiny page limit, flattened deep branches or claimed success when some posts failed.

## S5 — App contract, subject to connectivity decision

Register an actual `CoreCourseModuleDelegate` handler in `db/mobile.php`, with reserved mobile output methods and Ionic/App templates. Use the existing domain/access layer; mobile support must not duplicate or relax group, quote, media or grade rules. Server identity comes from the authenticated session/token, not a `userid` argument supplied by a client.

Recommended online v1: hierarchical reading with a visible ancestor route, opening posts in context, read markers, top-level posts and replies, selected quotations where the App selection UI supports them, permitted uploads and subscription preferences. Preserve thread relationships on small screens; do not advertise a flat list or browser link as full App support. Add a bounded/paginated conversation read contract before using it on large courses. Register narrowly scoped external write functions only where needed; App PHP templates can be served by `tool_mobile_get_content` without inventing an unnecessary full REST layer.

Files use Moodle's authenticated mobile file/URL handling; no public upload area or credentials in ordinary URLs/logs. Test role/group revocation between opening and submitting, locked conversations, retries without duplicated replies and user/account switching. An offline choice requires a separate local storage/outbox design and lifecycle for cached private content after access revocation. App grading administration, push delivery and support across App versions need explicit acceptance scope; registering a notification provider does not prove mobile push delivery.

## Common verification, release and rollback

Every implemented slice needs schema install/upgrade, backup/restore and Privacy API tests for new data, alongside current map/quotation/media regressions. Run CI on supported Moodle 5.0/5.2 with MariaDB/PostgreSQL, PHP lint, Moodle coding standard, metadata/savepoints, JavaScript and meaningful integration tests. Add cross-group denial tests before positive UI cases. Trace each new requirement to one test or explicitly open manual evidence item.

Notification processor capture is not delivered email; REST success is not an App render; viewport emulation is not a physical device. Record the environment and immutable Git/build identity for each observed journey. Validate a release ZIP, upgrade from the previous candidate and preserve representative posts/files before any staging deployment. Public compatibility/feature claims follow observed results. Proposed features remain listed as missing until their complete slice passes.

Before schema changes, back up database, Moodle data and plugin code. Rehearse restoring a consistent backup in the isolated environment; copying older PHP files is not a database rollback. Import rollback affects only the newly created target/job, never its source. Pausing/retrying a task must be visible to operators; do not introduce silent background mail or irreversible migration during upgrade. Commit each coherent verified slice, then deploy and verify only within the authorised environment.

## References

Inspected current plugin source at the baseline above, plus the local Moodle `MOODLE_502_STABLE` sources `lib/grouplib.php`, `mod/forum/lib.php`, `mod/forum/db/install.xml`, `mod/forum/db/messages.php`, forum delivery tasks and `mod/subsection/db/mobile.php`.

- [Moodle 5.0 Groups API](https://moodledev.io/docs/5.0/apis/subsystems/group)
- [Moodle 5.0 Message API](https://moodledev.io/docs/5.0/apis/core/message)
- [Moodle API index: Gradebook, Rating, Task, Backup and Privacy APIs](https://moodledev.io/docs/5.0/apis)
- [Moodle App plugins development guide](https://moodledev.io/general/app/development/plugins-development-guide)
- [App course-module example](https://moodledev.io/general/app/development/plugins-development-guide/examples/course-modules)

## This planning cycle's evidence

Passed: repository/clean baseline inspected; absent integrations checked in source/schema; official API documentation and local core implementations inspected. Open: the three product decisions, group migration prototype and every new runtime implementation/test/deployment. Previous candidate test results are historical context, not tests of these proposed features. This change edits documentation only and does not change plugin version, schema or deployment.
