# Samtalespor user guide

## Installation and update

Back up the Moodle database, Moodle data and the existing plugin directory before an update. Upload the release ZIP through Site administration > Plugins > Install plugins, or extract its spatialforum directory to mod/spatialforum. Complete the normal Moodle upgrade. Do not uninstall to update. Installing the ZIP requires no build tools.

Add Samtalespor from a course's activity chooser. Set its name and introduction, choose track depth and reaction/colour settings, and save. Site administrators can set upload types, file counts and size limits under the plugin settings. Moodle and course upload limits also apply.

## Reading and replying

Map shows the branching conversation. Open a card to read it in place; the surrounding thread remains visible. Close the card or press Escape to return to the overview. List preserves the same hierarchy and is the default at narrow widths. Search, Unread only and Next unread navigate the loaded conversation. Read state is per user and is saved when a post is opened successfully.

Create post starts a top-level contribution. Reply starts a linked child track after publishing; cancelling creates nothing. Select text in an opened post to enable Reply to selection. Add a comment and optionally select more passages. Up to ten passage/comment blocks can form a reply. Source links refer to the current original; deleted or changed sources may make quotations unavailable.

## Images, audio and video

Use Moodle's editor controls to insert or upload images, MP3 audio or MP4 video. VTT caption files are supported subject to editor/site configuration. Uploaded media are saved with the post and require activity access. Recording tools and codec support depend on the Moodle site and browser; recording is not a verified plugin feature. Provide alternative text, captions or a transcript appropriate to the content. Externally embedded media remain external.

## Notifications and digests

Open **Notification preferences** below the conversation. Choose **Off**, **Each new post** or **Daily digest**, then save. The preference is only for your own account and this activity. New posts from other people are included only while you have course, activity and group access. Your own and earlier posts are excluded. Getting a notification does not mark a post as read.

Delivery uses Moodle's configured notification channels and regular site cron. The daily cutoff defaults to 17:00 in your Moodle timezone; administrators may change the hour. Large digests can arrive in several batches. Links to images/audio/video open the authenticated Moodle post; files are not attached to notification emails. You can turn the activity subscription off even after course access is revoked, using its saved notification-preferences link after signing in. Already accepted messages cannot be recalled.

Restored/imported copies do not inherit your subscription. They require a new opt-in. Administrators should review delivery errors/uncertainty in the plugin delivery report and Moodle task/message logs.

## Moderation and data

Users with the moderation capability can lock tracks or remove posts after confirmation. Removal deletes managed post files; there is no undo. Course backup/restore includes posts and managed media. The Privacy API exports and erases personal data. Reactions and reply/branch actions follow Moodle capabilities and activity settings.

## Known limits

The whole conversation is loaded; over 150 posts use the list fallback. There is no pagination, grading, dedicated Moodle App integration or standard-forum import. Physical-device, screen-reader, cross-browser codec and concurrent-user performance coverage is not yet established. Validate with representative data before broader adoption.
