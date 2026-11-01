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


$string['addexcerpt'] = 'Tilføj markeret tekst';
$string['allowedmediatypes'] = 'Tilladte mediefiltyper';
$string['allowedmediatypes_desc'] = 'Vælg understøttede billed-, lyd-, video- og undertekstformater. Et tomt valg bruger alle understøttede formater. Grænserne gælder uploadede filer; eksterne medielinks forbliver eksterne.';
$string['allowlikes'] = 'Tillad like/dislike på hele sitet';
$string['allowlikes_desc'] = 'Når indstillingen er slået fra, skjules begge reaktioner, og ingen nye kan gives. Eksisterende reaktioner bevares.';
$string['allparticipants'] = 'Alle deltagere (fælles samtale)';
$string['alltracks'] = 'Alle spor';
$string['anonymoustrack'] = 'Spor fra fjernet forfatter';
$string['applygroupaudience'] = 'Anvend målgruppen på eksisterende indlæg';
$string['backoverview'] = 'Tilbage til overblik';
$string['branchfromhere'] = 'Start spor her';
$string['branchname'] = 'Navn på samtalesporet';
$string['branchrequirespost'] = 'Et nyt spor skal begynde ved et eksisterende indlæg.';
$string['closepost'] = 'Luk';
$string['closingcomment'] = 'Afsluttende kommentar (valgfri)';
$string['confirmdeletepost'] = 'Vil du slette dette indlæg? Dets plads i samtalen bevares.';
$string['confirmgroupaudience'] = 'Jeg har gennemgået de eksisterende indlæg og bekræfter denne målgruppe for dem alle.';
$string['continuingtracks'] = 'Spor, der fortsætter herfra';
$string['conversationmap'] = 'Samtalekort';
$string['conversationtrack'] = 'Samtalespor';
$string['copyfailed'] = 'Linket kunne ikke kopieres. Kopiér adressen fra browserens adresselinje.';
$string['copylink'] = 'Kopiér link';
$string['createbranch'] = 'Start samtalespor';
$string['createdat'] = 'Oprettet {$a}';
$string['createpost'] = 'Opret indlæg';
$string['currenttrack'] = 'Aktuelt spor';
$string['deletedpost'] = '[Indlægget er slettet af en moderator]';
$string['deliveryaccepted'] = 'Accepted by Moodle';
$string['deliveryfailed'] = 'Failed — retry limit reached';
$string['deliverypending'] = 'Pending';
$string['deliveryuncertain'] = 'Uncertain — administrator review required';
$string['depth'] = 'Dybde';
$string['depthlabel'] = 'Aktuel samtaledybde';
$string['digesthour'] = 'Daily digest hour';
$string['digesthour_desc'] = 'Daily cutoff hour in each recipient\'s Moodle timezone. The next cron run sends due digests; daylight-saving changes are respected. Changing this affects newly queued digests.';
$string['digestsubject'] = '{$a->forum}: daily digest ({$a->count} posts)';
$string['dislike'] = 'Dislike';
$string['dislikes'] = 'Dislikes';
$string['draftready'] = 'Kladdegendannelse bruger denne browserfane. Hold fanen åben, hvis forbindelsen svigter. Kladder udløber efter 24 timer.';
$string['editedat'] = 'Redigeret {$a}';
$string['editpost'] = 'Redigér mit indlæg';
$string['editposthelp'] = 'Dine svar forbliver forbundet. Ændringer viser et redigeret-tidspunkt og sender ikke en ny notifikation.';
$string['enablecolors'] = 'Giv hvert spor sin egen farve';
$string['enablelikes'] = 'Tillad like/dislike på indlæg';
$string['enablelikes_help'] = 'Deltagere kan vælge enten like eller dislike på et indlæg, skifte valg eller fjerne reaktionen. Administratoren kan slå begge fra for alle Samtalespor. Når funktionen slås fra, skjules knapper og antal, mens reaktionerne bevares.';
$string['excerptchanged'] = 'Originalteksten er ændret. Åbn originalindlægget for at se den aktuelle tekst.';
$string['excerptcomment'] = 'Din kommentar til citatet';
$string['excerptinstructions'] = 'Åbn originalindlægget ovenfor, markér en passage, og vælg Tilføj markeret tekst. Skriv en kommentar under hvert citat. Du kan medtage op til 10 citater i ét svar. Skriv mindst én kommentar eller en besked for at sende.';
$string['excerptlimit'] = 'Du kan højst medtage 10 citater i samme svar.';
$string['excerptquote'] = 'Citat fra originalindlægget';
$string['excerptremoved'] = 'Citat og kommentar er fjernet.';
$string['excerptreply'] = 'Svar på udvalgte dele';
$string['excerptrequiresjs'] = 'Svar på markeret tekst kræver JavaScript. Du kan stadig skrive et almindeligt svar og frivilligt medtage hele originalindlægget.';
$string['exportforum'] = 'Export spatial forum';
$string['exportintro'] = 'Exports threads, authors, groups, timestamps, formats and supported media references. Notification state, grades, completion and private data are excluded.';
$string['exportjson'] = 'Download standard forum interchange JSON';
$string['filteredlist'] = 'Søgeresultater vises som en liste. Vælg Kort for at rydde filtrene.';
$string['followthread'] = 'Følg svar i denne samtale';
$string['forumlocked'] = 'Denne samtale er låst.';
$string['groupaccessdenied'] = 'Du har ikke adgang til at læse eller deltage i denne gruppesamtale.';
$string['groupaudience'] = 'Målgruppe for eksisterende samtaler';
$string['groupmigrationrequired'] = 'Eksisterende samtalers målgruppe skal gennemgås, før deltagere kan bruge aktiviteten i gruppetilstand.';
$string['groupreviewbusy'] = 'Målgruppen bliver allerede gennemgået. Prøv igen.';
$string['groupreviewcomplete'] = 'Målgruppen for de eksisterende samtaler er gemt.';
$string['groupreviewexplanation'] = 'Aktiviteten indeholder {$a} eksisterende indlæg. Den valgte målgruppe gælder alle eksisterende indlæg og svar. Alle deltagere deler udtrykkeligt samtalerne med alle, der har adgang til aktiviteten. Ingen indlæg slettes eller kopieres. Annuller, hvis forskellige samtaler kræver forskellige målgrupper.';
$string['groupreviewstructure'] = 'En eksisterende gren med indlæg mangler et gyldigt link til sit udgangspunkt. Ret samtalens links, før gruppetilstand aktiveres. Ingen målgruppe er ændret.';
$string['includeparent'] = 'Medtag det foregående indlæg som citat';
$string['includeparent_help'] = 'Vælg dette for at vise det indlæg, du svarer på, som et tydeligt markeret citat i dit svar. Forbindelsen til indlægget bevares også uden citat. Citatet følger ændringer og sletning af originalen.';
$string['inreplyto'] = 'Svar på indlæg';
$string['invalidaction'] = 'Ugyldig handling.';
$string['invalidexcerpt'] = 'Et citat kunne ikke genfindes i originalindlægget eller overskrider grænsen. Markér teksten igen. Hvert citat og hver kommentar må højst være 4.000 tegn.';
$string['invalidmedia'] = 'Mediefilerne kunne ikke gemmes: {$a}';
$string['invalidsubject'] = 'Indtast et emne på mellem 1 og 255 tegn.';
$string['invalidsubscription'] = 'Only active participants can subscribe to this activity. You can still unsubscribe after losing course access.';
$string['invalidtrackname'] = 'Indtast et spornavn på mellem 1 og 255 tegn.';
$string['like'] = 'Like';
$string['likelocktimeout'] = 'Din reaktion kunne ikke gemmes lige nu. Prøv igen.';
$string['likes'] = 'Likes';
$string['likesdisabled'] = 'Like/dislike er slået fra i denne aktivitet.';
$string['likesdisabledsite'] = 'Administratoren har slået like/dislike fra på sitet.';
$string['linkcopied'] = 'Link til indlægget er kopieret.';
$string['listview'] = 'Liste';
$string['lockforum'] = 'Lås hele forummet';
$string['locktrack'] = 'Lås dette spor';
$string['maintrack'] = 'Hovedspor';
$string['maplimit'] = 'Store samtaler med over 150 indlæg vises som en liste.';
$string['mapview'] = 'Kort';
$string['markread'] = 'Markér som læst';
$string['maxdepth'] = 'Maksimal spordybde';
$string['maxdepth_help'] = 'Det maksimale antal indlejrede samtalespor inklusive hovedsporet.';
$string['maxdepthreached'] = 'Samtalen har nået den maksimale spordybde.';
$string['maxmediafilebytes'] = 'Maksimal størrelse pr. mediefil';
$string['maxmediafiles'] = 'Maksimalt antal filer pr. indlæg';
$string['maxmediapostbytes'] = 'Maksimal samlet mediestørrelse pr. indlæg';
$string['mediafilelimit'] = 'Et indlæg må højst indeholde {$a} uploadede filer.';
$string['mediafilesize'] = 'Hver uploadet fil må højst fylde {$a}.';
$string['mediafiletype'] = 'Denne filtype er ikke tilladt som medie i indlæg.';
$string['mediapostsize'] = 'Uploadede filer i ét indlæg må samlet højst fylde {$a}.';
$string['message'] = 'Besked';
$string['messageprovider:digests'] = 'Spatial forum daily digests';
$string['messageprovider:posts'] = 'New spatial forum posts';
$string['modulename'] = 'Samtalespor';
$string['modulename_help'] = 'Et forum, hvor forgrenede diskussioner bliver til navngivne og farvekodede samtalespor.';
$string['modulenameplural'] = 'Samtalespor';
$string['newpost'] = 'Nyt indlæg';
$string['nextunread'] = 'Næste ulæste';
$string['noinstances'] = 'Der er ingen samtalespor i dette kursus.';
$string['noposts'] = 'Der er endnu ingen indlæg i dette spor.';
$string['noresults'] = 'Ingen indlæg matcher dine filtre. Ryd søgefeltet eller fjern Kun ulæste.';
$string['notificationbusy'] = 'Notification preferences are being updated. Please try again.';
$string['notificationdeliveries'] = 'Spatial forum notification delivery';
$string['notificationdeliveries_desc'] = 'Counts contain no message bodies. Accepted means handed to Moodle, not confirmed inbox delivery. Explicit send failures retry up to five times with backoff. Uncertain deliveries (for example a crash after dispatch began) are never retried automatically: inspect message and task logs before deciding whether to retry. Delivery metadata expire after 30 days. Moodle cron must run regularly.';
$string['notificationpreferences'] = 'Notification preferences';
$string['of'] = 'af';
$string['onlyunread'] = 'Kun ulæste';
$string['opennotifiedpost'] = 'Open post in Moodle';
$string['originalunavailable'] = 'Det oprindelige indlæg er ikke længere tilgængeligt.';
$string['pluginadministration'] = 'Administration af Samtalespor';
$string['pluginname'] = 'Samtalespor';
$string['postnotificationsubject'] = '{$a->forum}: new post';
$string['postreply'] = 'Send svar';
$string['posts'] = 'indlæg';
$string['postsaved'] = 'Dit indlæg er gemt.';
$string['privacy:metadata:files'] = 'Billeder, lyd, video og undertekster indsat af indlæggets forfatter.';
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
$string['privacy:metadata:spatialforum_likes'] = 'Gemmer likes og dislikes på indlæg.';
$string['privacy:metadata:spatialforum_likes:postid'] = 'Indlægget, der blev reageret på.';
$string['privacy:metadata:spatialforum_likes:reaction'] = 'Reaktionen: 1 for like, -1 for dislike.';
$string['privacy:metadata:spatialforum_likes:timecreated'] = 'Tidspunktet for den seneste ændring af reaktionen.';
$string['privacy:metadata:spatialforum_likes:userid'] = 'Brugeren, der reagerede på indlægget.';
$string['privacy:metadata:spatialforum_posts'] = 'Gemmer brugernes indlæg.';
$string['privacy:metadata:spatialforum_posts:groupid'] = 'Samtalens målgruppe eller en udtrykkeligt fælles målgruppe.';
$string['privacy:metadata:spatialforum_posts:message'] = 'Indlæggets tekst.';
$string['privacy:metadata:spatialforum_posts:replyparts'] = 'Svarforfatterens kommentarer og henvisninger til udvalgte dele af originalindlægget. Originalteksten kopieres ikke.';
$string['privacy:metadata:spatialforum_posts:showparent'] = 'Om forfatteren valgte at medtage det foregående indlæg som citat.';
$string['privacy:metadata:spatialforum_posts:subject'] = 'Indlæggets emne.';
$string['privacy:metadata:spatialforum_posts:timecreated'] = 'Tidspunktet, hvor indlægget blev oprettet.';
$string['privacy:metadata:spatialforum_posts:userid'] = 'Brugeren, som oprettede indlægget.';
$string['privacy:metadata:spatialforum_read'] = 'Gemmer hvilke indlæg brugeren har læst.';
$string['privacy:metadata:spatialforum_read:postid'] = 'Indlægget, der blev læst.';
$string['privacy:metadata:spatialforum_read:timeread'] = 'Tidspunktet, hvor indlægget blev læst.';
$string['privacy:metadata:spatialforum_read:userid'] = 'Brugeren, som læste indlægget.';
$string['privacy:metadata:spatialforum_subscriptions'] = 'Activity notification preferences explicitly chosen by a participant.';
$string['privacy:metadata:spatialforum_subscriptions:firstpostid'] = 'Earlier post IDs excluded from notifications.';
$string['privacy:metadata:spatialforum_subscriptions:mode'] = 'Each-post or daily-digest delivery.';
$string['privacy:metadata:spatialforum_subscriptions:timecreated'] = 'When the participant subscribed.';
$string['privacy:metadata:spatialforum_subscriptions:timemodified'] = 'When their notification mode changed.';
$string['privacy:metadata:spatialforum_subscriptions:userid'] = 'The participant who opted in.';
$string['privacy:metadata:spatialforum_tracks'] = 'Gemmer navngivne samtalespor, der er oprettet fra indlæg.';
$string['privacy:metadata:spatialforum_tracks:name'] = 'Spornavn indtastet eller afledt af forfatterens første indlæg.';
$string['privacy:metadata:spatialforum_tracks:originpostid'] = 'Indlægget, som sporet blev startet fra.';
$string['privacy:metadata:tasks'] = 'Background tasks reference the source post to resolve eligible recipients after publication.';
$string['quoteoptions'] = 'Flere citatmuligheder';
$string['readingpost'] = 'Læs indlæg';
$string['readlocktimeout'] = 'Læsestatus kunne ikke gemmes lige nu. Prøv igen.';
$string['readnottracked'] = 'Registreres ikke';
$string['readpost'] = 'Læst';
$string['readsavefailed'] = 'Læsestatus kunne ikke gemmes. Indlægget er stadig markeret som ulæst. Luk og åbn det igen for at prøve igen.';
$string['removedpost'] = 'Slettet';
$string['removeexcerpt'] = 'Fjern citat og kommentar';
$string['replieshere'] = 'Svar herfra';
$string['reply'] = 'Svar';
$string['replylevel'] = 'Svarniveau';
$string['replyselection'] = 'Svar på markeringen';
$string['replystartsnewtrack'] = 'Dit svar starter et nyt samtalespor med forbindelse tilbage til dette indlæg. Du kan ændre sporets navn og svarets emne nedenfor.';
$string['reviewgroupaudience'] = 'Gennemgå eksisterende samtalers målgruppe';
$string['searchposts'] = 'Søg i samtalen';
$string['selectionhint'] = 'Markér tekst i indlægget for at svare på en bestemt passage.';
$string['selectionready'] = 'Markeringen er klar. Vælg Svar på markeringen for at fortsætte.';
$string['sendnotifications'] = 'Send spatial forum notifications and digests';
$string['showoriginal'] = 'Vis originalindlæg';
$string['spatialforum:addinstance'] = 'Tilføj et nyt samtalespor';
$string['spatialforum:like'] = 'Like eller dislike indlæg og fjerne egne reaktioner';
$string['spatialforum:moderate'] = 'Moderér samtalespor';
$string['spatialforum:reply'] = 'Skriv svar';
$string['spatialforum:startbranch'] = 'Start nye samtalespor';
$string['spatialforum:view'] = 'Se et samtalespor';
$string['spatialforumname'] = 'Navn på samtalesporet';
$string['startconversation'] = 'Start samtalen';
$string['subject'] = 'Emne';
$string['subscriptiondaily'] = 'Daily digest';
$string['subscriptionimmediate'] = 'Each new post';
$string['subscriptionmode'] = 'Notify me about new posts';
$string['subscriptionoff'] = 'Off for the entire activity, including followed conversations';
$string['subscriptionpolicy'] = 'Default subscription policy';
$string['subscriptionpolicy_desc'] = 'Controls the default and whether participants may opt out of activity notifications.';
$string['subscriptionpolicy_disabled'] = 'Disable subscriptions';
$string['subscriptionpolicy_forced'] = 'Always subscribe; participants cannot opt out';
$string['subscriptionpolicy_initial'] = 'Subscribe initially; participants may opt out';
$string['subscriptionpolicy_optin'] = 'Opt-in';
$string['subscriptionsaved'] = 'Your notification preference has been saved.';
$string['subscriptionscope'] = 'Notifications cover new posts in this activity that you are allowed to view. Your own posts and earlier posts are not sent. Delivery follows your Moodle notification preferences and the site cron schedule. Media stay in Moodle and require activity access.';
$string['threadnotifications'] = 'Samtalenotifikationer';
$string['undislike'] = 'Fjern dislike';
$string['unknownuser'] = 'Ukendt bruger';
$string['unlike'] = 'Fjern like';
$string['unlocktrack'] = 'Lås dette spor op';
$string['unread'] = 'Ulæst';
$string['unreadposts'] = 'ulæste';
$string['viewmode'] = 'Vis samtalen som';
$string['wholeconversation'] = 'Hele samtalen';
$string['yourroute'] = 'Din rute';
