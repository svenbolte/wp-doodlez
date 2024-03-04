# WPdoodlez, Polls, Quizz, Crossword and Hangman

### WPDoodlez
plan and vote to find a common appointment date with participiants for an event. Works similar to famous Doodle(TM) website.
It adds a custom post type "wpdoodlez".

You can just use the custom posts for polls and appointments or place the poll in an existing post or page using the shortcode:

Shortcode: ´´´[wpdoodlez_sc id=post-ID chart=true]´´´
 set post ID to integrate Doodlez or Poll in other pages, set chart to false if you do not want pie graph
 set chart to fals to omit showing cake chart (requires chartscodes plugin)

### Poll
uses same technology and custom post type like WPDoodlez
create classic polls to let visitors vote about a question

Shortcode: ´´´[wpdoodlez_stats id=22817]´´´ show stats of all polls in a list on a page or post
	id = 0  // or omit id parameter - shows stats of all polls and appointments, set other id to specify the output
Notice: You may use all shortcodes only once per page or post.

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
