if (document.getElementById("cwd-crossword")) {
    //---------------------------------//
    //   GLOBAL VARIABLES              //
    //---------------------------------//

    /* Start Options Page Variables */
    var bgColor = crossword_vars.cwdcw_bg_color;
    var borderColor = crossword_vars.cwdcw_border_color;
    var txtColor = crossword_vars.cwdcw_txt_color;
    var crossword_ansver_var = crossword_vars.cwdcw_ansver;
    var crossword_ansver_incorect = crossword_vars.cwdcw_ansver_incorect;
    var crossword_align_question = crossword_vars.cwdcw_align_question;
    var crossword_width_question = crossword_vars.cwdcw_question_width;
    var crossword_question_color = crossword_vars.cwdcw_question_txt_color;
    var counter_color = crossword_vars.cwdcw_counter_color;

    /* End Options Page Variables */

    /* Start Optional vars */
    var optional_bgColor = optional_crossword_vars.cwdcw_optional_bg_color;
    var optional_borderColor = optional_crossword_vars.cwdcw_optional_border_color;
    var optional_textColor = optional_crossword_vars.cwdcw_optional_text_color;
    var optional_questionColor = optional_crossword_vars.cwdcw_optional_question_color;
    var optional_counterColor = optional_crossword_vars.cwdcw_optional_counter_color;
    var correct_ansver_var = optional_crossword_vars.cwdcw_correct_ansver;
    var incorrect_ansver = optional_crossword_vars.cwdcw_incorrect_ansver;
    var crossword_ansver = 'yes';

    /* End Optional vars */

    var board, wordArr, wordBank, wordsActive, mode;

    var Bounds = {
        top: 0, right: 0, bottom: 0, left: 0,

        Update: function (x, y) {
            this.top = Math.min(y, this.top);
            this.right = Math.max(x, this.right);
            this.bottom = Math.max(y, this.bottom);
            this.left = Math.min(x, this.left);
        },

        Clean: function () {
            this.top = 999;
            this.right = 0;
            this.bottom = 0;
            this.left = 999;
        }
    };

    //---------------------------------//
    //   MAIN                          //
    //---------------------------------//

    function Play() {
        var letterArr = document.getElementsByClassName('cwd-letter');

        for (var i = 0; i < letterArr.length; i++) {
			letterArr[i].innerHTML = "<input autocomplete='offi' " +
				"class='cwd-char' " +
				"type='text' " +
				"maxlength='1' " +
				"style='" +
				"color:#000;" + "'" +
				"></input>";
        }

        mode = 0;
        ToggleInputBoxes(false);

        jQuery('.cwd-square.cwd-letter.cwd-first').each(function (index, value) {
            var r = jQuery(this).attr('data-c');

            jQuery(this).append("<div class='cwd-counter'>" + r.replace(' , ', ',').replace(/ /g,'') + "</div>");
        });

    }

    function Create() {
        if (mode === 0) {
            ToggleInputBoxes(true);
            document.getElementById("cwd-crossword").innerHTML = BoardToHtml(" ");
            mode = 1;
        }
        else {
            GetWordsFromInput();

            for (var i = 0, isSuccess = false; i < 10 && !isSuccess; i++) {
                CleanVars();
                isSuccess = PopulateBoard();
            }

            document.getElementById("cwd-crossword").innerHTML =
                (isSuccess) ? BoardToHtml(" ") : "Failed to find crossword.";
        }
    }

    function ToggleInputBoxes(active) {
        var w = document.getElementsByClassName('cwd-word'),
            d = document.getElementsByClassName('cwd-clue');

        for (var i = 0; i < w.length; i++) {
            if (active === true) {
                RemoveClass(w[i], 'cwd-hide');
                RemoveClass(d[i], 'cwd-clueReadOnly');
                d[i].disabled = '';
            }
            else {
                AddClass(w[i], 'cwd-hide');
                AddClass(d[i], 'cwd-clueReadOnly');
                d[i].disabled = 'readonly';
            }
        }
    }

    function GetWordsFromInput() {
        wordArr = [];
        for (var i = 0, val, w = document.getElementsByClassName("cwd-word"); i < w.length; i++) {
            val = w[i].value.toUpperCase();
            if (val !== null && val.length > 1) {
                wordArr.push(val);
            }
        }
    }

    function CleanVars() {
        Bounds.Clean();
        wordBank = [];
        wordsActive = [];
        board = [];

        for (var i = 0; i < 32; i++) {
            board.push([]);
            for (var j = 0; j < 32; j++) {
                board[i].push(null);
            }
        }
    }

    function PopulateBoard() {
        PrepareBoard();

        for (var i = 0, isOk = true, len = wordBank.length; i < len && isOk; i++) {
            isOk = AddWordToBoard();
        }
        return isOk;
    }

    function PrepareBoard() {
        wordBank = [];

        for (var i = 0, len = wordArr.length; i < len; i++) {
            wordBank.push(new WordObj(wordArr[i]));
        }

        for (i = 0; i < wordBank.length; i++) {
            for (var j = 0, wA = wordBank[i]; j < wA.char.length; j++) {
                for (var k = 0, cA = wA.char[j]; k < wordBank.length; k++) {
                    for (var l = 0, wB = wordBank[k]; k !== i && l < wB.char.length; l++) {
                        wA.totalMatches += (cA === wB.char[l]) ? 1 : 0;
                    }
                }
            }
        }
    }

    // TODO: Clean this guy up
    function AddWordToBoard() {
        var i, len, curIndex, curWord, curChar, curMatch, testWord, testChar,
            minMatchDiff = 9999, curMatchDiff;

        if (wordsActive.length < 1) {
            curIndex = 0;
            for (i = 0, len = wordBank.length; i < len; i++) {
                if (wordBank[i].totalMatches < wordBank[curIndex].totalMatches) {
                    curIndex = i;
                }
            }
            wordBank[curIndex].successfulMatches = [{x: 12, y: 12, dir: 0}];
        }
        else {
            curIndex = -1;

            for (i = 0, len = wordBank.length; i < len; i++) {
                curWord = wordBank[i];
                curWord.effectiveMatches = 0;
                curWord.successfulMatches = [];
                for (var j = 0, lenJ = curWord.char.length; j < lenJ; j++) {
                    curChar = curWord.char[j];
                    for (var k = 0, lenK = wordsActive.length; k < lenK; k++) {
                        testWord = wordsActive[k];
                        for (var l = 0, lenL = testWord.char.length; l < lenL; l++) {
                            testChar = testWord.char[l];
                            if (curChar === testChar) {
                                curWord.effectiveMatches++;

                                var curCross = {x: testWord.x, y: testWord.y, dir: 0};
                                if (testWord.dir === 0) {
                                    curCross.dir = 1;
                                    curCross.x += l;
                                    curCross.y -= j;
                                }
                                else {
                                    curCross.dir = 0;
                                    curCross.y += l;
                                    curCross.x -= j;
                                }

                                var isMatch = true;

                                for (var m = -1, lenM = curWord.char.length + 1; m < lenM; m++) {
                                    var crossVal = [];
                                    if (m !== j) {
                                        if (curCross.dir === 0) {
                                            var xIndex = curCross.x + m;

                                            if (xIndex < 0 || xIndex >= board.length) {
                                                isMatch = false;
                                                break;
                                            }

                                            crossVal.push(board[xIndex][curCross.y]);
                                            crossVal.push(board[xIndex][curCross.y + 1]);
                                            crossVal.push(board[xIndex][curCross.y - 1]);
                                        }
                                        else {
                                            var yIndex = curCross.y + m;

                                            if (yIndex < 0 || yIndex > board[curCross.x].length) {
                                                isMatch = false;
                                                break;
                                            }

                                            crossVal.push(board[curCross.x][yIndex]);
                                            crossVal.push(board[curCross.x + 1][yIndex]);
                                            crossVal.push(board[curCross.x - 1][yIndex]);
                                        }

                                        if (m > -1 && m < lenM - 1) {
                                            if (crossVal[0] !== curWord.char[m]) {
                                                if (crossVal[0] !== null) {
                                                    isMatch = false;
                                                    break;
                                                }
                                                else if (crossVal[1] !== null) {
                                                    isMatch = false;
                                                    break;
                                                }
                                                else if (crossVal[2] !== null) {
                                                    isMatch = false;
                                                    break;
                                                }
                                            }
                                        }
                                        else if (crossVal[0] !== null) {
                                            isMatch = false;
                                            break;
                                        }
                                    }
                                }

                                if (isMatch === true) {
                                    curWord.successfulMatches.push(curCross);
                                }
                            }
                        }
                    }
                }

                curMatchDiff = curWord.totalMatches - curWord.effectiveMatches;

                if (curMatchDiff < minMatchDiff && curWord.successfulMatches.length > 0) {
                    curMatchDiff = minMatchDiff;
                    curIndex = i;
                }
                else if (curMatchDiff <= 0) {
                    return false;
                }
            }
        }

        if (curIndex === -1) {
            return false;
        }

        var spliced = wordBank.splice(curIndex, 1);
        wordsActive.push(spliced[0]);

        var pushIndex = wordsActive.length - 1,
            rand = Math.random(),
            matchArr = wordsActive[pushIndex].successfulMatches,
            matchIndex = Math.floor(rand * matchArr.length),
            matchData = matchArr[matchIndex];

        wordsActive[pushIndex].x = matchData.x;
        wordsActive[pushIndex].y = matchData.y;
        wordsActive[pushIndex].dir = matchData.dir;

        for (i = 0, len = wordsActive[pushIndex].char.length; i < len; i++) {
            var xIndex = matchData.x,
                yIndex = matchData.y;

            if (matchData.dir === 0) {
                xIndex += i;
                board[xIndex][yIndex] = wordsActive[pushIndex].char[i];
            }
            else {
                yIndex += i;
                board[xIndex][yIndex] = wordsActive[pushIndex].char[i];
            }

            Bounds.Update(xIndex, yIndex);
        }

        return true;
    }

    function BoardToHtml(blank) {
        for (var i = Bounds.top - 1, str = ""; i < Bounds.bottom + 2; i++) {
            str += "<div class='cwd-row'>";
            for (var j = Bounds.left - 1; j < Bounds.right + 2; j++) {
                str += BoardCharToElement(board[j][i], j, i);
            }
            str += "</div>";
        }

        return str;
    }

    function BoardCharToElement(c, j, i) {
        var s = '';
        var co = '';
        var el = '';
        var counter = '';
        var arr1 = [];
        var arr1_el = [];
        for (var m = 0; m < wordsActive.length; m++) {

            if (wordsActive[m].x == j && wordsActive[m].y == i) {
                s = 'cwd-first';
                el = m;
                arr1_el.push(el);

                for (var g = 0, val, w = document.getElementsByClassName("cwd-word"); g < w.length; g++) {
                    val = w[g].value.toUpperCase();
                    if (val == wordsActive[m].string) {
                        co = parseInt(w[g].dataset['counter']);
                        arr1.push(co);
                    }

                }

            }
        }
        arr1.reverse();
        var arr = (c) ? ['cwd-square', 'cwd-letter ' + s] : ['cwd-square'];
        var arr_x = (c) ? [j] : [''];
        var arr_y = (c) ? [i] : [''];
        var arr_el = (c) ? [arr1_el.join(',')] : [''];
        return EleStr('div', [{a: 'class', v: arr}, {a: 'data-c', v: arr1.join(',')}, {a: 'data-x', v: arr_x}, {
            a: 'data-y',
            v: arr_y
        }, {a: 'data-el', v: arr_el}], c);
    }

    //---------------------------------//
    //   OBJECT DEFINITIONS            //
    //---------------------------------//

    function WordObj(stringValue) {
        this.string = stringValue;
        this.char = stringValue.split("");
        this.totalMatches = 0;
        this.effectiveMatches = 0;
        this.successfulMatches = [];
    }

    //---------------------------------//
    //   EVENTS                        //
    //---------------------------------//

    function RegisterEvents() {
        document.getElementById("cwd-crossword").onfocus = function () {
            return false;
        };
        /*document.getElementById("btnCreate").addEventListener('click',Create,false);
		document.getElementById("btnPlay").addEventListener('click',Play,false);*/
    }

    RegisterEvents();

    //---------------------------------//
    //   HELPER FUNCTIONS              //
    //---------------------------------//

    function EleStr(e, c, h) {
        h = (h) ? h : "";
        for (var i = 0, s = "<" + e + " "; i < c.length; i++) {
            s += c[i].a + "='" + ArrayToString(c[i].v, " ") + "' ";
        }
        return (s + ">" + h + "</" + e + ">");
    }

    function ArrayToString(a, s) {
        if (a === null || a.length < 1) return "";
        if (s === null) s = ",";
        for (var r = a[0], i = 1; i < a.length; i++) {
            r += s + a[i];
        }
        return r;
    }

    function AddClass(ele, classStr) {
        ele.className = ele.className.replaceAll(' ' + classStr, '') + ' ' + classStr;
    }

    function RemoveClass(ele, classStr) {
        ele.className = ele.className.replaceAll(' ' + classStr, '');
    }

    function ToggleClass(ele, classStr) {
        var str = ele.className.replaceAll(' ' + classStr, '');
        ele.className = (str.length === ele.className.length) ? str + ' ' + classStr : str;
    }

    String.prototype.replaceAll = function (replaceThis, withThis) {
        var re = new RegExp(replaceThis, "g");
        return this.replace(re, withThis);
    };

    //---------------------------------//
    //   INITIAL LOAD                  //
    //---------------------------------//

    Create();
    Play();
    jQuery(document).ready(function () {
        jQuery(".cwd-square.cwd-letter .cwd-char").keyup(function (event) {
            jQuery('.cwd-square.cwd-letter').removeClass('cwd-correct');
            jQuery('.cwd-square.cwd-letter').removeClass('cwd-error');
            jQuery('.cwd-square.cwd-letter.cwd-first').each(function (i, elem) {
                var el_i = jQuery(elem)[0].dataset.el;
                var arr_el = el_i.split(',');
                for (var v = 0; v < arr_el.length; v++) {
                    var el = arr_el[v];
                    var arr = wordsActive[el];
                    var arr_char = arr.char;
                    var dir = arr.dir;
                    var x = arr.x;
                    var y = arr.y;
                    var c = jQuery(elem)[0].dataset.c;
                    var df = '';
                    var flag_empty = '';
                    var flag_correct = true;

                    if (dir == 0) {
                        df = x;
                    }
                    else {

                        df = y;
                    }

                    if (dir == 0) {
                        var index = 0;
                        for (var z = df; z < arr_char.length + df; z++) {
                            var it = jQuery('.cwd-square.cwd-letter[data-x="' + z + '"][data-y="' + y + '"] .cwd-char').val().toUpperCase();
                            if (it) {
                                if (it != arr_char[index]) {
                                    flag_correct = false;
                                }
                            }
                            else {
                                flag_empty = 'empty';
                            }
                            index++;
                        }
                    }
                    else {
                        var index = 0;
                        for (var z = df; z < arr_char.length + df; z++) {
                            var it = jQuery('.cwd-square.cwd-letter[data-y="' + z + '"][data-x="' + x + '"] .cwd-char').val().toUpperCase();
                            if (it) {
                                if (it != arr_char[index]) {
                                    flag_correct = false;
                                }
                            }
                            else {
                                flag_empty = 'empty';
                            }
                            index++;
                        }
                    }
                        if (flag_empty != 'empty' && flag_correct == true) {

                            if (dir == 0 && crossword_ansver == 'yes') {

                                //gorizontale
                                /* added check if crossword_ansver value 'yes' */
                                for (var z = df; z < arr_char.length + df; z++) {

                                    if (crossword_ansver_var == 'yes') {
                                        jQuery('.cwd-square.cwd-letter[data-x="' + z + '"][data-y="' + y + '"] ').addClass('cwd-correct').css({"background-color": "#b3ffb4"});
                                    } else {
                                        jQuery('.cwd-square.cwd-letter[data-x="' + z + '"][data-y="' + y + '"] ').addClass('cwd-correct').css({"background-color": "none"});
                                    }
                                }
                            }
                            else { //verticale
                                for (var z = df; z < arr_char.length + df; z++) {

                                    if (crossword_ansver_var == 'yes') {
                                        jQuery('.cwd-square.cwd-letter[data-y="' + z + '"][data-x="' + x + '"] ').addClass('cwd-correct').css({"background-color": "#b3ffb4"});
                                    } else {
                                        jQuery('.cwd-square.cwd-letter[data-y="' + z + '"][data-x="' + x + '"] ').addClass('cwd-correct').css({"background-color": "none"});
                                    }
                                }
                            }
                        }
                        /* added check if crossword_ansver value 'no' */
                        if (crossword_ansver == 'no') {
                            jQuery('.cwd-square.cwd-letter').removeClass('cwd-correct');
                        }

                        if (flag_empty != 'empty' && flag_correct == false) {

                            if (dir == 0 && crossword_ansver_incorect == 'yes') {

                                /* added check if crossword_ansver_incorect value 'yes' */
                                for (var z = df; z < arr_char.length + df; z++) {
                                    jQuery('.cwd-square.cwd-letter[data-x="' + z + '"][data-y="' + y + '"] ').addClass('cwd-error');
                                }
                            }
                            else {
                                for (var z = df; z < arr_char.length + df; z++) {
                                    jQuery('.cwd-square.cwd-letter[data-y="' + z + '"][data-x="' + x + '"] ').addClass('cwd-error');
                                }
                            }
                        }
                        /* added check if crossword_ansver_incorect value 'no' */
                        if (crossword_ansver_incorect == 'no') {
                            jQuery('.cwd-square.cwd-letter').removeClass('cwd-error');
                        }
                }

            });

            if (jQuery(".cwd-square.cwd-letter").length == jQuery(".cwd-square.cwd-letter.cwd-correct").length) {

                jQuery('#overlay').fadeIn(400,
                    function () {
                        jQuery('#modal_form_crossword')
                            .css('display', 'block')
                            .animate({opacity: 1, top: '50%'}, 200);
                    });

            }
        });

        /* Added border-color and background color for crosswords squares */
            jQuery('.cwd-square.cwd-letter').css({
                'border-color': '#' + borderColor,
                'background-color': '#' + bgColor
            });

            jQuery('.cwd-crossword-questions .cwd-line .cwd-clue').css('color', '#' + crossword_question_color);
            jQuery('.cwd-crossword .cwd-counter').css('color', '#' + counter_color);

        // Close modal window ---
        jQuery('#modal_close, #overlay').click(function () {

            var src = jQuery('#modal_form_crossword iframe').attr('src');
            jQuery('#modal_form_crossword iframe').attr('src', '');
            jQuery('#modal_form_crossword iframe').attr('src', src);

            jQuery('#modal_form_crossword')
                .animate({opacity: 0, top: '45%'}, 200,
                    function () {
                        jQuery(this).css('display', 'none');
                        jQuery('#overlay').fadeOut(400);
                    }
                );
        });
    });

    //---------- Move, Delete, Update Chars in Fields of Crossword ----------//

    jQuery(document).ready(function () {

        lastMoveDirection = null;

        // Change cursor
        jQuery('.cwd-char').hover(function () {

            if (jQuery(this).val() == '') {
                jQuery(this).css({'cursor': 'text'});
            } else {
                jQuery(this).css({'cursor': 'pointer'});
            }
        }, function () {
        });

        // Selected char
        jQuery('.cwd-char').on('click touchstart', function () {
            this.select();
            this.setSelectionRange(0, 99999);
        });

        // Delete & move arrow
        jQuery('.cwd-char').on('keydown', function (e) {

            var datax = parseInt(jQuery(this).parent().attr('data-x'));
            var datay = parseInt(jQuery(this).parent().attr('data-y'));

            // Delete value
            if (e.which == 46 || e.which == 8) {

                e.preventDefault();
                this.value = '';

                // Move X
                if (jQuery(this).parent('div.cwd-letter').next('div.cwd-letter').length > 0) {
                    if (jQuery(window).width() > 769) {
                        jQuery(this).parent('div.cwd-letter').next('div.cwd-letter').find('input').focus();
                    }
                }
                // Move Y
                else {
                    var x = jQuery(this).parent('div.cwd-letter').attr('data-x');
                    if (jQuery(window).width() > 769) {
                        jQuery(this).parent('div.cwd-letter').parent('div.cwd-row').next('div.cwd-row').find('[data-x="' + x + '"]').find('input').focus();
                    }
                }
            }
            // Left arrow
            if (e.which == 37) {
                datax--;
                jQuery(this).parent().siblings('[data-x = "' + datax + '"][data-y = "' + datay + '"]').find('input').focus();
            }
            // Right arrow
            if (e.which == 39) {
                datax++;
                jQuery(this).parent().siblings('[data-x = "' + datax + '"][data-y = "' + datay + '"]').find('input').focus();
            }
            // Up arrow
            if (e.which == 38) {
                datay--;
                jQuery(this).parent().parent().prev().find('[data-x = "' + datax + '"][data-y = "' + datay + '"]').find('input').focus();
            }
            // Down arrow
            if (e.which == 40) {
                datay++;
                jQuery(this).parent().parent().next().find('[data-x = "' + datax + '"][data-y = "' + datay + '"]').find('input').focus();
            }
        });

        // Move cursor when print user

        function GetIEVersion() {
            var sAgent = window.navigator.userAgent;
            var Idx = sAgent.indexOf("MSIE");

            // If IE, return version number.
            if (Idx > 0)
                return parseInt(sAgent.substring(Idx + 5, sAgent.indexOf(".", Idx)));

            // If IE 11 then look for Updated user agent string.

            else if (!!navigator.userAgent.match(/Trident\/7\./) || navigator.userAgent.match(/ Edge\//) )
                return 11;

            else
                return 0; //It is not IE
        }

        if (jQuery(window).width() < 769) {

            var elems = document.getElementById('cwd-crossword').childNodes;

            for (var i = 0; i < elems.length; i++) {

                var div = elems[i].childNodes;

                for (var j = 0; j < div.length; j++) {

                    var div_ch = div[j].childNodes;

                    for (var k = 0; k < div_ch.length; k++) {

                        if (div_ch[k].nodeName == "INPUT") {

                            div_ch[k].addEventListener('input', function (e) {

                                e.preventDefault();

                                // Move X
                                if (lastMoveDirection == 'x' && this.parentNode.nextSibling.classList.contains('cwd-letter')) {
                                    lastMoveDirection = 'x';
                                    this.parentNode.nextSibling.querySelector(".cwd-char").focus();
                                    this.parentNode.nextSibling.querySelector(".cwd-char").setSelectionRange(0, 99999);
                                }
                                // Move Y
                                else {
                                    lastMoveDirection = 'y';
                                    var x = this.parentNode.getAttribute('data-x');


                                    if (jQuery(this).parent('div.cwd-letter').parent('div.cwd-row').next('div.cwd-row').find('[data-x="' + x + '"]').find('input').length > 0) {

                                        var range = jQuery(this).parent('div.cwd-letter').parent('div.cwd-row').next('div.cwd-row').find('[data-x="' + x + '"]').find('input');
                                        range.focus();
                                        range.get(0).setSelectionRange(0, 99999);

                                    } else {

                                        lastMoveDirection = 'x';
                                        this.parentNode.nextSibling.querySelector(".cwd-char").focus();
                                        this.parentNode.nextSibling.querySelector(".cwd-char").setSelectionRange(0, 99999);

                                    }

                                }
                            }, false);
                        }
                    }
                }
            }
        }
        // Desktop
        else {
            if (GetIEVersion() > 0) {
                jQuery('.cwd-char').on('keyup', function (e) {

                    var x = jQuery(this).parent('div.cwd-letter').attr('data-x');

                    // Change data in input field
                    if (window.getSelection && window.getSelection().toString() != '') {
                        if (lastMoveDirection == 'x' && jQuery(this).parent('div.cwd-letter').next('div.cwd-letter').length > 0) {
                            lastMoveDirection = 'x';
                            jQuery(this).val(e.char).parent('div.cwd-letter').next('div.cwd-letter').find('input').focus().select();
                        } else {
                            if (jQuery(this).val(e.char).parent('div.cwd-letter').parent('div.cwd-row').next('div.cwd-row').find('[data-x="' + x + '"]').find('input').length > 0) {
                                lastMoveDirection = 'y';
                                jQuery(this).val(e.char).parent('div.cwd-letter').parent('div.cwd-row').next('div.cwd-row').find('[data-x="' + x + '"]').find('input').focus().select();
                            }
                            else {
                                lastMoveDirection = 'x';
                                jQuery(this).val(e.char).parent('div.cwd-letter').next('div.cwd-letter').find('input').focus().select();
                            }
                        }
                    }
                    else {
                        // Move X
                        if (lastMoveDirection == 'x' && jQuery(this).parent('div.cwd-letter').next('div.cwd-letter').length > 0) {
                            lastMoveDirection = 'x';
                            jQuery(this).parent('div.cwd-letter').next('div.cwd-letter').find('input').focus().select();
                        }
                        // Move Y
                        else {
                            if (jQuery(this).parent('div.cwd-letter').parent('div.cwd-row').next('div.cwd-row').find('[data-x="' + x + '"]').find('input').length > 0) {
                                lastMoveDirection = 'y';
                                jQuery(this).parent('div.cwd-letter').parent('div.cwd-row').next('div.cwd-row').find('[data-x="' + x + '"]').find('input').focus().select();
                            }
                            else {
                                lastMoveDirection = 'x';
                                jQuery(this).parent('div.cwd-letter').next('div.cwd-letter').find('input').focus().select();
                            }
                        }
                    }
                });

            } else {
                jQuery('.cwd-char').on('keypress', function (e) {

                    var x = jQuery(this).parent('div.cwd-letter').attr('data-x');

                    // Change data in input field
                    if (window.getSelection && window.getSelection().toString() != '') {
                        if (lastMoveDirection == 'x' && jQuery(this).parent('div.cwd-letter').next('div.cwd-letter').length > 0) {
                            lastMoveDirection = 'x';
                            jQuery(this).val(e.char).parent('div.cwd-letter').next('div.cwd-letter').find('input').focus().select();
                        } else {
                            if (jQuery(this).val(e.char).parent('div.cwd-letter').parent('div.cwd-row').next('div.cwd-row').find('[data-x="' + x + '"]').find('input').length > 0) {
                                lastMoveDirection = 'y';
                                jQuery(this).val(e.char).parent('div.cwd-letter').parent('div.cwd-row').next('div.cwd-row').find('[data-x="' + x + '"]').find('input').focus().select();
                            }
                            else {
                                lastMoveDirection = 'x';
                                jQuery(this).val(e.char).parent('div.cwd-letter').next('div.cwd-letter').find('input').focus().select();
                            }
                        }
                    }
                    else {
                        // Move X
                        if (lastMoveDirection == 'x' && jQuery(this).parent('div.cwd-letter').next('div.cwd-letter').length > 0) {
                            lastMoveDirection = 'x';
                            jQuery(this).parent('div.cwd-letter').next('div.cwd-letter').find('input').focus().select();
                        }
                        // Move Y
                        else {
                            if (jQuery(this).parent('div.cwd-letter').parent('div.cwd-row').next('div.cwd-row').find('[data-x="' + x + '"]').find('input').length > 0) {
                                lastMoveDirection = 'y';
                                jQuery(this).parent('div.cwd-letter').parent('div.cwd-row').next('div.cwd-row').find('[data-x="' + x + '"]').find('input').focus().select();
                            }
                            else {
                                lastMoveDirection = 'x';
                                jQuery(this).parent('div.cwd-letter').next('div.cwd-letter').find('input').focus().select();

                            }

                        }
                    }
                });
            }
        }

        // Focus input counter
        jQuery('.cwd-letter').hover(
            function () {
                jQuery(this).find('.cwd-counter').hide()
            },
            function () {
                jQuery(this).find('.cwd-counter').show()
            }
        );

    });
}

//--- End block -