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
$string['alltracks'] = 'Alle spor';
$string['anonymoustrack'] = 'Spor fra fjernet forfatter';
$string['backoverview'] = 'Tilbage til overblik';
$string['branchfromhere'] = 'Start spor her';
$string['branchname'] = 'Navn på samtalesporet';
$string['branchrequirespost'] = 'Et nyt spor skal begynde ved et eksisterende indlæg.';
$string['closepost'] = 'Luk';
$string['closingcomment'] = 'Afsluttende kommentar (valgfri)';
$string['confirmdeletepost'] = 'Vil du slette dette indlæg? Dets plads i samtalen bevares.';
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
$string['depth'] = 'Dybde';
$string['depthlabel'] = 'Aktuel samtaledybde';
$string['dislike'] = 'Dislike';
$string['dislikes'] = 'Dislikes';
$string['editedat'] = 'Redigeret {$a}';
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
$string['filteredlist'] = 'Søgeresultater vises som en liste. Vælg Kort for at rydde filtrene.';
$string['forumlocked'] = 'Denne samtale er låst.';
$string['includeparent'] = 'Medtag det foregående indlæg som citat';
$string['includeparent_help'] = 'Vælg dette for at vise det indlæg, du svarer på, som et tydeligt markeret citat i dit svar. Forbindelsen til indlægget bevares også uden citat. Citatet følger ændringer og sletning af originalen.';
$string['inreplyto'] = 'Svar på indlæg';
$string['invalidaction'] = 'Ugyldig handling.';
$string['invalidexcerpt'] = 'Et citat kunne ikke genfindes i originalindlægget eller overskrider grænsen. Markér teksten igen. Hvert citat og hver kommentar må højst være 4.000 tegn.';
$string['invalidmedia'] = 'Mediefilerne kunne ikke gemmes: {$a}';
$string['invalidsubject'] = 'Indtast et emne på mellem 1 og 255 tegn.';
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
$string['modulename'] = 'Samtalespor';
$string['modulename_help'] = 'Et forum, hvor forgrenede diskussioner bliver til navngivne og farvekodede samtalespor.';
$string['modulenameplural'] = 'Samtalespor';
$string['newpost'] = 'Nyt indlæg';
$string['nextunread'] = 'Næste ulæste';
$string['noinstances'] = 'Der er ingen samtalespor i dette kursus.';
$string['noposts'] = 'Der er endnu ingen indlæg i dette spor.';
$string['noresults'] = 'Ingen indlæg matcher dine filtre. Ryd søgefeltet eller fjern Kun ulæste.';
$string['of'] = 'af';
$string['onlyunread'] = 'Kun ulæste';
$string['originalunavailable'] = 'Det oprindelige indlæg er ikke længere tilgængeligt.';
$string['pluginname'] = 'Samtalespor';
$string['postreply'] = 'Send svar';
$string['posts'] = 'indlæg';
$string['postsaved'] = 'Dit indlæg er gemt.';
$string['privacy:metadata:files'] = 'Billeder, lyd, video og undertekster indsat af indlæggets forfatter.';
$string['privacy:metadata:spatialforum_likes'] = 'Gemmer likes og dislikes på indlæg.';
$string['privacy:metadata:spatialforum_likes:postid'] = 'Indlægget, der blev reageret på.';
$string['privacy:metadata:spatialforum_likes:reaction'] = 'Reaktionen: 1 for like, -1 for dislike.';
$string['privacy:metadata:spatialforum_likes:timecreated'] = 'Tidspunktet for den seneste ændring af reaktionen.';
$string['privacy:metadata:spatialforum_likes:userid'] = 'Brugeren, der reagerede på indlægget.';
$string['privacy:metadata:spatialforum_posts'] = 'Gemmer brugernes indlæg.';
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
$string['privacy:metadata:spatialforum_tracks'] = 'Gemmer navngivne samtalespor, der er oprettet fra indlæg.';
$string['privacy:metadata:spatialforum_tracks:name'] = 'Spornavn indtastet eller afledt af forfatterens første indlæg.';
$string['privacy:metadata:spatialforum_tracks:originpostid'] = 'Indlægget, som sporet blev startet fra.';
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
$string['searchposts'] = 'Søg i samtalen';
$string['selectionhint'] = 'Markér tekst i indlægget for at svare på en bestemt passage.';
$string['selectionready'] = 'Markeringen er klar. Vælg Svar på markeringen for at fortsætte.';
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
$string['undislike'] = 'Fjern dislike';
$string['unknownuser'] = 'Ukendt bruger';
$string['unlike'] = 'Fjern like';
$string['unlocktrack'] = 'Lås dette spor op';
$string['unread'] = 'Ulæst';
$string['unreadposts'] = 'ulæste';
$string['viewmode'] = 'Vis samtalen som';
$string['wholeconversation'] = 'Hele samtalen';
$string['yourroute'] = 'Din rute';
