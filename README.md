# WPdoodlez Forked Mod
plan and vote to find a common appointment date with participiants for an event. Works similar to Doodle(TM) for WordPress

## Why this fork?
the main project was not updated for years now. I needed some features and added them to the project.
Completed the german and german formal translations. Added method to use structure for polls (onle one answer)

## GDPR (DSGVO) notice
Participiants do net need to enter their full name (a nicname would do).
A cookie "wpdoodlez-hashofthevote" with the given nic name is stored on the local computer to
notice that one has voted already - so you should opt-in cookies and place a note on your GDPR statement.

Doodlez can be password protected (with builtin wordpress logic) to prevent others seeing the content
Archives, Post Content, Post-Thumbnail, Categories and Tags have been enabled to provide full integration like a normal post.


## Highlights
* WPDoodlez can handle classic polls and doodle like appointment planning
If custom fields are named vote1...vote10, a poll is created, just displaying the vote summaries<br><br>
if custom fields are dates e.g  name: 12.12.2020    value: ja<br>
then a doodlez is created where visitors can set their name or shortcut and vote for all given event dates<br>

User parameter /admin=1 to display alternate votes display (more features when logged in as admin)<br><br>

* link to WPdoodlez is public, but post can have password
* A WPdoodlez can be in a review and be published at given time
* A WPdoodlez can have own URL
* Poll users do not need to be valid logged in wordpress users
* Users with "delete published post" rights can delete votes
* Users shortname will be stored in a cookie for 30 days (user can change only his own vote, but on the same computer)
* Every custom field set in a WPdoodle is a possible answer
* The first value of the custom field will be displayed in the row as users answer
* The last row in the table contains total votes count


## Details
Based on Wordpress custom types. Rich text, media, comments and categories can 
be a part of every polling. Voting options comes from custom fields (in post 
editor under the text editing field) and the first value of every given custom 
field will be shown as user answer. (Multiple answers are possible)
For example: Custom field name is *Monday*, the value *I like mondays*. The 
column name will be "Monday" and every person voted on monday have in the row
unter *Monday* *I like mondays*. If not voted on Monday the cell will be empty.

Every person can vote once. Used name will be stored in a cookie for 30 days. 
Every person can change only own voting if the cookie is still in the browser.

Administrators and editors can delete every vote (but not the cookie on the 
browser).
