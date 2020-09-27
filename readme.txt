=== Plugin Name ===
Contributors: robert_kolatzek, PBMod
Tags: doodle, poll, question, meeting
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Version: 9.1.0.10.33
Stable tag: 9.1.0.10.33
Requires at least: 5.1
Tested up to: 5.5.1
Requires PHP: 7.2


== Description ==

Everyone knows [doodle](https://doodle.com) It's a cute plattform to poll, to find the best meeting date or place, to make a decision with many people.

With this plugin you can create very simple doodles in your wordpress installation. If choosing custom fields names: vote0...votexxx a survey poll is done and the
voters stored anonymized.

WPdoodlez are own post type and very similar to a post. A WPdoodle extends a post and uses custom fields to set possible answers.

* A link to WPdoodle is public but not published everywhere
* A WPdoodle can be in a review and be published at given time
* A WPdoodle can have own URL 
* Poll users must not be valid logged in wordpress users
* Users with "delete published post" rights can delete votes
* Users name will be stored in a cookie for 30 days (user can change only his own vote, but on the same computer)
* Every custom field set in a WPdoodle is a possible answer
* The first value of the custom field will be displayed in the row as users answer
* The last row in the table contains total votes count

== Installation ==

After install this plugin you will see "WPDoodle" item in the menu on the left site. 

== Changelog ==

= 9.1.0.10.32 =
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

= 1.0.1 =
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

= 1.0.7 =
= 1.0.8 =
Update latest installable version to 4.7 without changing anything but documentation

= 1.0.9 =
= 1.0.10 =
Update latest tag to 4.9.6

= 9.1.13 =
Template updated to match current requirements
changed Custom file type to add post pic, category, tags, editor for Post Content, Archive
Adds Bar chart, when ChartsCodes Plugin is present
Tested with WP 5.4.2
