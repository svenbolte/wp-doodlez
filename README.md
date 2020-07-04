# WPdoodlez Forked Mod
plan and vote to find a common appointment date with participiants for an event. Works similar to Doodle(TM) for WordPress

## Why this fork?
the main project was not updated for years now. I needed some features and added them to the project.
Completed the german and german formal translations.

## GDPR (DSGVO) notice
Participiants do net need to enter their full name (a nicname would do).
A cookie "wpdoodlez-hashofthevote" with the given nic name is stored on the local computer to
notice that one has voted already - so you should opt-in cookies and place a note on your GDPR statement.

Doodlez can be password protected (with builtin wordpress logic) to prevent others seeing the content
Archives, Post Content, Post-Thumbnail, Categories and Tags have been enabled to provide full integration like a normal post.

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
