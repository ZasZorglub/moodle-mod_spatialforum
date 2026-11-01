# Samtalespor user guide

## Installation and update

Back up the Moodle database, Moodle data and the existing plugin directory before an update. Upload the release ZIP through Site administration > Plugins > Install plugins, or extract its spatialforum directory to mod/spatialforum. Complete the normal Moodle upgrade. Do not uninstall to update. Installing the ZIP requires no build tools.

Add Samtalespor from a course's activity chooser. Set its name and introduction, choose track depth and reaction/colour settings, and save. Site administrators can set upload types, file counts and size limits under the plugin settings. Moodle and course upload limits also apply.

## Reading and replying

Map shows the branching conversation. Open a card to read it in place; the surrounding thread remains visible. Close the card or press Escape to return to the overview. List preserves the same hierarchy and is the default at narrow widths. Enable **Compact view** to reduce card spacing and metadata height without changing post order, indentation or connections. The density choice is kept for the current browser tab. Search, Unread only and Next unread navigate the loaded conversation. Read state is per user and is saved when a post is opened successfully.

Create post starts a top-level contribution. Reply starts a linked child track after publishing; cancelling creates nothing. The original post remains available above the editor, and the author can optionally include the previous post as a quote.

## Images, audio and video

Use Moodle's editor controls to insert or upload images, MP3 audio or MP4 video. VTT caption files are supported subject to editor/site configuration. Uploaded media are saved with the post and require activity access. Recording tools and codec support depend on the Moodle site and browser; recording is not a verified plugin feature. Provide alternative text, captions or a transcript appropriate to the content. Externally embedded media remain external.

## Whole-activity grade and private feedback

In the activity settings, an editing teacher chooses **Grade → None**, **Point** or **Scale**. None is the default. Supported scales belong to the course or site and contain at least two choices. The type/maximum cannot change after grades or feedback have been saved; manage those results in Gradebook first. The normal Moodle grade-category/pass controls are available.

Teachers with **Assess participants** permission use that link below the conversation. The roster lists active participants with **Receive a whole-activity grade and private feedback**, normally students. It is paginated at 50 per page. Within this plugin, graders need a shared group inside the activity grouping in both visible/separate group modes, or access-all-groups permission. Course-forced mode applies. Choose **Assess participant**, enter points or a scale choice and optional private plain-text feedback, then save. An empty score clears it; zero is a real points grade. Saved feedback may exist without a numeric score. Cancel saves nothing.

Students open **Your grade and feedback** to see only their own permitted result. Neither score nor feedback appears in public posts. Hidden Gradebook results and course-level grade visibility are respected. Gradebook owns the values, feedback, history and privacy lifecycle. Locks, overrides, calculations, adjustments or rich feedback must be managed in Gradebook; protected forms are read-only. If another assessment changed while the form was open, reload and review the current result before saving. Independently granted Moodle Gradebook/course permissions still apply there.

## Notifications and digests

Open **Notification preferences** below the conversation for activity-wide settings. Open **Conversation notifications** inside an individual post for that conversation and all its descendant branches. Choose the activity frequency, a separate immediate/daily frequency, or mute that conversation. The posting checkbox follows replies by default for eligible participants; an existing conversation mute remains unchecked. Activity-wide Off clears all individual follows. Settings apply only to your own account. Your own and earlier posts are excluded, and current course/activity/group access is rechecked. Getting a notification does not mark a post as read.

Delivery uses Moodle's configured notification channels and regular site cron. The daily cutoff defaults to 17:00 in your Moodle timezone; administrators may change the time/timezone, delay individual messages, and disable particular notification types. Paused modes are labelled in the subscription selector; your preference is retained but disabled messages are not sent. Large digests can arrive in several batches. Links to images/audio/video open the authenticated Moodle post; files are not attached to notification emails. You can turn the activity subscription off even after course access is revoked, using its saved notification-preferences link after signing in. Already accepted messages cannot be recalled.

Site administrators configure these rules under **Site administration → Plugins → Activity modules → Spatial forum communication**. The page also controls plain-text subject/body templates for individual messages and digests, with a separate template for each digest item. Use only the variables displayed below each field. Preview and test use unsaved values with synthetic examples; test messages go only to your own administrator account. Saving records the configuration and updates pending schedules through cron. Rules apply site-wide to existing and new activities. Participants still choose whether to subscribe, and their Moodle notification channel preferences apply.

Restored/imported copies do not inherit your subscription. They require a new opt-in. Administrators should review delivery errors/uncertainty in the plugin delivery report and Moodle task/message logs.

## Moderation and data

Use **Edit my post** on your own live contribution to change its subject/body. The conversation must be open and your current permissions/group access must allow editing. Edits display an edited timestamp and keep replies connected. If another tab saved a change, the stale form remains visible for copying and review instead of overwriting that version.

Users with the moderation capability can lock tracks or remove posts after confirmation. Removal immediately hides the body, quotes and media. **Undo deletion** restores the same post for seven days; after expiry, cron permanently removes its body/media. Privacy erasure is permanent and cannot be undone. Backups preserve the original removal deadline. Reactions and reply/branch actions follow Moodle capabilities and activity settings.

## Known limits

Teachers can open **Import a standard forum** from the course navigation. Select a General or Each person posts one discussion forum and name the new copy. Review counts, exclusions and blockers, then explicitly confirm. Cron processes the copy while its content remains inaccessible. Keep the progress URL to refresh, retry an unchanged failed job, or discard an incomplete copy. The completed activity remains hidden: inspect its threads, group permissions and files before publishing in normal activity settings. The original is preserved. Private/timed content, unsupported files and incompatible permissions block conversion; grades, subscriptions, reads and historical notifications are excluded. See [import details](../tests/IMPORT-0100.md).

Every summary and parent connection remains in the overview. Above 150 posts, the view uses the list fallback and loads a post's full details when you open it. Loading placeholders do not mark posts read. A failed load offers retry or a link to reload with that post open, which also works without JavaScript. Full own-text search is retained as a plain-text index; database pagination is not implemented.

Draft recovery retains subject/body and form choices in the same browser tab for up to 24 hours. Restore is explicit; publication and Cancel clear the matching tab draft. Keep the tab open during a connection problem. Storage restrictions, the last second of typing, expired temporary media and other rich-text editors are not guaranteed. No cross-device draft synchronisation is claimed.

Authenticated services support online reading, posting/replying and subscription changes for Moodle App clients. A dedicated native App presentation and offline use remain unimplemented and must not be advertised. Assessment uses one whole-activity grade, not individual-post ratings or a rubric. Physical-device VoiceOver, cross-browser codecs and concurrent-user load still need validation.
