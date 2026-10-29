<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Samtalespor spatial discussion activity.
 *
 * @package    mod_spatialforum
 * @copyright  2026 Anders Christian Sloth
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


$string['addexcerpt'] = 'Add selected text';
$string['allowedmediatypes'] = 'Allowed media file types';
$string['allowedmediatypes_desc'] = 'Choose supported image, audio, video and caption formats. An empty selection uses all supported formats. Limits apply to uploaded files; links to external media remain external.';
$string['allowlikes'] = 'Allow like/dislike site-wide';
$string['allowlikes_desc'] = 'If disabled, both reactions are hidden and cannot be submitted. Existing reactions are retained.';
$string['allparticipants'] = 'All participants (shared conversation)';
$string['alltracks'] = 'All tracks';
$string['anonymoustrack'] = 'Removed author track';
$string['applygroupaudience'] = 'Apply this audience to existing posts';
$string['backoverview'] = 'Back to overview';
$string['branchfromhere'] = 'Start track here';
$string['branchname'] = 'Conversation track name';
$string['branchrequirespost'] = 'A new track must start from an existing post.';
$string['closepost'] = 'Close';
$string['closingcomment'] = 'Closing comment (optional)';
$string['confirmdeletepost'] = 'Delete this post? Its place in the conversation will be retained.';
$string['confirmgroupaudience'] = 'I have reviewed the existing posts and confirm this audience for all of them.';
$string['continuingtracks'] = 'Tracks continuing from here';
$string['conversationmap'] = 'Conversation map';
$string['conversationtrack'] = 'Conversation track';
$string['copyfailed'] = 'The link could not be copied. Copy the address from the browser address bar.';
$string['copylink'] = 'Copy link';
$string['createbranch'] = 'Start conversation track';
$string['createdat'] = 'Created {$a}';
$string['createpost'] = 'Create post';
$string['currenttrack'] = 'Current track';
$string['deletedpost'] = '[This post was deleted by a moderator]';
$string['deliveryaccepted'] = 'Accepted by Moodle';
$string['deliveryfailed'] = 'Failed — retry limit reached';
$string['deliverypending'] = 'Pending';
$string['deliveryuncertain'] = 'Uncertain — administrator review required';
$string['depth'] = 'Depth';
$string['depthlabel'] = 'Current conversation depth';
$string['digesthour'] = 'Daily digest hour';
$string['digesthour_desc'] = 'Daily cutoff hour in each recipient\'s Moodle timezone. The next cron run sends due digests; daylight-saving changes are respected. Changing this affects newly queued digests.';
$string['digestsubject'] = '{$a->forum}: daily digest ({$a->count} posts)';
$string['dislike'] = 'Dislike';
$string['dislikes'] = 'Dislikes';
$string['editedat'] = 'Edited {$a}';
$string['enablecolors'] = 'Give each track its own colour';
$string['enablelikes'] = 'Allow like/dislike on posts';
$string['enablelikes_help'] = 'Participants can choose either like or dislike, switch their choice or remove their reaction. The administrator can disable both site-wide. Disabling hides buttons and counts but preserves reactions.';
$string['excerptchanged'] = 'The original text has changed. Open the original post to read its current content.';
$string['excerptcomment'] = 'Your comment on this quote';
$string['excerptinstructions'] = 'Open the original post above, select a passage and choose Add selected text. Write a comment below each quote. You can include up to 10 quotes in one reply. Write at least one comment or a message to submit.';
$string['excerptlimit'] = 'You can include at most 10 quotes in one reply.';
$string['excerptquote'] = 'Quote from the original post';
$string['excerptremoved'] = 'Quote and comment removed.';
$string['excerptreply'] = 'Reply to selected passages';
$string['excerptrequiresjs'] = 'Replying to selected passages requires JavaScript. You can still write a regular reply and optionally include the whole original post.';
$string['filteredlist'] = 'Filtered results are shown as a list. Choose Map to clear the filters.';
$string['forumlocked'] = 'This conversation is locked.';
$string['groupaccessdenied'] = 'You cannot access or contribute to this group conversation.';
$string['groupaudience'] = 'Audience for existing conversations';
$string['groupmigrationrequired'] = 'Existing conversations need an explicit audience review before participants can use this activity in group mode.';
$string['groupreviewbusy'] = 'An audience review is already running. Please try again.';
$string['groupreviewcomplete'] = 'The audience of existing conversations has been recorded.';
$string['groupreviewexplanation'] = 'This activity contains {$a} existing posts. The selected audience will apply to all existing posts and replies. Choosing All participants explicitly shares these conversations with everyone who can access the activity. No posts are deleted or copied. Cancel if different conversations need different audiences.';
$string['groupreviewstructure'] = 'An existing branch with posts has no valid origin link. Repair its conversation links before enabling group mode; no audience has been changed.';
$string['includeparent'] = 'Include the previous post as a quote';
$string['includeparent_help'] = 'Select this to display the post you are replying to as a clearly marked quote in your reply. The reply relationship is kept even without a quote. The quote follows changes to or deletion of the original.';
$string['inreplyto'] = 'In reply to';
$string['invalidaction'] = 'Invalid action.';
$string['invalidexcerpt'] = 'A quote could not be found in the original post or exceeds the limit. Select the text again. Each quote and comment must be at most 4,000 characters.';
$string['invalidmedia'] = 'The media files could not be saved: {$a}';
$string['invalidsubject'] = 'Enter a subject containing between 1 and 255 characters.';
$string['invalidsubscription'] = 'Only active participants can subscribe to this activity. You can still unsubscribe after losing course access.';
$string['invalidtrackname'] = 'Enter a track name containing between 1 and 255 characters.';
$string['like'] = 'Like';
$string['likelocktimeout'] = 'Your reaction could not be saved right now. Please try again.';
$string['likes'] = 'Likes';
$string['likesdisabled'] = 'Like/dislike is disabled in this activity.';
$string['likesdisabledsite'] = 'Like/dislike is currently disabled by the site administrator.';
$string['linkcopied'] = 'Post link copied.';
$string['listview'] = 'List';
$string['lockforum'] = 'Lock the whole forum';
$string['locktrack'] = 'Lock this track';
$string['maintrack'] = 'Main track';
$string['maplimit'] = 'Large conversations with more than 150 posts are shown as a list.';
$string['mapview'] = 'Map';
$string['markread'] = 'Mark as read';
$string['maxdepth'] = 'Maximum track depth';
$string['maxdepth_help'] = 'The maximum number of nested conversation tracks, including the main track.';
$string['maxdepthreached'] = 'This conversation has reached its maximum track depth.';
$string['maxmediafilebytes'] = 'Maximum size per media file';
$string['maxmediafiles'] = 'Maximum files per post';
$string['maxmediapostbytes'] = 'Maximum combined media size per post';
$string['mediafilelimit'] = 'A post can contain at most {$a} uploaded files.';
$string['mediafilesize'] = 'Each uploaded file must be no larger than {$a}.';
$string['mediafiletype'] = 'This file type is not allowed for post media.';
$string['mediapostsize'] = 'Uploaded files in one post must total no more than {$a}.';
$string['message'] = 'Message';
$string['messageprovider:digests'] = 'Spatial forum daily digests';
$string['messageprovider:posts'] = 'New spatial forum posts';
$string['modulename'] = 'Spatial forum';
$string['modulename_help'] = 'A forum where branching discussions become named, colour-coded spatial tracks.';
$string['modulenameplural'] = 'Spatial forums';
$string['newpost'] = 'New post';
$string['nextunread'] = 'Next unread';
$string['noinstances'] = 'There are no spatial forums in this course.';
$string['noposts'] = 'There are no posts in this track yet.';
$string['noresults'] = 'No posts match your filters. Clear the search or turn off Unread only.';
$string['notificationbusy'] = 'Notification preferences are being updated. Please try again.';
$string['notificationdeliveries'] = 'Spatial forum notification delivery';
$string['notificationdeliveries_desc'] = 'Counts contain no message bodies. Accepted means handed to Moodle, not confirmed inbox delivery. Explicit send failures retry up to five times with backoff. Uncertain deliveries (for example a crash after dispatch began) are never retried automatically: inspect message and task logs before deciding whether to retry. Delivery metadata expire after 30 days. Moodle cron must run regularly.';
$string['notificationpreferences'] = 'Notification preferences';
$string['of'] = 'of';
$string['onlyunread'] = 'Unread only';
$string['opennotifiedpost'] = 'Open post in Moodle';
$string['originalunavailable'] = 'The original post is no longer available.';
$string['pluginadministration'] = 'Spatial forum administration';
$string['pluginname'] = 'Spatial forum';
$string['postnotificationsubject'] = '{$a->forum}: new post';
$string['postreply'] = 'Post reply';
$string['posts'] = 'posts';
$string['postsaved'] = 'Your post was saved.';
$string['privacy:metadata:files'] = 'Images, audio, video and captions embedded by a post author.';
$string['privacy:metadata:messages'] = 'Opt-in notifications are passed to the Moodle messaging subsystem.';
$string['privacy:metadata:spatialforum_deliveries'] = 'Pending and recent notification delivery metadata; message bodies are not stored here.';
$string['privacy:metadata:spatialforum_deliveries:attempts'] = 'Completed dispatch attempts.';
$string['privacy:metadata:spatialforum_deliveries:due'] = 'Scheduled delivery cutoff.';
$string['privacy:metadata:spatialforum_deliveries:messageid'] = 'Moodle message reference after acceptance.';
$string['privacy:metadata:spatialforum_deliveries:mode'] = 'Each-post or digest delivery.';
$string['privacy:metadata:spatialforum_deliveries:postid'] = 'The source post.';
$string['privacy:metadata:spatialforum_deliveries:status'] = 'Pending, accepted, failed or uncertain.';
$string['privacy:metadata:spatialforum_deliveries:timecreated'] = 'When delivery was queued.';
$string['privacy:metadata:spatialforum_deliveries:timemodified'] = 'When delivery state last changed.';
$string['privacy:metadata:spatialforum_deliveries:userid'] = 'The intended recipient.';
$string['privacy:metadata:spatialforum_likes'] = 'Stores likes and dislikes on posts.';
$string['privacy:metadata:spatialforum_likes:postid'] = 'The post that was reacted to.';
$string['privacy:metadata:spatialforum_likes:reaction'] = 'The reaction: 1 for like, -1 for dislike.';
$string['privacy:metadata:spatialforum_likes:timecreated'] = 'When the reaction was last changed.';
$string['privacy:metadata:spatialforum_likes:userid'] = 'The user who reacted to the post.';
$string['privacy:metadata:spatialforum_posts'] = 'Stores posts contributed by users.';
$string['privacy:metadata:spatialforum_posts:groupid'] = 'The audience group of the conversation, or an explicitly shared audience.';
$string['privacy:metadata:spatialforum_posts:message'] = 'The post body.';
$string['privacy:metadata:spatialforum_posts:replyparts'] = 'The reply author’s comments and references to selected passages in the original post. The original text is not copied.';
$string['privacy:metadata:spatialforum_posts:showparent'] = 'Whether the author chose to include the previous post as a quote.';
$string['privacy:metadata:spatialforum_posts:subject'] = 'The post subject.';
$string['privacy:metadata:spatialforum_posts:timecreated'] = 'When the post was created.';
$string['privacy:metadata:spatialforum_posts:userid'] = 'The user who created the post.';
$string['privacy:metadata:spatialforum_read'] = 'Stores which posts a user has read.';
$string['privacy:metadata:spatialforum_read:postid'] = 'The post that was read.';
$string['privacy:metadata:spatialforum_read:timeread'] = 'When the post was read.';
$string['privacy:metadata:spatialforum_read:userid'] = 'The user who read the post.';
$string['privacy:metadata:spatialforum_subscriptions'] = 'Activity notification preferences explicitly chosen by a participant.';
$string['privacy:metadata:spatialforum_subscriptions:firstpostid'] = 'Earlier post IDs excluded from notifications.';
$string['privacy:metadata:spatialforum_subscriptions:mode'] = 'Each-post or daily-digest delivery.';
$string['privacy:metadata:spatialforum_subscriptions:timecreated'] = 'When the participant subscribed.';
$string['privacy:metadata:spatialforum_subscriptions:timemodified'] = 'When their notification mode changed.';
$string['privacy:metadata:spatialforum_subscriptions:userid'] = 'The participant who opted in.';
$string['privacy:metadata:spatialforum_tracks'] = 'Stores named conversation tracks created from posts.';
$string['privacy:metadata:spatialforum_tracks:name'] = 'Track name entered or derived from the first post by its author.';
$string['privacy:metadata:spatialforum_tracks:originpostid'] = 'The post from which the track was started.';
$string['privacy:metadata:tasks'] = 'Background tasks reference the source post to resolve eligible recipients after publication.';
$string['quoteoptions'] = 'More quoting options';
$string['readingpost'] = 'Read post';
$string['readlocktimeout'] = 'Read state could not be saved right now. Please try again.';
$string['readnottracked'] = 'Not tracked';
$string['readpost'] = 'Read';
$string['readsavefailed'] = 'Read state could not be saved. The post is still marked unread. Close and reopen it to try again.';
$string['removedpost'] = 'Deleted';
$string['removeexcerpt'] = 'Remove quote and comment';
$string['replieshere'] = 'Replies from here';
$string['reply'] = 'Reply';
$string['replylevel'] = 'Reply level';
$string['replyselection'] = 'Reply to selection';
$string['replystartsnewtrack'] = 'Your reply starts a new conversation track linked back to this post. You can change the track name and reply subject below.';
$string['reviewgroupaudience'] = 'Review existing conversation audience';
$string['searchposts'] = 'Search this conversation';
$string['selectionhint'] = 'Select text in this post to reply to a specific passage.';
$string['selectionready'] = 'Selection ready. Choose Reply to selection to continue.';
$string['sendnotifications'] = 'Send spatial forum notifications and digests';
$string['showoriginal'] = 'Show original post';
$string['spatialforum:addinstance'] = 'Add a new spatial forum';
$string['spatialforum:like'] = 'Like or dislike posts and remove own reactions';
$string['spatialforum:moderate'] = 'Moderate spatial forums';
$string['spatialforum:reply'] = 'Post replies';
$string['spatialforum:startbranch'] = 'Start conversation tracks';
$string['spatialforum:view'] = 'View a spatial forum';
$string['spatialforumname'] = 'Spatial forum name';
$string['startconversation'] = 'Start the conversation';
$string['subject'] = 'Subject';
$string['subscriptiondaily'] = 'Daily digest';
$string['subscriptionimmediate'] = 'Each new post';
$string['subscriptionmode'] = 'Notify me about new posts';
$string['subscriptionoff'] = 'Off — no notifications';
$string['subscriptionsaved'] = 'Your notification preference has been saved.';
$string['subscriptionscope'] = 'Notifications cover new posts in this activity that you are allowed to view. Your own posts and earlier posts are not sent. Delivery follows your Moodle notification preferences and the site cron schedule. Media stay in Moodle and require activity access.';
$string['undislike'] = 'Remove dislike';
$string['unknownuser'] = 'Unknown user';
$string['unlike'] = 'Unlike';
$string['unlocktrack'] = 'Unlock this track';
$string['unread'] = 'Unread';
$string['unreadposts'] = 'unread';
$string['viewmode'] = 'Conversation view';
$string['wholeconversation'] = 'Whole conversation';
$string['yourroute'] = 'Your route';
