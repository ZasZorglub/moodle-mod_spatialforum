# Samtalespor user guide

## Installation and update

Back up the Moodle database, Moodle data and the existing plugin directory before an update. Upload the release ZIP through Site administration > Plugins > Install plugins, or extract its spatialforum directory to mod/spatialforum. Complete the normal Moodle upgrade. Do not uninstall to update. Installing the ZIP requires no build tools.

Add Samtalespor from a course's activity chooser. Set its name and introduction, choose track depth and reaction/colour settings, and save. Site administrators can set upload types, file counts and size limits under the plugin settings. Moodle and course upload limits also apply.

## Reading and replying

Map shows the branching conversation. Open a card to read it in place; the surrounding thread remains visible. Close the card or press Escape to return to the overview. List preserves the same hierarchy and is the default at narrow widths. Search, Unread only and Next unread navigate the loaded conversation. Read state is per user and is saved when a post is opened successfully.

Create post starts a top-level contribution. Reply starts a linked child track after publishing; cancelling creates nothing. Select text in an opened post to enable Reply to selection. Add a comment and optionally select more passages. Up to ten passage/comment blocks can form a reply. Source links refer to the current original; deleted or changed sources may make quotations unavailable.

## Images, audio and video

Use Moodle's editor controls to insert or upload images, MP3 audio or MP4 video. VTT caption files are supported subject to editor/site configuration. Uploaded media are saved with the post and require activity access. Recording tools and codec support depend on the Moodle site and browser; recording is not a verified plugin feature. Provide alternative text, captions or a transcript appropriate to the content. Externally embedded media remain external.

## Whole-activity grade and private feedback

In the activity settings, an editing teacher chooses **Grade → None**, **Point** or **Scale**. None is the default. Supported scales belong to the course or site and contain at least two choices. The type/maximum cannot change after grades or feedback have been saved; manage those results in Gradebook first. The normal Moodle grade-category/pass controls are available.

Teachers with **Assess participants** permission use that link below the conversation. The roster lists active participants with **Receive a whole-activity grade and private feedback**, normally students. It is paginated at 50 per page. Within this plugin, graders need a shared group inside the activity grouping in both visible/separate group modes, or access-all-groups permission. Course-forced mode applies. Choose **Assess participant**, enter points or a scale choice and optional private plain-text feedback, then save. An empty score clears it; zero is a real points grade. Saved feedback may exist without a numeric score. Cancel saves nothing.

Students open **Your grade and feedback** to see only their own permitted result. Neither score nor feedback appears in public posts. Hidden Gradebook results and course-level grade visibility are respected. Gradebook owns the values, feedback, history and privacy lifecycle. Locks, overrides, calculations, adjustments or rich feedback must be managed in Gradebook; protected forms are read-only. If another assessment changed while the form was open, reload and review the current result before saving. Independently granted Moodle Gradebook/course permissions still apply there.

## Notifications and digests

Open **Notification preferences** below the conversation. Choose **Off**, **Each new post** or **Daily digest**, then save. The preference is only for your own account and this activity. New posts from other people are included only while you have course, activity and group access. Your own and earlier posts are excluded. Getting a notification does not mark a post as read.

Delivery uses Moodle's configured notification channels and regular site cron. The daily cutoff defaults to 17:00 in your Moodle timezone; administrators may change the time/timezone, delay individual messages, and disable particular notification types. Paused modes are labelled in the subscription selector; your preference is retained but disabled messages are not sent. Large digests can arrive in several batches. Links to images/audio/video open the authenticated Moodle post; files are not attached to notification emails. You can turn the activity subscription off even after course access is revoked, using its saved notification-preferences link after signing in. Already accepted messages cannot be recalled.

Site administrators configure these rules under **Site administration → Plugins → Activity modules → Spatial forum communication**. The page also controls plain-text subject/body templates for individual messages and digests, with a separate template for each digest item. Use only the variables displayed below each field. Preview and test use unsaved values with synthetic examples; test messages go only to your own administrator account. Saving records the configuration and updates pending schedules through cron. Rules apply site-wide to existing and new activities. Participants still choose whether to subscribe, and their Moodle notification channel preferences apply.

Restored/imported copies do not inherit your subscription. They require a new opt-in. Administrators should review delivery errors/uncertainty in the plugin delivery report and Moodle task/message logs.

## Moderation and data

Users with the moderation capability can lock tracks or remove posts after confirmation. Removal deletes managed post files; there is no undo. Course backup/restore includes posts and managed media. The Privacy API exports and erases personal data. Reactions and reply/branch actions follow Moodle capabilities and activity settings.

## Known limits

The whole conversation is loaded; over 150 posts use the list fallback. Conversation pagination, dedicated Moodle App integration and standard-forum import remain unimplemented. Assessment uses one whole-activity grade, not individual-post ratings or a rubric. Physical-device, screen-reader, cross-browser codec and concurrent-user performance coverage is not yet established. Validate with representative data before broader adoption.
