<?php
/**
Plugin Name: WP Doodlez
Plugin URI: https://github.com/svenbolte/WPdoodlez
Author URI: https://github.com/svenbolte
Description: plan appointments, query polls and place a quiz or a crossword game on your wordpress site (with csv import for questions)
Contributors: Robert Kolatzek, PBMod, others
License: GPL v3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: WPdoodlez
Domain Path: /lang/
Author: PBMod
Version: 9.1.1.33
Stable tag: 9.1.1.33
Requires at least: 5.1
Tested up to: 5.9
Requires PHP: 8.0
*/

if (!defined('ABSPATH')) { exit; }
if (!defined('WPINC')) { die; }

// Load plugin textdomain.
function WPdoodlez_load_textdomain() {
  load_plugin_textdomain( 'WPdoodlez', false, plugin_basename( dirname( __FILE__ ) ) . '/lang' ); 
}
add_action( 'plugins_loaded', 'WPdoodlez_load_textdomain' );

/**
 * Register own template for doodles
 * @global post $post
 * @param string $single_template
 * @return string
 */
function wpdoodlez_template( $single_template ) {
    global $post;
	$wpxtheme = wp_get_theme(); // gets the current theme
	if ( 'Penguin' == $wpxtheme->name || 'Penguin' == $wpxtheme->parent_theme ) { $xpenguin = true;} else { $xpenguin=false; }
    if ( $post->post_type == 'wpdoodle' ) {
        if ($xpenguin) { $single_template = dirname( __FILE__ ) . '/wpdoodle-template-penguin.php';	} else {
			$single_template = dirname( __FILE__ ) . '/wpdoodle-template.php';
		}
    }
    return $single_template;
}
add_filter( 'single_template', 'wpdoodlez_template' );

// IP-Adresse des Users bekommen
function wd_get_the_user_ip() {
	if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
		//check ip from share internet
		$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		//to check ip is pass from proxy
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
		$ip = $_SERVER['REMOTE_ADDR'];
	}
	// letzte Stelle der IP anonymisieren (0 setzen)	
	$ip = long2ip(ip2long($ip) & 0xFFFFFF00);
	return apply_filters( 'wpb_get_ip', $ip );
}

// wpdoodlez_sc(ID) - Call WPDoodles complete Content from another page or post as a shortcode
function wpdoodlez_sc_func($atts) {
	wp_enqueue_script( "WPdoodlez", plugins_url( 'WPdoodlez.js', __FILE__ ), array('jquery'), null, true);
	wp_enqueue_style( "WPdoodlez", plugins_url( 'WPdoodlez.css', __FILE__ ), array(), null, 'all');
	global $post;
	$args = shortcode_atts(array( 'id' => 0, 'chart' => true ), $atts);
	$output ='';
	$qargs = array(
		'p'         => $args['id'],
		'post_type' => array('wpdoodle'),
		'post_status' => 'publish',
		'posts_per_page' => 1
	);
	$query1 = new WP_Query( $qargs );
	if ( $query1->have_posts() ) {
		while ( $query1->have_posts() ) {
			$query1->the_post();
			$output .= get_doodlez_content($args['chart']);
			$output .= '<script>var wpdoodle_ajaxurl = "' . admin_url( 'admin-ajax.php', is_ssl() ? 'https' : 'http' ).'";'; 
			$output .= 'var wpdoodle = "'. md5( AUTH_KEY . get_the_ID() ). '";</script>';
		}
		wp_reset_postdata();
	}
	return $output;
}
add_shortcode('wpdoodlez_sc', 'wpdoodlez_sc_func');


/**
 * Save a single vote as ajax request and set cookie with given user name
 */
function wpdoodlez_save_vote() {
    $values = get_option( 'wpdoodlez_' . strval($_POST[ 'data' ][ 'wpdoodle' ]), array() );
	$name   = sanitize_text_field( $_POST[ 'data' ][ 'name' ]);
    /* insert only without cookie (or empty name in cookie)
     * update only with same name in cookie
     */
    $nameInCookie = strval($_COOKIE[ 'wpdoodlez-' . $_POST[ 'data' ][ 'wpdoodle' ] ]);
    if ( (isset( $values[ $name ] ) && $nameInCookie == $name) ||
    (!isset( $values[ $name ] ) && empty( $nameInCookie ))
    ) {
        $values[ $name ] = array();
        foreach ( $_POST[ 'data' ][ 'vote' ] as $option ) {
            $values[ $name ][ strval($option[ 'name' ]) ] =  sanitize_text_field($option[ 'value' ]);
        }
    } else {
        echo json_encode( 
            array( 
                'save' => false , 
                'msg' => __('You have already voted but your vote was deleted. Your name was: ','WPdoodlez').$nameInCookie
			) 
        );
        wp_die();
    }
    update_option( 'wpdoodlez_' . (string)$_POST[ 'data' ][ 'wpdoodle' ], $values );
    if ($_COOKIE['hidecookiebannerx']==2 ) setcookie( 'wpdoodlez-' . (string)$_POST[ 'data' ][ 'wpdoodle' ], $name, time() + (3600 * 24 * 30), COOKIEPATH, COOKIE_DOMAIN, is_ssl() );
    echo json_encode( array( 'save' => true ) );
    wp_die();
}

add_action( 'wp_ajax_wpdoodlez_save', 'wpdoodlez_save_vote' );
add_action( 'wp_ajax_nopriv_wpdoodlez_save', 'wpdoodlez_save_vote' );

/**
 * Save a single poll as ajax request and set cookie with given user name
 */
function wpdoodlez_save_poll() {
    $values = get_option( 'wpdoodlez_' . strval($_POST[ 'data' ][ 'wpdoodle' ]), array() );
	$name   = sanitize_text_field( $_POST[ 'data' ][ 'name' ]);
    $values[ $name ] = array();
    foreach ( $_POST[ 'data' ][ 'vote' ] as $option ) {
    $values[ $name ][ strval($option[ 'name' ]) ] =  sanitize_text_field($option[ 'value' ]);
	}
	update_option( 'wpdoodlez_' . (string)$_POST[ 'data' ][ 'wpdoodle' ], $values );
    echo json_encode( array( 'save' => true ) );
    wp_die();
}
add_action( 'wp_ajax_wpdoodlez_save_poll', 'wpdoodlez_save_poll' );
add_action( 'wp_ajax_nopriv_wpdoodlez_save_poll', 'wpdoodlez_save_poll' );


/**
 * Delete a given vote identified by user name. Possible for all wp user with *delete_published_posts* right
 */
function wpdoodlez_delete_vote() {
    if ( !current_user_can( 'delete_published_posts' ) ) {
        echo json_encode( array( 'delete' => false ) );
        wp_die();
    }
    $values    = get_option( 'wpdoodlez_' . (string)$_POST[ 'data' ][ 'wpdoodle' ], array() );
    $newvalues = [ ];
    foreach ( $values as $key => $value ) {
        if ( $key != (string) $_POST[ 'data' ][ 'name' ] ) {
            $newvalues[ $key ] = $value;
        }
    }
    update_option( 'wpdoodlez_' . (string)$_POST[ 'data' ][ 'wpdoodle' ], $newvalues );
    echo json_encode( array( 'delete' => true ) );
    wp_die();
}

add_action( 'wp_ajax_nopriv_wpdoodlez_delete', 'wpdoodlez_delete_vote' );
add_action( 'wp_ajax_wpdoodlez_delete', 'wpdoodlez_delete_vote' );

/**
 * Register WPdoodle post type
 * Set cookie with the name of user (used by voting)
 */
function wpdoodlez_cookie() {
	$labels = [
		'name'               => 'WPdoodlez',
		'singular_name'      => 'WPdoodle',
		'menu_name'          => 'WPdoodle',
		'parent_item_colon'  => '',
		'all_items'          => __( 'All WPdoodlez', 'WPdoodlez'  ),
		'view_item'          => __( 'Show WPdoodle', 'WPdoodlez'  ),
		'add_new_item'       => __( 'New WPdoodle', 'WPdoodlez'  ),
		'add_new'            => __( 'Add WPdoodle ', 'WPdoodlez'  ),
		'edit_item'          => __( 'Edit WPdoodle', 'WPdoodlez'  ),
		'update_item'        => __( 'Update WPdoodle', 'WPdoodlez'  ),
		'search_items'       => __( 'Search WPdoodlez', 'WPdoodlez'  ),
		'not_found'          => __( 'Not found', 'WPdoodlez'  ),
		'not_found_in_trash' => __( 'Not found in trash', 'WPdoodlez'  ),
	];
	$args = [
		'labels'              => $labels,
		'supports'            => [ 'title', 'editor', 'thumbnail', 'comments', 'custom-fields', 'post-formats' ],
		// 'show_in_rest' => true,   // Gutenberg Anzeige des Editors, über ... / Ansicht dann Eigene Felder einschalten!
		 "taxonomies"		  => array("post_tag", "category"),
		'description'         => __( 'appointment planner, polls, quizzes', 'WPdoodlez' ),
		'hierarchical'        => false,
		'public'              => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_nav_menus'   => true,
		'show_in_admin_bar'   => true,
		'menu_position'       => 5,
		'menu_icon'           => 'dashicons-forms',
		'can_export'          => false,
		'has_archive'         => true,
		'exclude_from_search' => false,
		'publicly_queryable'  => true,
		'rewrite'             => [
			'slug'       => 'wpdoodle',
			'with_front' => true,
			'pages'      => false,
			'feeds'      => true,
		],
		'capability_type'     => 'page',
	];
	register_post_type( 'WPdoodle', $args );
    foreach ( $_COOKIE as $key => $value ) {
        if ( preg_match( '/wpdoodlez\-.+/i', (string)$key ) ) {
            if ($_COOKIE['hidecookiebannerx']==2 ) setcookie( (string)$key, (string)$value, time() + (3600 * 24 * 30), COOKIEPATH, COOKIE_DOMAIN, is_ssl() );
        }
    }
}
add_action( 'init', 'wpdoodlez_cookie' );

/**
 * Register WPdoodle post type and refresh rewrite rules
 */
function wpdoodlez_rewrite_flush() {
    wpdoodlez_cookie();
    flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'wpdoodlez_rewrite_flush' );
add_action( 'after_switch_theme', 'wpdoodlez_rewrite_flush' );

// show doodles on home page
add_action( 'pre_get_posts', 'wpse_242473_add_post_type_to_home' );
function wpse_242473_add_post_type_to_home( $query ) {

    if( $query->is_main_query() && $query->is_home() ) {
        $query->set( 'post_type', array( 'post', 'wpdoodle') );
    }
}

// Doodle Comments ab Werk aus
function default_comments_off( $data, $postarr ) {
     if( $data['post_type'] == 'wpdoodle' ) {
          //New posts don't have an ID - So this checks if the post is new or already exists
          if( !($postarr['ID']) ){
               $data['comment_status'] = 0; //0 = false | 1 = true
          }
     }
     return $data;
}
add_filter( 'wp_insert_post_data', 'default_comments_off', '', 2);


// Menüs erweitern um Dokulink
function create_menupages_wpdoodle() {
add_submenu_page(
    'edit.php?post_type=wpdoodle', // Parent slug
    'Dokumentation', // Page title
    'Dokumentation', // Menu title
    'manage_options', // Capability
    '',  // Slug
    'wpdoodle_doku',
);
}
add_action('admin_menu', 'create_menupages_wpdoodle');

function wpdoodle_doku() {
	echo '<h1>'. __( 'WPdoodlez Documentation','WPdoodlez' ).'</h1>';
	echo '<div class="postbox" style="padding:8px">';
	?>
	WPdoodlez can handle classic polls and doodle like appointment planning. It can be created in admin area<br>
	Use custom post type to call posts or integrate post content to your normal posts using the shortcode<br><br>
	If custom fields are named vote1...vote10, a poll is created, just displaying the vote summaries<br><br>
	if custom fields are dates e.g  name: 12.12.2020    value: ja<br>
	then a doodlez is created where visitors can set their name or shortcut and vote for all given event dates<br>
	<br>
	Admins can invoke URL parameter /admin=1 to display alternate votes display (more features when logged in as admin)<br><br>
	<?php
	echo '</div><div class="postbox" style="padding:8px">';
	?>
	<h2>Highlights</h2>
	* link to WPdoodlez is public, but post can have password <br>
	* A WPdoodlez can be in a review and be published at given time<br>
	* A WPdoodlez can have own URL <br>
	* Poll users do not need to be valid logged in wordpress users<br>
	* Users with "delete published post" rights can delete votes<br>
	* Users shortname will be stored in a cookie for 30 days (user can change only his own vote, but on the same computer)<br>
	* GDPR: If Cookie policy is set to: required only, no cookies will be set. For vote polls, no cookies will be set at all
	* Every custom field set in a WPdoodle is a possible answer<br>
	* The first value of the custom field will be displayed in the row as users answer<br>
	* The last row in the table contains total votes count<br>
	<?php
	echo '</div><div class="postbox" style="padding:8px">';
	?>
	<h2>Shortcode</h2>
	<code>[wpdoodlez_sc id=post-ID chart=true]</code>  set post ID to integrate Doodlez or Poll in other pages,
	 set chart to false if you do not want pie graph
	<?php
	echo '</div>';
}

// Mini Calendar display month 
function mini_calendar($month,$year,$eventarray){
	setlocale (LC_ALL, 'de_DE.utf8', 'de_DE@euro', 'de_DE', 'de', 'ge'); 
	/* days and weeks vars now ... */
	$calheader = date('Y-m-d',mktime(2,0,0,$month,1,$year));
	$running_day = date('w',mktime(2,0,0,$month,1,$year));
	if ( $running_day == 0 ) { $running_day = 7; }
	$days_in_month = date('t',mktime(2,0,0,$month,1,$year));
	$days_in_this_week = 1;
	$day_counter = 0;
	$dates_array = array();
	/* draw table */
	$calendar = '<table><thead><th style="text-align:center" colspan=8>' . date_i18n('F Y', mktime(2,0,0,$month,1,$year) ) . '</th></thead>';
	/* table headings */
	$headings = array('MO','DI','MI','DO','FR','SA','SO','Kw');
	$calendar.= '<tr><td style="padding:2px;text-align:center">'.implode('</td><td style="padding:2px;text-align:center">',$headings).'</td></tr>';
	/* row for week one */
	$calendar.= '<tr>';
	/* print "blank" days until the first of the current week */
	for($x = 1; $x < $running_day; $x++):
		$calendar.= '<td style="text-align:center;padding:2px;background:rgba(222,222,222,0.1);"></td>';
		$days_in_this_week++;
	endfor;
	/* keep going with days.... */
	for($list_day = 1; $list_day <= $days_in_month; $list_day++):
		/* add in the day number */
		$running_week = date('W',mktime(0,0,0,$month,$list_day,$year));
		/** QUERY THE DATABASE FOR AN ENTRY FOR THIS DAY !!  IF MATCHES FOUND, PRINT THEM !! **/
		$stylez= '<td style="text-align:center;padding:2px">';
		foreach ($eventarray as $calevent) {
			if ( date('Ymd',mktime(2,0,0,substr($calevent,3,2),substr($calevent,0,2),substr($calevent,6,4))) == date('Ymd',mktime(2,0,0,$month,$list_day,$year)) ) {
				$stylez= '<td style="text-align:center;padding:2px;background:#ffd800;font-weight:700">';
			}
		}	
		$calendar.= $stylez . $list_day . '</td>';
		if($running_day == 7):
			$calendar.= '<td style="text-align:center;padding:2px;background:rgba(222,222,222,0.1);"	>'.$running_week.'</td></tr>';
			if(($day_counter + 1 ) != $days_in_month):
				$calendar.= '<tr>';
			endif;
			$running_day = 0;
			$days_in_this_week = 0;
		endif;
		$days_in_this_week++; $running_day++; $day_counter++;
	endfor;
	/* finish the rest of the days in the week */
	if($days_in_this_week < 8 && $days_in_this_week > 1):
		for($x = 1; $x <= (8 - $days_in_this_week); $x++):
			$calendar.= '<td style="text-align:center;padding:2px"></td>';
		endfor;
		$calendar.= '<td style="padding:2px;text-align:center">'.$running_week.'</td></tr>';
	endif;
	/* end the table */
	$calendar.= '</table>';
	/* all done, return result */
	return $calendar;
}


// Doodlez Inhalte anzeigen
function get_doodlez_content($chartan) {
	global $wp;
	$htmlout = '';
	/* translators: %s: Name of current post */
	$htmlout .= get_the_content();
	$suggestions = $votes_cout  = [ ];
	$customs     = get_post_custom( get_the_ID() );
	foreach ( $customs as $key => $value ) {
		if ( !preg_match( '/^_/is', $key ) ) {
			$suggestions[ $key ] = $value;
			$votes_cout[ $key ]  = 0;
		}
	}

	// admin Details link für polls
	if (is_user_logged_in()) {
		if ( array_key_exists('vote1', $suggestions) ) {
			if (!isset($_GET['admin']) ) {
				$htmlout .= '<span style="float:right"><a href="'.add_query_arg( array('admin'=>'1' ), home_url( $wp->request ) ).'">' . __( 'poll details', 'WPdoodlez' ) . '</a></span>';	
			} else {
				$htmlout .= '<span style="float:right"><a href="'.home_url( $wp->request ) .'">' . __( 'poll results', 'WPdoodlez' ) . '</a></span>';	
			}	
		}
	}	
		
	/* password protected? */
	if ( !post_password_required() ) {
		// Wenn Feldnamen vote1...20, dann eine Umfrage machen, sonst eine Terminabstimmung
		$polli = array_key_exists('vote1', $suggestions);
		if (  $polli  && !isset($_GET['admin']) ) {
			$votes = get_option( 'wpdoodlez_' . md5( AUTH_KEY . get_the_ID() ), array() );
			foreach ( $votes as $name => $vote ) {
				foreach ( $suggestions as $key => $value ) {
					if ($key != "post_views_count" && $key != "likes") {
						if ( !empty($vote[ $key ]) ) {	$votes_cout[ $key ] ++; }
					}	
				}
			}	
			$xsum = 0;
			$pielabel = ''; $piesum = '';
			foreach ( $votes_cout as $key => $value ) {
				if ($key != "post_views_count" && $key != "likes" && $value > 0 ) {
					$xsum += $value;
					$pielabel.=str_replace(',','',$suggestions[$key][0]).','; $piesum .= $value.','; 
				}
			}
			$hashuser = substr(md5(time()),1,20) . '-' . wd_get_the_user_ip();
			$htmlout .= '<br><table id="pollselect"><thead><th colspan=3>' . __( 'your choice', 'WPdoodlez'  ) . '</th></thead>';	
			$xperc = 0;
			$votecounter = 0;
			foreach ( $suggestions as $key => $value ) {
				 if ($key != "post_views_count" && $key != "likes" ) {
						$votecounter += 1;
						if ($xsum>0) $xperc = sprintf("%.1f%%", ($votes_cout[ $key ]/$xsum) * 100);
						$htmlout .= '<tr><td  style="text-align:center"><label><input type="checkbox" name="'.$key.'" id="'.$key.'" onclick="selectOnlyThis(this.id)" class="wpdoodlez-input"></td><td>';
						$htmlout .= $value[ 0 ] .'</label></td><td  style="text-align:center">'.$votes_cout[ $key ].' ('.$xperc.')</td></tr>';
				}	
			}
			$htmlout .= '<tr><td style="text-align:center">' . __( 'total votes', 'WPdoodlez' ) . '</td><td></td><td style="text-align:center;font-size:1.2em">'.$xsum.'</td></tr>';
			$htmlout .= '<tr><td colspan=3><input type="hidden" id="wpdoodlez-name" value="'.$hashuser.'">';
			$htmlout .= '<button style="width:100%" id="wpdoodlez_poll">' . __( 'Vote!', 'WPdoodlez' ) . '</button></td></tr>';
			$htmlout .= '</table>';
			// only one selection allowed
			$htmlout .= '<script>function selectOnlyThis(id) {';
			$htmlout .= 'for (var i = 1;i <= '. $votecounter.'; i++) { document.getElementById("vote"+i).checked = false; }';
			$htmlout .= ' document.getElementById(id).checked = true;	}</script>';
		} else {
			// Dies nur ausführen, wenn Feldnamen nicht vote1...20 oder Admin Details Modus
			$htmlout .= '<h6>' . __( 'Voting', 'WPdoodlez' ) . '</h6>';
			if ( !$polli && function_exists('mini_calendar')) {
				$outputed_values = array();
				$xevents = array();
				foreach ( $suggestions as $key => $value ) {
					if ($key != "post_views_count" && $key != "likes" ) {
						array_push($xevents, $key);
					}
				}
				foreach ( $suggestions as $key => $value ) {
					if ($key != "post_views_count" && $key != "likes" ) {
						$workername = substr($key,6,4) . substr($key,3,2);
						if (!in_array($workername, $outputed_values)){
							$htmlout .= '<div style="font-size:0.9em;overflow:hidden;vertical-align:top;display:inline-block;max-width:32%;width:32%;margin-right:5px">'.mini_calendar(substr($key,3,2),substr($key,6,4),$xevents).'</div>';
							array_push($outputed_values, $workername);
						}
					}	
				}
			}	
			$htmlout .= '<table><thead><tr><th>' . __( 'User name', 'WPdoodlez'  ) . '</th>';
			foreach ( $suggestions as $key => $value ) {
				if ($key != "post_views_count" && $key != "likes" ) {
					$htmlout .= '<th style="word-wrap:break-all;overflow-wrap:anywhere">';
					// ICS Download zum Termin anbieten
					if( function_exists('export_ics') && is_singular() ) {
						$nextnth = strtotime($key);
						$nextnth1h = strtotime($key);
						$htmlout .= ' <a title="'.__("ICS add reminder to your calendar","penguin").'" href="'.home_url(add_query_arg(array($_GET, 'start' =>wp_date('Ymd\THis', $nextnth), 'ende' => wp_date('Ymd\THis', $nextnth1h) ),$wp->request.'/icalfeed/')).'"><i class="fa fa-calendar-check-o"></i></a> ';
					}	
					$htmlout .= $key . '</th>';
				}	
			}
			$htmlout .= '<th>' . __( 'Manage vote', 'WPdoodlez'  ). '</th>';
			$htmlout .= '</tr></thead><tbody>';
			if (!empty($_COOKIE[ 'wpdoodlez-' . md5( AUTH_KEY . get_the_ID() ) ] )) {
				$myname = $_COOKIE[ 'wpdoodlez-' . md5( AUTH_KEY . get_the_ID() ) ];
			}
			$htmlout .= '<tr id="wpdoodlez-form">';
			$htmlout .= '<td><input type="text" placeholder="'. __( 'Your name', 'WPdoodlez'  ) .'" ';			
			$htmlout .= ' class="wpdoodlez-input" id="wpdoodlez-name" size="10"></td>';			
			$votecounter = 0;
			foreach ( $suggestions as $key => $value ) {
				if ($key != "post_views_count" && $key != "likes"  ) {
					$votecounter += 1;
					$htmlout .= '<td><label> <input type="checkbox" name="'. $key.'" id="doodsel'.$votecounter.'" class="wpdoodlez-input">';
					$htmlout .= $value[ 0 ].'</label></td>';
				}
			}
			$htmlout .= '<td><button id="wpdoodlez_vote">'. __( 'Vote!', 'WPdoodlez'  ).'</button></td></tr>';
			$votes = get_option( 'wpdoodlez_' . md5( AUTH_KEY . get_the_ID() ), array() );
			// Page navigation
			if ( $polli ) { $nb_elem_per_page = 20; } else { $nb_elem_per_page = 100; }
			$number_of_pages = intval(count($votes)/$nb_elem_per_page)+1;
			$page = isset($_GET['seite'])?intval($_GET['seite']):0;
			//					foreach ( $votes as $name => $vote ) {
			foreach (array_slice($votes, $page*$nb_elem_per_page, $nb_elem_per_page) as $name => $vote) { 
				$htmlout .= '<tr id="wpdoodlez_' . md5( AUTH_KEY . get_the_ID() ) . '-' . md5( $name ) .'" ';
				$htmlout .= 'class="'. @$myname == $name ? 'myvote' : '' .'">';
				$htmlout .= '<td style="text-align:left">'; 
				// Wenn ipflag plugin aktiv und user angemeldet
				if( class_exists( 'ipflag' ) && is_user_logged_in() ) {
					global $ipflag;
					$nameip = substr($name,21,strlen($name)-21);
					if(isset($ipflag) && is_object($ipflag)){
						if(($info = $ipflag->get_info($nameip)) != false){
							$htmlout .= ' '.$info->code .  ' ' .$info->name. ' ' . $ipflag->get_flag($info, '') ;
						} else { $htmlout .= ' '. $ipflag->get_flag($info, '') . ' '; }
					} 
				}	
				$htmlout .= ' ' . substr($name,0,20) . '</td>';
				foreach ( $suggestions as $key => $value ) {
					if ($key != "post_views_count" && $key != "likes") {
						$htmlout .= '<td>';
						if ( !empty($vote[ $key ]) ) {
							$votes_cout[ $key ] ++;
							$htmlout .= '<label data-key="' . $key . '">'. $value[ 0 ].'</label>';
						} else {
							$htmlout .= '<label></label>';
						}
						$htmlout .= '</td>';
					}
				}	
				$htmlout .= '<td>';
				if ( current_user_can( 'delete_published_posts' ) ) {
					$htmlout .= '<button style="padding:3px 10px 3px 10px" class="wpdoodlez-delete" data-vote="'. md5( $name ).'" data-realname="'. $name.'">';
					$htmlout .= '<i title="' . __( 'delete', 'WPdoodlez' ) . '" class="fa fa-trash-o"></i></button>';
				}
				if ( !empty($myname) && $myname == $name ) {
					$htmlout .= '<button style="padding:3px 10px 3px 10px" class="wpdoodlez-edit" data-vote="'. md5( $name ). '" data-realname="'. $name.'">';
					$htmlout .= '<i title="'.__( 'edit', 'WPdoodlez' ).'" class="fa fa-edit"></i></button>';
				}
				$htmlout .= '</td></tr>';
			}
			$htmlout .= '</tbody><tfoot>';
			$htmlout .= '<tr><th>' . __( 'total votes', 'WPdoodlez' ).':</th>';
			$pielabel = ''; $piesum = '';
			foreach ( $votes_cout as $key => $value ) {
				if ($key != "post_views_count" && $key != "likes" ) {
					$htmlout .= '<th id="total-'. $key .'">'. $value.'</td>';
					$pielabel .=  strtoupper($key) . ',';
					$piesum .= $value . ',';
				}
			}
			$htmlout .= '<td><b>Zeilen: ' . ($nb_elem_per_page*($page) +1 )  . ' - '.($nb_elem_per_page*($page+1) ) .'</b></td>';
			$htmlout .= '</tr></tfoot>';
		}     //    Ende Terminabstimmung oder Umfrage, nun Fusszeile
		$htmlout .= '</table>';
		if ( isset($_GET['admin']) || !$polli) {
			if ( $number_of_pages >1 ) {
				// Page navigation		
				$html='<div class="nav-links">';
				for($i=0;$i<$number_of_pages;$i++){
					$seitennummer = $i+1;
					$html .= ' &nbsp;<a class="page-numbers" href="'.add_query_arg( array('admin'=>'1', 'seite'=>$i), home_url( $wp->request ) ).'">'.$seitennummer.'</a>';
				}	
				$htmlout .= $html . '</div>';
			}	
		}	
		// Chart Pie anzeigen zu den Ergebnissen
		$piesum = rtrim($piesum, ",");
		$pielabel = rtrim($pielabel, ",");
		if( class_exists( 'PB_ChartsCodes' ) && !empty($pielabel) && ($chartan) ) {
			$htmlout .= do_shortcode('[chartscodes_polar accentcolor="1" title="' . __( 'votes pie chart', 'WPdoodlez' ) . '" values="'.$piesum.'" labels="'.$pielabel.'" absolute="1"]');
		}	
	}		/* END password protected? */
	return $htmlout;
} // end of get doodlez content	

// ------------------- quizzz code and shortcode ---------------------------------------------------------------

function create_quiz_tax_category() {
	$labels = array(
        'name' => __( 'Quiz Categories', 'WPdoodlez' ),
        'singular_name' => __( 'Quiz Category', 'WPdoodlez' ),
        'search_items' =>  __( 'Search Quiz Categories', 'WPdoodlez' ),
        'popular_items' => __( 'Popular Quiz Categories', 'WPdoodlez' ),
        'all_items' => __( 'All Quiz Categories', 'WPdoodlez' ),
        'parent_item' => null,
        'parent_item_colon' => null,
        'edit_item' => __( 'Edit Quiz Category', 'WPdoodlez' ),
        'update_item' => __( 'Update Quiz Category', 'WPdoodlez' ),
        'add_new_item' => __( 'Add New Quiz Category', 'WPdoodlez' ),
        'new_item_name' => __( 'New Quiz Category', 'WPdoodlez' ),
        'separate_items_with_commas' => __( 'Separate categories with commas', 'WPdoodlez' ),
        'add_or_remove_items' => __( 'Add or remove Quiz categories', 'WPdoodlez' ),
        'choose_from_most_used' => __( 'Choose from the most used categories', 'WPdoodlez' )
        );
    register_taxonomy(  
    'quizcategory',  
    'Question',  // this is the custom post type(s) I want to use this taxonomy for
        array(  
            'hierarchical' => false,  
            'label' => __( 'Quiz-categories', 'WPdoodlez' ),  
            'query_var' => true,  
            'labels' => $labels,
            'hierarchical' => true,
            'show_ui' => true,
            'rewrite' => true  
        )  
    );  
}
add_action( 'init', 'create_quiz_tax_category' );

function create_quiz_post() {
	$labels = array(
		'name'                => __( 'Questions', 'WPdoodlez' ),
		'singular_name'       => __( 'Question', 'WPdoodlez' ),
		'add_new'             => __( 'Add New Question', 'WPdoodlez' ),
		'add_new_item'        => __( 'Add New Question', 'WPdoodlez' ),
		'edit_item'           => __( 'Edit Question', 'WPdoodlez' ),
		'new_item'            => __( 'New Question', 'WPdoodlez' ),
		'view_item'           => __( 'View Question', 'WPdoodlez' ),
		'search_items'        => __( 'Search Questions', 'WPdoodlez' ),
		'not_found'           => __( 'No Questions found', 'WPdoodlez' ),
		'not_found_in_trash'  => __( 'No Questions found in Trash', 'WPdoodlez' ),
		'parent_item_colon'   => __( 'Parent Question:', 'WPdoodlez' ),
		'menu_name'           => __( 'Questions', 'WPdoodlez' ),
	);
	$args = array(
		'labels'              => $labels,
		'hierarchical'        => false,
		'description'         => __( 'questions with one or four answers and help mask', 'WPdoodlez' ),
		'taxonomies'          => array( 'quizcategory', 'post_tag' ),
		'public'              => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_admin_bar'   => true,
		'menu_position'       => 5,
		'menu_icon'           => 'dashicons-yes',
		'show_in_nav_menus'   => true,
		'publicly_queryable'  => true,
		'exclude_from_search' => false,
		'has_archive'         => true,
		'query_var'           => true,
		'can_export'          => true,
		'rewrite'             => true,
		'capability_type'     => 'post',
		'supports'            => array(	'title', 'editor', 'thumbnail', 'comments' )
	);
	register_post_type( 'Question', $args );

	// CSV Import starten, wenn Dateiname im upload dir public_histereignisse.csv ist	
	if( isset($_REQUEST['quizzzcsv']) && ( $_REQUEST['quizzzcsv'] == true ) && isset( $_REQUEST['nonce'] ) ) {
		$nonce  = filter_input( INPUT_GET, 'nonce', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( ! wp_verify_nonce( $nonce, 'dnonce' ) ) wp_die('Invalid nonce..!!');
		importposts();
		echo '<script>window.location.href="'.get_home_url().'/wp-admin/edit.php?post_type=question"</script>';
	}
}
add_action( 'init', 'create_quiz_post' );

// Shortcode Random Question
function random_quote_func( $atts ){
	$attrs = shortcode_atts( array( 'orderby' => 'rand', 'order' => 'rand', 'items' => 1, ), $atts ); 
    $args=array(
      'orderby'=> $attrs['orderby'],
      'order'=> $attrs['order'],
      'post_type' => 'question',
      'post_status' => 'publish',
      'posts_per_page' => $attrs['items'],
	  'showposts' => $attrs['items'],
    );
	$my_query = null;
    $my_query = new WP_Query($args);
	$accentcolor = get_theme_mod( 'link-color', '#888' );
    $message = '';
    if( $my_query->have_posts() ) {
      while ($my_query->have_posts()) : $my_query->the_post(); 
		$antwortmaske='';
		$quizkat='';
		$terms = get_the_terms(get_the_id(), 'quizcategory'); // Get all terms of a taxonomy
		if ( $terms && !is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
					$quizkat .= '&nbsp; <i class="fa fa-folder-open"></i> <a href="'. get_term_link($term) .'">' . $term->name . '</a> &nbsp; ';
			}
		}	
		$answers = get_post_custom_values('quizz_answer');
		$answersb = get_post_custom_values('quizz_answerb');
		$answersc = get_post_custom_values('quizz_answerc');
		$answersd = get_post_custom_values('quizz_answerd');
		$hangrein = preg_replace("/[^A-Za-z]/", '', $answers[0]);
		if (isset($_GET['timer'])) { $timerurl='?timer=1'; } else { $timerurl = '?t=0'; }
		$listyle='text-align:center;border-radius:3px;padding:6px;display:block;margin-bottom:5px';
		$xlink='<div class="nav-links"><a class="page-numbers" title="Frage aufrufen und spielen" style="'.$listyle.'" href="'.get_post_permalink().$timerurl;
		if (!empty($answersb) && strlen($answersb[0])>1 ) {
			$ans=array($answers[0],$answersb[0],$answersc[0],$answersd[0]);
			shuffle($ans);
			foreach ($ans as $choice) {
				$antwortmaske .= $xlink.'&ans='.esc_html($choice) . '">' . $choice . '</a></div>';
			}
			unset($choice);
		} else {	
			// ansonsten freie Antwort anfordern von Antwort 1
			$antwortmaske .= $xlink.'"><span style="border-radius:3px;color:#fff;border:1px solid #ccc;font-weight:700;font-size:1.2em;padding:1px 0 1px 9px;letter-spacing:.5em;font-family:monospace">'.preg_replace( '/[^( |aeiouAEIOU.)$]/', '_', esc_html($answers[0])).'</span></a></div>';
		}	
		$antwortmaske.='<ul class="footer-menu"><li><a title="'.__('Crossword','WPdoodlez').'" href="'.add_query_arg( array('crossword'=>1), get_post_permalink() ).'" style="margin-top:10px"><i class="fa fa-table"></i> '. __('Crossword','WPdoodlez').'</a></li>';
		if (strlen($hangrein) <= 15 && strlen($hangrein) >= 5) $antwortmaske.='<li><a title="Frage mit Hangman Spiel lösen" href="'.add_query_arg( array('hangman'=>1), get_post_permalink() ).'"><i class="fa fa-universal-access"></i> '. __('Hangman','WPdoodlez').'</a></li>';
		$antwortmaske.='</ul>';
		$message .= '<div><p>';
		// Wenn eine Quizkategorie da, Katbild anzeigen
		$terms = get_the_terms(get_the_id(), 'quizcategory'); // Get all terms of a taxonomy
		if ( $terms && !is_wp_error( $terms ) ) {
			$category = $terms;
		} else {
			$category = get_the_category(); 
		}	
		if ( class_exists('ZCategoriesImages') && !empty($category) && z_taxonomy_image_url($category[0]->term_id) != NULL ) {
			$cbild = z_taxonomy_image_url($category[0]->term_id);
			$message .= '<div class="post-thumbnail" style="display:inline"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">';
			$message .= '<img alt="Quiz-Kategoriebild" src="' . $cbild . '" class="wp-post-image"></div>';	
		}			
		$message .= '<a title="alle Fragen anzeigen" href="'.esc_url(site_url().'/question?orderby=rand&order=rand').'"><i class="fa fa-question-circle"></i></a> &nbsp; ';
		$message .= '<span class="headline"><a title="Frage aufrufen und spielen" href="'.get_post_permalink().'">'.get_the_title().'</a></span> '.$quizkat;
		$message .= '</p><p>'.get_the_content().'</p>'.$antwortmaske.'</div>';
      endwhile;
    }
    wp_reset_query();  
    return $message;
}
add_shortcode( 'random-question', 'random_quote_func' );

// Kategorie anlegen oder auswählen
/**
 * (WordPress) Insert or get term id
 * @return int Term ID (0 no term was inserted or found)
 */
function __wp_insert_or_get_term_id($name, $taxonomy, $parent = 0) {
    if (!($term = get_term_by("name", $name, $taxonomy))) {
        $insert = wp_insert_term($name, $taxonomy, array(
            "parent" => $parent
        ));
        if (is_wp_error($insert)) {
            return 0;
        }
        return intval($insert["term_id"]);
    }
    return intval($term->term_id);
}


// Fragen importieren
function importposts() {
	set_time_limit(800);
	$edat= array();
	$upload_dir = wp_upload_dir();
	$upload_basedir = $upload_dir['basedir'] . '/public_histereignisse.csv';
	$uploaddiff_basedir = $upload_dir['basedir'] . '/public_histereignisse-update.csv';
	$row = 1;
	if ( file_exists( $upload_basedir ) ) {
		// Alle Fragen löschen
		$allposts= get_posts( array('post_type'=>'Question','numberposts'=>-1) );
		foreach ($allposts as $eachpost) { wp_delete_post( $eachpost->ID, true ); }
		$fullimport = 1;
		$handle = fopen($upload_basedir , "r");
	}
	if ( file_exists( $uploaddiff_basedir ) ) {
		$handle = fopen($uploaddiff_basedir , "r");
		$fullimport = 0;
	}
	if ( $handle !== FALSE ) {
		while (($data = fgetcsv($handle, 2000, ";")) !== FALSE) {
			if ( $row > 1 && !empty($data[1]) ) {
				// id; datum; charakter; land; titel; seitjahr; antwort; antwortb; antwortc; antwortd; zusatzinfo; kategorie
				$num = count($data);
				$edat = explode('.',$data[1]);
				$mydatum = $edat[2].'-'.$edat[1].'-'.$edat[0];
				$category_id =__wp_insert_or_get_term_id( $data[11], 'quizcategory' );
				$post_id = wp_insert_post(array (
				   'post_type' => 'Question',
				   'post_title' => $data[2].' '.$data[0],
				   'post_content' => $data[4],
				   'post_status' => 'publish',
				   'comment_status' => 'closed',
				   'ping_status' => 'closed', 
				   'tax_input'     => array(
                           'quizcategory' => array( $category_id ),
					),
				));
				if ($post_id) {
				   // insert post meta
				  add_post_meta( $post_id, 'quizz_answer', esc_html($data[6]) );
				  add_post_meta( $post_id, 'quizz_answerb', esc_html($data[7]) );
				  add_post_meta( $post_id, 'quizz_answerc', esc_html($data[8]) );
				  add_post_meta( $post_id, 'quizz_answerd', esc_html($data[9]) );
				  add_post_meta( $post_id, 'quizz_zusatzinfo', esc_html($data[10]) );
				  add_post_meta( $post_id, 'quizz_exact', NULL );
				  add_post_meta( $post_id, 'quizz_last', NULL );
				  add_post_meta( $post_id, 'quizz_lastpage', NULL );
				}	
			}	
			$row++;
		}
    fclose($handle);
	//	echo ($row-1) . ' Datensätze importiert';
	}		
}


function quiz_adminstats() {
	// wenn admin eingeloggt, Admin stats anzeigen
	if( current_user_can('administrator') &&  ( is_singular() ) ) {
		if (isset($_GET['timer'])) { $timerurl='?timer=1'; } else { $timerurl = ''; }
		global $wpdb;
		$message = '<h6>Admin-Statistik</h6>';
		// Top5 Right
		$the_query = new WP_Query(array('post_type' => 'question', 'posts_per_page' => 5, 'meta_key' => 'quizz_rightstat', 'orderby' => 'meta_value_num', 'order' => 'DESC'));
		if ( $the_query->have_posts() ) {
			while ( $the_query->have_posts() ) {
				$the_query->the_post();
				$message .= '<span style="color:green;display:inline-block;width:55px">R:'.get_post_meta( get_the_ID(), 'quizz_rightstat', true ).'</span><span style="color:tomato;display:inline-block;width:55px">F:'.get_post_meta( get_the_ID(), 'quizz_wrongstat', true ).'</span><a href="'.get_the_permalink().'">'.substr(get_the_content(),0,90).'</a><br>';
			}
		}
		wp_reset_postdata();
		// Top5 wrong
		$the_query = new WP_Query(array('post_type' => 'question', 'posts_per_page' => 5, 'meta_key' => 'quizz_wrongstat', 'orderby' => 'meta_value_num', 'order' => 'DESC'));
		if ( $the_query->have_posts() ) {
			while ( $the_query->have_posts() ) {
				$the_query->the_post();
				$message .= '<span style="color:tomato;display:inline-block;width:55px">F:'.get_post_meta( get_the_ID(), 'quizz_wrongstat', true ).'</span><span style="color:green;display:inline-block;width:55px">R:'.get_post_meta( get_the_ID(), 'quizz_rightstat', true ).'</span><a href="'.get_the_permalink().'">'.substr(get_the_content(),0,90).'</a><br>';
			}
		}
		wp_reset_postdata();
		// totals Right/wrong
		$rct=0;$wct=0;
		$the_query = new WP_Query(array('post_type' => 'question', 'posts_per_page' => -1, 'meta_key' => 'quizz_rightstat', 'orderby' => 'meta_value_num', 'order' => 'DESC'));
		if ( $the_query->have_posts() ) {
			while ( $the_query->have_posts() ) {
				$the_query->the_post();
				$rct = $rct + (int) get_post_meta( get_the_ID(), 'quizz_rightstat', true );
				$wct = $wct + (int) get_post_meta( get_the_ID(), 'quizz_wrongstat', true );
			}
		}
		wp_reset_postdata();
		if ($rct >0 || $wct > 0) {
			$message .= '<p>Gesamt gespielt: '.intval($rct + $wct).' Fragen, davon richtig: ' .$rct;
			$message .= ' &nbsp;<progress id="rf" value="'.intval($rct/($rct+$wct)*100).'" max="100" style="width:100px"></progress>';
			$message .= ' &nbsp; falsch: '.$wct;
			$message .= ' &nbsp;<progress id="rf" value="'.(100 - intval($rct/($rct+$wct)*100)).'" max="100" style="width:100px"></progress> </p>';
		}	
		return $message;
	}
}	// Ende Admin Stats

// // Schulnote auflösen
function get_schulnote( $prozent ) {
	if ($prozent >=97 ) $snote = 'sehr gut plus (0.7, 97-100%)';
	if ($prozent >=94 && $prozent <97 ) $snote = 'sehr gut (1.0, 94-96%)';
	if ($prozent >=92 && $prozent <94 ) $snote = 'sehr gut minus (1.3, 92-93%)';
	if ($prozent >=89 && $prozent <92 ) $snote = 'gut plus (1.7, 89-91%)';
	if ($prozent >=84 && $prozent <89 ) $snote = 'gut (2.0, 84-88%)';
	if ($prozent >=81 && $prozent <84 ) $snote = 'gut minus (2.3, 81-83%)';
	if ($prozent >=77 && $prozent <81 ) $snote = 'befriedigend plus (2.7, 77-80%)';
	if ($prozent >=71 && $prozent <77 ) $snote = 'befriedigend (3.0, 71-76%)';
	if ($prozent >=67 && $prozent <71 ) $snote = 'befriedigend minus (3.3, 67-70%)';
	if ($prozent >=62 && $prozent <67 ) $snote = 'ausreichend plus (3.7, 62-66%)';
	if ($prozent >=55 && $prozent <62 ) $snote = 'ausreichend (4.0, 55-61%)';
	if ($prozent >=50 && $prozent <55 ) $snote = 'ausreichend minus (4.3, 50-54%)';
	if ($prozent >=44 && $prozent <50 ) $snote = 'mangelhaft plus (4.7, 44-49%)';
	if ($prozent >=37 && $prozent <44 ) $snote = 'mangelhaft (5.0, 37-43%)';
	if ($prozent >=30 && $prozent <37 ) $snote = 'mangelhaft minus (5.3, 30-36%)';
	if ($prozent <30 ) $snote = 'ungenügend (6.0, unter 30%)';
	return $snote;
}

// Einzelanzeige
function quiz_show_form( $content ) {
	global $wp;
	setlocale (LC_ALL, 'de_DE.utf8', 'de_DE@euro', 'de_DE', 'de', 'ge'); 
	if (get_post_type()=='question'):
		global $answer;
		if (isset($_POST['answer'])) $answer = $_POST['answer'];	// user submitted answer
		if (isset($_POST['ans'])) $answer = $_POST['ans'];   // Answer is radio button selection 1 of 4
		if (isset($_GET['ans']))  $answer = sanitize_text_field($_GET['ans']);  // Answer is given from shortcode
		if (isset($_GET['ende'])) { $ende = sanitize_text_field($_GET['ende']); } else { $ende = 0; }
		// Link für nächste Zufallsfrage
		$args=array(
		  'orderby'=>'rand', 'post_type' => 'question', 'post_status' => 'publish', 'posts_per_page' => 1, 'showposts' => 1,
		);
		$my_query = null;
		$my_query = new WP_Query($args);
		$random_post_url = '';
		if( $my_query->have_posts() ) {
		  while ($my_query->have_posts()) : $my_query->the_post(); 
			$random_post_url = get_the_permalink();
		  endwhile;
		}
		wp_reset_query();  
		$terms = get_the_terms(get_the_id(), 'quizcategory'); // Get all terms of a taxonomy
		if ( $terms && !is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$content = '<span style="font-size:1.2em">Kategorie: ' . $term->name . ' <br>' . $content.'</span>'; 
			}
		}	
		// get meta values for this question
		$answers = get_post_custom_values('quizz_answer');
		$answersb = get_post_custom_values('quizz_answerb');
		$answersc = get_post_custom_values('quizz_answerc');
		$answersd = get_post_custom_values('quizz_answerd');
		$zusatzinfo = get_post_custom_values('quizz_zusatzinfo');
		$nextlevel = get_post_custom_values('quizz_nextlevel');
		$exact = get_post_custom_values('quizz_exact');
		$last_bool = get_post_custom_values('quizz_last');
		$lastpage = get_post_custom_values('quizz_lastpage');
		$rightstat = get_post_custom_values('quizz_rightstat');
		$wrongstat = get_post_custom_values('quizz_wrongstat');
		$error = "<p class='quiz_error quiz_message'>ERROR</p>";
		$lsubmittedanswer = strtolower($answer);
		$lactualanswer = strtolower($answers[0]);
		$hangrein = preg_replace("/[^A-Za-z]/", '', $answers[0]);
		// Hangman spielen oder normale Beantwortung
		if ( isset($_GET['crossword']) && is_singular() ) {
			$theForm = do_shortcode('[xwordquiz]');
		} else if ( isset($_GET['hangman']) && strlen($hangrein) <= 14 && strlen($hangrein) >= 5 ) {
			$theForm = $content . play_hangman($answers[0]);
			$theForm .= '<ul class="footer-menu" style="text-align:center;margin-top:20px;list-style:none;text-transform:uppercase;"><li><a href="' . $random_post_url .'"><i class="fa fa-random"></i> '. __('next random question','WPdoodlez').'</a></li></ul>';
			if ( strpos($theForm,"background-color:green") !== false ) {	
				ob_start();
				if ($_COOKIE['hidecookiebannerx']==2 ) setcookie('rightscore', intval($_COOKIE['rightscore']) + 1, time()+60*60*24*30, '/');
				ob_flush();
				update_post_meta( get_the_ID(), 'quizz_rightstat', $rightstat[0] + 1 );
			}
			if ( strpos($theForm,"background-color:tomato") !== false ) {	
				ob_start();
				if ($_COOKIE['hidecookiebannerx']==2 ) setcookie('wrongscore', intval($_COOKIE['wrongscore']) + 1, time()+60*60*24*30, '/');
				ob_flush();
				update_post_meta( get_the_ID(), 'quizz_wrongstat', $wrongstat[0] + 1 );
			}
		} else {
			// 4 Antworten gemixt vorgeben, wenn gesetzt, freie Antwort, wenn nur eine
			$ansmixed='';
			if (!empty($_POST) ) { $hideplay = ""; } else { $hideplay="document.getElementById('quizform').submit();"; }
			if (!empty($answersb) && strlen($answersb[0])>1 ) {
				$showsubmit ='none';
				if (empty($_POST) ) {
					$ans = array($answers[0],$answersb[0],$answersc[0],$answersd[0]);
					shuffle($ans);
				} else {
					$ans = explode(";",$_POST['answers4']);
				}	
				$xex = 0;
				foreach ($ans as $choice) {
					$xex++;
					$labstyle = ''; $astyle='';
					if (!empty($_POST) ) {
						if ( $choice == $answer ) { $labstyle = 'background:tomato'; $astyle='color:#fff'; } 
						if ( $choice == $answers[0] ) { $labstyle = 'background:green'; $astyle='color:#fff'; } 
					}	
					$ansmixed .= '<input onclick="'.$hideplay.'" type="radio" name="ans" id="ans'.$xex.'" value="'.$choice.'">';
					$ansmixed .= ' &nbsp; <label style="'.$labstyle.'" for="ans'.$xex.'"><a style="'.$astyle.'"><b>'.chr($xex+64).'</b> &nbsp; '.$choice.'</a></label>';
				} 
				$ansmixed .='<input type="hidden" name="answers4" id="answers4" value="'.implode(";",$ans).'">';
				$pollyans = implode(" , ",$ans);
				unset($choice);
			} else {	
				// ansonsten freie Antwort anfordern von Antwort 1
				if ( empty($_POST) ) $showsubmit='inline-block'; else $showsubmit='none';
				$ansmixed .= __('answer mask','WPdoodlez'). '<span style="border-radius:3px;background-color:#eee;margin:0 5px;font-weight:700;font-size:1.2em;padding:3px 0 3px 9px;letter-spacing:.5em;font-family:monospace">';
				$ansmixed .= preg_replace( '/[^( |aeiouAEIOU.)$]/', '_', esc_html($answers[0])).'</span>' . strlen(esc_html($answers[0])).__(' characters long. ','WPdoodlez');
				if ( empty($_POST) ) {
					if ($exact[0]!="exact") { $ansmixed .= __('not case sensitive','WPdoodlez'); } else { $ansmixed .= __('case sensitive','WPdoodlez'); }
					$ansmixed .='<input autocomplete="off" style="width:100%" type="text" name="answer" id="answer" placeholder="'. __('your answer','WPdoodlez').'" class="quiz_answer answers">';
				}
			$pollyans = esc_html(preg_replace( '/[^( |aeiouAEIOU.)$]/', '_', esc_html($answers[0])));
			}	
			if ($exact[0]=="exact") {
				//exact, strict match
				if ($answer == $answers[0]) {
					$correct = "yes";
				} else {
					$correct = "no";
				}
			} else {
				$needlehaystack = strrpos($lsubmittedanswer, $lactualanswer);
				if ( $needlehaystack > -1 ) {
					$correct = "yes";
				} else {
					$correct = "no";
				}
			}
		if ( strlen($answers[0])>5 ) { $wikinachschlag = '<br><div class="nav_links" style="text-align:center"><i class="fa fa-wikipedia-w"></i> <a title="Wiki more info" target="_blank" href="https://de.wikipedia.org/wiki/'.$answers[0].'">Wiki-Artikel</a></div>'; } else { $wikinachschlag='';}
			if ( $correct == "yes" ) {
				ob_start();
				if ($_COOKIE['hidecookiebannerx']==2 ) setcookie('rightscore', intval($_COOKIE['rightscore']) + 1, time()+60*60*24*30, '/');
				ob_flush();
				update_post_meta( get_the_ID(), 'quizz_rightstat', $rightstat[0] + 1 );
				if ($last_bool[0] != "last") {
					if ( !empty($nextlevel[0]) ) {
						// raise a hook for updating record
						do_action( 'quizz_level_updated', $nextlevel[0] );
						$goto = $nextlevel[0];
						wp_safe_redirect( get_post_permalink($goto) );
					} else {
						$error = $ansmixed.'<div style="margin-top:30px;font-size:1.2em;color:#fff;background-color:green;display:inline-block; width:100%; padding:5px; border:1px solid #ddd;border-radius:3px"><i class="fa fa-lg fa-thumbs-o-up"></i> &nbsp; ' . __('correct answer: ','WPdoodlez') . ' '. $answers[0];
						if ( !empty($zusatzinfo) && strlen($zusatzinfo[0])>1 ) $error .= '<p style="margin-top:30px;color:#fff"><i class="fa fa-newspaper-o"></i> &nbsp; '.$zusatzinfo[0].'</p>';
						$error .= '</div>'.$wikinachschlag;
						$showqform = 'display:none';
					}
				} else {
					// raise a hook for end of quiz
					do_action( 'quizz_ended', $lastpage[0] );
					$goto = $lastpage[0];
					wp_safe_redirect( add_query_arg( array('ende'=>1), home_url($wp->request) ) );
				}
			} else {
				if ($_SERVER['REQUEST_METHOD'] == 'POST' || isset($_GET['ans']) ) {
					$error = $ansmixed.'<div style="margin-top:30px;font-size:1.2em;color:#fff;background-color:tomato;display:inline-block; width:100%; padding:5px; border:1px solid #ddd;border-radius:3px">';
					$error .= '<i class="fa fa-lg fa-thumbs-o-down"></i> &nbsp; '. $answer;
					$error .= '<br>'. __(' is the wrong answer. Correct is','WPdoodlez').'<br><i class="fa fa-lg fa-thumbs-up"></i> &nbsp; '.esc_html($answers[0]);
					if ( !empty($zusatzinfo) && strlen($zusatzinfo[0])>1 ) $error .= '<p style="margin-top:30px;color:#fff"><i class="fa fa-newspaper-o"></i> &nbsp; '.$zusatzinfo[0].'</p>';
					$error .= '</div>'.$wikinachschlag;
					$showqform = 'display:none';
					ob_start();
					if ($_COOKIE['hidecookiebannerx']==2 ) setcookie('wrongscore', intval($_COOKIE['wrongscore']) + 1, time()+60*60*24*30, '/');
					ob_flush();
					update_post_meta( get_the_ID(), 'quizz_wrongstat', $wrongstat[0] + 1 );
				} else { $error = "";$showqform = ''; }
			}
			$accentcolor = get_theme_mod( 'link-color', '#888' );
			$formstyle = '<style>.qiz input[type=radio] {display:none;} .qiz input[type=radio] + label {display:inline-block;width:100%;padding:8px;border-radius:3px;cursor:pointer;background:'.$accentcolor.'}';
			$formstyle .= '.qiz input[type=radio] + label:hover{box-shadow:inset 0 0 100px 100px rgba(255,255,255,.15)} .qiz input[type=radio] + label a {color:#fff} ';
			if ( empty($_POST) ) {
				$formstyle .= '.qiz input[type=radio]:checked + label { background-image:none;background:'.$accentcolor.';border:2px solid #000} .qiz input[type=radio]:checked + label a {color:#fff}';
			} else {
				$formstyle .= '.qiz input[type=radio] + label {cursor:not-allowed} ';
			}
			$formstyle .='</style>';
			$listyle = '<li style="padding:6px;display:inline;margin-right:10px;">';
			$letztefrage ='<div style="text-align:center;margin-top:10px"><ul class="footer-menu" style="list-style:none;display:inline;text-transform:uppercase;">';
			$terms = get_the_terms(get_the_id(), 'quizcategory'); // Get all terms of a taxonomy
			$copytags = '';
			if ( $terms && !is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
						$copytags .= '&nbsp; Kategorie: ' . $term->name; 
				}
			}	
			$copyfrage = '  ' . wp_strip_all_tags( preg_replace("/[?,]/", '', get_the_title() ).'  '.$copytags.'  '. preg_replace("/[?,]/", '',get_the_content() ).' ? '.$pollyans);
			$letztefrage.= $listyle.'<input title="Frage in Zwischenablage kopieren" style="cursor:pointer;background-color:'.$accentcolor.';color:white;margin-top:5px;vertical-align:top;width:40px;height:20px;font-size:9px;padding:0" type="text" class="copy-to-clipboard" value="'.$copyfrage.'">';
			$letztefrage .= '</li>' . $listyle. '<a href="'.get_home_url().'/question?orderby=rand&order=rand"><i class="fa fa-list"></i> '. __('all questions overview','WPdoodlez').'</a>';
			if (isset($nextlevel) || isset($last_bool[0]) ) {
				$letztefrage.='</li>'.$listyle;
				if ($last_bool[0] == "last") { $letztefrage .= 'letzte Frage'; } else { $letztefrage .= '<a href="'.get_post_permalink($nextlevel[0]).'"><i class="fa fa-arrow-circle-right"></i> nächste Frage: '.$nextlevel[0].'</a>'; }
				$letztefrage.='</li>';
			} else {
				if (isset($_GET['timer'])) { $timerurl='?timer=1'; } else { $timerurl = ''; }
				$letztefrage.='</li>'.$listyle.'<a href="' . $random_post_url . $timerurl.'"><i class="fa fa-random"></i> '. __('next random question','WPdoodlez').'</a>';
				if (strlen($hangrein) <= 14 && strlen($hangrein) >= 5) $letztefrage.='</li>'.$listyle.'<a href="'.add_query_arg( array('hangman'=>1), get_post_permalink() ).'"><i class="fa fa-universal-access"></i> '. __('Hangman','WPdoodlez').'</a></li>';
			}
			$letztefrage.='</li>'.$listyle.'<a title="'.__('get certificate','WPdoodlez').'" href="'.add_query_arg( array('ende'=>1), home_url($wp->request) ).'"><i class="fa fa-certificate"></i> '.__('get certificate','WPdoodlez').'</a></li>';
			if ( @$wrongstat[0] > 0 || @$rightstat[0] >0 ) { $perct = intval(@$rightstat[0] / (@$wrongstat[0] + @$rightstat[0]) * 100); } else { $perct= 0; }
			if ( @$_COOKIE['wrongscore'] > 0 || @$_COOKIE['rightscore'] >0 ) { $sperct = intval (@$_COOKIE['rightscore'] / (@$_COOKIE['wrongscore'] + @$_COOKIE['rightscore']) * 100); } else { $sperct= 0; }
			$letztefrage .= '</ul><br><br><ul></li>'.$listyle. __('Total scores','WPdoodlez');
			$letztefrage .= ' <progress id="rf" value="'.$perct.'" max="100" style="width:100px"></progress> R: '. @$rightstat[0].' / F: '. @$wrongstat[0];
			if ($_COOKIE['hidecookiebannerx']==2 ) {
				$letztefrage .= '</li>'.$listyle. __('Your session','WPdoodlez');
				$letztefrage .= ' <progress id="rf" value="'.$sperct.'" max="100" style="width:100px"></progress> R: ' . @$_COOKIE['rightscore']. ' / F: '.@$_COOKIE['wrongscore'].'</li>';
			}	
			$letztefrage .= '</ul></div>';
			$letztefrage .= quiz_adminstats();
			if (!$ende) {
				$antwortmaske = $content . '<div class="qiz">';
				$antwortmaske .= $error.'<form id="quizform" action="" method="POST" class="quiz_form form" style="'.$showqform.'">';
				if ( !current_user_can('administrator') ) {      // Nur Timer anzeigen, wenn kein Admin angemeldet
					$antwortmaske .= "<!-- noformat on --><script>function empty() { var x; x = document.getElementById('answer').value; if (x == '') { alert('".__('please enter a value','WPdoodlez')."'); return false; }; }</script>";
					if (isset($_GET['timer'])) { $timeranaus = '1'; } else { $timeranaus = '0'; }
					if ( empty($_POST) && $timeranaus == '0' && ( !empty($answersb) && strlen($answersb[0])>1 ) ) {     // Timer 30 Sekunden
						$admincanstop = 'clearInterval(myTimer)';
						  // if ( current_user_can('administrator') ) $admincanstop = 'clearInterval(myTimer)'; else $admincanstop='';
						$antwortmaske .= '<style>.progress:before {content:attr(value) " Sekunden" }</style><progress onclick="'.$admincanstop.'" id="sec" class="progress" value="" max="30"></progress>';
						$antwortmaske .= "<!-- noformat on --><script>var myTimer; function clock(c) { myTimer = setInterval(myClock, 1000); ";
						$antwortmaske .= "     function myClock() { document.getElementById('sec').value = --c; ";
						$antwortmaske .= "       if (c == 0) { clearInterval(myTimer); document.getElementById('ans2').checked=true;document.getElementById('ans2').value='".__(' no answer (timeout occured)','WPdoodlez')."'; document.getElementById('quizform').submit(); }  }  } ";
						$antwortmaske .= "clock(30);</script><!-- noformat off -->";
					}
				}	
				$antwortmaske .= $ansmixed;
				$theForm = $formstyle . $antwortmaske.'<input onclick="return empty();" style="display:'.$showsubmit.';margin-top:10px;width:100%" type="submit" value="'.__('check answer','WPdoodlez').'" class="quiz_button"></form></div>'. $letztefrage;
			} else {    // Zertifikat ausgeben
				$theForm = '<script>document.getElementsByClassName("entry-title")[0].style.display = "none";</script>';
				$theForm .= '<img src="'.plugin_dir_url(__FILE__).'/quizkatbilder/lightbulb-1000-250.jpg" style="width:100%;border-radius:3px"><div style="text-align:center;padding-top:20px;font-size:1.5em">'. __('test terminated. thanks.','WPdoodlez');
				$theForm .= '<br><br><br>'.__('you have ','WPdoodlez') . (@$_COOKIE['wrongscore'] + @$_COOKIE['rightscore']).' Fragen beantwortet,<br>davon ' .@$_COOKIE['rightscore']. '  ('.$sperct.'%) richtig und '.@$_COOKIE['wrongscore'].' ('. (100 - $sperct) .'%) falsch.';
				$theForm .= '<p style="margin-top:20px"><progress id="file" value="'.$sperct.'" max="100"> '.$sperct.' </progress></p>';
				if ( $sperct < 50 ) { $fail='<span style="color:tomato">leider nicht</span>'; } else { $fail=''; }
				$theForm .= '<p style="margin-top:20px">In Schulnoten ausgedrückt: '.get_schulnote( $sperct ).',<br>somit <strong>'.$fail.' bestanden</strong>.</p>';
				$theForm .= '<p style="font-size:0.7em;margin-top:2em">'.date_i18n( 'D, j. F Y, H:i:s', false, false);
				$theForm .= '<span style="font-family:Brush Script MT;font-size:2em;padding-left:24px">'.wp_get_current_user()->display_name.'</span></p>';
				$theForm .= '<p style="font-size:0.7em">'. get_bloginfo('name') .' &nbsp; '. get_bloginfo('url') .'<br>'.get_bloginfo('description'). '</p></div>';
				if( class_exists( 'PB_ChartsCodes' ) ) {
					$piesum = $sperct . ',' . (100 - $sperct);
					$theForm .= do_shortcode('[chartscodes_polar accentcolor="1" title="" values="'.$piesum.'" labels="richtig,falsch" absolute="1"]');
				}	
			}
		}		
		return $theForm;
	else :
		return $content;
	endif;
}
add_filter( 'the_content', 'quiz_show_form' );


function quizz_add_custom_box() {
    $screens = array( 'question' );
    foreach ( $screens as $screen ) {
        add_meta_box(
            'answers-more',
            __( 'Answers &amp; more', 'WPdoodlez' ),
            'quizz_inner_custom_box',
            $screen, 'normal'
        );
    }
}
add_action( 'add_meta_boxes', 'quizz_add_custom_box' );

function quizz_inner_custom_box( $post ) {
	// Add an nonce field so we can check for it later.
	wp_nonce_field( 'quizz_inner_custom_box', 'quizz_inner_custom_box_nonce' );
	// Use get_post_meta() to retrieve an existing value from the database and use the value for the form.
	$value = get_post_meta( $post->ID, 'quizz_answer', true );
	$valueb = get_post_meta( $post->ID, 'quizz_answerb', true );
	$valuec = get_post_meta( $post->ID, 'quizz_answerc', true );
	$valued = get_post_meta( $post->ID, 'quizz_answerd', true );
	$zusatzinfo = get_post_meta( $post->ID, 'quizz_zusatzinfo', true );
	echo '<label for="quizz_answer">' . _e( "Answer", 'WPdoodlez' ) . ' A</label> ';
	echo ' <input type="text" id="quizz_answer" name="quizz_answer" value="' . esc_attr( $value ) . '" size="75">';
	$value1 = get_post_meta( $post->ID, 'quizz_exact', true);
	echo ' <input type="checkbox" name="quizz_exact" id="quizz_exact" value="exact" ' . (($value1=="exact") ? " checked" : "") . '>'. __('exact match (also enforces case)','WPdoodlez');
	echo '<br />';
	// Distraktoren, im Quiz werden die Antworten gemischt
	echo '<label for="quizz_answerb">' . _e( "Answer", 'WPdoodlez' ) . ' B</label> ';
	echo ' <input type="text" id="quizz_answerb" name="quizz_answerb" value="' . esc_attr( $valueb ) . '" size="75"> optional<br>';
	echo '<label for="quizz_answerc">' . _e( "Answer", 'WPdoodlez' ) . ' C</label> ';
	echo ' <input type="text" id="quizz_answerc" name="quizz_answerc" value="' . esc_attr( $valuec ) . '" size="75"> optional<br>';
	echo '<label for="quizz_answerd">' . _e( "Answer", 'WPdoodlez' ) . ' D</label> ';
	echo ' <input type="text" id="quizz_answerd" name="quizz_answerd" value="' . esc_attr( $valued ) . '" size="75"> optional<br>';
	echo '<label for="quizz_zusatzinfo">' . _e( "moreinfo", 'WPdoodlez' ) . ' </label> ';
	echo ' <input type="text" id="quizz_zusatzinfo" name="quizz_zusatzinfo" value="' . esc_attr( $zusatzinfo ) . '" size="75"> optional<br>';
	global $wpdb;
	$query = "SELECT `post_id` FROM $wpdb->postmeta WHERE `meta_value`='%s'";
	$prev = $wpdb->get_var( $wpdb->prepare($query, $post->ID) );
	echo '<p><label for="quizz_prevlevel">'. __( "previous question", 'WPdoodlez' ) . '</label> ';
	  $args = array(
			'post_type' => 'question',
			'exclude' => $post->ID,
			'post_status' => 'publish'
		);
	  $questions = get_posts( $args );
	echo ' <select id="quizz_prevlevel" name="quizz_prevlevel"><option value="0">keine</option>';
	  foreach ($questions as $question) {
		echo "<option value='" . $question->ID . "'". (( $prev == $question->ID ) ? ' selected' : '') . ">" . $question->post_title . "-" . $question->post_content ."</option>";
	  }
	echo '</select></p>';
	$last = get_post_meta( $post->ID, 'quizz_last', true);
	echo '<input type="checkbox" name="quizz_last" id="quizz_last" value="last"' . (($last=="last") ? " checked" : "" ) . '>'. __('last question?','WPdoodlez');
	$lastlevel = get_post_meta( $post->ID, 'quizz_lastpage', true);
	$args = array(
		'post_type' => 'page',
		'post_status' => 'publish'
	);
	$lastpages = get_posts($args);
	echo ' <select id="quizz_lastpage" name="quizz_lastpage">';
	echo '<option value="0">keine</option>';
	foreach ($lastpages as $lastpage) {
		echo "<option value='" . $lastpage->ID . "'". (( $lastlevel == $lastpage->ID ) ? ' selected' : '') . ">" . $lastpage->post_title ."</option>";
	}
	echo '</select>';
	$rightstat = get_post_meta( $post->ID, 'quizz_rightstat', true);
	$wrongstat = get_post_meta( $post->ID, 'quizz_wrongstat', true);
	if (!empty($rightstat) || !empty($wrongstat)) echo '<p>'. __('stats right wrong answers','WPdoodlez').': '.@$rightstat[0].' / '.@$wrongstat[0].'</p>';
}

function quizz_save_postdata( $post_id ) {
  // Check if our nonce is set.We need to verify this came from the our screen and with proper authorization
  if ( ! isset( $_POST['quizz_inner_custom_box_nonce'] ) )
    return $post_id;
  $nonce = $_POST['quizz_inner_custom_box_nonce'];
  // Verify that the nonce is valid.
  if ( ! wp_verify_nonce( $nonce, 'quizz_inner_custom_box' ) ) return $post_id;
  // If this is an autosave, our form has not been submitted, so we don't want to do anything.
  if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return $post_id;
  // Check the user's permissions.
  if ( 'page' == $_POST['post_type'] ) {
    if ( ! current_user_can( 'edit_page', $post_id ) )
        return $post_id;
  } else {
    if ( ! current_user_can( 'edit_post', $post_id ) )
        return $post_id;
  }
  // Sanitize user input.OK, its safe for us to save the data now
  $myanswer = sanitize_text_field( $_POST['quizz_answer'] );
  $myanswerb = sanitize_text_field( $_POST['quizz_answerb'] );
  $myanswerc = sanitize_text_field( $_POST['quizz_answerc'] );
  $myanswerd = sanitize_text_field( $_POST['quizz_answerd'] );
  $zusatzinfo = sanitize_text_field( $_POST['quizz_zusatzinfo'] );
  $fromlevel = $_POST['quizz_prevlevel'];
  $exact = $_POST['quizz_exact'];
  $lastlevel_bool = $_POST['quizz_last'];
  $lastpage = $_POST['quizz_lastpage'];
  // Update the meta field in the database.
  update_post_meta( $post_id, 'quizz_answer', $myanswer );
  update_post_meta( $post_id, 'quizz_answerb', $myanswerb );
  update_post_meta( $post_id, 'quizz_answerc', $myanswerc );
  update_post_meta( $post_id, 'quizz_answerd', $myanswerd );
  update_post_meta( $post_id, 'quizz_zusatzinfo', $zusatzinfo );
  update_post_meta( $fromlevel, 'quizz_nextlevel', $post_id );
  update_post_meta( $post_id, 'quizz_exact', $exact );
  update_post_meta( $post_id, 'quizz_last', $lastlevel_bool );
  update_post_meta( $post_id, 'quizz_lastpage', $lastpage );
}
add_action( 'save_post', 'quizz_save_postdata' );

add_action( 'manage_posts_extra_tablenav', 'admin_order_list_top_bar_button', 20, 1 );
function admin_order_list_top_bar_button( $which ) {
    global $current_screen;
    if ('question' == $current_screen->post_type) {
     $nonce = wp_create_nonce( 'dnonce' );
     echo " <a href='".$_SERVER['REQUEST_URI']."&quizzzcsv=true&nonce=".$nonce."' class='button'>";
        _e( 'Import from CSV', 'WPdoodlez' );
        echo '</a> ';
    }
}

// Add the custom columns to the book post type:
add_filter( 'manage_question_posts_columns', 'set_custom_edit_question_columns' );
function set_custom_edit_question_columns($columns) {
    $new = array();
	$columns['frageantwort'] = __( 'question/answer', 'WPdoodlez' );
	$columns['quizcategory'] = __( 'quiz category', 'WPdoodlez' );
    $frageantwort = $columns['frageantwort'];  // save the tags column
    $quizcatcol = $columns['quizcategory'];  
    unset($columns['frageantwort']);   // remove it from the columns list
    unset($columns['quizcategory']);   
    foreach($columns as $key=>$value) {
        if($key=='tags') {  // when we find the date column
           $new['frageantwort'] = $frageantwort;  // put the tags column before it
           $new['quizcategory'] = $quizcatcol;
        }    
        $new[$key]=$value;
    }  
	return $new;
}
// Add the data to the custom columns for the book post type:
add_action( 'manage_question_posts_custom_column' , 'custom_question_column', 10, 2 );
function custom_question_column( $column, $post_id ) {
    switch ( $column ) {
		case 'frageantwort' :
			echo get_the_content( $post_id ).'<br>';
			echo '<b>'.get_post_meta( $post_id , 'quizz_answer' , true ).'</b>'; 
			break;
		case 'quizcategory' :
			$terms = get_the_terms($post_id, 'quizcategory'); // Get all terms of a taxonomy
			if ( $terms && !is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
						echo $term->name; 
				}
			}	
			break;
    }
}

// -------- hangman spiel mit Wort laden  //  echo play_hangman('Katze');

function printPage($image, $guesstemplate, $which, $guessed, $wrong) {
	global $hang;
	global $wp;
	$gtml = '<style>input[type=button][disabled],button:disabled,button[disabled] { border: 1px solid #999999;background-color:#cccccc;color: #666666;}</style>';
	$gtml .= '<code style="font-family:monospace;font-size:1.5em">';
	$gtml .= $guesstemplate. '<br>';
	$formurl = add_query_arg( array('hangman'=>'1' ), home_url( $wp->request ) );
	$gtml .= '</code><form name="galgen" method="post" action="'. $formurl .'">';
	$gtml .= '<input type="hidden" name="wrong" value="'.$wrong.'" />';
	$gtml .= '<input type="hidden" name="lettersguessed" value="'.$guessed.'" />';
	$gtml .= '<input type="hidden" name="word" value="'.$which.'" />';
	$gtml .= '<input type="hidden" name="letter" id="letter" size="1" style="max-size:1" autofocus /><br><br>';
	$ci=0;
	foreach (range('A', 'Z') as $char) {
		$ci += 1;
		$gtml .= '<input style="width:35px;padding:5px;margin-bottom:5px" onclick="document.getElementById(\'letter\').value=this.value;this.form.submit();" type="button" value="'.$char.'" ';
		if ( !empty($guessed) && strpos($guessed,$char) !== false ) { $gtml .= ' disabled'; }
		$gtml .= '> &nbsp;';
	}  
	$gtml .= '<input style="display:none" type="submit" value="raten"></form>';
	$gtml .= '<div style="float:left;width:38%"><code style="font-family:monospace;font-size:1.3em;line-height:0">'.$image.'</code></div>';
	$gtml .= '<div style="padding-top:5%;float:left;width:58%;height:220px"><b>Galgenmännchen</b> Die Lösung kann aus mehreren Wörtern bestehen. Leerzeichen, Umlaute und Sonderzeichen wurden aus den Lösungswörtern entfernt. Es bleiben <b>'.(strlen($guesstemplate)/ 2) .'</b> Zeichen</div>';
	return $gtml;
}

function play_hangman($rein) {
	global $hang;
	$hang = array();
	$hang[0] = nl2br(str_replace (" ","&nbsp;",
	' _______
	 |/    | 
	 |
	 0
	 |
	 |
	 | 
	_|_______
	'));
	$hang[1] =nl2br(str_replace (" ","&nbsp;",
	' _______ 
	 |/    | 
	 |     o
	 1
	 |
	 |
	 | 
	_|_______
	'));
	$hang[2] =nl2br(str_replace (" ","&nbsp;",
	' _______
	 |/    | 
	 |     o
	 2     |
	 |     |
	 |
	 | 
	_|_______
	'));
	$hang[3] =nl2br(str_replace (" ","&nbsp;",
	' _______
	 |/    | 
	 |     o
	 3     |
	 |     |
	 |    /
	 | 
	_|_______
	'));
	$hang[4] =nl2br(str_replace (" ","&nbsp;",
	' _______
	 |/    | 
	 |     o
	 4     |
	 |     |
	 |    / \
	 | 
	_|_______
	'));
	$hang[5] =nl2br(str_replace (" ","&nbsp;",
	' _______
	 |/    | 
	 |     o
	 5   --|
	 |     |
	 |    / \
	 | 
	_|_______
	'));
	$hang[6] =nl2br(str_replace (" ","&nbsp;",
	' _______
	 |/    | 
	 |     o
	 6   --|--
	 |     |
	 |    / \
	 | 
	_|_______
	'));
	global $words;
	$ers = array('Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss' );
	$rein = strtr($rein,$ers);
	$rein = preg_replace("/[^A-Za-z]/", '', $rein);
	$words = strtoupper($rein);
	$method = $_SERVER["REQUEST_METHOD"];
	if ($method == "POST") {
		$which = $_POST["word"];
		$word  = $words;
		$wrong = $_POST["wrong"];
		$lettersguessed = $_POST["lettersguessed"];
		$guess = $_POST["letter"];
		$letter = strtoupper($guess[0]);
		if(!strstr($word, $letter)) {	$wrong++;  }
		$lettersguessed = $lettersguessed . $letter;
		$guesstemplate = matchLetters($word, $lettersguessed);
		if (!strstr($guesstemplate, "_")) {
			return '<div style="margin-top:30px;font-size:1.2em;color:white;background-color:green;display:inline-block; width:100%; padding:5px; border:1px solid #ddd;border-radius:3px;margin-bottom:15px">Gewonnen - Gratulation. Sie haben <i>'.$word.'</i> erraten.</div>';
		} else if ($wrong >= 6) {
			return '<div style="margin-top:30px;font-size:1.2em;color:white;background-color:tomato;display:inline-block; width:100%; padding:5px; border:1px solid #ddd;border-radius:3px;margin-bottom:15px">Verloren - <i>'.$word.'</i> war die Lösung.</div>';
		} else {
			return printPage($hang[$wrong], $guesstemplate, $which, $lettersguessed, $wrong);
		}
	} else {
		$word =  $words;
		$len = strlen($word);
		$guesstemplate = str_repeat('_ ', $len);
		return printPage($hang[0], $guesstemplate, 0, "", 0);
	}
}

function matchLetters($word, $guessedLetters) {
	$len = strlen($word);
	$guesstemplate = str_repeat("_ ", $len);
	for ($i = 0; $i < $len; $i++) {
		$ch = $word[$i];
		if (strstr($guessedLetters, $ch)) {
			$pos = 2 * $i;
			$guesstemplate[$pos] = $ch;
		}
	}
	return $guesstemplate;
}
// Hangman Ende
//   ----------------------------- Quizzz module ended -------------------------------------

// ------------------------------- Shortcode für crosswordquizz ----------------------------------

// Register frontent scripts and styles - will be enqueued on shortcode usage
add_action('wp_enqueue_scripts', 'cwdcw_setup_wdscript');
function cwdcw_setup_wdscript() {
    global $post;
	wp_register_style('crossword-style', plugins_url('crossword.min.css', __FILE__) );
	wp_register_script('crossword-script', plugins_url('crossword.min.js', __FILE__), array('jquery'), false, '', true);
}

function xwordquiz( $atts ) {
	$attrs = shortcode_atts( array( 'items' => -1 ), $atts ); 
    $args=array(
      'orderby'=> 'rand',
      'order'=> 'rand',
      'post_type' => 'question',
      'post_status' => 'publish',
      'posts_per_page' => $attrs['items'],
	  'showposts' => $attrs['items'],
    );
    $my_query = new WP_Query($args);
	$rows=array();
    if( $my_query->have_posts() ) {
      while ($my_query->have_posts()) : $my_query->the_post(); 
		$answers = get_post_custom_values('quizz_answer');
		$crossohneleer =  (strpos($answers[0], ' ') == false);
		$crossant = preg_replace("/[^A-Za-zäöüÄÖÜß]/", '', esc_html($answers[0]) );
		$crossfrag = get_the_content();
		// ansonsten freie Antwort anfordern von Antwort 1
		if ($crossohneleer &&
			strlen($crossant) <= 12 && strlen($crossant) >= 2 &&
			strlen($crossfrag) <= 40 && strlen($crossfrag) >= 5 ) {
				$element = array( "word" => $crossant, "clue" => $crossfrag );
				$rows[] = $element;
		}	
      endwhile;
    }
    wp_reset_query();  
	// Enqueue the registered scipts and css
	wp_enqueue_style('crossword-style');
	wp_enqueue_script('crossword-script');
    /* Adds additional data */
     wp_localize_script('crossword-script', 'crossword_vars', array(
        'cwdcw_ansver' => 'yes',
        'cwdcw_ansver_incorect' => 'yes',
    ));
    $html = '';
    if ($rows) {
		$html .= '<script>document.getElementById("primary").className="page-fullwidth"</script>';
        $html .= '<style>@media print{@page {size: landscape}}#secondary{width:100%!important}</style><div class="crossword_wrapper">';
        $html .= '<div class="cwd-row cwd-crossword-row"><div class="cwd-crossword-container">';
        $html .= ' <div class="cwd-center cwd-crossword" id="cwd-crossword"></div><br>';
        $html .= '</div></div>';
        $html .= '<div class="cwd-center cwd-crossword-questions">';
        $i = 1;
        foreach ($rows as $row) {
				if ($i == 16){ break; }
				if ( is_user_logged_in() ) {
					$adminsolves = 'onclick="javascript:for (let el of document.querySelectorAll(\'.cwd-hide\')) { if (el.style.visibility==\'hidden\') { el.style.visibility=\'visible\';} else {el.style.visibility = \'hidden\';} };" ';
				} else {
					$adminsolves = '';
				}
				$ansmixed = '&nbsp; <a '.$adminsolves.' style="border-radius:3px;background-color:#eee;font-weight:700;padding:0 3px 0 6px;letter-spacing:.5em;font-family:monospace" title="'.strlen(esc_html($row['word'])).__(' characters long. ','WPdoodlez').'">'.preg_replace( '/[^( |aeiouAEIOU.)$]/', '_', esc_html(strtoupper($row['word']))).'</a>';
				$html .= '<div class="cwd-line">';
				$html .= '<input autocomplete="offi" class="cwd-word" data-counter="' . $i . '" type="hidden" value="' . $row['word'] . '">';
				$html .= '<div class="cwd-clue" data-counter="' . $i . '">' . $i . '. ' . $row['clue'] . ' ' .$ansmixed;
        		$html .= '<span class="cwd-hide" style="float:right"><strong>'.strtoupper($row['word']).'</strong></span></div></div>';
			$i++;
		}
        $html .= '</div>';
        $html .= '</div><div class="clearfix"></div>';
        $html .= '<style></style>';
        $html .= "<script></script>";
        $html .= "<script>
        /* <![CDATA[ */
        var optional_crossword_vars = {
            'cwdcw_correct_ansver':'true',
            'cwdcw_incorrect_ansver':'true'
        };
        /* ]]> */
        </script>";
        $message = __('Congratulations! You solved the crossword game.', 'WPdoodlez');
        $html .= '<div id="modal_form_crossword">
                    <span id="modal_close">X</span>
                    <div class="content">' . do_shortcode($message) . '</div>
                  </div>
                  <div id="overlay"></div>';
    }
    return $html;

}
add_shortcode( 'xwordquiz', 'xwordquiz' );
//   ----------------------------- Kreuzwort module ended -------------------------------------
?>
