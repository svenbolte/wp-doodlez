### Plugin Name: WPDoodlez and Quizzz ###
Contributors: robert_kolatzek, PBMod
Tags: doodle, poll, question, meeting, vote
Text Domain: WPDoodlez
Domain Path: /lang/
Version: 9.1.1.23
Stable tag: 9.1.1.23
Requires at least: 5.1
Tested up to: 5.8.2
Requires PHP: 7.4

## Description##

QUIZZZ: Create a sequential quiz on WordPress with the Quizzz plugin. Use a shortcode random_question to display in posts/pages

WPDOODLEZ: Everyone knows [doodle](https://doodle.com) It is a plattform to poll, to find the best meeting date or place, to make a decision with many people.
With this plugin you can create very simple doodles in your wordpress installation. If choosing custom fields names: vote0...votexxx a survey poll is done and the
voters stored anonymized. WPdoodlez are own post type and very similar to a post. A WPdoodle extends a post and uses custom fields to set possible answers.

* can handle polls and doodlez, depending on what field names are set: vote1...x results in a poll, other field names like dates result in doodlez
* link to WPdoodle is public, searchable and found in the archives
* can be in a review and be published at given time
* can have own URL 
* password on post protects access if wanted
* voters do not need to be valid logged in wordpress users
* doodlez: Users with "delete published post" rights can delete votes
* doodlez: Users name will be stored in a cookie for 30 days (user can change only his own vote, but on the same computer)
* Every custom field set in a WPdoodle is a possible answer
* The first value of the custom field will be displayed in the row as users answer
* The last row in the table contains total votes count

== Installation ==

To upload the plugin through WordPress, instead of FTP:
1. Upload the downloaded zip file on the 'Add New' plugins screen (see the 'Upload' tab) in your WordPress admin area and activate.
After install this plugin you will see "WPDoodle" and "Questions" item in the menu on the left site. 

##  Quizzz Module and shortcode  ##

Create a sequential quiz on WordPress with the Quizzz plugin. Use a shortcode random_question to display in posts/pages
You can create rich questions, with rich text, images, videos, audio, as you would in any other WordPress post, and let the user answer in plain text, and move on to the next question if they've answered correctly.
The answer conditions can be either 'exact match & case-sensitive', or can be phrase-matched (eg. the list of correct answers can be "xyz, abc, def", and if the user enters "abc", it's counted as the right answer.

Use the shortcode or link to custom post type "question" do display questions

The plugin also raises the following hooks:
quizz_level_updated: raised when the user's answer is considered correct and they're pushed to the next question
quizz_ended: raised when the list of questions comes to an end, and the user is sent to a designated page (eg. a congratulations page)


== Usage ==

1. Under Questions in the WordPress admin menu, click on Add New Question. 
2. Enter the question in the big post area. This can be plain text, images, or embedded multimedia. 
3. Enter the correct answer in the Answer field below the question field.
4. Choose whether you will accept only exact matches, or a part answer (eg. you enter a series of answers delimited by commas) is valid.
5. Select which question leads to the current question.
6. Select whether this is the final question of the series, and if it is, choose the Page which will be displayed when the player is done with the quiz. Eg. a thank you page, or a success page.


== Changelog ==

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
