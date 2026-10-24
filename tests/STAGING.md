# Staging validation for 0.2.1

Do not merge to main until the target Moodle/Workplace checks below pass.

## Additional 0.2.1 checks

- Like, dislike, switch in both directions and remove a reaction. Confirm separate counts, one reaction per user/post and unchanged votes from other users. Submit the same state twice; it must remain unchanged. Both teacher and administrator disable switches apply to both reactions.
- Reply with the parent-quote checkbox off (default), then on. The parent must only appear in the saved reply when selected, including in a manually created child track. In both cases preserve the reply relationship. Trigger validation failure and confirm the checkbox choice survives.
- Upgrade 0.2.0 with existing likes and replies: likes stay positive and published parent quotes remain visible. New replies default to no quote. Check the new `reaction` and `showparent` fields and no pending upgrade on a second visit.
- Backup/restore preserves dislike values, quote preferences and reply parents across tracks. Privacy export includes the reaction and quote preference; privacy deletion also removes dislike-only users.
- Every Reply to an existing post opens a new-track form with editable name/subject. Save two replies to A: each gets its own child track whose origin is A. Reply to B: create a child of B's track. Test with and without quote. Cancelling creates nothing. At maximum depth, without branch permission or while locked, both the UI and direct requests must prevent replies that would create a track.

## Reported issues and implemented changes

1. Reply opens with an empty subject. `post.php` now supplies an editable `Re:` default from the selected parent, avoiding another prefix when it already starts with `Re:` and respecting the 255-character field limit.
2. No reactions. Added POST-only like/dislike forms, separate counts and a capability. One user can give one reaction per post. Teacher activity settings and the administrator site switch both enforce disabling on the server. Existing reactions are retained while hidden.
3. Replies lack parent context. The form shows a labelled reference and offers an unchecked opt-in quote checkbox. Only opted-in saved replies show the parent blockquote. It is rendered live from the parent so moderator removal and privacy erasure also remove the displayed quote. Quotes do not recursively quote ancestor posts.
4. Reporter cannot activate Reply on post 5 in a long thread. The supplied screenshot does not establish the exact browser hit-test failure. The old code applied perspective/rotate/translateZ to the whole growing plane and posts inside an overflow-hidden container. The candidate fix removes transforms from interactive content, keeps the 3D decoration non-interactive, and enlarges action targets to at least 44px. Exact reproduction and confirmation remain required on the target site.

## Required target-site checks

- Back up the staging database and plugin directory. Upgrade an existing MVP with posts, tracks and unread state. Confirm the new version, preserved data, `enablelikes` field and likes table; run upgrade again and check for no pending changes.
- Also install fresh on Moodle 5.x. Add an activity, root post, reply and child track. Confirm white root and coloured child tracks and route/map navigation.
- Reply to a post with a non-ASCII subject; check a single `Re:` prefix. Edit it, trigger a form validation error and confirm the edited subject remains. A new root post and a new branch should still start with a blank subject.
- Check the labelled parent quote before posting and in the saved reply. Reply to that reply: only the direct parent appears. Remove the parent as moderator or erase its author's data: the parent's content must disappear from quotes.
- Use at least 8 posts, including several long HTML messages. At desktop, tablet and mobile widths scroll to posts 5 through 8 and activate Reply with mouse, touch and keyboard. Move the mouse around the stage, repeat with reduced motion enabled, and ensure the whole last post remains reachable. Inspect the rendered links/overlays if the issue persists.
- Student A likes a post, reloads and unlikes; student B's like remains. Repeat identical submissions and check no duplicates. Try a guest, a role without `mod/spatialforum:like`, invalid session keys, GET requests, a deleted post and a post from another activity: all unauthorised writes must fail.
- Disable likes in the activity as editing teacher and at site level as admin. In each case controls/counts disappear and a direct POST is rejected. Re-enable and confirm the stored likes return.
- Lock track and whole activity. Students cannot reply or branch. Editing teachers/moderators retain their appropriate controls; a non-editing teacher does not gain moderator access by default. Likes are independent of the conversation lock.
- Back up/restore with user data: check remapped reply parents, branch origins, root track and likes, including a privacy-erased parent tombstone. Back up/restore without user data: no posts, reads or likes; track structure and like settings remain.
- Privacy: a user who has only liked a post must be discoverable in the correct activity context; export includes likes and timestamps; individual/bulk deletion removes their likes only in approved contexts; whole-context deletion and activity deletion leave no orphan likes. Check unrelated activities are unchanged.

## Evidence

The first real Moodle CI installation (run 40105388505) stopped on three existing XMLDB debug warnings: both `name` fields and the post `subject` declared an empty-string default on a NOT NULL CHAR field. The install schema now omits those defaults, matching the normalised schema Moodle created for the MVP.

The branch includes real Moodle PHPUnit tests for activity creation, reply context, subjects, like idempotency and settings, role/lock controls, likes-only privacy users, existing-data upgrade and backup/restore remapping. The workflow runs these with Moodle Plugin CI. Local Node checks also exercise the compiled AMD module and source with/without reduced motion and render eight post cards to check the fifth reply URL and per-post form parameters. These structural checks do not prove browser hit testing.

The in-app browser automation host returned `The browser access host is unavailable` on 24 October 2026. No deployment to the target site or target-site test pass is claimed by this change.
