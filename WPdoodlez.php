<?php
/**
Plugin Name: WP Doodlez
Plugin URI: https://github.com/svenbolte/WPdoodlez
Author URI: https://github.com/svenbolte
Description: plan appointments, query polls and place a quiz or a crossword or wordpuzzle game on your wordpress site (with csv import for questions)
Contributors: Robert Kolatzek, PBMod, others
License: GPL v3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: WPdoodlez
Domain Path: /lang/
Author: PBMod
Version: 9.1.1.153
Stable tag: 9.1.1.153
Requires at least: 6.0
Tested up to: 6.7.1
Requires PHP: 8.2
*/

if (!defined('ABSPATH')) exit;

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
	// quiztype
	if ( $post->post_type == 'question' ) {
		if ($xpenguin) { $single_template = dirname( __FILE__ ) . '/question-template-penguin.php';	} else {
			$single_template = dirname( __FILE__ ) . '/question-template.php';
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
//  only use one shortcode per page or post - not more of them on one page/post!
function wpdoodlez_sc_func($atts) {
	wp_enqueue_script( "WPdoodlez", plugins_url( 'WPdoodlez.js', __FILE__ ), array('jquery'), null, true);
	wp_enqueue_style( "WPdoodlez", plugins_url( 'WPdoodlez.css', __FILE__ ), array(), null, 'all');
	global $post;
	$args = shortcode_atts(array( 'id' => 0, 'chart' => 1 ), $atts);
	$output ='';
	$qargs = array(
		'p'         => $args['id'],
		'post_type' => array('wpdoodle'),
		'post_status' => 'publish',
		'posts_per_page' => 2
	);
	$query1 = new WP_Query( $qargs );
	if ( $query1->have_posts() ) {
		while ( $query1->have_posts() ) {
			$query1->the_post();
			$output .= get_doodlez_content(intval($args['chart']));
			$output .= '<script>var wpdoodle_ajaxurl = "' . admin_url( 'admin-ajax.php', is_ssl() ? 'https' : 'http' ).'";'; 
			$output .= 'var wpdoodle = "'. md5( AUTH_KEY . get_the_ID() ). '";</script>';
		}
		wp_reset_postdata();
	}
	return $output;
}
add_shortcode('wpdoodlez_sc', 'wpdoodlez_sc_func');


// wpdoodlez-adminstats for polls
function wpdoodlez_stats_func($atts) {
	global $wp,$post;
	$args = shortcode_atts(array( 'id' => 0, 'type' => 'poll' ), $atts);   // Type = poll oder doodlez
	if (intval($args['id']) > 0) $idfilter=array(intval($args['id'])); else $idfilter='';
	$wpdtype = esc_html($args['type']);
	$output ='';
	$qargs = array(
		'post_type' => array('wpdoodle'),
		'post_status' => 'publish',
		'post__in'  => $idfilter,
		'posts_per_page' => -1
	);
	$query1 = new WP_Query( $qargs );
	if ( $query1->have_posts() ) {
		while ( $query1->have_posts() ) {
			$query1->the_post();
			$suggestions = $votes_cout  = [ ];
			$customs     = get_post_custom( get_the_ID() );
			foreach ( $customs as $key => $value ) {
				if ( !preg_match( '/^_/is', $key ) ) {
					$suggestions[ $key ] = $value;
					$votes_cout[ $key ]  = 0;
				}
			}
			$polli = array_key_exists('vote1', $suggestions);
			if ( $wpdtype == 'poll' && $polli ) {
				$votes = get_option( 'wpdoodlez_' . md5( AUTH_KEY . get_the_ID() ), array() );
				$cct = 0;
				$voteip='';$firstvote='';$votezeit='';
				foreach ( $votes as $name => $vote ) {
					$cct += 1;
					foreach ( $suggestions as $key => $value ) {
						if (!in_array($key, array("post_views_count","post_views_timestamp","likes","limit_modified_date" ))) {
							if ( !empty($vote[ $key ]) ) {	$votes_cout[ $key ] ++; }
						}	
					}
					if (isset($vote['zeit'])) {
						$diff = time() - $vote['zeit'];
						if (round((intval($diff) / 86400), 0) < 30) { $newcolor = "yellow"; } else { $newcolor = "white"; }
						$votezeit = '<abbr title="'.__( 'vote', 'WPdoodlez' ).' '.$cct.'" class="newlabel '.$newcolor.'">'.wp_date(get_option('date_format').' '.get_option('time_format'), $vote['zeit'] ).' '.ago($vote['zeit']).'</abbr></br>';
						if ($cct == 1 ) $firstvote = $votezeit;
					} else { $votezeit=''; $firstvote=''; }
					if (isset($vote['ipaddr'])) $voteip = $vote['ipaddr']; else $voteip='';
				}	
				$xsum = 0;
				foreach ( $votes_cout as $key => $value ) {
					if (!in_array($key, array("post_views_count","post_views_timestamp","likes","limit_modified_date" )) && $value > 0 ) {
						$xsum += $value;
					}
				}
				$titelqm = get_the_title();
				if ( substr($titelqm, -1, 1) != '?' ) $titelqm .= '?';
				$output .= '<table>';	
				$output .= '<thead><tr><th colspan=3><i class="fa fa-lg fa-check-square-o"></i> <a title="'.__( 'Vote!', 'WPdoodlez' ).'" href="'.get_the_permalink().'">'.get_the_id().' &nbsp; '.$titelqm.'</a></th></tr></thead>';
				$xperc = 0;
				$votecounter = 0;
				foreach ( $suggestions as $key => $value ) {
					 if (!in_array($key, array("post_views_count","post_views_timestamp","likes","limit_modified_date" ))) {
							$votecounter += 1;
							if ($xsum>0) $xperc = round(($votes_cout[ $key ]/$xsum) * 100,1);
							$output .= '<tr><td style="text-align:center">';
							$output .= $value[ 0 ] .'</label></td><td style="text-align:center">'.$votes_cout[ $key ].'</td>';
							$output .= '<td style="min-width:200px;width:30%"><progress style="width:100%" max="100" value="'.$xperc.'"></td></tr>';
					}	
				}
				$output .= '<tfoot><tr><td style="text-transform:none">' . __( 'total votes', 'WPdoodlez' ) . ' '.$firstvote.'</td><td style="text-align:center;font-size:1.2em">'.$xsum.'</td><td style="text-transform:none">'. $votezeit;
				// Wenn ipflag plugin aktiv und user angemeldet
				if( class_exists( 'ipflag' ) && is_user_logged_in() ) {
					global $ipflag;
					if(isset($ipflag) && is_object($ipflag)){
						if(($info = $ipflag->get_info($voteip)) != false){
							$output .= ' '.$info->code .  ' ' .$info->name. ' ' . $ipflag->get_flag($info, '').' '.$voteip;
						} else { $output .= ' '. $ipflag->get_flag($info, '').' '.$voteip; }
					} 
				}	
				$output .= '</td></tr></tfoot>';
				$output .= '</table>';
			} else if ( $wpdtype == 'doodlez' && !$polli ) {      
				// Kopfzeilen     // Type = 'doodlez'
				$output .= '<table><thead><tr><th colspan=10><i class="fa fa-calendar-o"></i> 
					<a href="'.get_the_permalink().'">
					' . __( 'Voting', 'WPdoodlez' ) . ': '.get_the_title().'</a> &nbsp;'. get_the_excerpt(). '</th></tr>';
				$output .= '<tr><th>' . __( 'User name', 'WPdoodlez'  ) . '</th><th><i class="fa fa-clock-o"></i></th>';
				foreach ( $suggestions as $key => $value ) {
					if (!in_array($key, array("post_views_count","post_views_timestamp","likes","limit_modified_date" ))) {
						$output .=  '<th style="word-break:break-all;overflow-wrap:anywhere">';
						// ICS Download zum Termin anbieten
						if( function_exists('export_ics') && is_singular() ) {
							$nextnth = strtotime($key);
							$nextnth1h = strtotime($key);
							$output .=  ' <a title="'.__("ICS add reminder to your calendar","penguin").'" href="'.home_url(add_query_arg(array($_GET, 'start' =>wp_date('Ymd\THis', $nextnth), 'ende' => wp_date('Ymd\THis', $nextnth1h) ),$wp->request.'/icalfeed/')).'"><i class="fa fa-calendar-check-o"></i></a> ';
						}	
						$output .=  $key . '</th>';
					}	
				}
				$output .=  '</tr></thead><tbody>';
				$votes = get_option( 'wpdoodlez_' . md5( AUTH_KEY . get_the_ID() ), array() );
				// Inhalt Abstimmungen
				foreach ( $votes as $name => $vote ) {
					$output .= '<tr>';
					$output .=  '<td style="text-align:left">'.$name.'</td>'; 
					$output .=  '<td style="text-align:left"><abbr>'; 
					if (isset($vote['zeit'])) $votezeit = wp_date(get_option('date_format').' '.get_option('time_format'), $vote['zeit'] ); else $votezeit='';
					if (isset($vote['ipaddr'])) $voteip = $vote['ipaddr']; else $voteip='';
					// Wenn ipflag plugin aktiv und user angemeldet
					$output .=  ' ' . $votezeit.'</abbr></td>';
					foreach ( $suggestions as $key => $value ) {
						if (!in_array($key, array("post_views_count","post_views_timestamp","likes","limit_modified_date" ))) {
							$output .=  '<td style="text-align:center">';
							if ( !empty($vote[ $key ]) ) {
								$votes_cout[ $key ] ++;
								$output .=  '<label data-key="' . $key . '">'. $value[ 0 ].'</label>';
							} else {
								$output .=  '<label></label>';
							}
							$output .=  '</td>';
						}
					}	
					$output .= '</tr>';
				}
				$output .= '</tbody><tfoot>';
				$output .= '<tr><td>' . __( 'total votes', 'WPdoodlez' ).':</td><td></td>';
				foreach ( $votes_cout as $key => $value ) {
					if (!in_array($key, array("post_views_count","post_views_timestamp","likes","limit_modified_date" ))) {
						$output .= '<td>'. $value.'</td>';
					}
				}
				$output .= '</tr></tfoot>';
				$output .= '</table>';
			}	
		}
		wp_reset_postdata();
	}
	return $output;
}
add_shortcode('wpdoodlez_stats', 'wpdoodlez_stats_func');


/**
 * Save a single vote as ajax request and set cookie with given user name
 */
function wpdoodlez_save_vote() {
    $values = get_option( 'wpdoodlez_' . strval($_POST[ 'data' ][ 'wpdoodle' ]), array() );
	$name   = sanitize_text_field( $_POST[ 'data' ][ 'name' ]);
    //	insert only without cookie (or empty name in cookie) update only with same name in cookie
    $nameInCookie = strval($_COOKIE[ 'wpdoodlez-' . $_POST[ 'data' ][ 'wpdoodle' ] ]);
    if ( (isset( $values[ $name ] ) && $nameInCookie == $name) ||
    (!isset( $values[ $name ] ) && empty( $nameInCookie ))
    ) {
        $values[ $name ] = array();
        $values[$name]['zeit'] = time();
        $values[$name]['ipaddr'] = wd_get_the_user_ip();
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
    $values[$name]['zeit'] = time();
    $values[$name]['ipaddr'] = wd_get_the_user_ip();
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
		'can_export'          => true,
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

function columns_wpdoodle($columns) {
    $columns['Shortcode'] = 'Doodlez-Shortcode';
    return $columns;
}
add_filter('manage_wpdoodle_posts_columns', 'columns_wpdoodle');

add_action ('manage_wpdoodle_posts_custom_column','wpdoo_post_custom_columns');
function wpdoo_post_custom_columns($column) {
  global $post;
  $custom = get_post_custom();
  switch ($column) {
    case "Shortcode":
		echo '<input type="text" title="id=&quot;' . esc_attr( $post->ID ) . '&quot;" class="copy-to-clipboard" value="[wpdoodlez_sc id=&quot;' . esc_attr( $post->ID ) . '&quot;]" readonly>';
		echo '<p class="newlabel yellow" style="display: none;">' . __( 'Shortcode copied to clipboard.', 'WPdoodlez' ) . '</p>';
		break;
  }
}

/**
 * Register WPdoodle post type and refresh rewrite rules
 */
function wpdoodlez_rewrite_flush() {
    wpdoodlez_cookie();
    flush_rewrite_rules();
	// Dateien ins uploads/quizbilder kopieren
	require_once(ABSPATH . "wp-includes/pluggable.php"); 
	require_once(ABSPATH . 'wp-admin/includes/media.php');
	require_once(ABSPATH . 'wp-admin/includes/file.php');
	require_once(ABSPATH . 'wp-admin/includes/image.php');
	$folder = plugin_dir_path( __FILE__ ) . "quizbilder";
	$files = array();
	$handler = opendir($folder);
	while ($file = readdir($handler)) {
		if ($file != '.' && $file != '..') $files[] = $file;
	}
	closedir($handler);
	wp_mkdir_p(wp_upload_dir()['basedir'].'/quizbilder');
	foreach ( $files as $file ) {
		copy ($folder.'/'.$file, wp_upload_dir()['basedir'].'/quizbilder/'.$file);
	}
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
	### WPDoodlez<br>
	plan and vote to find a common appointment date with participiants for an event. Works similar to famous Doodle(TM) website.
	It adds a custom post type "wpdoodlez".<br>
	Add Shortcode [wpdoodlez_sc id=post-ID chart=true] to any page or post or html widget to embed poll/doodlez into them<br><br>
	### Poll<br>
	uses same technology and custom post type like WPDoodlez
	create classic polls to let visitors vote about a question<br>
	Add Shortcode [wpdoodlez_stats] to any page or post for stats on all polls in a list
	<br><br>
	### Quizzz<br>
	Play a quiz game with one or four answers
	Quizzz supports categories images and integrates them in single and header if used in theme. It adds a custom post type "question"
	see readme.txt for more details. Put pictures in folder uploads/quizbilder do display quizfragen with a picture
	Add Shortcode [random-question] to any post, page or html-widget<br>
	#### Crossword<br>
	display a crossword game built on the quizzz words
	<br>
	#### Wort-Shuffle<br>
	rearrange word letters in correct order
	<br>
	#### Wordpuzzle
	display a wordpuzzle with random words from quizfragen Answers
	<br><br>
	## Details for WPDoodlez
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
}

// Doodlez Inhalte anzeigen
function get_doodlez_content($chartan) {
	global $wp;
	$htmlout = '';
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
		$htmlout .= '<ul class="footer-menu" style="float:right">';
		$htmlout .= '<li><a href="'.add_query_arg( array('exportteilnehmer'=>'1' ), home_url( $wp->request ) ).'">' . __( 'export to CSV', 'WPdoodlez' ) . '</a></li>';	
		if ( array_key_exists('vote1', $suggestions) ) {
			if (!isset($_GET['admin']) ) {
				$htmlout .= '<li><a href="'.add_query_arg( array('admin'=>'1' ), home_url( $wp->request ) ).'">' . __( 'poll details', 'WPdoodlez' ) . '</a></li>';	
			} else {
				$htmlout .= '<li><a href="'.home_url( $wp->request ) .'">' . __( 'poll results', 'WPdoodlez' ) . '</a></li>';	
			}	
		}
		$htmlout .= '</ul>';
	}	
	/* password protected? */
	if ( !post_password_required() ) {
		// Wenn Feldnamen vote1...20, dann eine Umfrage machen, sonst eine Terminabstimmung
		$polli = array_key_exists('vote1', $suggestions);   // if polli add icon to content
		if ( $polli  && !isset($_GET['admin']) ) {
			$htmlout .= '<i class="fa fa-lg fa-check-square-o"></i> Umfrage: '. get_the_content();
			$hashuser = substr(md5(time()),1,20);
			$votes = get_option( 'wpdoodlez_' . md5( AUTH_KEY . get_the_ID() ), array() );
			$cct = 0;
			$voteip='';$firstvote='';$votezeit='';
			foreach ( $votes as $name => $vote ) {
				$cct += 1;
				foreach ( $suggestions as $key => $value ) {
					if (!in_array($key, array("post_views_count","post_views_timestamp","likes","limit_modified_date" ))) {
						if ( !empty($vote[ $key ]) ) {	$votes_cout[ $key ] ++; }
					}	
				}
				if (isset($vote['zeit'])) {
					$diff = time() - $vote['zeit'];
					if (round((intval($diff) / 86400), 0) < 30) { $newcolor = "yellow"; } else { $newcolor = "white"; }
					$votezeit = '<abbr title="'.__( 'vote', 'WPdoodlez' ).' '.$cct.'" class="newlabel '.$newcolor.'">'.wp_date(get_option('date_format').' '.get_option('time_format'), $vote['zeit'] ).' '.ago($vote['zeit']).'</abbr></br>';
					if ($cct == 1 ) $firstvote = $votezeit;
				} else { $votezeit=''; $firstvote=''; }
				if (isset($vote['ipaddr'])) $voteip = $vote['ipaddr']; else $voteip='';
			}	
			$xsum = 0;
			$pielabel = ''; $piesum = '';
			foreach ( $votes_cout as $key => $value ) {
				if (!in_array($key, array("post_views_count","post_views_timestamp","likes","limit_modified_date" )) && $value > 0 ) {
					$xsum += $value;
					$pielabel.=str_replace(',','',$suggestions[$key][0]).','; $piesum .= $value.','; 
				}
			}
			$htmlout .= '<br><table id="pollselect"><thead><th colspan="4">' . __( 'your choice', 'WPdoodlez'  ) . '</th></thead>';	
			$xperc = 0;
			$votecounter = 0;
			foreach ( $suggestions as $key => $value ) {
				 if (!in_array($key, array("post_views_count","post_views_timestamp","likes","limit_modified_date" ))) {
						$votecounter += 1;
						if ($xsum>0) $xperc = round(($votes_cout[ $key ]/$xsum) * 100,1);
						$htmlout .= '<tr><td  style="text-align:center"><label><input type="checkbox" name="'.$key.'" id="'.$key.'" onclick="selectOnlyThis(this.id)" class="wpdoodlez-input"></td><td>';
						$htmlout .= $value[ 0 ] .'</label></td><td style="text-align:center">'.$votes_cout[ $key ].'</td>';
						$htmlout .= '<td style="max-width:240px"><progress style="width:220px" max="100" value="'.$xperc.'"></td></tr>';
				}	
			}
			$htmlout .= '<tfoot><tr><td colspan=2 style="text-align:center">' . __( 'total votes', 'WPdoodlez' ) . ' '.$firstvote. '</td><td style="text-align:center;font-size:1.2em">'.$xsum.'</td><td style="text-transform:none">'. $votezeit;
			// Wenn ipflag plugin aktiv und user angemeldet
			if( class_exists( 'ipflag' ) && is_user_logged_in() ) {
				global $ipflag;
				if(isset($ipflag) && is_object($ipflag)){
					if(($info = $ipflag->get_info($voteip)) != false){
						$htmlout .= ' '.$info->code .  ' ' .$info->name. ' ' . $ipflag->get_flag($info, '').' '.$voteip;
					} else { $htmlout .= ' '. $ipflag->get_flag($info, '').' '.$voteip; }
				} 
			}	
			$htmlout .= '</td></tr></tfoot>';
			$htmlout .= '<tr><td colspan=4><input type="hidden" id="wpdoodlez-name" value="'.$hashuser.'">';
			$htmlout .= '<button style="width:100%" id="wpdoodlez_poll">' . __( 'Vote!', 'WPdoodlez' ) . '</button></td></tr>';
			$htmlout .= '</table>';
			// only one selection allowed
			$htmlout .= '<script>function selectOnlyThis(id) {';
			$htmlout .= 'for (var i = 1;i <= '. $votecounter.'; i++) { document.getElementById("vote"+i).checked = false; }';
			$htmlout .= ' document.getElementById(id).checked = true;	}</script>';
		} else {
			// Dies nur ausführen, wenn Feldnamen nicht vote1...20 oder Admin Details Modus
			$htmlout .= '<i class="fa fa-lg fa-calendar-o"></i> Terminabstimmung: '. get_the_content();
			$htmlout .= '<h6>' . __( 'Voting', 'WPdoodlez' ) . '</h6>';
			if ( !$polli && function_exists('timeline_calendar')) {
				$outputed_values = array();
				$xevents = array();
				foreach ( $suggestions as $key => $value ) {
					if (!in_array($key, array("post_views_count","post_views_timestamp","likes","limit_modified_date" ))) {
						array_push($xevents, $key);
					}
				}
				foreach ( $suggestions as $key => $value ) {
					if (!in_array($key, array("post_views_count","post_views_timestamp","likes","limit_modified_date" ))) {
						$workername = substr($key,6,4) . substr($key,3,2);
						if (!in_array($workername, $outputed_values)){
							$htmlout .= '<div style="font-size:0.9em;overflow:hidden;vertical-align:top;display:inline-block;max-width:32%;width:32%;margin-right:5px">'.timeline_calendar(substr($key,3,2),substr($key,6,4),$xevents).'</div>';
							array_push($outputed_values, $workername);
						}
					}	
				}
			}	
			$htmlout .= '<table><thead><tr><th>' . __( 'User name', 'WPdoodlez'  ) . '</th><th><i class="fa fa-clock-o"></i></th>';
			foreach ( $suggestions as $key => $value ) {
				if (!in_array($key, array("post_views_count","post_views_timestamp","likes","limit_modified_date" ))) {
					$htmlout .= '<th style="word-break:break-all;overflow-wrap:anywhere">';
					// ICS Download zum Termin anbieten
					if( function_exists('export_ics') && is_singular() ) {
						$nextnth = strtotime($key);
						$nextnth1h = strtotime($key);
						$htmlout .= ' <a title="'.__("ICS add reminder to your calendar","WPdoodlez").'" href="'.home_url(add_query_arg(array($_GET, 'start' =>wp_date('Ymd\THis', $nextnth), 'ende' => wp_date('Ymd\THis', $nextnth1h) ),$wp->request.'/icalfeed/')).'"><i class="fa fa-calendar-check-o"></i></a> ';
					}	
					$htmlout .= $key . '</th>';
				}	
			}
			$htmlout .= '<th title="' . __( 'Manage vote', 'WPdoodlez'  ). '"><i class="fa fa-cogs"></i></th>';
			$htmlout .= '</tr></thead><tbody>';
			if (!empty($_COOKIE[ 'wpdoodlez-' . md5( AUTH_KEY . get_the_ID() ) ] )) {
				$myname = $_COOKIE[ 'wpdoodlez-' . md5( AUTH_KEY . get_the_ID() ) ];
			}
			$htmlout .= '<tr id="wpdoodlez-form">';
			$htmlout .= '<td><input type="text" placeholder="'. __( 'Your name', 'WPdoodlez'  ) .'" ';			
			$htmlout .= ' class="wpdoodlez-input" id="wpdoodlez-name" size="12"></td><td></td>';			
			$votecounter = 0;
			foreach ( $suggestions as $key => $value ) {
				if (!in_array($key, array("post_views_count","post_views_timestamp","likes","limit_modified_date" ))) {
					$votecounter += 1;
					$htmlout .= '<td><label> <input type="checkbox" name="'. $key.'" id="doodsel'.$votecounter.'" class="wpdoodlez-input">';
					$htmlout .= $value[ 0 ].'</label></td>';
				}
			}
			$htmlout .= '<td><button id="wpdoodlez_vote" title="' . __( 'Vote!', 'WPdoodlez'  ) . '" style="padding:6px">';
			$htmlout .= '<i class="fa fa-check-square-o"></i> '. __( 'Go!', 'WPdoodlez'  ) .'</button></td></tr>';
			$votes = get_option( 'wpdoodlez_' . md5( AUTH_KEY . get_the_ID() ), array() );
			// Page navigation
			if ( $polli ) { $nb_elem_per_page = 25; } else { $nb_elem_per_page = 100; }
			$number_of_pages = intval(count($votes)/$nb_elem_per_page)+1;
			$page = isset($_GET['seite'])?intval($_GET['seite']):0;
		
			//	falls ohne paginierung:	foreach ( $votes as $name => $vote ) {
			foreach (array_slice($votes, $page*$nb_elem_per_page, $nb_elem_per_page) as $name => $vote) { 
				$htmlout .= '<tr id="wpdoodlez_' . md5( AUTH_KEY . get_the_ID() ) . '-' . md5( $name ) .'" ';
				$htmlout .= 'class="'. @$myname == $name ? 'myvote' : '' .'">';
				$htmlout .= '<td style="text-align:left">'.$name.'</td>'; 
				$htmlout .= '<td style="text-align:left"><abbr>'; 
				if (isset($vote['zeit'])) $votezeit = wp_date(get_option('date_format').' '.get_option('time_format'), $vote['zeit'] ); else $votezeit='';
				if (isset($vote['ipaddr'])) $voteip = $vote['ipaddr']; else $voteip='';
				// Wenn ipflag plugin aktiv und user angemeldet
				if( class_exists( 'ipflag' ) && is_user_logged_in() ) {
					global $ipflag;
					if(isset($ipflag) && is_object($ipflag)){
						if(($info = $ipflag->get_info($voteip)) != false){
							$htmlout .= ' '.$info->code .  ' ' .$info->name. ' ' . $ipflag->get_flag($info, '').' '.$voteip;
						} else { $htmlout .= ' '. $ipflag->get_flag($info, '').' '.$voteip; }
					} 
				}	
				$htmlout .= ' ' . $votezeit.'</abbr></td>';
				foreach ( $suggestions as $key => $value ) {
					if (!in_array($key, array("post_views_count","post_views_timestamp","likes","limit_modified_date" ))) {
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
					$htmlout .= '<button title="' . __( 'delete', 'WPdoodlez' ) . '" style="padding:3px 6px 3px 6px" class="wpdoodlez-delete" data-vote="'. md5( $name ).'" data-realname="'. $name.'">';
					$htmlout .= '<i class="fa fa-trash-o"></i></button>';
				}
				if ( !empty($myname) && $myname == $name ) {
					$htmlout .= '<button title="'.__( 'edit', 'WPdoodlez' ).'" style="padding:3px 5px 3px 5px" class="wpdoodlez-edit" data-vote="'. md5( $name ). '" data-realname="'. $name.'">';
					$htmlout .= '<i class="fa fa-edit"></i></button>';
				}
				$htmlout .= '</td></tr>';
			}
			$htmlout .= '</tbody><tfoot>';
			$htmlout .= '<tr><td>' . __( 'total votes', 'WPdoodlez' ).':</td><td></td>';
			$pielabel = ''; $piesum = '';
			foreach ( $votes_cout as $key => $value ) {
				if (!in_array($key, array("post_views_count","post_views_timestamp","likes","limit_modified_date" ))) {
					$htmlout .= '<td id="total-'. $key .'">'. $value.'</td>';
					$pielabel .=  strtoupper($key) . ',';
					$piesum .= $value . ',';
				}
			}
			$htmlout .= '<td><b>[' . ($nb_elem_per_page*($page) +1 )  . ' - '.($nb_elem_per_page*($page+1) ) .'</b>]</td>';
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
		if( class_exists( 'PB_ChartsCodes' ) && !empty($pielabel) && (1 === $chartan) ) {
			$htmlout .= do_shortcode('[chartscodes_polar accentcolor="1" title="' . __( 'votes pie chart', 'WPdoodlez' ) . '" values="'.$piesum.'" labels="'.$pielabel.'" absolute="1"]');
		}	
	}		/* END password protected? */
	return $htmlout;
} // end of get doodlez content	

// Doodlez Teilnehmerliste Export CSV (nur als Admin, Aufruf aus WPD-Template)

function wpd_exportteilnehmer() {
	if ( current_user_can('administrator') ) {
		global $wp;
		$htmlout = '';
		$suggestions = $votes_cout  = [ ];
		$customs     = get_post_custom( get_the_ID() );
		foreach ( $customs as $key => $value ) {
			if ( !preg_match( '/^_/is', $key ) ) {
				$suggestions[ $key ] = $value;
				$votes_cout[ $key ]  = 0;
			}
		}
		$polli = array_key_exists('vote1', $suggestions);   // if polli add icon to content
		if ($polli) $pollfilename = __('poll','WPdoodlez'); else $pollfilename = __('appointment','WPdoodlez');
		$filename = 'wpd_teilnehmer_'.$pollfilename.'_'.trim(get_the_title(get_the_ID()));
		$output = fopen('php://output', 'w');
		ob_clean();
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private", false);
		header('Content-Type: text/csv; charset=utf-8');
		header("Content-Disposition: attachment; filename=\"" . $filename . ".csv\";" );
		header("Content-Transfer-Encoding: binary");	
			
		/* password protected? */
		if ( !post_password_required() ) {
			$csvhead = array( __( 'User name', 'WPdoodlez' ),__( 'bookingdate', 'WPdoodlez' ),
					__( 'ip-address', 'WPdoodlez' ), __( 'country', 'WPdoodlez'  ) );
			foreach ( $suggestions as $key => $value ) {
				if (!in_array($key, array("post_views_count","post_views_timestamp","likes","limit_modified_date" ))) {
					$csvhead[] = $key;
				}	
			}
			// print_r($csvhead);   // CSV Tabellenkopf schreiben
			fputcsv( $output, $csvhead, ';');
			// Tabellenzeilen
			$votes = get_option( 'wpdoodlez_' . md5( AUTH_KEY . get_the_ID() ), array() );
			foreach ( $votes as $name => $vote ) {
				$csvout = array();
				$csvout[] = mb_convert_encoding($name, 'ISO-8859-1', 'UTF-8'); 
				if (isset($vote['zeit'])) $votezeit = wp_date(get_option('date_format').' '.get_option('time_format'), $vote['zeit'] ); else $votezeit='';
				if (isset($vote['ipaddr'])) $voteip = $vote['ipaddr']; else $voteip='';
				$csvout[] = $votezeit; 
				$csvout[] = $voteip; 
				// Wenn ipflag plugin aktiv und user angemeldet
				if( class_exists( 'ipflag' ) && is_user_logged_in() ) {
					global $ipflag;
					if(isset($ipflag) && is_object($ipflag)){
						if(($info = $ipflag->get_info($voteip)) != false){
							$csvout[] = $info->code .  ' ' .$info->name;
						} else { $csvout[] =  '-'; }
					} 
				}	
				foreach ( $suggestions as $key => $value ) {
					if (!in_array($key, array("post_views_count","post_views_timestamp","likes","limit_modified_date" ))) {
						if ( !empty($vote[ $key ]) ) {
							$votes_cout[ $key ] ++;
							$csvout[] = mb_convert_encoding($value[ 0 ], 'ISO-8859-1', 'UTF-8');
						} else {
							$csvout[] = '';
						}
					}
				}	
				fputcsv( $output, $csvout, ';');
				// print_r($csvout);
			}
			fclose($output);
		}		/* END password protected? */
		return $htmlout;
	}
} // nur als Admin



// === ------------------- quizzz code and shortcode --------------------------------------------------------------- ===

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
		'supports'            => array(	'title', 'editor', 'thumbnail' )
	);
	register_post_type( 'Question', $args );
    flush_rewrite_rules();

	// CSV Import starten, wenn Dateiname im upload dir public_histereignisse.csv ist	
	if( isset($_REQUEST['quizzzcsv']) && ( $_REQUEST['quizzzcsv'] == true ) && isset( $_REQUEST['nonce'] ) ) {
		$nonce  = filter_input( INPUT_GET, 'nonce', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( ! wp_verify_nonce( $nonce, 'dnonce' ) ) wp_die('Invalid nonce..!!');
		importposts();
		echo '<script>window.location.href="'.get_home_url().'/wp-admin/edit.php?post_type=question"</script>';
	}
	// CSV Export starten	
	if( isset($_REQUEST['quizzzcsvexport']) && ( $_REQUEST['quizzzcsvexport'] == true ) && isset( $_REQUEST['nonce'] ) ) {
		$nonce  = filter_input( INPUT_GET, 'nonce', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( ! wp_verify_nonce( $nonce, 'dnonce' ) ) wp_die('Invalid nonce..!!');
		exportposts();
		echo '<script>window.location.href="'.get_home_url().'/wp-admin/edit.php?post_type=question"</script>';
	}
}
add_action( 'init', 'create_quiz_post' );

// Admin-Spalten ergänzen
// Tabellenkopf und -fuß um Felder erweitern
add_filter('manage_edit-question_columns','qu_edit_admin_columns') ;
function qu_edit_admin_columns($columns) {
  unset($columns['shortcodes']);
  unset($columns['tags']);
  $columns['land'] = __('land of origin','WPdoodlez');
  return $columns;
}

// Inhalte aus benutzerdefinierten Feldern holen und den Spalten hinzufügen
add_action ('manage_question_posts_custom_column','qu_post_custom_columns');
function qu_post_custom_columns($column) {
  global $post;
  $custom = get_post_custom();
  switch ($column) {
    case "land":
		$hkland = get_post_custom_values('quizz_herkunftsland')[0];
		$hkiso = get_post_custom_values('quizz_iso')[0];
      echo $hkland.' '.$hkiso;
    break;
  }
}

// Spielesammlung einblenden
function wpd_games_bar() {
	$spiele = '<ul class="footer-menu" style="display:inline-block">';
	$spiele .= '<li><a style="padding:2px 5px" title="'.__('play crossword','WPdoodlez').'" href="'.add_query_arg( array('crossword'=>1), get_post_permalink() ).'"><i class="fa fa-th"></i> '. __('crossword','WPdoodlez').'</a></li>';
	$spiele .= '<li><a style="padding:2px 5px" title="'.__('play word puzzle','WPdoodlez').'" href="'.add_query_arg( array('crossword'=>2), get_post_permalink() ).'"><i class="fa fa-puzzle-piece"></i> '. __('puzzle','WPdoodlez').'</a></li>';
	$spiele .= '<li><a style="padding:2px 5px" title="'.__('play hangman','WPdoodlez').'" href="'.add_query_arg( array('crossword'=>3), get_post_permalink() ).'"><i class="fa fa-universal-access"></i> '. __('hangman','WPdoodlez').'</a></li>';
	$spiele .= '<li><a style="padding:2px 5px" title="'.__('play sudoku','WPdoodlez').'" href="'.add_query_arg( array('crossword'=>4), get_post_permalink() ).'"><i class="fa fa-table"></i> '. __('Sudoku','WPdoodlez').'</a></li>';
	$spiele .= '<li><a style="padding:2px 5px" title="'.__('play word shuffle','WPdoodlez').'" href="'.add_query_arg( array('crossword'=>5), get_post_permalink() ).'"><i class="fa fa-map-signs"></i> '. __('Shuffle','WPdoodlez').'</a></li>';
	$spiele .= '</ul>';
	return $spiele;
}

// Zusammenstellung eines Quiz mit Wertung Personengebunden
function personal_quiz_exam_func($atts) {
	$atts = shortcode_atts( array(
		'items' => 20, // Default value 20
		'cats' => array('edv'), // Default Cat slug is edv
	), $atts );
	$catarg = sanitize_text_field($atts[ 'cats' ]);
	$anzfragen = (int) $atts['items'];
	if (! empty($_POST)) {   // Antworten und Auswerten
		// Beenden, wenn nonce nicht stimmt.
		if ( !wp_verify_nonce( $_POST['quiz_exam_nonce'], 'quiz_exam_submit' ) ) return "Nonce not valid";
		$auswertung='';
		$erzpunkte = 0;
		foreach ($_POST as $key => $value) {
			if (str_contains($key,'tname')) $tname = esc_html($value);
			if (str_contains($key,'zeit')) $tzeit = esc_html($value);
			if (str_contains($key,'ans')) $given = preg_replace("/[^A-Za-z0-9]/", '', strtolower(esc_html($value)));
			if (str_contains($key,'rt')) $correcta = preg_replace("/[^A-Za-z0-9]/", '', strtolower(esc_html(base64_decode($value))));
			if (str_contains($key,'fra')) {
				if ($given == $correcta) {
					$erzpunkte += 1; 
					$auswertung .= '#'. $value.':R';
				} else {
					$auswertung .= '#'. $value.':F';
				}	
				$auswertung .= ' | ';
			}	
		}
		$sperct = round( ( $erzpunkte / $anzfragen ) * 100 , 0 );
		// Zertifikat ausgeben
		sscanf($tzeit, "%d:%d:%d", $hours, $minutes, $seconds);
		$time_seconds = isset($seconds) ? $hours * 3600 + $minutes * 60 + $seconds : $hours * 60 + $minutes;
		if ($time_seconds >= 1) $timepersec = round(($time_seconds / $anzfragen),1); else $timepersec = 0;
		if ($timepersec > 10) $unusual=''; else $unusual = '<br><span style="color:tomato">'.__('the result is highly unlikely','WPdoodlez').'!</span>';
		$theForm = '<script>document.getElementsByClassName("entry-title")[0].style.display = "none";</script>';
		$theForm .= '<div style="position:relative">
			<div><img src="'.plugin_dir_url(__FILE__).'/lightbulb-1000-300.jpg" style="width:100%"></div>
			<div class="middle" style="opacity:1;position:absolute;top:50%;bottom:50%;width:100%;text-align:center;color:white;font-size:4em;z-index:99999">'.__('quiz exam certificate','WPdoodlez') .'</div>
			</div><div style="text-align:center;padding-top:20px;font-size:1.5em">';
		$theForm .= '<h6>'.$tname.'</h6>'.__('you have ','WPdoodlez') .' '.__('at quiz-exam','WPdoodlez') .' "'. strtoupper($catarg)
			.'"<br>'.__('within ','WPdoodlez') .' ['.$tzeit.'] '.__('minutes','WPdoodlez') .' '. $anzfragen .' '
			.__('questions answered','WPdoodlez').', '.__('that is','WPdoodlez').': '.$timepersec.'/s. '.$unusual
			.' <br>'.__('you targetet','WPdoodlez').' '.$erzpunkte. '  ('.$sperct.'%) '.__('right','WPdoodlez').' '.__('and','WPdoodlez') .' '.$anzfragen - $erzpunkte.' ('. (100 - $sperct) .'%) '.__('wrong','WPdoodlez').'.';
		$theForm .= '<p style="margin-top:20px"><progress id="file" value="'.$sperct.'" max="100"> '.$sperct.' </progress></p>';
		if ( $sperct < 50 ) { 
			$fail='<span style="color:tomato"><i class="fa fa-thumbs-down"></i> '.__('unfortunately not','WPdoodlez').'</span>';
		} else { $fail='<span style="color:green"><i class="fa fa-thumbs-up"></i> '; }
		$theForm .= '<p style="margin-top:20px">'.__('in school grades','WPdoodlez').': '.get_schulnote( $sperct ).',<br>'.__('and','WPdoodlez').' <strong>'.$fail.' '.__('passed','WPdoodlez').'</strong>.</p>';
		$theForm .= '<blockquote style="font-size:.8em">'.__('evaluation','WPdoodlez') .' &nbsp;'.$auswertung.'</blockquote>';
		$theForm .= '<p style="font-size:0.7em;margin-top:2em">'.wp_date( 'D, j. F Y, H:i:s', false, false);
		$theForm .= '<span style="font-family:Brush Script MT;font-size:2.6em;padding-left:24px">'.wp_get_current_user()->display_name.'</span></p>';
		$theForm .= '<p style="font-size:0.7em">'. get_bloginfo('name') .' &nbsp; '. get_bloginfo('url') .'<br>'.get_bloginfo('description'). '</p></div>';
		// Wenn Penguin Theme vorhanden, dann in Datenbank schreiben tabelle pruefungen, ansonsten Tabelle anlegen und speichern
		global $wpdb;
		// creates pruefungen in database if not exists
		$table = $wpdb->prefix . "pruefungen";
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE IF NOT EXISTS " . $table . " (
			id int(11) not null auto_increment,
			name varchar(60) not null,
			seminar varchar(200) not null,
			gesamt int(3) not null,
			erreicht int(3) not null,
			erzielt int(3) not null,
			falsch varchar(4000) not null,
			dauer varchar(60) not null,
			bewertung varchar(500) not null,
			userip varchar(50) not null,
			datum TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`id`) ) $charset_collate;";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
		$html = '';
		// does the inserting, in case the form is filled and submitted
		$table = $wpdb->prefix."pruefungen";
		$name = strip_tags($_POST["tname"], "");
		$seminar = 'Quiz-Examen - '.$catarg;
		$gesamt = $anzfragen;
		$erreicht = $erzpunkte;
		$erzielt =  $sperct;
		$bewertung = get_schulnote( $sperct );
		$falsch = $auswertung;
		$dauer = $tzeit;
		$userip = wd_get_the_user_ip();
		$datum = current_time( "mysql" );
		$filtered_name = preg_replace('/[^a-zA-ZüäöÜÄÖß -]/', '', $name);
		$wpdb->insert(
			$table,
			array(
				"name" => $filtered_name,
				"seminar" => $seminar,
				"gesamt" => $gesamt,
				"erreicht" => $erreicht,
				"erzielt" => $erzielt,
				"bewertung" => $bewertung,
				"falsch" => $falsch,
				"dauer" => $dauer,
				"userip" => $userip,
				"datum" => $datum
			)
		);
		// Zertifikat ausgeben
		return $theForm;
	} else {   // Fragen stellen
		$htmout = '<form id="quizform" action="" method="POST" class="quiz_form form">';
		$htmout .= wp_nonce_field( 'quiz_exam_submit', 'quiz_exam_nonce', 1, 0 );
		$htmout .= '<p>' . sprintf (__('you can take an exam with %s questions. Your name will be used on the certificate after the exam. See GDPR notice at end. Good luck!','WPdoodlez'),$anzfragen).'</p>';
		$htmout .= '<strong>'.__('your name please','WPdoodlez').'</strong>* <input required="required" type="text" placeholder="'.__('firstname lastname','WPdoodlez').'" name="tname" id="tname" size="22" maxlength="40" value="">';
		$htmout .= '&nbsp; Spielzeit: <input style="text-align:center;height:1.3em;width:70px;font-size:1.3em" type="text" name="zeit" id="zeit">
			<script>var start = new Date();
			function leadingZero(tish) {
			if (tish <= 9) { tish = \'0\'+tish; }
			return tish;}
			function zeit() {
			var jetzt = new Date();
			sekunden = parseInt((jetzt.getTime() - start.getTime()) / 1000);
			minuten = parseInt(sekunden / 60);
			sekunden = sekunden % 60;
			text = minuten + ":" + leadingZero(sekunden);
			document.getElementById(\'zeit\').value = text;
			timerID=setTimeout("zeit()", 1000);	}
			zeit();</script>';
		if (!empty($catarg)) {
			// cpt question categories
			$catsarray = explode(',', $catarg );
			$catargs = array(
				'posts_per_page' => esc_html($atts[ 'items' ]),  'ignore_sticky_posts' => 1, 
				'post_type' => 'question',
				'tax_query' => array(
					array(
						'taxonomy' => 'quizcategory',
						'field'    => 'slug',
						'terms' => $catsarray
					)
				),
				'orderby'        => 'rand' // Keep post order the same as the order of post ids
			);
			$rndpostcats = new WP_Query( $catargs );
			$cc = 0;
			while ( $rndpostcats->have_posts() ) {
				$cc += 1;
				$rndpostcats->the_post();
				$herkunftsland = get_post_custom_values('quizz_herkunftsland');
				$quizbild = get_post_custom_values('quizz_bild');
				$answers = get_post_custom_values('quizz_answer');
				$answersb = get_post_custom_values('quizz_answerb');
				$answersc = get_post_custom_values('quizz_answerc');
				$answersd = get_post_custom_values('quizz_answerd');
				$ans = array($answers[0],$answersb[0],$answersc[0],$answersd[0]);
				shuffle($ans);
				$htmout .= '<blockquote class="blockbuulb"><p class="headline">'.$cc.' <span class="newlabel white">'.$herkunftsland[0].'</span> - '.get_the_title().' - '.get_the_content().'</p>';
				// Bild zur Quizfrage einfügen, wenn vorhanden
				$bildshow=''; $bildlink='';
				if (!empty($quizbild[0])) {
					$upload_dir = wp_upload_dir();
					$upload_basedir = $upload_dir['basedir'];
					$file = $upload_basedir . '/quizbilder/' . $quizbild[0];
					if ( file_exists( $file ) ) {
						$bildlink = $upload_dir['baseurl'].'/quizbilder/'.$quizbild[0];
						$bildshow = '<div style="opacity:.8"><a href="'.$bildlink.'"><img style="height:114px;max-height:150px;max-width:150px;min-width:150px;position:absolute;right:0;bottom:0;width:150px"" title="'.$quizbild[0].'" src="'.$bildlink.'"></a></div>';
					} 
					$htmout .= $bildshow;
				}
				$ci = 0;
				foreach ($ans as $an) {
					$ci += 1;
					$htmout .= '<input class="radio-custom" required="required" type="radio" name="ans-'.$cc.'" id="ans-'.$cc.'-'.$ci.'" value="'.$an.'">';
					$htmout .= ' &nbsp; <label for="ans-'.$cc.'-'.$ci.'" class="radio-custom-label"><b>'.chr($ci+64).'</b> &nbsp; '.$an.'</label><br>';
				}	
				$htmout .= '<input type="hidden" name="rt-'.$cc.'" id="rt-'.$cc.'" value="'.base64_encode($answers[0]).'">';
				$htmout .= '<input type="hidden" name="fra-'.$cc.'" id="fra-'.$cc.'" value="'.get_the_id().'">';
				$htmout .= '</blockquote>';
			}
			wp_reset_query();
			
			$htmout .= '<input type="checkbox" class="checkbox-custom" required="required" id="gdpr" name="gdpr">';
			$htmout .= '<label for="gdpr" class="checkbox-custom-label"> Mit der Nutzung dieses Formulars erklären Sie sich mit der Speicherung und Verarbeitung Ihrer Daten durch diese Website (siehe Datenschutz) einverstanden.</label>';
			$htmout .= '<input type="submit" class="submit" style="width:100%" value="'.__('submit exam','WPdoodlez').'"></form>';
			return $htmout;
		}	
	}
}	
// Add the shortcode to the WordPress system
add_shortcode('personal_quiz', 'personal_quiz_exam_func');

// Shortcode Random Question (als Widget auf der Homepage)
function random_quote_func($atts) {
	$atts = shortcode_atts( array(
		'gamebar' => 0, // Spiele-Leiste anzeigen - default wird meta_icons angezeigt
	), $atts );
	$gamebar = sanitize_text_field( (int) $atts[ 'gamebar' ]);
	if ( is_home() || is_front_page() ) {
		$args=array(
		  'orderby'=>'rand','order'=>'rand','post_type'=>'question','post_status'=>'publish','posts_per_page'=>1,'showposts'=>1
		);
		$my_query = new WP_Query($args);
		$accentcolor = get_theme_mod( 'link-color', '#888' );
		$message = '';
		// Ausgabeschleife
		if( $my_query->have_posts() ) {
		  while ($my_query->have_posts()) {
			$my_query->the_post(); 
			$quizkat='';
			$terms = get_the_terms(get_the_id(), 'quizcategory'); // Get all terms of a taxonomy
			if ( $terms && !is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$quizkat .= '<i class="fa fa-folder-open"></i> <a href="'. get_term_link($term) .'">' . $term->name . '</a> &nbsp;';
				}
			}	
			$herkunftsland = get_post_custom_values('quizz_herkunftsland');
			$answers = get_post_custom_values('quizz_answer');
			$quizbild = get_post_custom_values('quizz_bild');
			if (isset($_GET['timer'])) { $timerurl='?timer=1'; } else { $timerurl = '?t=0'; }
			$message .= '<header class="entry-header" style="margin:0 -4px">';
			// Wenn eine Quizkategorie da, Katbild anzeigen
			$terms = get_the_terms(get_the_id(), 'quizcategory'); // Get all terms of a taxonomy
			if ( $terms && !is_wp_error( $terms ) ) {
				$category = $terms;
			} else {
				$category = get_the_category(); 
			}	
			if ( class_exists('ZCategoriesImages') && !empty($category) && z_taxonomy_image_url($category[0]->term_id) != NULL ) {
				$cbild = z_taxonomy_image_url($category[0]->term_id);
				$message .= '<div class="post-thumbnail"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">';
				$message .= '<img alt="Quiz-Kategoriebild" src="' . $cbild . '" class="wp-post-image" style="height:120px">';	
				$readmore = '<div class="middle">' . __('answer question', 'WPdoodlez') . ' &nbsp;<i class="fa fa-forward"></i></div>';
				$message .= $readmore;
				// Bild zur Quizfrage einfügen, wenn vorhanden
				$bildshow=''; $bildlink='';
				if (!empty($quizbild[0])) {
					$upload_dir = wp_upload_dir();
					$upload_basedir = $upload_dir['basedir'];
					$file = $upload_basedir . '/quizbilder/' . $quizbild[0];
					if ( file_exists( $file ) ) {
						$bildlink = $upload_dir['baseurl'].'/quizbilder/'.$quizbild[0];
						$bildshow = '<div class="middle" style="opacity:.8;top:3px"><img style="height:114px;max-height:150px;max-width:150px;min-width:150px;position:absolute;right:3px;top:0;width:150px"" title="'.$quizbild[0].'" src="'.$bildlink.'"></div>';
					} 
					$message .= $bildshow;
				}
				$message .= '</div>';
			}			
			if (1 == $gamebar) {
				$message .= '<div class="meta-icons iconleiste" style="background-color:'.$accentcolor.'10">';
				$message .= '<a title="alle Fragen anzeigen" href="'.esc_url(site_url().'/question?orderby=rand&order=rand').'">
					<i class="fa fa-question-circle"></i></a><span class="greybox">'. wpd_games_bar().'</span>';
				$message .= '</div><div class="greybox" style="background-color:'.$accentcolor.'19">' . '' . $quizkat.'</div>';
			} else {
				if ( function_exists('meta_icons')) $message .= '<div class="meta-icons" style="background-color:'.$accentcolor.'10">
					'. meta_icons(0).'</div>';
			}		
			$message .= '</header>';
			$message .= '<h2 class="entry-title"><a title="' . __('answer question', 'WPdoodlez') . '" href="'.get_post_permalink().'">'.get_the_title();
			$message .= '&nbsp; ' . $herkunftsland[0].'</a></h2>';
			$message .= '<div class="entry-content">'.get_the_content().'</div>';
		  } // while Schleife Ende
		}
		wp_reset_query();  
		return $message;
	} else return '<span style="color:red">only use on frontpage as widget!</span>';
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
	set_time_limit(900);
	$edat= array();
	$upload_dir = wp_upload_dir();
	$upload_basedir = $upload_dir['basedir'] . '/public_hist_quizfrage.csv';
	$uploaddiff_basedir = $upload_dir['basedir'] . '/public_hist_quizfrage_update.csv';
	$handle = false;	
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
	if ( false !== $handle ) {
		while (($data = fgetcsv($handle, 2000, ";")) !== FALSE) {
			if ( $row > 1 && !empty($data[1]) ) {
				// id; datum; charakter; land; titel; seitjahr; antwort; antwortb; antwortc; antwortd; zusatzinfo; kategorie;iso;bild
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
				  add_post_meta( $post_id, 'quizz_herkunftsland', esc_html($data[3]) );
				  add_post_meta( $post_id, 'quizz_iso', esc_html($data[12]) );
				  add_post_meta( $post_id, 'quizz_bild', esc_html($data[13]) );
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

// Fragen exportieren als CSV
function exportposts() {
	global $wp;
    $args=array(
      'orderby'=> 'title',
      'order'=> 'asc',
      'post_type' => 'question',
      'post_status' => 'publish',
      'posts_per_page' => -1,
    );
	$query = new WP_Query($args);
	if ($query->have_posts()): 
		$filename = 'public_hist_quizfrage';
		$output = fopen('php://output', 'w');
		ob_clean();
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private", false);
		header('Content-Type: text/csv; charset=utf-8');
		header("Content-Disposition: attachment; filename=\"" . $filename . ".csv\";" );
		header("Content-Transfer-Encoding: binary");	
		fputcsv( $output, array('id','datum','charakter','land','titel','seitjahr','bemerkungen','antwortb','antwortc','antwortd','zusatzinfo','quizkat','ISO','Bild'), ';');
		while ($query->have_posts()): $query->the_post();
			$herkunftsland = get_post_custom_values('quizz_herkunftsland');
			$hkiso = get_post_custom_values('quizz_iso');
			$answers = get_post_custom_values('quizz_answer');
			$answersb = get_post_custom_values('quizz_answerb');
			$answersc = get_post_custom_values('quizz_answerc');
			$answersd = get_post_custom_values('quizz_answerd');
			$zusatzinfo = get_post_custom_values('quizz_zusatzinfo');
			$quizbild = get_post_custom_values('quizz_bild');
			$terms = get_the_terms(get_the_id(), 'quizcategory'); // Get all terms of a taxonomy
			if ( $terms && !is_wp_error( $terms ) ) {
				$category = $terms;
			} else {
				$category = get_the_category(); 
			}	
			$xdatum = get_the_date('d.m.Y');
			$xjahr = get_the_date('Y');
			$frage = get_the_content();
			$titel = get_the_title();
			$fragenr = substr($titel, strpos($titel, " ") + 1);
			$exportrow = $fragenr.';'.$xdatum.';'.'Quizfrage'.';'.$herkunftsland[0].';'.$frage.';'.$xjahr.';'.$answers[0].';'.$answersb[0].';'.$answersc[0].';'.$answersd[0].';'.$zusatzinfo[0].';'.$category[0]->name.';'.$hkiso[0].';'.$quizbild[0];
			fputs( $output, $exportrow . "\n" );
		endwhile;
		wp_reset_postdata();
		fclose($output);
		exit;
	endif;
}


// quiz total right/wrong stats auch für zertifikat
function totalrightwrong() {
	$message='';
	// totals Right/wrong gesamt
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
		$message .= '<div><span style="display:inline-block;width:40%">Gesamt gespielt: '.wpdoo_number_format_short( (float) intval($rct + $wct) ?? 0).' Fragen</span>';
		$message .= ' <progress id="rf" value="'.intval($rct/($rct+$wct)*100).'" max="100" style="width:100px"></progress> R:'.wpdoo_number_format_short( (float) $rct ?? 0).' &nbsp;';
		$message .= ' <progress id="rf" value="'.(100 - intval($rct/($rct+$wct)*100)).'" max="100" style="width:100px"></progress> F:'.wpdoo_number_format_short( (float) $wct ?? 0).'</div>';
	}	
	// totals Right/wrong by quizcategory
   $args = array(
	   'taxonomy' => 'quizcategory',
	   'orderby' => 'name',
	   'order'   => 'ASC'
   );
   $qcats = get_categories($args);
   foreach($qcats as $qcat) {
		$rct=0;$wct=0;
		$the_query = new WP_Query(array('post_type' => 'question', 'posts_per_page' => -1,
		  'tax_query' => array(
				array(
					'taxonomy' => 'quizcategory',
					'field' => 'slug',
					'terms' => $qcat->slug,
				),
			),
		  'meta_key' => 'quizz_rightstat', 'orderby' => 'meta_value_num', 'order' => 'DESC'));
		if ( $the_query->have_posts() ) {
			while ( $the_query->have_posts() ) {
				$the_query->the_post();
				$rct = $rct + (int) get_post_meta( get_the_ID(), 'quizz_rightstat', true );
				$wct = $wct + (int) get_post_meta( get_the_ID(), 'quizz_wrongstat', true );
			}
		}
		wp_reset_postdata();
		if ($rct >0 || $wct > 0) {
			$message .= '<div><span style="display:inline-block;width:40%"><i class="fa fa-folder-open"></i> <a href="'.get_category_link( $qcat->term_id ).'" target="_blank">'
			.$qcat->name.'</a> gespielt: '.wpdoo_number_format_short( (float) intval($rct + $wct) ?? 0).' Fragen</span>';
			$message .= ' <progress id="rf" value="'.intval($rct/($rct+$wct)*100).'" max="100" style="width:100px"></progress> R:' .wpdoo_number_format_short( (float) $rct ?? 0).' &nbsp;';
			$message .= ' <progress id="rf" value="'.(100 - intval($rct/($rct+$wct)*100)).'" max="100" style="width:100px"></progress> F:'.wpdoo_number_format_short( (float) $wct ?? 0).'</div>';
		}	
	}
	return $message;
}


// ------ wenn admin eingeloggt, Admin stats anzeigen ------
function quiz_adminstats($statsbisher) {
	if( current_user_can('administrator') &&  ( is_singular() ) ) {
		if (isset($_GET['timer'])) { $timerurl='?timer=1'; } else { $timerurl = ''; }
		global $wpdb;
		$message = '<h6>Admin-Statistik</h6>';
		if ( empty($_POST) && !empty($statsbisher) ) {
			$message .='<a style="cursor:pointer" title="mouseover zum Einblenden, klicken zum Ausblenden" onmouseover="document.getElementById(\'showonhover\').style.display = \'block\'" onclick="document.getElementById(\'showonhover\').style.display = \'none\'"><i class="fa fa-plus-square"></i> Ergebnisse</a><br>';
			$message .= '<div id="showonhover" style="position:relative;display:none;margin-bottom:8px">'.$statsbisher.'</div>';
		}	
		// Top5 Right
		$the_query = new WP_Query(array('post_type' => 'question', 'posts_per_page' => 5, 'meta_key' => 'quizz_rightstat', 'orderby' => 'meta_value_num', 'order' => 'DESC'));
		if ( $the_query->have_posts() ) {
			while ( $the_query->have_posts() ) {
				$the_query->the_post();
				$rite = (float) get_post_meta( get_the_ID(), 'quizz_rightstat', true );
				$wrg = (float) get_post_meta( get_the_ID(), 'quizz_wrongstat', true );
				$rwsum = (float) ($rite + $wrg);
				if ( $rwsum > 0) { $rpct = number_format_i18n($rite / $rwsum * 100); } else { $rpct = 0; }
				$message .= '<span style="color:#1bab1b;display:inline-block;width:55px">';
				$message .= 'R:'.wpdoo_number_format_short( (float) get_post_meta( get_the_ID(), 'quizz_rightstat', true ) ?? 0).'</span><span style="color:tomato;display:inline-block;width:55px">F:'.wpdoo_number_format_short( (float) get_post_meta( get_the_ID(), 'quizz_wrongstat', true ) ?? 0).'</span>';
				$message .= ' <progress id="rfs" value="'.$rpct.'" max="100" style="width:80px"></progress> <a href="'.get_the_permalink().'">'.substr(get_the_content(),0,80).'</a><br>';
			}
		}
		wp_reset_postdata();
		// Top5 wrong
		$the_query = new WP_Query(array('post_type' => 'question', 'posts_per_page' => 5, 'meta_key' => 'quizz_wrongstat', 'orderby' => 'meta_value_num', 'order' => 'DESC'));
		if ( $the_query->have_posts() ) {
			while ( $the_query->have_posts() ) {
				$the_query->the_post();
				$rite = (float) get_post_meta( get_the_ID(), 'quizz_rightstat', true );
				$wrg = (float) get_post_meta( get_the_ID(), 'quizz_wrongstat', true );
				$rwsum = (float) ($rite + $wrg);
				if ( $rwsum > 0) { $rpct = number_format_i18n($wrg / $rwsum * 100); } else { $rpct = 0; }
				$message .= '<span style="color:tomato;display:inline-block;width:55px">F:'.wpdoo_number_format_short( (float) get_post_meta( get_the_ID(), 'quizz_wrongstat', true ) ?? 0).'</span><span style="color:#1bab1b;display:inline-block;width:55px">R:'.wpdoo_number_format_short( (float) get_post_meta( get_the_ID(), 'quizz_rightstat', true ) ?? 0).'</span>';
				$message .= ' <progress id="rfs" value="'.$rpct.'" max="100" style="width:80px"></progress> <a href="'.get_the_permalink().'">'.substr(get_the_content(),0,80).'</a><br>';
			}
		}
		wp_reset_postdata();
		// richtig/falsch und r/f nach Kategorie
		$message .= totalrightwrong();
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

// Converts a number into a short version, eg: 1000 -> 1k
function wpdoo_number_format_short( $n ) {
	if (empty($n) || is_null($n)) $n = 0;
	if ($n < 900) {
		$precision = 0;
		$n_format = number_format($n, $precision, ',', '.');
		$suffix = '';
	} else if ($n < 900000) {
		$precision = 2;
		$n_format = number_format($n / 1000, $precision, ',', '.');
		$suffix = 'k';
	} else if ($n < 900000000) {
		$precision = 2;
		$n_format = number_format($n / 1000000, $precision, ',', '.');
		$suffix = 'M';
	} else if ($n < 900000000000) {
		$precision = 2;
		$n_format = number_format($n / 1000000000, $precision, ',', '.');
		$suffix = 'G';
	} else {
		$precision = 2;
		$n_format = number_format($n / 1000000000000, $precision, ',', '.');
		$suffix = 'T';
	}
	if ( $precision > 0 ) {
		$dotzero = '.' . str_repeat( '0', $precision );
		$n_format = str_replace( $dotzero, '', $n_format );
	}
	return '<span title="'.number_format_i18n($n ?? 0).'">' . $n_format . $suffix . '</span>';
}


// Einzelanzeige
function quiz_show_form( $content ) {
	if (get_post_type() == 'question') {
		// Beenden, wenn nonce nicht stimmt.
		if (!empty($_POST) && empty($_POST['tname']) ) {
			if ( !wp_verify_nonce( $_POST['quiz_nonce'], 'quiz_submit' ) ) return "Nonce not valid";
		}	
		global $wp;
		setlocale (LC_ALL, 'de_DE.utf8', 'de_DE@euro', 'de_DE', 'de', 'ge'); 
		global $answer;
		if (isset($_POST['answer'])) $answer = esc_html($_POST['answer']);	// user submitted answer
		if (isset($_POST['ans'])) $answer = esc_html($_POST['ans']);   // Answer is radio button selection 1 of 4
		if (isset($_GET['ans']))  $answer = esc_html($_GET['ans']);  // Answer is given from shortcode
		if (isset($_GET['ende'])) { $ende = esc_html($_GET['ende']); } else { $ende = 0; }
		if (2 == $ende) {    // Personal Exam zum Thema durchführen und auswerten (auch als Shortcode)
			$theForm = do_shortcode('[personal_quiz items=20 cats='.get_the_terms( get_the_ID(), 'quizcategory' )[0]->slug.']');
			return $theForm;
			exit;
		}	
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
		// Link für nächste Zufallsfrage der gleichen Kategorie
		$terms = get_the_terms(get_the_id(), 'quizcategory'); // Get all terms of a taxonomy
		$args=array(
		  'orderby'=>'rand', 'post_type' => 'question', 
	      'tax_query' => array(	array( 'taxonomy' => 'quizcategory', 'field' => 'slug', 'terms' => $terms[0]->slug, )),
		  'post_status' => 'publish', 'posts_per_page' => 1, 'showposts' => 1,
		);
		$my_query = null;
		$my_query = new WP_Query($args);
		$random_samecat_url = '';
		if( $my_query->have_posts() ) {
		  while ($my_query->have_posts()) : $my_query->the_post(); 
			$random_samecat_url = get_the_permalink();
		  endwhile;
		}
		wp_reset_query();  
		// --------------- ende random posts -------------
		$tcolor = get_theme_mod( 'link-color', '#006060' );
		$backgd = hexdec(substr($tcolor,1,2)).','.hexdec(substr($tcolor,3,2)).','.hexdec(substr($tcolor,5,2)).',.1';
		// get meta values for this question
		$herkunftsland = get_post_custom_values('quizz_herkunftsland');
		$hkiso = get_post_custom_values('quizz_iso');
		$answers = get_post_custom_values('quizz_answer');
		$answersb = get_post_custom_values('quizz_answerb');
		$answersc = get_post_custom_values('quizz_answerc');
		$answersd = get_post_custom_values('quizz_answerd');
		$zusatzinfo = get_post_custom_values('quizz_zusatzinfo');
		$quizbild = get_post_custom_values('quizz_bild');
		$exact = get_post_custom_values('quizz_exact');
		$rightstat = get_post_custom_values('quizz_rightstat');
		$wrongstat = get_post_custom_values('quizz_wrongstat');
		$answerstatsa = get_post_custom_values('quizz_answerstatsa') ?? array(0);
		$answerstatsb = get_post_custom_values('quizz_answerstatsb') ?? array(0);
		$answerstatsc = get_post_custom_values('quizz_answerstatsc') ?? array(0);
		$answerstatsd = get_post_custom_values('quizz_answerstatsd') ?? array(0);
		$error = '';
		$lsubmittedanswer = preg_replace("/[^A-Za-z0-9]/", '', strtolower(esc_html($answer)));
		$lactualanswer = preg_replace("/[^A-Za-z0-9]/", '', strtolower(esc_html($answers[0])));
		// Bild einfügen, wenn vorhanden
		$bildshow='';$bildlink='';
		if (!empty($quizbild[0])) {
			$upload_dir = wp_upload_dir();
			$upload_basedir = $upload_dir['basedir'];
			$file = $upload_basedir . '/quizbilder/' . $quizbild[0];
			if ( file_exists( $file ) ) {
				$bildlink = $upload_dir['baseurl'].'/quizbilder/'.$quizbild[0];
				$bildshow = '<div style="border:2px none;float:right;text-align:right"><a href="'.$bildlink.'"><img style="width:300px" title="'.$quizbild[0].'" src="'.$bildlink.'"></a></div>';
			}
		}
		if ( $terms && !is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
			$content = '<blockquote class="blockqmark" style="font-size:1.2em">'.$bildshow.do_shortcode('[ipflag iso='.$hkiso[0].']').'<p><strong>Kategorie ' . $term->name . ' &nbsp; eine Frage aus '.$herkunftsland[0]. '</strong></p>' . $content.'</blockquote>'; 
			}
		}	
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
				if (!empty($_POST) || isset($_GET['ans']) ) {
					if ( preg_replace("/[^A-Za-z0-9]/", '', strtolower(esc_html($choice))) == $lsubmittedanswer ) { $labstyle = 'background:tomato'; $astyle='color:#fff'; } 
					if ( preg_replace("/[^A-Za-z0-9]/", '', strtolower(esc_html($choice))) == $lactualanswer ) { $labstyle = 'background:#1bab1b'; $astyle='color:#fff'; } 
				}	
				$ansmixed .= '<input onclick="'.$hideplay.'" type="radio" name="ans" id="ans'.$xex.'" value="'.$choice.'">';
				$ansmixed .= ' &nbsp; <label style="'.$labstyle.'" for="ans'.$xex.'"><a style="'.$astyle.'"><b>'.chr($xex+64).'</b> &nbsp; '.$choice.'</a></label>';
			} 
			$ansmixed .='<input type="hidden" name="answers4" id="answers4" value="'.implode(";",$ans).'">';
			$pollyans = '-A- '.$ans[0].' , -B- '.$ans[1].' , -C- '.$ans[2].' , -D- '.$ans[3];
			unset($choice);
		} else {	
			// ansonsten freie Antwort anfordern von Antwort 1
			if ( empty($_POST)) $showsubmit='inline-block'; else $showsubmit='none';
			$ansmixed .= __('answer mask','WPdoodlez'). '<span style="background-color:#eee;margin:8px 5px;font-weight:700;font-size:1.2em;padding:3px 0 3px 9px;letter-spacing:.5em;font-family:monospace">';
			$ansmixed .= preg_replace( '/[^( |aeiouAEIOU.)$]/', '_', esc_html($answers[0])).'</span>' . strlen(esc_html($answers[0])).__(' characters long. ','WPdoodlez');
			if ( empty($_POST) ) {
				if ($exact[0]!="exact") { $ansmixed .= __('not case sensitive','WPdoodlez'); } else { $ansmixed .= __('case sensitive','WPdoodlez'); }
				$ansmixed .='<input autocomplete="off" style="width:100%" type="text" name="answer" id="answer" placeholder="'. __('your answer','WPdoodlez').'" class="quiz_answer answers">';
			}
			$pollyans = esc_html(preg_replace( '/[^( |aeiouAEIOU.)$]/', '*', esc_html($answers[0])));
		}	
		// Stats hochzählen welche antwort gegeben wurde (ungemischt)
		if (!empty($_POST) ) {
			if ($answer == $answers[0]) update_post_meta( get_the_ID(), 'quizz_answerstatsa', ($answerstatsa[0] + 1) ?? 0 );
			else if ($answer == $answersb[0]) update_post_meta( get_the_ID(), 'quizz_answerstatsb', ($answerstatsb[0] + 1) ?? 0 );
			else if ($answer == $answersc[0]) update_post_meta( get_the_ID(), 'quizz_answerstatsc', ($answerstatsc[0] + 1) ?? 0 );
			else if ($answer == $answersd[0]) update_post_meta( get_the_ID(), 'quizz_answerstatsd', ($answerstatsd[0] + 1) ?? 0 );
			$wikinachschlag = '<ul class="footer-menu" style="font-size:.9em">';
			$wikinachschlag .= 'Spielergebnisse: <li><a title="Wikipedia more info" target="_blank" href="https://de.wikipedia.org/wiki/'.$answers[0].'"><i class="fa fa-wikipedia-w"></i> Wiki-Artikel</a></li>'; 
			$wikinachschlag .= '<li><a title="Fireball search for question" target="_blank" href="https://fireball.de/de/search?q='.esc_html(get_the_content()).'"><i class="fa fa-fire"></i> Fireball-Suche</a></li></ul>'; 
		}	
		// Statistik der bisherrigen Antworten anzeigen
		$astatsa = $answerstatsa[0];
		$astatsb = $answerstatsb[0];
		$astatsc = $answerstatsc[0];
		$astatsd = $answerstatsd[0];
		$statsbishersum = $astatsa + $astatsb + $astatsc + $astatsd;
		if ($statsbishersum > 0) {
			$statsbisher = '<div style="display:block;font-size:.9rem"><progress value="'.round($astatsa / $statsbishersum * 100 , 1).'" max="100" style="width:140px"></progress> '.number_format_i18n($astatsa).' - '. $answers[0].'</div>';
			$statsbisher .= '<div style="display:block;font-size:.9rem"><progress value="'.round($astatsb / $statsbishersum * 100 , 1).'" max="100" style="width:140px"></progress> '.number_format_i18n($astatsb).' - '. $answersb[0].'</div>';
			$statsbisher .= '<div style="display:block;font-size:.9rem"><progress value="'.round($astatsc / $statsbishersum * 100 , 1).'" max="100" style="width:140px"></progress> '.number_format_i18n($astatsc).' - '. $answersc[0].'</div>';
			$statsbisher .= '<div style="display:block;font-size:.9rem"><progress value="'.round($astatsd / $statsbishersum * 100 , 1).'" max="100" style="width:140px"></progress> '.number_format_i18n($astatsd).' - '. $answersd[0].'</div>';
		} else $statsbisher = '';
		// Frage beantwortet, richtig oder falsch ermitteln
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
		// Wenn richtige Antwort
		if ( $correct == "yes" ) {
			$error = $ansmixed.'<blockquote class="blockbulb" style="font-size:1.2em;margin-top:1.6em"><i class="fa fa-lg fa-thumbs-o-up"></i> &nbsp; ' . __('correct answer: ','WPdoodlez') . ' '. $answers[0];
			if ( !empty($zusatzinfo) && strlen($zusatzinfo[0])>1 ) $error .= '<p style="margin-top:15px"><i class="fa fa-newspaper-o"></i> &nbsp; '.$zusatzinfo[0].'</p>';
			$error .= $wikinachschlag.$statsbisher.'</blockquote>';
			$showqform = 'display:none';
			ob_start();
			if (isset($_COOKIE['hidecookiebannerx']) && $_COOKIE['hidecookiebannerx']==2 ) setcookie('rightscore', @intval($_COOKIE['rightscore']) + 1, time()+60*60*24*30, '/');
			ob_flush();
			update_post_meta( get_the_ID(), 'quizz_rightstat', ($rightstat[0] + 1) ?? 0 );
		} else {   	// Wenn falsche Antwort
			if ($_SERVER['REQUEST_METHOD'] == 'POST' || isset($_GET['ans']) ) {
				$error = $ansmixed.'<blockquote class="blockbulb" style="font-size:1.2em;margin-top:1.6em">';
				$error .= '<i class="fa fa-lg fa-thumbs-o-down"></i> &nbsp; '. $answer;
				$error .= '<br>'. __(' is the wrong answer. Correct is','WPdoodlez').'<br><i class="fa fa-lg fa-thumbs-up"></i> &nbsp; '.esc_html($answers[0]);
				if ( !empty($zusatzinfo) && strlen($zusatzinfo[0])>1 ) $error .= '<p style="margin-top:15px"><i class="fa fa-newspaper-o"></i> &nbsp; '.$zusatzinfo[0].'</p>';
				$error .= $wikinachschlag.$statsbisher.'</blockquote>';
				$showqform = 'display:none';
				ob_start();
				if (isset($_COOKIE['hidecookiebannerx']) && $_COOKIE['hidecookiebannerx']== 2 ) setcookie('wrongscore', (@intval($_COOKIE['wrongscore']) + 1), time()+60*60*24*30, '/');
				ob_flush();
				update_post_meta( get_the_ID(), 'quizz_wrongstat', ($wrongstat[0] + 1) ?? 0 );
			} else { $error = "";$showqform = ''; }
		}
		// ------------- Menü unten anzeigen -------------------------------
		$accentcolor = get_theme_mod( 'link-color', '#888' );
		$formstyle = '<style>.qiz input[type=radio] {display:none;} .qiz input[type=radio] + label {display:block;padding:8px;cursor:pointer;background:'.$accentcolor.'}';
		$formstyle .= '.qiz input[type=radio] + label:hover{box-shadow:inset 0 0 100px 100px rgba(255,255,255,.15)} .qiz input[type=radio] + label a {color:#fff} ';
		if ( empty($_POST) ) {
			$formstyle .= '.qiz input[type=radio]:checked + label { background-image:none;background:'.$accentcolor.';border:2px solid #000} .qiz input[type=radio]:checked + label a {color:#fff}';
		} else {
			$formstyle .= '.qiz input[type=radio] + label {cursor:not-allowed} ';
		}
		$formstyle .='</style>';
		$listyle = '<li style="padding:6px 0 6px 0;display:inline">';
		$letztefrage = '<blockquote class="blockleer" style="margin-top:1em;text-align:center"><ul class="footer-menu" style="padding:2px 2px;text-transform:uppercase;">';
		// für die Zwischenablage 
		$terms = get_the_terms(get_the_id(), 'quizcategory'); // Get all terms of a taxonomy
		$copytags = '';
		if ( $terms && !is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
					$copytags .= ' Kategorie: ' . $term->name . ' - '; 
			}
		}	
		$copyfrage = '  ' . wp_strip_all_tags( preg_replace("/[?,:]()/", '', get_the_title() ).'  '.$copytags.' eine Frage aus '. $herkunftsland[0] .'  '. preg_replace("/[?,:()]/", '',get_the_content() ).' ? '.preg_replace("/[?:()]/", '.',$pollyans ));
		$letztefrage .= $listyle.'<input name="clippy" title="Frage in Zwischenablage kopieren" style="cursor:pointer;background-color:'.$accentcolor.';color:white;margin-top:5px;vertical-align:top;width:49px;height:20px;font-size:9px;padding:0" type="text" class="copy-to-clipboard" value="'.$copyfrage.'">';
		$letztefrage .= '<p class="newlabel yellow" style="display: none;">Frage in Zwischenablage kopiert</p></li>' . $listyle. '<a title="'. __('overview','WPdoodlez').'" href="'.get_home_url().'/question?orderby=rand&order=rand"><i class="fa fa-list"></i></a>';
		// wenn current theme penguin, dann link zu umfragen
		$wpxtheme = wp_get_theme(); 
		if ( 'Penguin' == $wpxtheme->name || 'Penguin' == $wpxtheme->parent_theme ) { $xpenguin = true;} else { $xpenguin=false; }
		if ( current_user_can('administrator') && $xpenguin ) {
			$liveumfrage = wp_strip_all_tags( preg_replace("/[?,:]()/", '', get_the_title() ).' '.$copytags.' eine Frage aus '. $herkunftsland[0] .' '. preg_replace("/[?,:()]/", '',get_the_content() ).','.preg_replace("/[?:()]/", '.',$pollyans ));
			if (strlen($liveumfrage)<450) $letztefrage .= '</li><li><a title="'. __('admin: create live poll','WPdoodlez').'" href="'.get_home_url().'/live-umfragen?frage='.$liveumfrage.'"><i class="fa fa-check-square-o"></i>'. __('live poll','WPdoodlez').'</a>';
		}	
		// Nächste und letzte Frage Link, oder Kreuzwort oder Wortsucherätsel
		if (isset($_GET['timer'])) { $timerurl='?timer=1'; } else { $timerurl = ''; }
		$letztefrage .= '</li>'.$listyle.'<a title="Zufallsfrage" href="' . $random_post_url . $timerurl.'"><i class="fa fa-random"></i> '. __('next random question','WPdoodlez').'</a>';
		$letztefrage .= '</li>'.$listyle.'<a title="Zufallsfrage gleiche Kat." href="' . $random_samecat_url . $timerurl.'"><i class="fa fa-rotate-right"></i> '. $terms[0]->name. ' '.__('next random question','WPdoodlez').'</a>';
		$letztefrage.='</li>'.$listyle.'<a title="'.__('personal exam same cat.','WPdoodlez').'" href="'.add_query_arg( array('ende'=>2), home_url($wp->request) ).'"><i class="fa  fa-graduation-cap"></i> '.__('quiz exam','WPdoodlez').'</a></li>';
		$letztefrage.='</li>'.$listyle.'<a title="'.__('certificate','WPdoodlez').'" href="'.add_query_arg( array('ende'=>1), home_url($wp->request) ).'"><i class="fa fa-certificate"></i> '.__('certificate','WPdoodlez').'</a></li>';
		$letztefrage .= wpd_games_bar();
		if ( @$wrongstat[0] > 0 || @$rightstat[0] >0 ) { $perct = intval(@$rightstat[0] / (@$wrongstat[0] + @$rightstat[0]) * 100); } else { $perct= 0; }
		if ( @$_COOKIE['wrongscore'] > 0 || @$_COOKIE['rightscore'] >0 ) { $sperct = intval (@$_COOKIE['rightscore'] / (@$_COOKIE['wrongscore'] + @$_COOKIE['rightscore']) * 100); } else { $sperct= 0; }
		$letztefrage .= '</ul><p>'. __('this question total','WPdoodlez');
		$letztefrage .= ' <progress id="rf" value="'.$perct.'" max="100" style="width:100px"></progress> R: '. number_format_i18n(@$rightstat[0] ?? 0).' / F: '. number_format_i18n(@$wrongstat[0] ?? 0);
		if (isset($_COOKIE['hidecookiebannerx']) && $_COOKIE['hidecookiebannerx']==2 ) {
			$letztefrage .= ' &nbsp; '. __('Your session','WPdoodlez');
			$letztefrage .= ' <progress id="rf" value="'.$sperct.'" max="100" style="width:100px"></progress> R: ' . number_format_i18n(@$_COOKIE['rightscore'] ?? 0). ' / F: '.number_format_i18n(@$_COOKIE['wrongscore'] ?? 0);
		}	
		$letztefrage .= '</p></blockquote>';
		$letztefrage .= quiz_adminstats($statsbisher);
		
		if (0 == $ende) {   // Zertikikat Link noch nicht angeklickt
			$antwortmaske = $content . '<div class="qiz">';
			$antwortmaske .= $error.'<form id="quizform" action="" method="POST" class="quiz_form form"  style="'.$showqform.'">';
			$antwortmaske .= wp_nonce_field( 'quiz_submit', 'quiz_nonce', 1, 0 );
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
		} else if (1 == $ende) {    // Zertifikat ausgeben
			$theForm = '<script>document.getElementsByClassName("entry-title")[0].style.display = "none";</script>';
			$theForm .= '<div style="position:relative">
				<div><img src="'.plugin_dir_url(__FILE__).'/lightbulb-1000-300.jpg" style="width:100%"></div>
				<div class="middle" style="opacity:1;position:absolute;top:50%;bottom:50%;width:100%;text-align:center;color:white;font-size:4em;z-index:99999">'.__('quiz certificate','WPdoodlez') .'</div>
				</div>';
			$theForm .= '<div style="text-align:center;padding-top:5px;font-size:1.4em">'. __('test terminated. thanks.','WPdoodlez');
			$theForm .= __('you have ','WPdoodlez') . (@$_COOKIE['wrongscore'] + @$_COOKIE['rightscore']).' '.__('questions answered','WPdoodlez')
			.',<br>'.__('with','WPdoodlez').' '. @$_COOKIE['rightscore']. '  ('.$sperct.'%)  '.__('right','WPdoodlez').' '.__('and','WPdoodlez') .' '.@$_COOKIE['wrongscore'].' ('. (100 - $sperct) .'%) '.__('wrong','WPdoodlez').'.';
			$theForm .= '<p style="margin-top:20px"><progress id="file" value="'.$sperct.'" max="100"> '.$sperct.' </progress></p>';
			if ( $sperct < 50 ) { 
				$fail='<span style="color:tomato"><i class="fa fa-thumbs-down"></i> '.__('unfortunately not','WPdoodlez').'</span>';
			} else { $fail='<span style="color:green"><i class="fa fa-thumbs-up"></i> '; }
			$theForm .= '<p style="margin-top:20px">'.__('in school grades','WPdoodlez').' '.get_schulnote( $sperct ).',<br>'.__('and','WPdoodlez').' <strong>'.$fail.' '.__('passed','WPdoodlez').'</strong>.</p>';
			$theForm .= '<blockquote style="font-size:.6em;overflow:hidden;height:340px;max-height:400px">'.totalrightwrong().'</blockquote>';
			$theForm .= '<p style="font-size:0.7em;margin-top:5px">'.wp_date( 'D, j. F Y, H:i:s', false, false);
			$theForm .= '<span style="font-family:Brush Script MT;font-size:2.6em;padding-left:24px">'.wp_get_current_user()->display_name.'</span></p>';
			$theForm .= '<p style="font-size:0.7em">'. get_bloginfo('name') .' &nbsp; '. get_bloginfo('url') .'<br>'.get_bloginfo('description'). '</p></div>';
		}
		return $theForm;
	} else return $content;
}
add_filter( 'the_content', 'quiz_show_form' );


function quizz_add_custom_box() {
    $screens = array( 'question' );
    foreach ( $screens as $screen ) {
        add_meta_box(
            'answers-more', __( 'Answers &amp; more', 'WPdoodlez' ),
            'quizz_inner_custom_box', $screen, 'normal'
        );
    }
}
add_action( 'add_meta_boxes', 'quizz_add_custom_box' );

function quizz_inner_custom_box( $post ) {
	// Add an nonce field so we can check for it later.
	wp_nonce_field( 'quizz_inner_custom_box', 'quizz_inner_custom_box_nonce' );
	// Use get_post_meta() to retrieve an existing value from the database and use the value for the form.
	$herkunftsland = get_post_meta( $post->ID, 'quizz_herkunftsland', true );
	if (empty($herkunftsland)) $herkunftsland="Deutschland";
	$hkiso = get_post_meta( $post->ID, 'quizz_iso', true );
	if (empty($hkiso)) $hkiso="DE";
	$value = get_post_meta( $post->ID, 'quizz_answer', true );
	$valueb = get_post_meta( $post->ID, 'quizz_answerb', true );
	$valuec = get_post_meta( $post->ID, 'quizz_answerc', true );
	$valued = get_post_meta( $post->ID, 'quizz_answerd', true );
	$zusatzinfo = get_post_meta( $post->ID, 'quizz_zusatzinfo', true );
	$quizbild = get_post_meta( $post->ID, 'quizz_bild', true );
	
	echo '<style>label{width:120px;display:inline-block}</style>';
	echo '<p><label for="quizz_herkunftsland"><strong>' . __( 'origin country', 'WPdoodlez' ) . '</strong></label>';
	echo '<input type="text" id="quizz_herkunftsland" name="quizz_herkunftsland" value="' . esc_attr( $herkunftsland ) . '" size="40"> ';
	echo '<label for="quizz_iso">' . __( "origin ISO", 'WPdoodlez' ) . '</label>';
	echo '<input type="text" id="quizz_iso" name="quizz_iso" value="' . esc_attr( $hkiso ) . '" size="2">';
	// Länder Selectbox und Code
	  if (empty ($countries)) $countries = array (
	//   Array aus isolaender.csv von ssl.pbcs.de nur deutsche länder
	'AD' => 'Andorra',
	'AE' => 'Vereinigte Arabische Emirate',
	'AF' => 'Afghanistan',
	'AG' => 'Antigua und Barbuda',
	'AI' => 'Anguilla',
	'AL' => 'Albanien',
	'AM' => 'Armenien',
	'AO' => 'Angola',
	'AQ' => 'Antarktis (Sonderstatus durch Antarktisvertrag)',
	'AQ' => 'Antarktis',
	'AR' => 'Argentinien',
	'AS' => 'Amerikanisch-Samoa',
	'AT' => 'Österreich',
	'AU' => 'Australien',
	'AW' => 'Aruba',
	'AX' => 'Aland',
	'AZ' => 'Aserbaidschan',
	'BA' => 'Bosnien und Herzegowina',
	'BB' => 'Barbados',
	'BD' => 'Bangladesch',
	'BE' => 'Belgien',
	'BF' => 'Burkina Faso',
	'BG' => 'Bulgarien',
	'BH' => 'Bahrain',
	'BI' => 'Burundi',
	'BJ' => 'Benin',
	'BL' => 'Saint-Barthelemy',
	'BM' => 'Bermuda',
	'BN' => 'Brunei',
	'BO' => 'Bolivien',
	'BQ' => 'Bonaire  Saba  Sint Eustatius',
	'BR' => 'Brasilien',
	'BS' => 'Bahamas',
	'BT' => 'Bhutan',
	'BV' => 'Bouvetinsel',
	'BW' => 'Botswana',
	'BY' => 'Belarus',
	'BZ' => 'Belize',
	'CA' => 'Kanada',
	'CC' => 'Kokosinseln',
	'CD' => 'Kongo  Demokratische Republik',
	'CF' => 'Zentralafrikanische Republik',
	'CG' => 'Kongo  Republik',
	'CH' => 'Schweiz',
	'CI' => 'Elfenbeinküste',
	'CK' => 'Cookinseln',
	'CL' => 'Chile',
	'CM' => 'Kamerun',
	'CN' => 'China  Volksrepublik',
	'CO' => 'Kolumbien',
	'CR' => 'Costa Rica',
	'CS' => 'Tschechoslowakei (ehemals)',
	'CU' => 'Kuba',
	'CV' => 'Kap Verde',
	'CW' => 'Curacao',
	'CX' => 'Weihnachtsinsel',
	'CY' => 'Zypern',
	'CZ' => 'Tschechien',
	'DD' => 'DDR (ehemals)',
	'DE' => 'Deutschland',
	'DJ' => 'Dschibuti',
	'DK' => 'Dänemark',
	'DM' => 'Dominica',
	'DO' => 'Dominikanische Republik',
	'DZ' => 'Algerien',
	'EC' => 'Ecuador',
	'EE' => 'Estland',
	'EG' => 'Ägypten',
	'EH' => 'Westsahara',
	'ER' => 'Eritrea',
	'ES' => 'Spanien',
	'ET' => 'Äthiopien',
	'EU' => 'Europäische Union',
	'FI' => 'Finnland',
	'FJ' => 'Fidschi',
	'FK' => 'Falklandinseln',
	'FM' => 'Mikronesien',
	'FO' => 'Färöer',
	'FR' => 'Frankreich',
	'GA' => 'Gabun',
	'GB' => 'Vereinigtes Königreich',
	'GD' => 'Grenada',
	'GE' => 'Georgien',
	'GF' => 'Französisch-Guayana',
	'GG' => 'Guernsey (Kanalinsel)',
	'GH' => 'Ghana',
	'GI' => 'Gibraltar',
	'GL' => 'Grönland',
	'GM' => 'Gambia',
	'GN' => 'Guinea',
	'GP' => 'Guadeloupe',
	'GQ' => 'Äquatorialguinea',
	'GR' => 'Griechenland',
	'GS' => 'Südgeorgien und die Südlichen Sandwichinseln',
	'GT' => 'Guatemala',
	'GU' => 'Guam',
	'GW' => 'Guinea-Bissau',
	'GY' => 'Guyana',
	'HK' => 'Hongkong',
	'HM' => 'Heard und McDonaldinseln',
	'HN' => 'Honduras',
	'HR' => 'Kroatien',
	'HT' => 'Haiti',
	'HU' => 'Ungarn',
	'ID' => 'Indonesien',
	'IE' => 'Irland',
	'IL' => 'Israel',
	'IM' => 'Insel Man',
	'IN' => 'Indien',
	'IO' => 'Britisches Territorium im Indischen Ozean',
	'IQ' => 'Irak',
	'IR' => 'Iran',
	'IS' => 'Island',
	'IT' => 'Italien',
	'JE' => 'Jersey (Kanalinsel)',
	'JM' => 'Jamaika',
	'JO' => 'Jordanien',
	'JP' => 'Japan',
	'KE' => 'Kenia',
	'KG' => 'Kirgisistan',
	'KH' => 'Kambodscha',
	'KI' => 'Kiribati',
	'KM' => 'Komoren',
	'KN' => 'St. Kitts und Nevis',
	'KP' => 'Nordkorea',
	'KR' => 'Südkorea',
	'KW' => 'Kuwait',
	'KY' => 'Kaimaninseln',
	'KZ' => 'Kasachstan',
	'LA' => 'Laos',
	'LB' => 'Libanon',
	'LC' => 'St. Lucia',
	'LI' => 'Liechtenstein',
	'LK' => 'Sri Lanka',
	'LR' => 'Liberia',
	'LS' => 'Lesotho',
	'LT' => 'Litauen',
	'LU' => 'Luxemburg',
	'LV' => 'Lettland',
	'LY' => 'Libyen',
	'MA' => 'Marokko',
	'MC' => 'Monaco',
	'MD' => 'Moldau',
	'ME' => 'Montenegro',
	'MF' => 'Saint-Martin (französischer Teil)',
	'MG' => 'Madagaskar',
	'MH' => 'Marshallinseln',
	'MK' => 'Nordmazedonien',
	'ML' => 'Mali',
	'MM' => 'Myanmar',
	'MN' => 'Mongolei',
	'MO' => 'Macau',
	'MP' => 'Nördliche Marianen',
	'MQ' => 'Martinique',
	'MR' => 'Mauretanien',
	'MS' => 'Montserrat',
	'MT' => 'Malta',
	'MU' => 'Mauritius',
	'MV' => 'Malediven',
	'MW' => 'Malawi',
	'MX' => 'Mexiko',
	'MY' => 'Malaysia',
	'MZ' => 'Mosambik',
	'NA' => 'Namibia',
	'NC' => 'Neukaledonien',
	'NE' => 'Niger',
	'NF' => 'Norfolkinsel',
	'NG' => 'Nigeria',
	'NI' => 'Nicaragua',
	'NL' => 'Niederlande',
	'NO' => 'Norwegen',
	'NP' => 'Nepal',
	'NR' => 'Nauru',
	'NU' => 'Niue',
	'NZ' => 'Neuseeland',
	'OM' => 'Oman',
	'PA' => 'Panama',
	'PE' => 'Peru',
	'PF' => 'Französisch-Polynesien',
	'PG' => 'Papua-Neuguinea',
	'PH' => 'Philippinen',
	'PK' => 'Pakistan',
	'PL' => 'Polen',
	'PM' => 'Saint-Pierre und Miquelon',
	'PN' => 'Pitcairninseln',
	'PR' => 'Puerto Rico',
	'PS' => 'Palästina',
	'PT' => 'Portugal',
	'PW' => 'Palau',
	'PY' => 'Paraguay',
	'QA' => 'Katar',
	'RE' => 'Reunion',
	'RO' => 'Rumänien',
	'RS' => 'Serbien',
	'RU' => 'Russland',
	'RW' => 'Ruanda',
	'SA' => 'Saudi-Arabien',
	'SB' => 'Salomonen',
	'SC' => 'Seychellen',
	'SD' => 'Sudan',
	'SE' => 'Schweden',
	'SG' => 'Singapur',
	'SH' => 'St. Helena  Ascension und Tristan da Cunha',
	'SI' => 'Slowenien',
	'SJ' => 'Spitzbergen und Jan Mayen',
	'SK' => 'Slowakei',
	'SL' => 'Sierra Leone',
	'SM' => 'San Marino',
	'SN' => 'Senegal',
	'SO' => 'Somalia',
	'SR' => 'Suriname',
	'SS' => 'Südsudan',
	'ST' => 'Sao Tome und Principe',
	'SV' => 'El Salvador',
	'SX' => 'Sint Maarten',
	'SY' => 'Syrien',
	'SZ' => 'Eswatini',
	'TC' => 'Turks- und Caicosinseln',
	'TD' => 'Tschad',
	'TF' => 'Französische Süd- und Antarktisgebiete',
	'TG' => 'Togo',
	'TH' => 'Thailand',
	'TJ' => 'Tadschikistan',
	'TK' => 'Tokelau',
	'TL' => 'Osttimor',
	'TM' => 'Turkmenistan',
	'TN' => 'Tunesien',
	'TO' => 'Tonga',
	'TR' => 'Türkei',
	'TT' => 'Trinidad und Tobago',
	'TV' => 'Tuvalu',
	'TW' => 'Taiwan',
	'TZ' => 'Tansania',
	'UA' => 'Ukraine',
	'UG' => 'Uganda',
	'UM' => 'United States Minor Outlying Islands',
	'UN' => 'Vereinte Nationen',
	'US' => 'Vereinigte Staaten',
	'UY' => 'Uruguay',
	'UZ' => 'Usbekistan',
	'VA' => 'Vatikanstadt',
	'VC' => 'St. Vincent und die Grenadinen',
	'VE' => 'Venezuela',
	'VG' => 'Britische Jungferninseln',
	'VI' => 'Amerikanische Jungferninseln',
	'VN' => 'Vietnam',
	'VU' => 'Vanuatu',
	'WF' => 'Wallis und Futuna',
	'WS' => 'Samoa',
	'YE' => 'Jemen',
	'YT' => 'Mayotte',
	'YU' => 'Jugoslawien (ehemals)',
	'ZA' => 'Südafrika',
	'ZM' => 'Sambia',
	'ZW' => 'Simbabwe',
	'ZZ' => 'International',
	// array ende kopieren
	);
	echo '<label for="quizz_cselect">' . __( "or select from list", 'WPdoodlez' ) . '</label> ';
	echo '<select onchange="document.getElementById(\'quizz_herkunftsland\').value = document.getElementById(\'quizz_cselect\').value.substring(3,40);document.getElementById(\'quizz_iso\').value = document.getElementById(\'quizz_cselect\').value.substring(0,2);" name="quizz_cselect" id="quizz_cselect">';
	foreach ($countries as $cid => $country) {
	   echo '<option value="' . $cid.'|'.$country . '" ' . ($cid == $hkiso ? 'selected="selected"' : null) . '>' . $cid.' | '.$country . '</option>';
	} 
	echo '</select></p>';

	echo '<p><label for="quizz_bild">' . __( "picture in path uploads/quizbilder", 'WPdoodlez' ) . '</label>';
	echo ' <input type="text" id="quizz_bild" name="quizz_bild" value="' . esc_attr( $quizbild ) . '" size="40" style="max-width:40%"> optional<br>';

	echo '</p><p><label for="quizz_answer">' . __( 'correct answer', 'WPdoodlez' ) . ' [A]</label> ';
	echo ' <input required="required" placeholder="'.__( 'required', 'WPdoodlez' ).'" type="text" id="quizz_answer" name="quizz_answer" value="' . esc_attr( $value ) . '" size="75">';
	$value1 = get_post_meta( $post->ID, 'quizz_exact', true);
	echo ' <input type="checkbox" name="quizz_exact" id="quizz_exact" value="exact" ' . (($value1=="exact") ? " checked" : "") . '>'. __('exact match (also enforces case)','WPdoodlez');
	echo '<br>';
	// Distraktoren, im Quiz werden die Antworten gemischt
	echo '<label for="quizz_answerb">' . __( "wrong answer", 'WPdoodlez' ) . ' [B]</label>';
	echo ' <input type="text" id="quizz_answerb" name="quizz_answerb" value="' . esc_attr( $valueb ) . '" size="75" style="max-width:80%"> optional<br>';
	echo '<label for="quizz_answerc">' . __( "wrong answer", 'WPdoodlez' ) . ' [C]</label>';
	echo ' <input type="text" id="quizz_answerc" name="quizz_answerc" value="' . esc_attr( $valuec ) . '" size="75" style="max-width:80%"> optional<br>';
	echo '<label for="quizz_answerd">' . __( "wrong answer", 'WPdoodlez' ) . ' [D]</label>';
	echo ' <input type="text" id="quizz_answerd" name="quizz_answerd" value="' . esc_attr( $valued ) . '" size="75" style="max-width:80%"> optional<br>';
	echo '<label for="quizz_zusatzinfo">' . __( "moreinfo", 'WPdoodlez' ) . ' </label>';
	echo ' <input type="text" id="quizz_zusatzinfo" name="quizz_zusatzinfo" value="' . esc_attr( $zusatzinfo ) . '" size="220" style="max-width:80%"> optional<br>';
	$rightstat = get_post_meta( $post->ID, 'quizz_rightstat', true);
	$wrongstat = get_post_meta( $post->ID, 'quizz_wrongstat', true);
	if (!empty($rightstat) || !empty($wrongstat)) echo '<p>'. __('stats right wrong answers','WPdoodlez').': '.@$rightstat[0].' / '.@$wrongstat[0].'</p>';
}

add_filter('enter_title_here', 'my_title_place_holder' , 20 , 2 );
function my_title_place_holder($title , $post){
	if( $post->post_type == 'question' ){
		$my_title = __('Enter Quiz Title and number','WPdoodlez');
		return $my_title;
	}
	return $title;
}

function my_default_title_filter() {
    global $post_type, $post;
    if ('question' == $post_type) {
		$post_args = array(
			'post_type' => 'question', 'post_status' => 'publish',
			'orderby' => 'title', 'posts_per_page' => 1, 'order'   => 'DESC',
		);
		$fragentitel = get_posts($post_args);   
		if(!empty($fragentitel)) {
			foreach($fragentitel as $single_post){
				$titelnum = (int) substr($single_post->post_title,9,5);
			}
		}
        return 'Quizfrage '.$titelnum + 1;
    }
}
add_filter('default_title', 'my_default_title_filter');


function quizz_save_postdata( $post_id ) {
  // Check if our nonce is set. We need to verify this came from the our screen and with proper authorization
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
  $myherkunft = sanitize_text_field( $_POST['quizz_herkunftsland'] );
  $myiso = sanitize_text_field( $_POST['quizz_iso'] );
  $myanswer = sanitize_text_field( $_POST['quizz_answer'] );
  $myanswerb = sanitize_text_field( $_POST['quizz_answerb'] );
  $myanswerc = sanitize_text_field( $_POST['quizz_answerc'] );
  $myanswerd = sanitize_text_field( $_POST['quizz_answerd'] );
  $zusatzinfo = sanitize_text_field( $_POST['quizz_zusatzinfo'] );
  $quizbild = sanitize_text_field( $_POST['quizz_bild'] );
  $fromlevel = $_POST['quizz_prevlevel'];
  $exact = $_POST['quizz_exact'];
  $lastlevel_bool = $_POST['quizz_last'];
  $lastpage = $_POST['quizz_lastpage'];
  // Update the meta field in the database.
  update_post_meta( $post_id, 'quizz_herkunftsland', $myherkunft );
  update_post_meta( $post_id, 'quizz_iso', $myiso );
  update_post_meta( $post_id, 'quizz_answer', $myanswer );
  update_post_meta( $post_id, 'quizz_answerb', $myanswerb );
  update_post_meta( $post_id, 'quizz_answerc', $myanswerc );
  update_post_meta( $post_id, 'quizz_answerd', $myanswerd );
  update_post_meta( $post_id, 'quizz_zusatzinfo', $zusatzinfo );
  update_post_meta( $post_id, 'quizz_bild', strtolower($quizbild) );
  update_post_meta( $post_id, 'quizz_exact', $exact );
}
add_action( 'save_post', 'quizz_save_postdata' );

add_action( 'manage_posts_extra_tablenav', 'admin_order_list_top_bar_button', 20, 1 );
function admin_order_list_top_bar_button( $which ) {
    global $current_screen;
    if ('question' == $current_screen->post_type) {
		$nonce = wp_create_nonce( 'dnonce' );
		echo "&nbsp; <a title=\"place public_histereignisse.csv in upload folder to DELETE and replace all questions &#10;or place public_histereignisse-update.csv in upload folder\" href='".$_SERVER['REQUEST_URI']."&quizzzcsv=true&nonce=".$nonce."' class='button button-primary'>";
		_e( 'Import from CSV', 'WPdoodlez' );
		echo '</a> &nbsp; ';
		$upload_dir = wp_upload_dir();
		$upload_basedir = $upload_dir['basedir'] . '/public_hist_quizfrage.csv';
		$uploaddiff_basedir = $upload_dir['basedir'] . '/public_hist_quizfrage_update.csv';
		$importmodus='<span style="color:red">keine Datei in /uploads</span>';
		if ( file_exists( $upload_basedir ) ) $importmodus='<span style="color:orange">Löschen und Vollimport</span>';
		if ( file_exists( $uploaddiff_basedir ) ) $importmodus='<span style="color:green">Update Differenz</span>';
		echo '<b>'.$importmodus.'</b> &nbsp; ';
		echo " <a title=\"export questions as csv semicolon separated\" href='".$_SERVER['REQUEST_URI']."&quizzzcsvexport=true&nonce=".$nonce."' class='button'>";
		_e( 'Export to CSV', 'WPdoodlez' );
		echo '</a> ';
    }
}

// quiz-category filter for admin fragen list  $which (the position of the filters form) is either 'top' or 'bottom'
add_action( 'restrict_manage_posts', function ( $post_type, $which ) {
    if ( 'top' === $which && 'question' === $post_type ) {
        $taxonomy = 'quizcategory';
        $tax = get_taxonomy( $taxonomy );            // get the taxonomy object/data
        $cat = filter_input( INPUT_GET, $taxonomy ); // get the selected category slug

        echo '<label class="screen-reader-text" for="my_tax">Filter by ' .
            esc_html( $tax->labels->singular_name ) . '</label>';

        wp_dropdown_categories( [
            'show_option_all' => $tax->labels->all_items,
            'hide_empty'      => 0, // include categories that have no posts
            'hierarchical'    => $tax->hierarchical,
            'show_count'      => 0, // don't show the category's posts count
            'orderby'         => 'name',
            'selected'        => $cat,
            'taxonomy'        => $taxonomy,
            'name'            => $taxonomy,
            'value_field'     => 'slug',
        ] );
    }
}, 10, 2 );

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
			echo '<br><i>'.get_post_meta( $post_id , 'quizz_bild' , true ).'</i>'; 
			break;
    }
}

//   ----------------------------- Quizzz module ended -------------------------------------

// ------------------------------- crosswordquizz ----------------------------------

function xwordquiz() {
    $args=array(
      'orderby'=> 'rand',
      'order'=> 'rand',
      'post_type' => 'question',
      'post_status' => 'publish',
      'posts_per_page' => -1,
	  'showposts' => -1,
    );
    $my_query = new WP_Query($args);
	$rows=array();
    if( $my_query->have_posts() ) {
      while ($my_query->have_posts()) : $my_query->the_post(); 
		$answers = get_post_custom_values('quizz_answer');
		$crossohneleer =  (strpos($answers[0], ' ') == false);
		if ($crossohneleer) {
				$crossant = umlauteumwandeln(preg_replace("/[^A-Za-z]/", '', esc_html($answers[0]) ) );
				$crossfrag = get_the_content();
				if( strlen($crossant) <= 12 && strlen($crossant) >= 2 &&
				    strlen($crossfrag) <= 40 && strlen($crossfrag) >= 5 ) {
					$element = array( "word" => $crossant, "clue" => $crossfrag );
					$rows[] = $element;
				}	
		}	
      endwhile;
    }
    wp_reset_query();  
	// Enqueue the registered scipts and css
	wp_enqueue_style('crossword-style');
	wp_enqueue_script('crossword-script');
    /* Adds additional data */
    wp_localize_script('crossword-script', 'crossword_vars', array(
        'cwdcw_ansver' => 'yes', 'cwdcw_ansver_incorect' => 'yes',
    ));
	$html = wpd_games_bar();
	$html .= '<ul class="footer-menu" style="display:inline-block"><li><a title="'.__('new game','WPdoodlez').'" href="' .
		add_query_arg( array('crossword'=>1), get_post_permalink() ).'"><i class="fa fa-exchange"></i> '. __('start new game','WPdoodlez').'</a></li></ul>';
    if ($rows) {
		$html .= '<script>document.getElementById("primary").className="page-fullwidth"</script>';
        $html .= '<div class="crossword_wrapper">';
        $html .= '<div class="cwd-row cwd-crossword-row" style="width:100%"><div class="cwd-crossword-container">';
        $html .= ' <div class="cwd-center cwd-crossword" id="cwd-crossword"></div><br>';
        $html .= '</div></div>';
        $html .= '<div class="cwd-center cwd-crossword-questions">';
        $i = 1;
        foreach ($rows as $row) {
				if ($i == 21) break;
				if ( is_user_logged_in() ) {
					$adminsolves = 'onclick="javascript:for (let el of document.querySelectorAll(\'.cwd-hide\')) { if (el.style.visibility==\'hidden\') { el.style.visibility=\'visible\';} else {el.style.visibility = \'hidden\';} };" ';
				} else {
					$adminsolves = '';
				}
				$ansmixed = '&nbsp; <a '.$adminsolves.' style="background-color:#eee;font-weight:700;padding:0 3px 0 6px;letter-spacing:.5em;font-family:monospace" title="'.strlen(esc_html($row['word'])).__(' characters long. ','WPdoodlez').'">'.preg_replace( '/[^( |aeiouAEIOU.)$]/', '_', esc_html(strtoupper($row['word']))).'</a>';
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
//   ----------------------------- Kreuzwort module ended -------------------------------------

// ------------------------------- wordsearch puzzle ----------------------------------

function umlauteumwandeln($str) {   // wandelt Umlaute und Akzentbuchstaben in normale Buchstaben/ue/oe um
	$tempstr = array(   'Ä' => 'AE', 'Ö' => 'OE', 'Ü' => 'UE', 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 
		'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
		'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ø'=>'O', 'Ù'=>'U',
		'Ú'=>'U', 'Û'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
		'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
		'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );
	return strtr($str, $tempstr);
}

function xwordpuzzle() {
    $args=array(
      'orderby'=> 'rand',
      'order'=> 'rand',
      'post_type' => 'question',
      'post_status' => 'publish',
      'posts_per_page' => -1,
	  'showposts' => -1,
    );
    $my_query = new WP_Query($args);
	$rows=array();
    if( $my_query->have_posts() ) {
      while ($my_query->have_posts()) : $my_query->the_post(); 
		$answers = get_post_custom_values('quizz_answer');
		$crossohneleer =  (strpos($answers[0], ' ') == false);
		if ($crossohneleer) {
				$crossant = preg_replace("/[^A-Za-zäüöÄÖÜß]/", '', esc_html(umlauteumwandeln($answers[0]) ) );
				$crossfrag = get_the_content();
				if( strlen($crossant) <= 12 && strlen($crossant) >= 2 &&
				    strlen($crossfrag) <= 40 && strlen($crossfrag) >= 5 ) {
					$element = array( "word" => $crossant, "clue" => $crossfrag );
					$rows[] = $element;
				}	
		}	
      endwhile;
    }
    wp_reset_query();  
    $html = '';
    if ($rows) {
		$i = 1;
		$wdstring='[';$wcstring='[';
        foreach ($rows as $row) {
			if ($i == 8){ break; }
			$wdstring .= "'".strtoupper($row['word'])."',";
			$wcstring .= "'".strtoupper($row['clue'])."',";
			$i++;
		}
		$wdstring=rtrim($wdstring,',').']';
		$wcstring=rtrim($wcstring,',').']';
		$html .= wpd_games_bar();
		$html .= '<ul class="footer-menu" style="display:inline-block"><li><a title="'.__('new game','WPdoodlez').'" href="' .
			add_query_arg( array('crossword'=>2), get_post_permalink() ).'"><i class="fa fa-exchange"></i> '. __('start new game','WPdoodlez').'</a></li></ul>';
		$html .= '<p>'.__('please mark words with pressed mousekey','WPdoodlez');
		$html .= '</p><div class="wrap"><section id="ws-area"></section>';
		$html .= '<ul class="ws-words"></ul></div>';
		$html .= '<!-- noformat on --><script>';
		$html .= " var inwords=$wdstring; var insortlongest=$wdstring; var inclues=$wcstring;
			  var longest = insortlongest.reduce(function (a, b) {return a.length > b.length ? a : b;});
			  var gameAreaEl = document.getElementById('ws-area');
			  var gameobj = gameAreaEl.wordSearch({
			  'directions': ['W', 'N', 'WN', 'EN'],
			  'words': inwords,
			  'clues': inclues,
			  'gridSize': longest.length,
			  'wordsList' : [],
			  'debug': false,}
			  );
			  var words = gameobj.settings.wordsList,
				wordsWrap = document.querySelector('.ws-words');
			  for (i in words) {
				var liEl = document.createElement('li');
				liEl.setAttribute('class', 'ws-word');
				liEl.innerText = words[i];
				wordsWrap.appendChild(liEl);
			  }
			</script><!-- noformat off -->
		";
		return $html;
	}
}
//   ----------------------------- wortpuzzle module ended -------------------------------------

//   ----------------------------- hangman begins -------------------------------------
function xwordhangman() {
	$randomhang = isset($_GET['randomize'])?intval(esc_html($_GET['randomize'])):0;
	if ($randomhang == 1) {
		// Get random word from all answers and the question as hint
		$args=array(
		  'orderby'=> 'rand',
		  'order'=> 'rand',
		  'post_type' => 'question',
		  'post_status' => 'publish',
		  'posts_per_page' => -1,
		  'showposts' => -1,
		);
		$my_query = new WP_Query($args);
		$rows=array();
		if( $my_query->have_posts() ) {
		  while ($my_query->have_posts()) : $my_query->the_post(); 
			$answers = get_post_custom_values('quizz_answer');
			$crossohneleer =  (strpos($answers[0], ' ') == false);
			if ($crossohneleer) {
				$crossant = preg_replace("/[^A-Za-z0-9]/", '', esc_html(umlauteumwandeln($answers[0]) ) );
				$crossfrag = get_the_content();
				if( strlen($crossant) <= 20 && strlen($crossant) >= 2 &&
					strlen($crossfrag) <= 40 && strlen($crossfrag) >= 5 ) {
					$quizkat='';
					$terms = get_the_terms(get_the_id(), 'quizcategory'); // Get all terms of a taxonomy
					if ( $terms && !is_wp_error( $terms ) ) {
						foreach ( $terms as $term ) {
							$quizkat .= ' &nbsp; <i class="fa fa-folder-open"></i> ' . $term->name . ' ';
						}
					}	
					$herkunftsland = get_post_custom_values('quizz_herkunftsland');
					$element = array( "wlink" => get_the_permalink(), "word" => $crossant, "clue" => $crossfrag, 'details' => '<a target="_blank" title="Frage als Quizfrage spielen" href="'.get_the_permalink().'">'.get_the_title().'</a> aus '.$herkunftsland[0].' '.$quizkat.' ' );
					$rows[] = $element;
				}	
			}	
		  endwhile;
		}
		wp_reset_query();  
		if ($rows) {
			$wdstring='';$wcstring='';$wdetails='';
			$wdstring = strtoupper($rows[0]['word']).",";
			$wcstring = ($rows[0]['clue']).",";
			$wdetails = $rows[0]['details'];
			$wdstring=rtrim($wdstring,',');
			$wcstring=rtrim($wcstring,',');
			$wlink = $rows[0]['wlink'];
		}
	} else {
		$quizkat='';
		$terms = get_the_terms(get_the_id(), 'quizcategory'); // Get all terms of a taxonomy
		if ( $terms && !is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$quizkat .= ' &nbsp; <i class="fa fa-folder-open"></i> ' . $term->name . ' ';
			}
		}	
		$herkunftsland = get_post_custom_values('quizz_herkunftsland');
		$answers = get_post_custom_values('quizz_answer');
		$crossant = preg_replace("/[^A-Za-z0-9]/", '', esc_html(umlauteumwandeln($answers[0]) ) );
		$wdstring = $crossant;
		$wcstring = get_the_content();
		$wdetails = '<a target="_blank" title="Frage als Quizfrage spielen" href="'.get_the_permalink().'">'.get_the_title().'</a> aus '.$herkunftsland[0].' '.$quizkat.' ';
		$wlink = get_the_permalink();
	}	
	$suffix = ( defined( 'STYLE_DEBUG' ) && STYLE_DEBUG ) ? '' : '.min';
	// Enqueue the JavaScript file
	wp_enqueue_script('hangman-app-script', plugins_url('/hangapp'.$suffix.'.js', __FILE__), array(), '1.0', true);
	wp_register_style( 'wp-hangman-styles', plugin_dir_url( __FILE__ ) . '/hangstyle'.$suffix.'.css', null );
	// script laden und lokalisieren
	$ratewort = base64_encode($wdstring); 
	wp_localize_script( 'hangman-app-script', 'hangman_app_script_data', Array ( 'answer' => $ratewort ) );
	wp_enqueue_style('wp-hangman-styles');

	$htmout = wpd_games_bar();
	$htmout .= '<ul class="footer-menu" style="display:inline-block"><li><a title="'.__('new game','WPdoodlez').'" href="' .
		add_query_arg( array('crossword'=>3,'randomize'=>1), get_post_permalink() ).'"><i class="fa fa-exchange"></i> '. __('start new game','WPdoodlez').'</a></li></ul>';
	$htmout .= '<p>Erraten Sie das Wort/den Satz (ohne Leerzeichen) aus '.strlen($wdstring).' Buchstaben 
		('.count( array_unique( str_split( $wdstring))).' davon eindeutig). &nbsp; 
		<i class="fa fa-hand-o-right"></i> <a href="'.add_query_arg( array('crossword'=>3,'randomize'=>0), $wlink ).'"><i title="Link aufrufen" class="fa fa-share-square-o"></i></a> 
		 &nbsp; <input type="text" placeholder="Wort und Enter" title="Link zum teilen kopieren" class="copy-to-clipboard" value="'.add_query_arg( array('crossword'=>3,'randomize'=>0), $wlink ).'" readonly>
		 &nbsp; '.$wdetails.'<p style="font-size:1.3em;margin-top:1em"> &nbsp; <i class="fa fa-question-circle"></i> '.$wcstring.'</p>
 		<div id="hangman-game">
		<div id="hangman-available-characters"><!-- the hangman game begins -->
		<ul id="hangman-available-characters-list"></ul></div>
		<div id="hangman-answer-placeholders"></div><div id="hangman-notices"></div>
		<div id="hangman-figure"><canvas id="hangman-canvas"></canvas></div></div><!-- the hangman game ends -->
	';
	return $htmout;
}
//   ----------------------------- hangman ended -------------------------------------

//   ----------------------------- Sudoku php game and solver -------------------------------------

// Sudoku Class --------------------------------------------------------------------

class Sudoku {
    private $_matrix;
    public function __construct(array $matrix = null) {
        if (!isset($matrix)) {
            $this->_matrix = $this->_getEmptyMatrix();
        } else {
            $this->_matrix = $matrix;
        }
    }
 
    public function generate() {
        $this->_matrix = $this->_solve($this->_getEmptyMatrix());
        $cells = array_rand(range(0, 80), 30);
        $i = 0;
        foreach ($this->_matrix as &$row) {
            foreach ($row as &$cell) {
                if (!in_array($i++, $cells)) {
                    $cell = null;
                }
            }
        }
        return $this->_matrix;
    }
 
    public function solve() {
        $this->_matrix = $this->_solve($this->_matrix);
        return $this->_matrix;
    }
 
    public function getHtml() {
		echo '<div class="sdk-table">';
        for ($row = 0; $row < 9; $row++) {
            echo '<div class="sdk-row">';
            for ($column = 0; $column < 9; $column++) {
				echo '<div class="sdk-col">';
				if (empty($this->_matrix[$row][$column])) echo '<input class="sdk-input" min="1" max="9" type="number" step="1" size="1">';
				else echo $this->_matrix[$row][$column];
				echo '</div>';
            }
            echo '</div>';
        }
		echo '</div>';
    }
 
    private function _getEmptyMatrix() {
        return array_fill(0, 9, array_fill(0, 9, 0));
    }
 
    private function _solve($matrix) {
        while(true) {
            $options = array();
            foreach ($matrix as $rowIndex => $row) {
                foreach ($row as $columnIndex => $cell) {
                    if (!empty($cell)) {
                        continue;
                    }
                    $permissible = $this->_getPermissible($matrix, $rowIndex, $columnIndex);
                    if (count($permissible) == 0) {
                        return false;
                    }
                    $options[] = array(
                        'rowIndex' => $rowIndex,
                        'columnIndex' => $columnIndex,
                        'permissible' => $permissible
                    );
                }
            }
            if (count($options) == 0) {
                return $matrix;
            }
 
            usort($options, array($this, '_sortOptions'));
 
            if (count($options[0]['permissible']) == 1) {
                $matrix[$options[0]['rowIndex']][$options[0]['columnIndex']] = current($options[0]['permissible']);
                continue;
            }
 
            foreach ($options[0]['permissible'] as $value) {
                $tmp = $matrix;
                $tmp[$options[0]['rowIndex']][$options[0]['columnIndex']] = $value;
                if ($result = $this->_solve($tmp)) {
                    return $result;
                }
            }
 
            return false;
        }
    }
 
    private function _getPermissible($matrix, $rowIndex, $columnIndex) {
        $valid = range(1, 9);
        $invalid = $matrix[$rowIndex];
        for ($i = 0; $i < 9; $i++) {
            $invalid[] = $matrix[$i][$columnIndex];
        }
        $box_row = $rowIndex % 3 == 0 ? $rowIndex : $rowIndex - $rowIndex % 3;
        $box_col = $columnIndex % 3 == 0 ? $columnIndex : $columnIndex - $columnIndex % 3;
        $invalid = array_unique(array_merge(
            $invalid,
            array_slice($matrix[$box_row], $box_col, 3),
            array_slice($matrix[$box_row + 1], $box_col, 3),
            array_slice($matrix[$box_row + 2], $box_col, 3)
        ));
        $valid = array_diff($valid, $invalid);
        shuffle($valid);
        return $valid;
    }
 
    private function _sortOptions($a, $b) {
        $a = count($a['permissible']);
        $b = count($b['permissible']);
        if ($a == $b) {
            return 0;
        }
        return ($a < $b) ? -1 : 1;
    }
 
}

// ---------------------------------------------------------------------------------

/* -------------- sample grid to solve  ---------------------
$grid = array(
    array(0,0,0,0,0,0,2,0,3),
    array(8,0,7,0,0,0,0,6,0),
    array(0,0,2,6,5,0,0,0,8),
    array(0,3,0,0,0,0,0,0,0),
    array(7,5,0,2,0,0,1,0,0),
    array(0,0,1,0,3,0,5,0,0),
    array(4,0,0,5,0,0,8,7,0),
    array(6,0,0,0,4,2,0,0,0),
    array(0,9,5,0,6,0,0,2,0)
);
$s = new Sudoku($grid);  // new class can be instantiated and stored in a variable using the new keyword and passing the variable into that function:
$s->solve();  /// This Function is to solve the puzzle..
echo $s->getHtml(); // DIsplay the solution or output in the html format

// --------------- generate new sudoku -----------------------
$s2 = new Sudoku();
$s2->generate();  // Generate the new sudoku puzzle
echo $s2->getHtml();
$s2->solve();
echo $s2->getHtml();
   ------------------------------------------------------ */

function xsudoku() {
	echo '<style>
	input::-webkit-outer-spin-button,input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
	@media screen {#loesung {display:none} }
	@media print {#loesung {display:block} }
	.sdk-input{font-size:.9em;height:30px;text-align:center;width:30px}
	.sdk-row:after{clear:both;content:".";display:block;height:0;visibility:hidden}
	.sdk-table{border:#AAA solid 2px;border-radius:6px;color:#777;display:block;font-size:1.8em;margin:auto;max-width:421px;position:relative}
	.sdk-row{display:block;position:relative;width:100%;z-index:2}
	.sdk-col{align-content:center;align-items:center;border-color:#CCC;border-style:dashed;border-width:2px 2px 0 0;display:flex;float:left;justify-content:center;min-height:45px;position:relative;width:45px}
	.sdk-row.sdk-border{border-bottom:#AAA solid 2px}
	.sdk-col.sdk-border{border-right:#AAA solid 2px}
	.sdk-table .sdk-row .sdk-col:last-child{border-right:none}
	.sdk-table .sdk-row:first-child .sdk-col,.sdk-table .sdk-row:nth-child(4) .sdk-col,.sdk-table .sdk-row:nth-child(7) .sdk-col{border-top:none}
	.sdk-row:first-child .sdk-col:nth-child(n+4):nth-child(-n+6),.sdk-row:nth-child(2) .sdk-col:nth-child(n+4):nth-child(-n+6),.sdk-row:nth-child(3) .sdk-col:nth-child(n+4):nth-child(-n+6),.sdk-row:nth-child(7) .sdk-col:nth-child(n+4):nth-child(-n+6),.sdk-row:nth-child(8) .sdk-col:nth-child(n+4):nth-child(-n+6),.sdk-row:nth-child(9) .sdk-col:nth-child(n+4):nth-child(-n+6),.sdk-row:nth-child(4) .sdk-col:nth-child(n+1):nth-child(-n+3),.sdk-row:nth-child(5) .sdk-col:nth-child(n+1):nth-child(-n+3),.sdk-row:nth-child(6) .sdk-col:nth-child(n+1):nth-child(-n+3),.sdk-row:nth-child(4) .sdk-col:nth-child(n+7):nth-child(-n+9),.sdk-row:nth-child(5) .sdk-col:nth-child(n+7):nth-child(-n+9),.sdk-row:nth-child(6) .sdk-col:nth-child(n+7):nth-child(-n+9){background-color:#ccc8}
	</style>';
	echo wpd_games_bar();
	echo '<ul class="footer-menu" style="display:inline-block"><li><a title="'.__('new game','WPdoodlez').'" 
		href="'.add_query_arg( array('crossword'=>4), get_post_permalink() ).'"><i class="fa fa-exchange"></i> '. __('start new game','WPdoodlez').'</a></li></ul>';
	$disclaimer = __('Random generated Sudoku. Enter your numbers and click for solution or print with solution' ,'WPdoodlez').'. ';
    echo esc_html__($disclaimer);
	$s2 = new Sudoku();
	$s2->generate();  // Generate the new sudoku puzzle
	echo $s2->getHtml();
	echo '<button style="width:100%" onclick="document.getElementById(\'loesung\').style.display = \'block\'">';
	echo __('solution','WPdoodlez'). '</button>';
	echo '<div id="loesung">';
	$s2->solve();
	echo $s2->getHtml();
	echo '</div>';
}

//   ----------------------------- sudoku ended -------------------------------------

//   ----------------------------- wortpuzzle module ended -------------------------------------

// ------------ Funktionen --------------
	function str_shuffle_unicode($str) {
		$tmp = preg_split("//u", $str, -1, PREG_SPLIT_NO_EMPTY);
		shuffle($tmp);
		return join("", $tmp);
	}

	function checkWord($guess, $solution){
		// schwarz=Buchstabe nicht enthalten, rot=enthalten falsche Position, grün=enthalten richtige Position
		$arr1 = preg_split('//u', $solution); 
		$arr2 = preg_split('//u', $guess); 
		$arr1_c = array_count_values($arr1);
		$arr2_c = array_count_values($arr2);
		$out = '';
		foreach($arr2 as $key=>$value){
			$arr1_c[$value]=(isset($arr1_c[$value])?$arr1_c[$value]-1:0);
			$arr2_c[$value]=(isset($arr2_c[$value])?$arr2_c[$value]-1:0);
			if ($key>0 && $key < count($arr2) - 1 ) {
				if (isset($arr2[$key]) && isset($arr1[$key]) && $arr1[$key] == $arr2[$key]) {
					$out .='<span id="drag'.$key.'" class="drag" draggable="true" ondragstart="drag(event)" style="color:green;">'.$arr2[$key].'</span>';
				} elseif (in_array($value,$arr1) && $arr2_c[$value] >= 0 && $arr1_c[$value] >= 0) {
					$out .='<span id="drag'.$key.'" class="drag" draggable="true" ondragstart="drag(event)" style="color:red">'.$arr2[$key].'</span>';
				} else {
					$out .='<span id="drag'.$key.'" class="drag" draggable="true" ondragstart="drag(event)" style="color:black">'.$arr2[$key].'</span>';
				}
			}	
		}
		return $out;
	}

function xwordshuffle() {
    $args=array(
      'orderby'=> 'rand',
      'order'=> 'rand',
      'post_type' => 'question',
      'post_status' => 'publish',
      'posts_per_page' => -1,
	  'showposts' => -1,
    );
    $my_query = new WP_Query($args);
	$rows=array();
    if( $my_query->have_posts() ) {
      while ($my_query->have_posts()) : $my_query->the_post(); 
		$answers = get_post_custom_values('quizz_answer');
		$crossohneleer =  (strpos($answers[0], ' ') == false);
		if ($crossohneleer) {
				$crossant = preg_replace("/[^A-Za-zäüöÄÖÜß]/", '', esc_html(umlauteumwandeln($answers[0]) ) );
				$crossfrag = get_the_content();
				if( strlen($crossant) <= 12 && strlen($crossant) >= 4 &&
				    strlen($crossfrag) <= 40 && strlen($crossfrag) >= 5 ) {
					$element = array( "word" => $crossant, "clue" => $crossfrag );
					$rows[] = $element;
				}	
		}	
      endwhile;
    }
    wp_reset_query();  
    $html = '';
    if ($rows) {
		$html .= wpd_games_bar();
		$html .= '<ul class="footer-menu" style="display:inline-block"><li><a title="'.__('new game','WPdoodlez').'" href="' .
			add_query_arg( array('crossword'=>5), get_post_permalink() ).'"><i class="fa fa-exchange"></i> '. __('start new game','WPdoodlez').'</a></li></ul>';
		$html .= '<p>'.__('please drag and drop (or enter) word in right order','WPdoodlez') . '</p>';
		$html .= '<style>#div1 {display:inline-block;font-size:2rem;width:100%;height: 2.1em;
		padding: 10px;border: 1px solid #aaa;white-space:nowrap}
		.drag {font-size:2rem;font-family:sans-serif;border:1px dotted black;padding:4px;margin:12px}
		</style><script>
		function allowDrop(ev) {  ev.preventDefault();	}
		function drag(ev) {  ev.dataTransfer.setData("text", ev.target.id);	}
		function drop(ev) {
		  ev.preventDefault();
		  var data = ev.dataTransfer.getData("text");
		  ev.target.appendChild(document.getElementById(data));
		}
		function gettext() {
			var node = document.getElementById("div1");
			textContent = node.textContent;
			document.getElementById("solution").value = textContent;
			if ( textContent == document.getElementById("compareto").value ) {
				alert("'.__('Congrats. You solved by drag and drop','WPdoodlez').'");
			}
		}
		function checkright() {
			textContent = document.getElementById("solution").value.toUpperCase();
			if ( textContent == document.getElementById("compareto").value.toUpperCase() ) {
				alert("'.__('Congrats. You solved by entering the word','WPdoodlez').'");
			}
		}
		function shuf_solve() {
		  var x = document.getElementById("aufloesen");
		  if (x.style.display === "none") {
			x.style.display = "inline-block";
		  } else {
			x.style.display = "none";
		  }
		}
		</script>';
		// **** convertieren ***
		setlocale (LC_ALL, 'de_DE@euro', 'de_DE', 'de', 'ge');
		$hint = $rows[0]['clue'];
		$str = $rows[0]['word'];
		$strup = mb_strtoupper($str, "UTF-8");
		$html .= '<p style="font-size:1.2em;margin-top:1em"><i class="fa fa-hand-o-right"></i> ' . $hint . '</p>';
		$shuffled = str_shuffle_unicode($strup);
		$html .= '<p>' . checkWord($shuffled,$strup).'<br></p>';
		$html .= '<div id="div1" ondrop="drop(event);gettext()" ondragover="allowDrop(event)"></div>';
		$html .= '<br><input type="text" placeholder="'.__('input word and ENTER','WPdoodlez').'" id="solution" onchange="checkright()" 
			style="text-transform:uppercase;width:100%" /><label for="solution">'.__('your guess','WPdoodlez').'</label>';
		$html .= '<input type="hidden" id="compareto" style="text-transform:uppercase" value="'.$strup.'"/>';
		$html .= ' &nbsp; <button class="btn" onclick="shuf_solve()">'.__('solution','WPdoodlez').'</button>
			<div id="aufloesen" style="display:none;text-transform:uppercase;font-size:1.3em">'.$strup.'</div>';
		return $html;
	}
}

//   ----------------------------- wortpuzzle module ended -------------------------------------

?>
