### Plugin Name: WPDoodlez and Quizzz ###
Contributors: robert_kolatzek, PBMod
Tags: doodle, poll, question, meeting, vote
Text Domain: WPDoodlez
Domain Path: /lang/
Version: 9.1.1.140
Stable tag: 9.1.1.140
Requires at least: 6.0
Tested up to: 6.5.2
Requires PHP: 8.1

## Description ##

### WPDoodlez
plan and vote to find a common appointment date with participiants for an event. Works similar to famous Doodle(TM) website.
It adds a custom post type "wpdoodlez".

You can just use the custom posts for polls and appointments or place the poll in an existing post or page using the shortcode:

Shortcode: ´´´[wpdoodlez_sc id=post-ID chart=true]´´´
 set post ID to integrate Doodlez or Poll in other pages, set chart to false if you do not want pie graph
 set chart to fals to omit showing cake chart (requires chartscodes plugin)

 Notice: You may use this shortcode only once per page or post.

### Poll
uses same technology and custom post type like WPDoodlez
create classic polls to let visitors vote about a question

Shortcode: ´´´[wpdoodlez_stats id=22817 type=poll|doodlez]´´´ show stats of all polls or doodlez in a list on a page or post
	id = 0  // or omit id parameter - shows stats of all polls and appointments, set other id to specify the output
	type = poll (or empty, default) displays all poll results // or type = doodlez display all doodlez results

### Quizzz
Play a quiz game with one or four answers
Quizzz supports categories images and integrates them in single and header if used in theme.
It adds a custom post type "question". See readme.txt for more details.
If you place pictures in upload/quizbilder folder and enter the filename of the picture you can make questions displaying the picture

Add Shortcode ´´´[random-question]´´´ to an html widget (only on front page)

#### Crossword
You can add a random crossword game to your pages and posts or call the crossword game from the random quizzz widget.
16 random words and questions will be chosen and put into a crossword game. Admin can display the solutions, users can see vocals as hints.

	== Crossword Usage == call any quiz page with url parameter crossord=1 or call from the menu during a quiz or on random question widget.

add crossword=1 to any quizfrage post commandline 
displays a crossword game built on the quizzz words (by shortcode and clickable in random question widget

#### Wordpuzzle
add crossword=2 to any quizfrage post commandline 
displays a wordpuzzle with random words from quizfragen Answers

#### Hangman js
add crossword=3 to any quizfrage post commandline 
displays a hangman quiz with canvas graph. Using random words from quizfragen Answers


## Why this fork?
the main project was not updated for years now. I needed some features and added them to the project.
Completed the german and german formal translations. Added method to use structure for polls (only one answer allowed)
Added quiz custom post type "question" to make a 4 answers and single answer quiz with results certificate. questions can be played as hangman, when answer ist suitable

## GDPR (DSGVO) notice
Participiants do net need to enter their full name (a nick name would do).
A cookie "wpdoodlez-hashofthevote" with the given nic name is stored on the local computer to
notice that one has voted already - so you should opt-in cookies and place a note on your GDPR statement.

If Using penguin-mod theme cookies will only be set after visitor allows comfort cookies (stated by hidecookiebannerx=2 cookie-setting).

WPDoodlez (posts or posts with wpd shortcode) can be password protected (with builtin wordpress logic) to prevent others seeing the content
Archives, Post Content, Post-Thumbnail, Categories and Tags have been enabled to provide full integration like a normal post.

Quizzz game has a shortcode for random questions and a custom post type "question" for quiz. It can display a certificate to print
and stores your ranking in a personal cookie. personal rankings are only stored in your browser, not the web server and only if you allow comfort cookies


## Details for the plugin

WPDoodlez can handle classic polls and doodle like appointment planning
If custom fields are named vote1...vote10, a poll is created, just displaying the vote summaries if custom fields are dates e.g name: 12.12.2020 value: ja
then a doodlez is created where visitors can set their name or shortcut and vote for all given event dates
User parameter /admin=1 to display alternate votes display (more features when logged in as admin)

Highlights
* link to WPdoodlez is public, but post can have password
* A WPdoodlez can be in a review and be published at given time
* A WPdoodlez can have own URL
* Poll users do not need to be valid logged in wordpress users
* Users with "delete published post" rights can delete votes
* Users shortname will be stored in a cookie for 30 days (user can change only his own vote, but on the same computer)
* Every custom field set in a WPdoodle is a possible answer
* The first value of the custom field will be displayed in the row as users answer
* The last row in the table contains total votes count
* shortcodes for poll stats and to embed a poll in any page or post or html widget


== Installation ==
To upload the plugin through WordPress, instead of FTP:
1. Upload the downloaded zip file on the 'Add New' plugins screen (see the 'Upload' tab) in your WordPress admin area and activate.
2. Goto Wordpress admin panel, settings, permalinks and update permalink structure!
After that you will see "WPDoodle" and "Questions" item in the menu on the left site. 

== WPdoodlez Usage as appointment planner (Terminplaner) ==
For colums Create Custom fields and enter Date values or date and time values in following formats:
  field name:	15.02.2022     or      15.02.22 16:00  <-- These field names must be unique!
  field value: X or JA or whatever you want as marker  <-- the values may all be the same
  
== WPdoodlez Usage as poll  (Umfrage) ==
For colums Create Custom fields and enter the following data
  field name:  vote1            <-- must be vote1 for 1st field, vote2 for 2nd field (case sensitive, must be lower case)
  field value: your option text <-- The answer/option you want to display (examples 'Yes', 'No', 'i want beer', 'i like it so much')
  

##  ------------------------------- Quizzz Module and shortcode -----------------------------  ##

Create a sequential quiz on WordPress with the Quizzz plugin. Use a shortcode random_question to display in posts/pages
You can create rich questions, with rich text, images, videos, audio, as you would in any other WordPress post, and let the user answer in plain text, and move on to the next question if they have answered correctly.
The answer conditions can be either 'exact match & case-sensitive', or can be phrase-matched (eg. the list of correct answers can be "xyz, abc, def", and if the user enters "abc", it's counted as the right answer.

### How to import questions in csv format: ###

Create a csv file with the following structure:
	// id; datum; charakter; land; titel; seitjahr; antwort; antwortb; antwortc; antwortd; zusatzinfo; kategorie
name the file "public_hist_quizfrage.csv" asnd upload it into your wordpress upload directory like: /wp-content/uploads
	Goto admin areas, open questions list and press the CSV Import Button on top
	present questions WILL BE DELETED and csv content imported as custom post type question
	You may assign a categories images category image if using the plugin. It will show the cpt category image
	Sample images are in the plugin subfolder quizkatbilder
If you name the file "public_hist_quizfrage_update.csv"	 questions in file will be added
Use the shortcode or link to custom post type "question" do display questions

The plugin also raises the following hooks:
quizz_level_updated: raised when the user's answer is considered correct and they're pushed to the next question
quizz_ended: raised when the list of questions comes to an end, and the user is sent to a designated page (eg. a congratulations page)

== Quiz Usage ==
1. Under Questions in the WordPress admin menu, click on Add New Question. 
2. Enter the question in the big post area. This can be plain text, images, or embedded multimedia. 
3. Enter the correct answer in the Answer field below the question field.
4. Choose whether you will accept only exact matches, or a part answer (eg. you enter a series of answers delimited by commas) is valid.
5. Select which question leads to the current question.
6. Select whether this is the final question of the series, and if it is, choose the Page which will be displayed when the player is done with the quiz. Eg. a thank you page, or a success page.


## --------------------------------------- Changelog ---------------------------------------------------- ##


=== 9.1.1.136 ===
hangman game added as javascript - much faster and slim and better canvas graphics
fixed behaviour of cake chart - default is on, but you can can set if to off by shortcode param.

=== 9.1.1.131 ===
hangman game option removed - as it was played rarely

=== 9.1.1.129 ===
add statistics to be displayed after answer how many people gave what answer (total and percent bar)
works without cookies, just stores the selected answer anonymously in options.

=== 9.1.1.127 ===
quizkatbilder moved to categories images plugin
lightbulb banner moved to root dir and removed quitkatbilder folder

=== 9.1.1.124 ===
random-question optimized, many functions removed. it is intentionally for use on homepage as an html-widget and displays cat image, origin, number, 
 image, other games links and a random question. click on the question title or image to answer question

=== 9.1.1.118 ===
more quizkat-images and quizfragen with images improved
image thumbnail now shown on random-question shortcode banner

=== 9.1.1.116 ===
csv import fix on empty file
new quizkatbilder created by bing AI with Dall-E and Clipcrop and paint.net

=== 9.1.1.114 ===
Import and export of picturelink added
Editor picturename e.g. ki-bild0001.jpg added too
place pictures in folder uploads/quizbilder and name them how you put in list

=== 9.1.1.113 ===
changed quizzertifikat banner by KI-Banner "Quizzertifikat"

=== 9.1.1.110 ===
quizz: Country and iso select box at new and edit field
quizz: Title "Quizfrage xxxx" with auto increment number added on new questions

=== 9.1.1.102 ===
WP 6.2.2 compatibility and PHP 8.2.x
Added quizfragen Export function in admin area to export Quizfragen and import on another site (without using csv template)

=== 9.1.1.94 ===
post_views_timestamp exception handeled

=== 9.1.1.93 ===
Quizzfragen aktualisiert

=== 9.1.1.91 ===
Quizzfragen aktualisiert
Quizz Adminbereich zeigt Land und Iso an
Übersetzungen

=== 9.1.1.72 ===
add templates for quizfragen using penguin or other themes. removed siedbar, set to middle

=== 9.1.1.70 ===
PHP 8.1 compatibility fixes

=== 9.1.1.60 ===
timestamp of booking and (last octet shortened) IP of user will be stored in database
poll will show last and first date of bookings
doodlez admin list and poll admin list will show each booking date and shortened ip

=== 9.1.1.43 ===
Fixes Cookie empty index warnings, when allowed initially

=== 9.1.1.40-42 ===
added "copy doodlez-shortcode" column in admin area
Styling and crossword and quizzz fixes and optimizations

=== 9.1.1.33 ===
add shortcode [wpdoodlez_sc id=xxxxx chart=0/1]. It can be called from all other posts or pages and displays just the content
  (with interactive fields) of the doodlez
WP 5.9 compatibility
optimized php code

=== 9.1.1.29 ===
5.8.3 compatibility and some styling optimized

=== 9.1.1.28 ===
added Crossword game to play from widget or by shortcode
set crossword to fullwidth size (primary)

=== 9.1.1.26 ===
Updated documentation
integrated ical download on doodlez when feed type icalfeed is present (comes from penguin plugin)

=== 9.1.1.25 ===
pressing csv import button behaviour in admin area changed:
if naming the update-file: public_histereignisse-update.csv and uploading it to uploads folder of your wordpress site, it will ADD questions
when named /public_histereignisse.csv' it WILL DELETE all present questions and replace them by import 

=== 9.1.1.24 ===
Cookies wrongscore and rightsccore will only be set if cookie hidecookiebannerx = 2

=== 9.1.1.19 ===
singe answer mask style changed
quizcategory images added. If an image is added via categories images plugin, it will be displayed on lists and on header in single view
lightbulb pic moved to quizkatbilder folder

=== 9.1.1.16 ===
display quizcategory (with  CPT taxonomy archive link) in shortcode and singular content

=== 9.1.1.15 ===
Add quizcategory (custom taxonomy only valid for CPT "Question" that can be imported as last column in the csv file (quizkat)
Show category in question if one exists
showing up categories in random question shortcode too

=== 9.1.1.14 ===
copy question to clipboard to transfer it to teams chat or other platforms
add /rss to url to get a custom post type feed on questions

=== 9.1.1.13 ===
add some more quizzz questions

=== 9.1.1.11 ===
Hangman option (user selectable) integrated when answer is between 5 and 14 characters. 

=== 9.1.1.10 ===
Quiz 40 new questions added and timer for answers set to 30 seconds. when timer expired question is scored as wrong

=== 9.1.1.9 ===
Quiz output boxed and colored tomato or green, added thumbsup/down, translations

=== 9.1.1.8 ===
Quiz: Schulnotensystem erweitert, Prozentgrenzen werden nun mit ausgegeben, ab 97%: Eins Plus 0.7

=== 9.1.1.7 ===
quiz styling inline CSS beautified

=== 9.1.1.6 ===
During a Wordpress session Quiz results will be counted. Nothing will be stored - it is a GDPR compliant counter.

=== 9.1.1.5 ===
Option to provide 4 answers added. The results are shown shuffled with radio control to select one
single answers have a help mask with vocals shown
styling of shortcode output and content

=== 9.1.1.4 ===
added german and german formal translations and text domain quizzz, rewrite of non translated strings as __('','quizz')
added shortcode [[random_question]] with optional parameters and defaults:
        'orderby'   => 'rand',
        'order'   => 'rand',
        'items'   => 1,

=== 9.1.1.3 ===
questions (and answers) can be imported from csv file now. Use button in admin area an place csv file 
with the following (utf-8, semicolon separated, crlf) in the wordpress upload dir before pressing button:
filename: public_histereignisse.csv
fields: // id;	datum;	charakter;	land;	titel;	seitjahr;	bemerkungen
fixed some bugs in editor, added an end of quiz page if no ending page redirect selected

=== 9.1.1.2 ===
31 March 2014:	Using the WordPress permalink function instead of creating the URL structure for redirects.

= 9.1.0.10.36 =
template penguin: header_image removed from page template cos its in header-image templat part already

= 9.1.0.10.35 =
compatible with WP 5.5.3
On Votings (using field names vote1...x) only one selection is allowed now, on appointment findings (doodlez) still more selections than one is allowed.

= 9.1.0.10.33 =
calendar fixes

= 9.1.0.10.32 =
display and variable declaration fixed, admin details only for polls, appointments will always be displayed detailed
month calendars displayed with colored event days (on doodlezz, not on polls)

= 9.1.0.10.31 =
Display answers on pie chart using votes. upper case display in details view

= 9.1.0.10.30 =
pagination for votes admin details (20 entry before page next)
pagination for doodlez set to 100 entries per page. Totals are calculated per page

= 9.1.0.10.29 =
Supports post formats now. To show the full content of the doodle on home page some coding must be done.

= 9.1.0.10.28 =
Added Tag Support to custom file type

= 9.1.0.10.27 =
bugfix when used with a theme that is not penguin
wpdoodlez now loads special template when penguin theme is present, else a dafault template will be loaded
fixed error when switching themes. docu menu did not work

= 9.1.0.10.26 =
doodlez are listed in the main loop on the home page now
admin features, Details link without url parameter clickable, show country/flag only for logged in users
language adaptions / translations

= 9.1.0.10.25 =
if chartscodes plugin is installed, the voters country/flag be displayed (got from filtered/shortened ip)

= 9.1.0.10.23 =
If custom fields are named vote1...vote10, a poll is created, just displaying the vote summaries
if custom fields are dates e.g  name: 12.12.2020    value: ja then a doodlez is created where visitors can set their name or shortcut and vote for all given event dates

= 9.1.0.10.21 =
own styling in template and theme integration for penguin
do not count custom data types set by various other plugins like twitter feed
add integration of chartscodes plugin to show pie charts of voting

= 9.1.13 =
Template updated to match current requirements
changed Custom file type to add post pic, category, tags, editor for Post Content, Archive
Adds Bar chart, when ChartsCodes Plugin is present
Tested with WP 5.4.2

= 1.0.1 = development before PBMod Fork
Fix for plugin activation. Sorry for the mess!

= 1.0.2 =
Fix registering rewrite rule in activation

= 1.0.3 = 
Translate word "Doodle" as "WPdoodle" because of trade mark collision

= 1.0.4 =
Bugfix: Load and execute javascript after loading jQuery -> external file
New: Load css from a file 
New: Overwrite plugins css with own css definistions in user.css (loading only if exists)

= 1.0.5 =
Bugfix: Loading js file
New: highlight your vote by using css class "myvote"

= 1.0.6 =
Error message by second voting try. (After deleting a vote cookies are still
in the browser of the person, which vote was deleted. She/he can not vote any more
and doesn't know why. The message in a javascrip alert fix this bad behaviour.)

= 1.0.7 = 1.0.8 =
Update latest installable version to 4.7 without changing anything but documentation

= 1.0.9 = 1.0.10 =
Update latest tag to 4.9.6
