# WPdoodlez
Doodle like finding scheduling and polling for WordPress

Based on Wordpress custom types. Trich text, media, comments and categories can 
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
