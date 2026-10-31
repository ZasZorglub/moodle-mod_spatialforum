# Supervised pilot checklist

Prepared 31 October 2026. Baseline a4e7167, 0.10.0-dev / 2026102904.
The existing web plugin can undergo supervised exploratory testing. Dedicated Moodle App support is not implemented at this baseline. Do not describe this checklist as App verification or Marketplace readiness.

## Roles and records

Use a dedicated test course with an editing teacher and two synthetic learners in different groups. Use English content only. Do not change or delete existing course discussions to prepare the pilot. Record the plugin commit, Moodle version, role, browser/App version, iPhone model/iOS version, expected result, actual result and screenshot for each case. Mark unexecuted cases open.

## Web journeys

| Case | Steps | Required result |
| --- | --- | --- |
| Thread context | Create a root, reply and nested reply; open each card and switch to List | Parents, replies and route remain connected; opening a card preserves the overview |
| Reply context | Open a reply, expand/collapse the original and choose the optional parent quote | Original starts expanded; quote choice is below the editor; activity description and retired selection reply are absent |
| Writing recovery | Write English subject/body, reopen in the same tab, restore and submit twice using the same request | Draft fields recover and only one post/branch is published |
| Edit and undo | Edit your own synthetic post; remove it as moderator; undo removal | Edited timestamp appears, replies remain connected, removed body/media are unavailable until restored |
| Conversation follow | Follow one thread, mute another, change immediate/daily delivery and then choose activity-wide Off | Only current authorised future posts are delivered, no duplicates/history replay, and activity-wide Off removes all follows |
| Keyboard and VoiceOver | Open a post, navigate to parent/child, reply, filter and close using keyboard or iPhone VoiceOver | Labels/parent relationships are announced, focus returns to the summary, no trap; record actual iPhone/iOS/browser results |
| Large conversation | Read a synthetic conversation above 150 posts and open a post using a direct link, retry and fallback | Every summary/parent remains; only requested detail is fetched; loading/failure does not mark a post read |
| Media | Upload PNG/JPEG, MP3, MP4 and VTT; reload as the second learner | Image renders, audio/video play on the actual device, captions/files resolve through authenticated URLs |
| Groups | Read and reply as both learners in separate groups; try a copied direct post/file URL | Each learner sees only permitted content; denied requests expose no body/file; visible-group read access does not grant write access |
| Locks | Open a reply form, lock the conversation as teacher, then submit | Server rejects the stale write |
| Notifications | Subscribe/unsubscribe; check immediate/digest rules and admin templates using the test mail sink | Recipient, subject, body and timing match policy; historical imports send no messages |
| Assessment | Teacher saves zero, a positive grade and feedback; learner opens own result | Zero differs from ungraded; hidden grades/feedback remain hidden; another learner's result is denied |
| Import | Preview a supported standard forum; confirm hidden copy; let cron finish; review threads/files | Preview has no copy side effects; originals are preserved; target stays hidden; copied counts and parents match |
| Import recovery | Change a synthetic source after confirmation; inspect failure and discard | Partial copy stays inaccessible; discard removes only the new target/job/maps |
| Restore | Back up a completed synthetic activity and restore to a separate test course | Authorised content, groups, media, threads and grades follow the documented restore policy |

## iPhone Moodle App gate

The user has offered an iPhone for actual App testing. Before testing the plugin in the App, implement and verify handler discovery, bounded hierarchical reads, ancestor navigation, authenticated media and online reply submission with retry protection. No browser redirect may be counted as native App support.

Then test login, course/activity discovery, parent/reply navigation, image/audio/video playback, submission and reload, interrupted request retry, group/role revocation before submission, locked discussions and account switching. Verify that server identity comes from the token and that no previous account's private content remains visible. Offline writing is outside the accepted first-version scope.

## Start and stop conditions

Start a supervised web session only after the current HTTPS URL, teacher/learner logins, version and cron/mail-sink setup are verified. Temporary Cloudflare URLs must be checked again for each session. Record App cases as open until actual device evidence exists.

Stop the pilot on cross-group disclosure, duplicate submission after retry, missing imported content, source mutation, unintentional external mail or grade/feedback leakage. Preserve evidence and diagnose before further writes. Keep a consistent database/data/code backup before schema changes; an older PHP directory alone cannot roll back a database upgrade.
