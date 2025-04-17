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
						$votezeit = '<abbr title="'.__( 'vote', 'WPdoodlez' ).' '.$cct.'">' . colordatebox( $vote['zeit'], NULL, NULL, 1 ) . '</abbr></br>';
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
					if (isset($vote['zeit'])) $votezeit = colordatebox ( $vote['zeit'], NULL, NULL, 1 ); else $votezeit='';
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
		echo '<p class="newlabel" style="background-color:#fe8;display:none">' . __( 'Shortcode copied to clipboard.', 'WPdoodlez' ) . '</p>';
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
					$votezeit = '<abbr title="'.__( 'vote', 'WPdoodlez' ).' '.$cct.'">'. colordatebox($vote['zeit'], NULL, NULL, 1 ) . '</abbr></br>';
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
			fputcsv( $output, $csvhead, ';', escape: "");
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
				fputcsv( $output, $csvout, ';', escape: "");
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
	$spiele .= '<li><a style="padding:2px 5px" title="'.__('play word shuffle','WPdoodlez').'" href="'.add_query_arg( array('crossword'=>6), get_post_permalink() ).'"><i class="fa fa-recycle"></i> '. __('Rebus','WPdoodlez').'</a></li>';
	$spiele .= '<li><a style="padding:2px 5px" title="'.__('play word shuffle','WPdoodlez').'" href="'.add_query_arg( array('crossword'=>7), get_post_permalink() ).'"><i class="fa fa-gg"></i> '. __('syllable puzzle','WPdoodlez').'</a></li>';
	$spiele .= '<li><a style="padding:2px 5px" title="'.__('play word shuffle','WPdoodlez').'" href="'.add_query_arg( array('crossword'=>8), get_post_permalink() ).'"><i class="fa fa-car"></i> '. __('car quartet','WPdoodlez').'</a></li>';
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
		$theForm .= '<p style="font-size:0.7em;margin-top:2em">'.wp_date( 'D, j. F Y, H:i:s');
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
			if ( function_exists('meta_icons')) $message .= '<div class="meta-icons" style="background-color:'.$accentcolor.'10">'. meta_icons(0).'</div>';
			else $message .= '<div class="greybox" style="background-color:'.$accentcolor.'19">' . '' . $quizkat.'</div>';
			$message .= '</header>';
			$message .= '<h2 class="entry-title"><a title="' . __('answer question', 'WPdoodlez') . '" href="'.get_post_permalink().'">'.get_the_title();
			$message .= '&nbsp; ' . $herkunftsland[0].'</a></h2>';
			$message .= '<div class="entry-content">'.get_the_content().'</div>';
			if (1 == $gamebar) {
				$message .= '<div style="text-align:center;background-color:'.$accentcolor.'10">';
				$message .= wpd_games_bar();
				$message .= '</div>';
			}		

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
		while (($data = fgetcsv($handle, 2000, ";", escape: "")) !== FALSE) {
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
		fputcsv( $output, array('id','datum','charakter','land','titel','seitjahr','bemerkungen','antwortb','antwortc','antwortd','zusatzinfo','quizkat','ISO','Bild'), ';', escape: "");
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


// Einzelanzeige Quiz
function quiz_show_form( $content ) {
	if (get_post_type() == 'question' && !isset($_GET['crossword']) ) {
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
		$letztefrage .= '<p class="newlabel" style="background-color:#fe8;display:none">Frage in Zwischenablage kopiert</p></li>' . $listyle. '<a title="'. __('overview','WPdoodlez').'" href="'.get_home_url().'/question?orderby=rand&order=rand"><i class="fa fa-list"></i></a>';
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
			$theForm .= '<p style="font-size:0.7em;margin-top:5px">'.wp_date( 'D, j. F Y, H:i:s');
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
    public function __construct(?array $matrix = null) {
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

//   ----------------------------- rebus module begin -------------------------------------

	function convertUmlaute($word) {
		$replacements = [
			"Ä" => "AE",
			"ä" => "ae",
			"Ö" => "OE",
			"ö" => "oe",
			"Ü" => "UE",
			"ü" => "ue",
			"ß" => "ss"
		];
		return strtr($word, $replacements);
	}

	function createRebus($word) {
		// Array für mehrere mögliche Bilder, ihre Bedeutungen und Unicode-Zeichen
		$imageMeanings = [
			"A" => [["Apfel", "🍎"], ["Anker", "⚓"], ["Ameise", "🐜"], ["Auto", "🚗"], ["Alpaka", "🦙"],["Aepfel", "🍎"], ["Aehre", "🌾"], ["Aequator", "🌍"]],
			"B" => [["Buch", "📖"], ["Ball", "⚽"], ["Blume", "🌺"], ["Baum", "🌳"], ["Baer", "🐻"]],
			"C" => [["Clown", "🤡"], ["Computer", "💻"], ["Citrone", "🍋"], ["Couch", "🛋️"], ["Chamaeleon", "🦎"]],
			"D" => [["Diamant", "💎"], ["Drache", "🐉"], ["Dino", "🦖"], ["Delfin", "🐬"], ["Dose", "🥫"]],
			"E" => [["Eule", "🦉"], ["Ei", "🥚"], ["Erdbeere", "🍓"], ["Edelstein", "💍"], ["Elch", "🦌"]],
			"F" => [["Fisch", "🐟"], ["Flasche", "🍾"], ["Fuchs", "🦊"], ["Fahrrad", "🚲"], ["Feuer", "🔥"]],
			"G" => [["Gitarre", "🎸"], ["Globus", "🌍"], ["Gabel", "🍴"], ["Gans", "🦢"], ["Gorilla", "🦍"]],
			"H" => [["Haus", "🏠"], ["Hammer", "🔨"], ["Hut", "🎩"], ["Hund", "🐶"], ["Herz", "❤️"]],
			"I" => [["Igel", "🦔"], ["Insel", "🏝️"], ["Iglu", "❄️"], ["Indigener", "ᐂ"], ["Instrument", "🎷"]],
			"J" => [["Jacke", "🧥"], ["Juwel", "💍"], ["Joghurt", "🥛"], ["Jet", "✈️"], ["Jaguar", "🐆"]],
			"K" => [["Katze", "🐱"], ["Kuchen", "🍰"], ["Krabbe", "🦀"], ["Keks", "🍪"], ["Kaktus", "🌵"]],
			"L" => [["Lampe", "💡"], ["Loewe", "🦁"], ["Loeffel", "🥄"], ["Laterne", "🎃"], ["Lorbeer", "🌿"]],
			"M" => [["Mond", "🌙"], ["Melone", "🍉"], ["Maus", "🐭"], ["Mikrofon", "🎤"], ["Meerjungfrau", "🧜"]],
			"N" => [["Nase", "👃"], ["Nacht", "🌌"], ["Nuss", "🌰"], ["Nebel", "🌫️"], ["Nilpferd", "🦛"]],
			"O" => [["Orange", "🍊"], ["Obst", "🍓"], ["Ofen", "🍞"], ["Oktopus", "🐙"], ["Orchester", "🎻"],["Oel", "🛢️"], ["Oesterreich", "🎿"], ["Oeffnung", "🚪"]],
			"P" => [["Pferd", "🐴"], ["Papagei", "🦜"], ["Pizza", "🍕"], ["Pfanne", "🍳"], ["Palme", "🌴"]],
			"Q" => [["Qualle", "🐙"], ["Quark", "🍶"], ["Quarz", "⛏️"], ["Quitte", "🍐"], ["Quad", "🛺"]],
			"R" => [["Rose", "🌹"], ["Regenschirm", "☂️"], ["Roboter", "🤖"], ["Rucksack", "🎒"], ["Regenbogen", "🌈"]],
			"S" => [["Sonne", "☀️"], ["Schiff", "🚢"], ["Schlange", "🐍"], ["Schneemann", "⛄"], ["Sessel", "🪑"]],
			"T" => [["Tasse", "☕"], ["Tisch", "🪑"], ["Tiger", "🐯"], ["Traktor", "🚜"], ["Trommel", "🥁"]],
			"U" => [["Uhr", "⏰"], ["Unicorn", "🦄"], ["Unterwasserwelt", "🐠"], ["Ulme", "🌳"], ["Uhu", "🦉"],["Ueberraschung", "🎉"], ["Uebung", "🏋️"], ["Uebersetzer", "🌐"]],
			"V" => [["Vogel", "🐦"], ["Vanille", "🌸"], ["Vulkan", "🌋"], ["Violine", "🎻"], ["Vampir", "🧛"]],
			"W" => [["Wal", "🐋"], ["Wolke", "☁️"], ["Waffel", "🧇"], ["Walnuss", "🌰"], ["Wasserfall", "🌊"]],
			"X" => [["X-Ray", "❌"], ["Xylophon", "🎵"], ["Xenon-Lampe", "💡"]],
			"Y" => [["Yak", "🐂"], ["Yeti", "🧌"], ["Yoga", "🧘"], ["Yoyo", "🪀"], ["Yacht", "🛥️"]],
			"Z" => [["Zebra", "🦓"], ["Zauberstab", "✨"], ["Zitrone", "🍋"], ["Zwiebel", "🧅"], ["Zoo", "🐾"]]
		];

		$rebus = [];
		$decodedWords = [];
		$letters = str_split(strtoupper($word));
		foreach ($letters as $letter) {
			if ($letter === " ") {
				$rebus[] = '<span style="font-size:2.1em;margin-right:1em">␣</span>';
			} elseif (array_key_exists($letter, $imageMeanings)) {
				$options = $imageMeanings[$letter];
				$randomChoice = $options[array_rand($options)];
				// Zufällige zusätzliche Buchstaben generieren
				$additionalChars = chr(rand(65, 90)) . chr(rand(65, 90)); // Zwei zufällige Buchstaben (A-Z)
				// Zusätzliche Buchstaben mitten in das Originalwort einfügen
				$middle = floor(strlen($randomChoice[0]) / 2);
				$wordWithAdditionalChars = substr($randomChoice[0], 0, $middle) . $additionalChars . substr($randomChoice[0], $middle);
				// Das gesamte Wort mit den eingefügten Buchstaben durchmischen
				$scrambledImage = str_shuffle($wordWithAdditionalChars);
				$rebus[] = '<span style="font-size:2em;margin-right:2em;white-space:nowrap">' . $randomChoice[1] . ' ' . strtoupper($scrambledImage) . '</span>';
				$decodedWords[] = $randomChoice[1].' '.strtoupper($randomChoice[0]);
			} else {
				$rebus[] = $letter;
			}
		}
		return ["rebus" => $rebus, "decoded" => $decodedWords];
	}

function xrebus() {
    $html = wpd_games_bar();
	$html .= '<ul class="footer-menu" style="display:inline-block"><li><a title="'.__('new game','WPdoodlez').'" href="' .
		add_query_arg( array('crossword'=>6), get_post_permalink() ).'"><i class="fa fa-exchange"></i> '. __('start new game','WPdoodlez').'</a></li></ul>';

	if ($_SERVER["REQUEST_METHOD"] == "POST") {
		$word = esc_html(trim($_POST['xwort']));
		$hint = esc_html(trim($_POST['xhint']));
		$userInput = strtoupper(trim($_POST['loesung']));
		$isCorrect = (int) ($userInput == strtoupper($word) ) ?? 0;
	} else {
		// Zufalls-Antwort aus Quizfragen
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
					$crossant = preg_replace("/[^A-Za-zäüöÄÖÜß ]/", '', esc_html(umlauteumwandeln($answers[0]) ) );
					$crossfrag = get_the_content();
					if( strlen($crossant) <= 40 && strlen($crossant) >= 8 ) {
						$element = array( "word" => $crossant, "clue" => $crossfrag );
						$rows[] = $element;
					}	
		  endwhile;
		}
		wp_reset_query();  
		// Aufruf Beispiel hier Quizantwort einsetzen
		$word = convertUmlaute($rows[0]['word']); // Dein Satz, umgewandelt in AE statt Umlaute
		$hint = $rows[0]['clue'];
	}
	$rebusData = createRebus($word);
	// Ausgabe des Rebus
	$html .= '<p>Gesucht wird ein Wort oder ein Satz bestehend aus '.strlen($word).' Buchstaben (inkl. Leerzeichen) und '.str_word_count($word).' Wörtern.
		Die Emojis können einen Hinweis geben, müssen aber nicht. Die vermischten Wörter enthalten jeweils zwei zusätzliche falsche
		Buchstaben und sollen ebenfalls erraten werden.</p><div class="headline"><i class="fa fa-lg fa-lightbulb-o"></i> '.$hint.'</div>';
	$html .= implode(" ", $rebusData["rebus"]);
	$html .= '<p><form method="post"><label for="loesung">Dein Lösungswort oder Satz:</label>
		<input type="text" id="loesung" name="loesung" required>
		<input type="hidden" id="xwort" name="xwort" value="'.$word.'">
		<input type="hidden" id="xhint" name="xhint" value="'.$hint.'">
		<button type="submit">Prüfen</button>';
	$html .= wp_nonce_field( 'rebus_submit', 'rebus_nonce', 1, 0 );
	$html .= '</form></p>';
	if (isset($isCorrect)) {
		$html .= '<h6>'.__('result','WPdoodlez').'</h6><p>';
		$html .= '<p>';
		if ($isCorrect == 1) {
			$html .= '✅ '.__('correct answer','WPdoodlez').'</p>';
			$html .= '<p><strong>Entschlüsselte Wörter:</strong> ' . implode(", ", $rebusData["decoded"]) . '</p>
				<p><strong>Lösung:</strong> ' . $word . '</p>';
		} else {
			$html .= '❌ '.__('wrong answer','WPdoodlez').' '.__('try again','WPdoodlez').'</p>';
		}
	}
	return $html;
	
}
//   ----------------------------- rebus module ended -------------------------------------

//   ----------------------------- Silbenrätsel module begin -------------------------------------

function generateRandomWords($numWords, $words) {
    shuffle($words);
    return array_slice($words, 0, $numWords);
}

function splitIntoSyllables($word) {
    // Einfache Silbentrennung basierend auf Vokalen
    return preg_split('/(?<=[aeiou])/', $word, -1, PREG_SPLIT_NO_EMPTY);
}

function xsillableshuffle() {
	$html = wpd_games_bar();
	$html .= '<ul class="footer-menu" style="display:inline-block"><li><a title="'.__('new game','WPdoodlez').'" href="' .
		add_query_arg( array('crossword'=>7), get_post_permalink() ).'"><i class="fa fa-exchange"></i> '. __('start new game','WPdoodlez').'</a></li></ul>';
    $html .= '<style>#words{margin-top:1em}
	.syllable{background-color:#eee8;border:1px solid #ccc;cursor:pointer;display:inline-block;font-size:1.1em;margin:5px;padding:8px}
	.syllable.strikethrough{background-color:#8888;color:#aaa;text-decoration:line-through}
.dropzone{background-color:#eee8;border:1px solid #aaa;font-size:1.2em;min-width:200px;padding:10px}.hintbox{margin-bottom:4px;border:1px dotted #888}</style>';
		// Zufalls-Antwort aus Quizfragen
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
	// Wörter und Hints bauen
	foreach ($rows as $row) {
		$wort = $row['word'];
		$words[] = $wort;
		$hints[$wort] = $row['clue'];
	}	
	/*
	//Lösungswörter Beispiele
	$words = [ "Berlin", "Hamburg" ];
	// Hinweise
	$hints = [ "Berlin" => "Die Hauptstadt von Deutschland.", "Hamburg" => "Eine große Hafenstadt im Norden Deutschlands."	];
	*/

	$randomWords = generateRandomWords(10, $words);
	$syllables = [];
	foreach ($randomWords as $word) {
		$syllables = array_merge($syllables, splitIntoSyllables($word));
	}
	sort($syllables);
	// Hauptprogramm Silben
	$html .= '<div id="syllables">';
	foreach ($syllables as $syllable) {
	$html .= '<div class="syllable" draggable="true" ondragstart="drag(event)">' . $syllable .'</div>';
	}
	$html .= '</div><div id="words">';
	foreach ($randomWords as $ctr => $word) {
		$html .= '<div class="dropzone" ondrop="drop(event)" ondragover="allowDrop(event)" data-word="'. $word .'"></div>
			<div class="hintbox">' .($ctr + 1) . '. ' . $hints[$word] . '</div>';
	}
	$html .= '</div><button onclick="checkAnswers()">Auswerten und Lösung anzeigen</button><div id="results"></div>';
	$html .= '<script>
		function allowDrop(event) {
			event.preventDefault();
		}
		function drag(event) {
			event.dataTransfer.setData("text", event.target.innerText);
		}
		function drop(event) {
			event.preventDefault();
			var data = event.dataTransfer.getData("text");
			event.target.innerText += data;
			var syllables = document.querySelectorAll(".syllable");
			syllables.forEach(function(syllable) {
				if (syllable.innerText === data) {
					syllable.classList.add("strikethrough");
				}
			});
		}
	 function checkAnswers() {
		var dropzones = document.querySelectorAll(".dropzone");
		var results = document.getElementById("results");
		var correctCount = 0;
		var incorrectCount = 0;
		results.innerHTML = "<h2>Auswertung</h2>";
		dropzones.forEach(function(dropzone) {
			var word = dropzone.getAttribute("data-word");
			if (dropzone.innerText === word) {
				correctCount++;
				results.innerHTML += "<div>Richtig: " + word + "</div>";
			} else {
				incorrectCount++;
				results.innerHTML += "<div style=\'color:red\'>Falsch: " + word + " (Deine Antwort: " + dropzone.innerText + ")</div>";
			}
		});
		var total = correctCount + incorrectCount;
		var percentage = (correctCount / total) * 100;
		results.innerHTML += "<p>Richtige Antworten: " + correctCount + ", ";
		results.innerHTML += "Falsche Antworten: " + incorrectCount + ". ";
		results.innerHTML += "Erreichter Prozentsatz: " + percentage.toFixed(2) + "%</p>";
	}

	</script>';
	return $html;
}
//   ----------------------------- Silbenrätsel module ended -------------------------------------

// --------------------------------- Autoquartett Spiel PHP Wordpress --------------------------------------------------------
function xautoquartett() {
	// Gesamt-Array aller Autodaten inkl. PBs Autodatenbank
	$kartenfull = [
	[ "baujahr" => "2020", "name" => "AstonMartin DBS", "ps" => 725, "vmax" => 340, "verbrauch" => 15, "beschleunigung" => 3.4, "gewicht" => 1700, "hubraum" => 5200, "zylinder" => 12, "preis" => 280000, "bild" => "tn-astonmartindbs.jpg" ],
	[ "baujahr" => "2005", "name" => "Audi A3 2.0 TDI", "ps" => 150, "vmax" => 216, "verbrauch" => 4.2, "beschleunigung" => 8.7, "gewicht" => 1285, "hubraum" => 1968, "zylinder" => 4, "preis" => 24000, "bild" => "tn-audia3-sb.jpg" ],
	[ "baujahr" => "2013", "name" => "Audi A4 2.0 TDI", "ps" => 143, "vmax" => 210, "verbrauch" => 4.9, "beschleunigung" => 9.1, "gewicht" => 1560, "hubraum" => 1968, "zylinder" => 4, "preis" => 38000, "bild" => "tn-audia4kom-2013.jpg" ],
	[ "baujahr" => "1996", "name" => "Audi A4 1.8 FSI", "ps" => 125, "vmax" => 200, "verbrauch" => 7.1, "beschleunigung" => 10.9, "gewicht" => 1390, "hubraum" => 1781, "zylinder" => 4, "preis" => 42000, "bild" => "tn-a4-2000.jpg" ],
	[ "baujahr" => "2005", "name" => "Audi A4 1.9 TDI", "ps" => 150, "vmax" => 215, "verbrauch" => 4.8, "beschleunigung" => 9.2, "gewicht" => 1550, "hubraum" => 1968, "zylinder" => 4, "preis" => 34000, "bild" => "tn-a4-2005.jpg" ],
	[ "baujahr" => "2005", "name" => "Audi A4 2.0 TDI", "ps" => 140, "vmax" => 212, "verbrauch" => 5.7, "beschleunigung" => 9.9, "gewicht" => 1430, "hubraum" => 1968, "zylinder" => 4, "preis" => 30000, "bild" => "tn-audia32005lim.jpg" ],
	[ "baujahr" => "2002", "name" => "Audi A4 3.0 FSI Cabrio", "ps" => 220, "vmax" => 242, "verbrauch" => 10.8, "beschleunigung" => 7.8, "gewicht" => 1695, "hubraum" => 2976, "zylinder" => 6, "preis" => 43000, "bild" => "tn-audia4cab.jpg" ],
	[ "baujahr" => "2012", "name" => "Audi A6 2.0 TDI", "ps" => 177, "vmax" => 226, "verbrauch" => 5, "beschleunigung" => 8.3, "gewicht" => 1650, "hubraum" => 1968, "zylinder" => 4, "preis" => 48000, "bild" => "tn-audia6variant2012.jpg" ],
	[ "baujahr" => "2010", "name" => "Audi A6 3.0 TDI", "ps" => 218, "vmax" => 245, "verbrauch" => 5, "beschleunigung" => 6.6, "gewicht" => 1840, "hubraum" => 2967, "zylinder" => 6, "preis" => 58000, "bild" => "tn-audia6-2010.jpg" ],
	[ "baujahr" => "2013", "name" => "Audi R8 V10 FSI quattro", "ps" => 525, "vmax" => 313, "verbrauch" => 13.8, "beschleunigung" => 3.8, "gewicht" => 1720, "hubraum" => 5204, "zylinder" => 10, "preis" => 135200, "bild" => "tn-audir8v10sp.jpg" ],
	[ "baujahr" => "2016", "name" => "Bentley Bentayga SUV First Edition", "ps" => 600, "vmax" => 301, "verbrauch" => 13.1, "beschleunigung" => 4.1, "gewicht" => 2440, "hubraum" => 5998, "zylinder" => 12, "preis" => 250000, "bild" => "tn-bentley-suv.jpg" ],
	[ "baujahr" => "2017", "name" => "Bentley Continental GT Coupe", "ps" => 672, "vmax" => 322, "verbrauch" => 14, "beschleunigung" => 4.5, "gewicht" => 2320, "hubraum" => 5998, "zylinder" => 12, "preis" => 320000, "bild" => "tn-bentleycontgtmansouri.jpg" ],
	[ "baujahr" => "1997", "name" => "BMW 316i compact", "ps" => 102, "vmax" => 188, "verbrauch" => 7.5, "beschleunigung" => 12.3, "gewicht" => 1215, "hubraum" => 1596, "zylinder" => 4, "preis" => 20000, "bild" => "tn-bmw316i.jpg" ],
	[ "baujahr" => "2003", "name" => "BMW 316i", "ps" => 102, "vmax" => 188, "verbrauch" => 7.5, "beschleunigung" => 12.3, "gewicht" => 1215, "hubraum" => 1596, "zylinder" => 4, "preis" => 20000, "bild" => "tn-bmw323i.jpg" ],
	[ "baujahr" => "2014", "name" => "BMW 318d Sport", "ps" => 143, "vmax" => 210, "verbrauch" => 4.5, "beschleunigung" => 9.1, "gewicht" => 1430, "hubraum" => 1598, "zylinder" => 4, "preis" => 35000, "bild" => "tn-bmw3er-2012.jpg" ],
	[ "baujahr" => "2000", "name" => "BMW 318i", "ps" => 143, "vmax" => 215, "verbrauch" => 7.1, "beschleunigung" => 9.3, "gewicht" => 1350, "hubraum" => 1796, "zylinder" => 4, "preis" => 28000, "bild" => "tn-e46.jpg" ],
	[ "baujahr" => "2004", "name" => "BMW 318iA", "ps" => 143, "vmax" => 215, "verbrauch" => 7.1, "beschleunigung" => 9.3, "gewicht" => 1350, "hubraum" => 1796, "zylinder" => 4, "preis" => 30000, "bild" => "tn-bmw320d-facelift.jpg" ],
	[ "baujahr" => "2002", "name" => "BMW 318ti compact", "ps" => 140, "vmax" => 209, "verbrauch" => 8.3, "beschleunigung" => 9.9, "gewicht" => 1255, "hubraum" => 1796, "zylinder" => 4, "preis" => 25000, "bild" => "tn-e46compact.jpg" ],
	[ "baujahr" => "2019", "name" => "BMW 320d Limousine", "ps" => 190, "vmax" => 240, "verbrauch" => 4, "beschleunigung" => 6.8, "gewicht" => 1495, "hubraum" => 1995, "zylinder" => 4, "preis" => 42000, "bild" => "tn-bmw3er-2019.jpg" ],
	[ "baujahr" => "2014", "name" => "BMW 320d Luxury (Modell 2013)", "ps" => 184, "vmax" => 230, "verbrauch" => 4.5, "beschleunigung" => 7.6, "gewicht" => 1505, "hubraum" => 1995, "zylinder" => 4, "preis" => 40000, "bild" => "tn-bmw3er-2012.jpg" ],
	[ "baujahr" => "2004", "name" => "BMW 320d", "ps" => 190, "vmax" => 240, "verbrauch" => 4, "beschleunigung" => 6.8, "gewicht" => 1495, "hubraum" => 1995, "zylinder" => 4, "preis" => 42000, "bild" => "tn-e46.jpg" ],
	[ "baujahr" => "1990", "name" => "BMW 320i", "ps" => 129, "vmax" => 201, "verbrauch" => 7.3, "beschleunigung" => 10.2, "gewicht" => 1290, "hubraum" => 1991, "zylinder" => 6, "preis" => 30000, "bild" => "tn-bmw320.jpg" ],
	[ "baujahr" => "2001", "name" => "BMW 323i", "ps" => 170, "vmax" => 230, "verbrauch" => 8.5, "beschleunigung" => 8, "gewicht" => 1490, "hubraum" => 2494, "zylinder" => 6, "preis" => 32000, "bild" => "tn-bmw323i.jpg" ],
	[ "baujahr" => "2010", "name" => "BMW 325d Limousine", "ps" => 218, "vmax" => 245, "verbrauch" => 4.9, "beschleunigung" => 6.8, "gewicht" => 1495, "hubraum" => 1995, "zylinder" => 4, "preis" => 42000, "bild" => "tn-bmwe90-2010.jpg" ],
	[ "baujahr" => "2001", "name" => "BMW 330ci Cabrio (E46)", "ps" => 231, "vmax" => 247, "verbrauch" => 9.9, "beschleunigung" => 6.9, "gewicht" => 1560, "hubraum" => 2979, "zylinder" => 6, "preis" => 43000, "bild" => "tn-bmw-sommer.jpg" ],
	[ "baujahr" => "2000", "name" => "BMW 330ci Cabrio", "ps" => 231, "vmax" => 247, "verbrauch" => 9.9, "beschleunigung" => 6.9, "gewicht" => 1560, "hubraum" => 2979, "zylinder" => 6, "preis" => 43000, "bild" => "tn-bmw330cic.jpg" ],
	[ "baujahr" => "2013", "name" => "BMW 330d sport (F30)", "ps" => 258, "vmax" => 250, "verbrauch" => 5.4, "beschleunigung" => 5.6, "gewicht" => 1600, "hubraum" => 2993, "zylinder" => 6, "preis" => 72000, "bild" => "tn-bmw3er-2012.jpg" ],
	[ "baujahr" => "2004", "name" => "BMW 330xd", "ps" => 204, "vmax" => 238, "verbrauch" => 7.9, "beschleunigung" => 6.9, "gewicht" => 1665, "hubraum" => 2993, "zylinder" => 6, "preis" => 42000, "bild" => "tn-bmw323i.jpg" ],
	[ "baujahr" => "2007", "name" => "BMW 335i CoupeCabrio (E93)", "ps" => 306, "vmax" => 250, "verbrauch" => 9.1, "beschleunigung" => 5.8, "gewicht" => 1810, "hubraum" => 2979, "zylinder" => 6, "preis" => 55000, "bild" => "tn-bmwe93.jpg" ],
	[ "baujahr" => "2014", "name" => "BMW 435 ci Cabrio (2014er Modell)", "ps" => 306, "vmax" => 250, "verbrauch" => 7.5, "beschleunigung" => 5.5, "gewicht" => 1760, "hubraum" => 2979, "zylinder" => 6, "preis" => 60000, "bild" => "tn-bmw435icabrio.jpg" ],
	[ "baujahr" => "2018", "name" => "BMW 440i xDrive Cabrio", "ps" => 326, "vmax" => 250, "verbrauch" => 8.3, "beschleunigung" => 5.1, "gewicht" => 1850, "hubraum" => 2998, "zylinder" => 6, "preis" => 68000, "bild" => "tn-bmw440icab.jpg" ],
	[ "baujahr" => "2009", "name" => "BMW 520d (2008er Facelift)", "ps" => 184, "vmax" => 230, "verbrauch" => 4.5, "beschleunigung" => 8.1, "gewicht" => 1700, "hubraum" => 1995, "zylinder" => 4, "preis" => 81000, "bild" => "tn-bmwe60-2009.jpg" ],
	[ "baujahr" => "2012", "name" => "BMW 520d (F10) Limousine", "ps" => 184, "vmax" => 230, "verbrauch" => 4.5, "beschleunigung" => 8.1, "gewicht" => 1700, "hubraum" => 1995, "zylinder" => 4, "preis" => 81000, "bild" => "tn-bmw5-f10.jpg" ],
	[ "baujahr" => "2012", "name" => "BMW 520d (MJ2012)", "ps" => 184, "vmax" => 230, "verbrauch" => 4.5, "beschleunigung" => 8.1, "gewicht" => 1700, "hubraum" => 1995, "zylinder" => 4, "preis" => 81000, "bild" => "tn-bmw5-f102012.jpg" ],
	[ "baujahr" => "2005", "name" => "BMW 520iA", "ps" => 170, "vmax" => 226, "verbrauch" => 8.6, "beschleunigung" => 8.9, "gewicht" => 1505, "hubraum" => 1995, "zylinder" => 4, "preis" => 39000, "bild" => "tn-bmwe60.jpg" ],
	[ "baujahr" => "2002", "name" => "BMW 523i Touring Automatik", "ps" => 177, "vmax" => 224, "verbrauch" => 9.5, "beschleunigung" => 9.5, "gewicht" => 1655, "hubraum" => 2497, "zylinder" => 6, "preis" => 42000, "bild" => "tn-bmwe39touring.jpg" ],
	[ "baujahr" => "2013", "name" => "BMW 525d Touring (F11 Facelift 2013)", "ps" => 218, "vmax" => 235, "verbrauch" => 5.3, "beschleunigung" => 7.2, "gewicht" => 1845, "hubraum" => 1995, "zylinder" => 4, "preis" => 55000, "bild" => "tn-bmwf12-2013.jpg" ],
	[ "baujahr" => "2003", "name" => "BMW 530d sport", "ps" => 193, "vmax" => 235, "verbrauch" => 7.2, "beschleunigung" => 7.1, "gewicht" => 1680, "hubraum" => 2926, "zylinder" => 6, "preis" => 45000, "bild" => "tn-bmwe39.jpg" ],
	[ "baujahr" => "2007", "name" => "BMW 530d Touring (E61", "ps" => 235, "vmax" => 245, "verbrauch" => 6.6, "beschleunigung" => 6.9, "gewicht" => 1735, "hubraum" => 2993, "zylinder" => 6, "preis" => 82500, "bild" => "tn-bmwe61-2007.jpg" ],
	[ "baujahr" => "2013", "name" => "BMW 530d Xdrive Touring", "ps" => 258, "vmax" => 250, "verbrauch" => 5.7, "beschleunigung" => 5.8, "gewicht" => 1800, "hubraum" => 2993, "zylinder" => 6, "preis" => 72100, "bild" => "tn-bmwf12-2013.jpg" ],
	[ "baujahr" => "2004", "name" => "BMW 530d", "ps" => 265, "vmax" => 250, "verbrauch" => 5.4, "beschleunigung" => 5.7, "gewicht" => 1825, "hubraum" => 2993, "zylinder" => 6, "preis" => 45000, "bild" => "tn-bmwe60.jpg" ],
	[ "baujahr" => "2004", "name" => "BMW 530i", "ps" => 231, "vmax" => 250, "verbrauch" => 9.7, "beschleunigung" => 7.1, "gewicht" => 1605, "hubraum" => 2979, "zylinder" => 6, "preis" => 43500, "bild" => "tn-bmwe39.jpg" ],
	[ "baujahr" => "2012", "name" => "BMW 530xd (Modell F10)", "ps" => 258, "vmax" => 250, "verbrauch" => 5.7, "beschleunigung" => 5.8, "gewicht" => 1800, "hubraum" => 2993, "zylinder" => 6, "preis" => 72000, "bild" => "tn-bmw5f10sw.jpg" ],
	[ "baujahr" => "2000", "name" => "BMW 535i", "ps" => 245, "vmax" => 250, "verbrauch" => 10.5, "beschleunigung" => 7.2, "gewicht" => 1650, "hubraum" => 3498, "zylinder" => 6, "preis" => 82500, "bild" => "tn-bmwe39.jpg" ],
	[ "baujahr" => "2017", "name" => "BMW 640d Coupe", "ps" => 313, "vmax" => 250, "verbrauch" => 5.5, "beschleunigung" => 5.4, "gewicht" => 1865, "hubraum" => 2993, "zylinder" => 6, "preis" => 80000, "bild" => "tn-bmw640dcoupe2017.jpg" ],
	[ "baujahr" => "2004", "name" => "BMW 645ci Cabrio (E64)", "ps" => 333, "vmax" => 250, "verbrauch" => 12.8, "beschleunigung" => 6.1, "gewicht" => 1790, "hubraum" => 4398, "zylinder" => 8, "preis" => 80000, "bild" => "tn-bmw6cabrio.jpg" ],
	[ "baujahr" => "2004", "name" => "BMW 645ci Coupe (E63)", "ps" => 333, "vmax" => 250, "verbrauch" => 11.7, "beschleunigung" => 5.6, "gewicht" => 1590, "hubraum" => 4398, "zylinder" => 8, "preis" => 75000, "bild" => "tn-bmw645coupe.jpg" ],
	[ "baujahr" => "2003", "name" => "BMW 645ci Coupe", "ps" => 333, "vmax" => 250, "verbrauch" => 11.7, "beschleunigung" => 5.6, "gewicht" => 1590, "hubraum" => 4398, "zylinder" => 8, "preis" => 75000, "bild" => "tn-bmw645coupe.jpg" ],
	[ "baujahr" => "2011", "name" => "BMW 650i Cabrio (F12) 2011", "ps" => 407, "vmax" => 250, "verbrauch" => 10.7, "beschleunigung" => 5, "gewicht" => 1940, "hubraum" => 4395, "zylinder" => 8, "preis" => 90000, "bild" => "tn-bmw6ercabriof12.jpg" ],
	[ "baujahr" => "2015", "name" => "BMW 650i Cabrio Facelift 2015", "ps" => 450, "vmax" => 250, "verbrauch" => 9.5, "beschleunigung" => 4.6, "gewicht" => 2055, "hubraum" => 4395, "zylinder" => 8, "preis" => 92000, "bild" => "tn-bmw6cab2015.jpg" ],
	[ "baujahr" => "2011", "name" => "BMW 650i Cabrio", "ps" => 407, "vmax" => 250, "verbrauch" => 10.7, "beschleunigung" => 5, "gewicht" => 1940, "hubraum" => 4395, "zylinder" => 8, "preis" => 90000, "bild" => "tn-bmwf12cabrio.jpg" ],
	[ "baujahr" => "2014", "name" => "BMW 650i Coupe x-drive", "ps" => 450, "vmax" => 250, "verbrauch" => 9.2, "beschleunigung" => 4.4, "gewicht" => 1930, "hubraum" => 4395, "zylinder" => 8, "preis" => 85000, "bild" => "tn-bmw650icoupe14.jpg" ],
	[ "baujahr" => "2015", "name" => "BMW 650i Grand Coupe Facelift 2015", "ps" => 450, "vmax" => 250, "verbrauch" => 9.5, "beschleunigung" => 4.6, "gewicht" => 2055, "hubraum" => 4395, "zylinder" => 8, "preis" => 95000, "bild" => "tn-bmw650gc2015.jpg" ],
	[ "baujahr" => "2018", "name" => "BMW 650i Xdrive Cabrio", "ps" => 449, "vmax" => 250, "verbrauch" => 9.3, "beschleunigung" => 4.5, "gewicht" => 2055, "hubraum" => 4395, "zylinder" => 8, "preis" => 100000, "bild" => "tn-bmw650ixdrive18.jpg" ],
	[ "baujahr" => "2003", "name" => "BMW 730i", "ps" => 258, "vmax" => 245, "verbrauch" => 11.1, "beschleunigung" => 8.5, "gewicht" => 1700, "hubraum" => 2996, "zylinder" => 6, "preis" => 65000, "bild" => "tn-bmwe38.jpg" ],
	[ "baujahr" => "2016", "name" => "BMW 740iL Modell 2016", "ps" => 326, "vmax" => 250, "verbrauch" => 7.5, "beschleunigung" => 5.2, "gewicht" => 1825, "hubraum" => 2998, "zylinder" => 6, "preis" => 88000, "bild" => "tn-bmw750il-2016.jpg" ],
	[ "baujahr" => "2017", "name" => "BMW 740iL", "ps" => 326, "vmax" => 250, "verbrauch" => 7.5, "beschleunigung" => 5.2, "gewicht" => 1825, "hubraum" => 2998, "zylinder" => 6, "preis" => 88000, "bild" => "tn-bmw-740il-2017.jpg" ],
	[ "baujahr" => "2019", "name" => "BMW 750iL Limousine", "ps" => 530, "vmax" => 250, "verbrauch" => 9.5, "beschleunigung" => 4, "gewicht" => 2045, "hubraum" => 4395, "zylinder" => 8, "preis" => 100000, "bild" => "tn-bmw750il-2018.jpg" ],
	[ "baujahr" => "2017", "name" => "BMW 760il xdrive", "ps" => 610, "vmax" => 250, "verbrauch" => 12.5, "beschleunigung" => 3.7, "gewicht" => 2255, "hubraum" => 6592, "zylinder" => 12, "preis" => 150000, "bild" => "tn-bmw760il-2017.jpg" ],
	[ "baujahr" => "2019", "name" => "BMW 840d Cabrio", "ps" => 340, "vmax" => 250, "verbrauch" => 6.2, "beschleunigung" => 5.2, "gewicht" => 2048, "hubraum" => 2993, "zylinder" => 6, "preis" => 105000, "bild" => "tn-bmw-840d-2019.jpg" ],
	[ "baujahr" => "2014", "name" => "BMW i3", "ps" => 170, "vmax" => 150, "verbrauch" => 13.1, "beschleunigung" => 7.3, "gewicht" => 1345, "hubraum" => 0, "zylinder" => 0, "preis" => 41500, "bild" => "tn-bmwi3-2014.jpg" ],
	[ "baujahr" => "2018", "name" => "BMW i3S", "ps" => 184, "vmax" => 160, "verbrauch" => 14, "beschleunigung" => 6.9, "gewicht" => 1365, "hubraum" => 0, "zylinder" => 0, "preis" => 77600, "bild" => "tn-bmw-i3s-2018.jpg" ],
	[ "baujahr" => "2024", "name" => "BMW i7 xDrive 60", "ps" => 536, "vmax" => 240, "verbrauch" => 18.4, "beschleunigung" => 4.7, "gewicht" => 2715, "hubraum" => 0, "zylinder" => 0, "preis" => 135900, "bild" => "tn-bmw-i7-2024.jpg" ],
	[ "baujahr" => "2014", "name" => "BMW i8 electric drive", "ps" => 362, "vmax" => 250, "verbrauch" => 2.1, "beschleunigung" => 4.4, "gewicht" => 1560, "hubraum" => 1499, "zylinder" => 3, "preis" => 125000, "bild" => "tn-bmwi8-2014.jpg" ],
	[ "baujahr" => "2023", "name" => "BMW iX xdrive 50", "ps" => 516, "vmax" => 200, "verbrauch" => 21, "beschleunigung" => 4.6, "gewicht" => 2585, "hubraum" => 0, "zylinder" => 0, "preis" => 96000, "bild" => "tn-bmw-ix.jpg" ],
	[ "baujahr" => "2017", "name" => "BMW M4 Cabrio 2017", "ps" => 431, "vmax" => 250, "verbrauch" => 9.5, "beschleunigung" => 4.6, "gewicht" => 1825, "hubraum" => 2979, "zylinder" => 6, "preis" => 85000, "bild" => "tn-bmw-m4cabrio-2017.jpg" ],
	[ "baujahr" => "2016", "name" => "BMW M6 Cabrio", "ps" => 600, "vmax" => 250, "verbrauch" => 9.9, "beschleunigung" => 3.9, "gewicht" => 2055, "hubraum" => 4395, "zylinder" => 8, "preis" => 135000, "bild" => "tn-bmw6cab2015.jpg" ],
	[ "baujahr" => "2019", "name" => "BMW M850i Cabrio", "ps" => 530, "vmax" => 250, "verbrauch" => 10, "beschleunigung" => 4.1, "gewicht" => 2125, "hubraum" => 4395, "zylinder" => 8, "preis" => 125000, "bild" => "tn-bmwm850icab.jpg" ],
	[ "baujahr" => "2002", "name" => "BMW Mini One", "ps" => 90, "vmax" => 185, "verbrauch" => 6.6, "beschleunigung" => 10.9, "gewicht" => 1075, "hubraum" => 1598, "zylinder" => 4, "preis" => 16200, "bild" => "tn-minione.jpg" ],
	[ "baujahr" => "2011", "name" => "BMW X1 1.8 s-drive Sport", "ps" => 150, "vmax" => 200, "verbrauch" => 6, "beschleunigung" => 10.2, "gewicht" => 1600, "hubraum" => 1997, "zylinder" => 4, "preis" => 30000, "bild" => "tn-bmwx1-2011.jpg" ],
	[ "baujahr" => "2013", "name" => "BMW X1 1.8d xdrive", "ps" => 143, "vmax" => 205, "verbrauch" => 5.6, "beschleunigung" => 9.6, "gewicht" => 1500, "hubraum" => 1995, "zylinder" => 4, "preis" => 48000, "bild" => "tn-bmwx1-2013.jpg" ],
	[ "baujahr" => "2012", "name" => "BMW X1 2.3d xdrive", "ps" => 204, "vmax" => 230, "verbrauch" => 5.7, "beschleunigung" => 7.6, "gewicht" => 1600, "hubraum" => 1995, "zylinder" => 4, "preis" => 31200, "bild" => "tn-bmwx1xdrive.jpg" ],
	[ "baujahr" => "2005", "name" => "BMW X3 2.0d", "ps" => 150, "vmax" => 198, "verbrauch" => 7.2, "beschleunigung" => 10.2, "gewicht" => 1720, "hubraum" => 1995, "zylinder" => 4, "preis" => 35000, "bild" => "tn-bmwx3.jpg" ],
	[ "baujahr" => "2018", "name" => "BMW X4 M4.0i", "ps" => 360, "vmax" => 250, "verbrauch" => 8.9, "beschleunigung" => 4.8, "gewicht" => 1940, "hubraum" => 2998, "zylinder" => 6, "preis" => 70000, "bild" => "tn-bmwx4-40m.jpg" ],
	[ "baujahr" => "2009", "name" => "BMW X5 3.0D", "ps" => 235, "vmax" => 220, "verbrauch" => 8.2, "beschleunigung" => 7.6, "gewicht" => 2145, "hubraum" => 2993, "zylinder" => 6, "preis" => 60000, "bild" => "tn-bmwx5-2008.jpg" ],
	[ "baujahr" => "2016", "name" => "BMW X5 4.0e", "ps" => 313, "vmax" => 210, "verbrauch" => 3.3, "beschleunigung" => 7.1, "gewicht" => 2327, "hubraum" => 1997, "zylinder" => 4, "preis" => 75000, "bild" => "tn-bmw-x5-40e.jpg" ],
	[ "baujahr" => "2007", "name" => "BMW X5 4.8", "ps" => 355, "vmax" => 240, "verbrauch" => 12.5, "beschleunigung" => 6.5, "gewicht" => 2260, "hubraum" => 4799, "zylinder" => 8, "preis" => 65000, "bild" => "tn-bmwx52007.jpg" ],
	[ "baujahr" => "2013", "name" => "BMW X6 4.0d", "ps" => 306, "vmax" => 236, "verbrauch" => 7.7, "beschleunigung" => 6.5, "gewicht" => 2185, "hubraum" => 2993, "zylinder" => 6, "preis" => 102000, "bild" => "tn-bmwx6-2013.jpg" ],
	[ "baujahr" => "2011", "name" => "BMW X6 orionsilbermetallic", "ps" => 306, "vmax" => 240, "verbrauch" => 8.3, "beschleunigung" => 6.7, "gewicht" => 2050, "hubraum" => 2993, "zylinder" => 6, "preis" => 75000, "bild" => "tn-bmwx6.jpg" ],
	[ "baujahr" => "2002", "name" => "BMW Z3-Roadster 2.0i", "ps" => 150, "vmax" => 210, "verbrauch" => 9.2, "beschleunigung" => 8.9, "gewicht" => 1345, "hubraum" => 1991, "zylinder" => 6, "preis" => 30000, "bild" => "tn-bmwz3.jpg" ],
	[ "baujahr" => "2003", "name" => "BMW Z4 3.0i Roadster", "ps" => 231, "vmax" => 250, "verbrauch" => 9.1, "beschleunigung" => 5.9, "gewicht" => 1290, "hubraum" => 2979, "zylinder" => 6, "preis" => 40000, "bild" => "tn-bmwz4.jpg" ],
	[ "baujahr" => "2025", "name" => "Bugatti Chiron", "ps" => 1500, "vmax" => 430, "verbrauch" => 8, "beschleunigung" => 2.2, "gewicht" => 2070, "hubraum" => 8, "zylinder" => 16, "preis" => 2890000, "bild" => "tn-bugattichiron.jpg" ],
	[ "baujahr" => "2000", "name" => "Buick (GM) 3.0l V6 Limousine", "ps" => 264, "vmax" => 210, "verbrauch" => 10.2, "beschleunigung" => 7.5, "gewicht" => 1600, "hubraum" => 2997, "zylinder" => 6, "preis" => 30000, "bild" => "tn-buick2000.jpg" ],
	[ "baujahr" => "2021", "name" => "Chevrolet Corvette Z06", "ps" => 650, "vmax" => 315, "verbrauch" => 13.7, "beschleunigung" => 3, "gewicht" => 1607, "hubraum" => 6162, "zylinder" => 8, "preis" => 124000, "bild" => "tn-corvette-z6.jpg" ],
	[ "baujahr" => "2004", "name" => "Citroen C3", "ps" => 70, "vmax" => 160, "verbrauch" => 5.5, "beschleunigung" => 14.2, "gewicht" => 1050, "hubraum" => 1124, "zylinder" => 4, "preis" => 25000, "bild" => "tn-citroenc3.jpg" ],
	[ "baujahr" => "2023", "name" => "Dodge Challenger SRT Hellcat", "ps" => 717, "vmax" => 320, "verbrauch" => 18, "beschleunigung" => 3.6, "gewicht" => 1950, "hubraum" => 6166, "zylinder" => 8, "preis" => 99000, "bild" => "tn-dodgehellcat.jpg" ],
	[ "baujahr" => "2014", "name" => "Ferrari California 30", "ps" => 490, "vmax" => 312, "verbrauch" => 13.1, "beschleunigung" => 3.8, "gewicht" => 1735, "hubraum" => 4297, "zylinder" => 8, "preis" => 180000, "bild" => "tn-ferrari450cal.jpg" ],
	[ "baujahr" => "2011", "name" => "Ferrari F430", "ps" => 490, "vmax" => 315, "verbrauch" => 13.1, "beschleunigung" => 4, "gewicht" => 1450, "hubraum" => 4308, "zylinder" => 8, "preis" => 180000, "bild" => "tn-ferrarif430.jpg" ],
	[ "baujahr" => "2002", "name" => "Ferrari F550 Maranello", "ps" => 485, "vmax" => 320, "verbrauch" => 22.9, "beschleunigung" => 4.4, "gewicht" => 1690, "hubraum" => 5474, "zylinder" => 12, "preis" => 230000, "bild" => "tn-maranello550.jpg" ],
	[ "baujahr" => "2000", "name" => "Fiat Brava Station Wagon", "ps" => 103, "vmax" => 185, "verbrauch" => 7.5, "beschleunigung" => 12.5, "gewicht" => 1200, "hubraum" => 1581, "zylinder" => 4, "preis" => 18000, "bild" => "tn-fiatbrava.jpg" ],
	[ "baujahr" => "2013", "name" => "Ford C-Max braun", "ps" => 125, "vmax" => 195, "verbrauch" => 6, "beschleunigung" => 11.4, "gewicht" => 1400, "hubraum" => 1596, "zylinder" => 4, "preis" => 38900, "bild" => "tn-fordcmax2012.jpg" ],
	[ "baujahr" => "1984", "name" => "Ford Fiesta", "ps" => 60, "vmax" => 150, "verbrauch" => 6.5, "beschleunigung" => 14, "gewicht" => 800, "hubraum" => 1117, "zylinder" => 4, "preis" => 7000, "bild" => "tn-fordfiesta.jpg" ],
	[ "baujahr" => "2005", "name" => "Ford Focus (MJ 2005) Turnier", "ps" => 100, "vmax" => 186, "verbrauch" => 6.4, "beschleunigung" => 12.1, "gewicht" => 1330, "hubraum" => 1596, "zylinder" => 4, "preis" => 30000, "bild" => "tn-focusturnier05.jpg" ],
	[ "baujahr" => "1999", "name" => "Ford Focus Fließheck", "ps" => 75, "vmax" => 170, "verbrauch" => 6.7, "beschleunigung" => 13.6, "gewicht" => 1080, "hubraum" => 1596, "zylinder" => 4, "preis" => 15000, "bild" => "tn-focus.jpg" ],
	[ "baujahr" => "2000", "name" => "Ford Focus Kombi", "ps" => 75, "vmax" => 168, "verbrauch" => 6.7, "beschleunigung" => 14.1, "gewicht" => 1135, "hubraum" => 1596, "zylinder" => 4, "preis" => 16500, "bild" => "tn-focus.jpg" ],
	[ "baujahr" => "2008", "name" => "Ford Focus", "ps" => 100, "vmax" => 186, "verbrauch" => 6.4, "beschleunigung" => 12.1, "gewicht" => 1330, "hubraum" => 1596, "zylinder" => 4, "preis" => 30000, "bild" => "tn-focus.jpg" ],
	[ "baujahr" => "2011", "name" => "Ford Grand C-Max", "ps" => 150, "vmax" => 200, "verbrauch" => 6, "beschleunigung" => 10.2, "gewicht" => 1600, "hubraum" => 1997, "zylinder" => 4, "preis" => 23000, "bild" => "tn-fordgrandcmax.jpg" ],
	[ "baujahr" => "1998", "name" => "Ford Ka", "ps" => 60, "vmax" => 150, "verbrauch" => 6.5, "beschleunigung" => 14, "gewicht" => 800, "hubraum" => 1299, "zylinder" => 4, "preis" => 10000, "bild" => "tn-fordka.jpg" ],
	[ "baujahr" => "2011", "name" => "Ford Kuga 2", "ps" => 150, "vmax" => 192, "verbrauch" => 5.2, "beschleunigung" => 9.9, "gewicht" => 1702, "hubraum" => 1997, "zylinder" => 4, "preis" => 28000, "bild" => "tn-fordkuga2011.jpg" ],
	[ "baujahr" => "2001", "name" => "Ford Mondeo Kombi", "ps" => 150, "vmax" => 215, "verbrauch" => 7.8, "beschleunigung" => 9.3, "gewicht" => 1532, "hubraum" => 1999, "zylinder" => 4, "preis" => 42000, "bild" => "tn-fordmondeo.jpg" ],
	[ "baujahr" => "2007", "name" => "Ford Mondeo TDCI", "ps" => 140, "vmax" => 210, "verbrauch" => 5.9, "beschleunigung" => 9.5, "gewicht" => 1540, "hubraum" => 1997, "zylinder" => 4, "preis" => 24000, "bild" => "tn-fordmondeo2007.jpg" ],
	[ "baujahr" => "2008", "name" => "Ford Mondeo Turnier", "ps" => 140, "vmax" => 210, "verbrauch" => 5.9, "beschleunigung" => 9.5, "gewicht" => 1540, "hubraum" => 1997, "zylinder" => 4, "preis" => 25000, "bild" => "tn-fordmondeotur2008.jpg" ],
	[ "baujahr" => "2019", "name" => "Ford Mustang Shelby GT500", "ps" => 760, "vmax" => 330, "verbrauch" => 17, "beschleunigung" => 3.5, "gewicht" => 1880, "hubraum" => 5262, "zylinder" => 8, "preis" => 97500, "bild" => "tn-fordmustangshelbygt.jpg" ],
	[ "baujahr" => "2008", "name" => "Ford S-Max", "ps" => 140, "vmax" => 200, "verbrauch" => 6, "beschleunigung" => 10.2, "gewicht" => 1600, "hubraum" => 1997, "zylinder" => 4, "preis" => 30000, "bild" => "tn-fordsmax.jpg" ],
	[ "baujahr" => "2014", "name" => "Jaguar F-Type S Roadster", "ps" => 380, "vmax" => 275, "verbrauch" => 8.8, "beschleunigung" => 4.9, "gewicht" => 1597, "hubraum" => 2995, "zylinder" => 6, "preis" => 120000, "bild" => "tn-jaguarftype2014.jpg" ],
	[ "baujahr" => "1995", "name" => "Jaguar XJS", "ps" => 241, "vmax" => 241, "verbrauch" => 15.9, "beschleunigung" => 7.9, "gewicht" => 1825, "hubraum" => 3980, "zylinder" => 6, "preis" => 60000, "bild" => "tn-jaguarxjs.jpg" ],
	[ "baujahr" => "2009", "name" => "Kia Ceed", "ps" => 100, "vmax" => 183, "verbrauch" => 5.8, "beschleunigung" => 12.6, "gewicht" => 1200, "hubraum" => 1591, "zylinder" => 4, "preis" => 16000, "bild" => "tn-kiaceed.jpg" ],
	[ "baujahr" => "2020", "name" => "Koenigsegg Jesko", "ps" => 1280, "vmax" => 480, "verbrauch" => 29, "beschleunigung" => 2.5, "gewicht" => 1420, "hubraum" => 5000, "zylinder" => 8, "preis" => 3100500, "bild" => "tn-koenigsegg.jpg" ],
	[ "baujahr" => "2024", "name" => "Lamborghini Gallardo", "ps" => 530, "vmax" => 309, "verbrauch" => 29.1, "beschleunigung" => 4.2, "gewicht" => 2250, "hubraum" => 5000, "zylinder" => 10, "preis" => 191000, "bild" => "tn-lambogallardo.jpg" ],
	[ "baujahr" => "2005", "name" => "Mahindra Ur-Jeep", "ps" => 62, "vmax" => 72, "verbrauch" => 7, "beschleunigung" => 20, "gewicht" => 1375, "hubraum" => 2500, "zylinder" => 4, "preis" => 15000, "bild" => "tn-mahindra.jpg" ],
	[ "baujahr" => "2023", "name" => "Maybach 65 S-Klasse", "ps" => 630, "vmax" => 250, "verbrauch" => 12.7, "beschleunigung" => 4.1, "gewicht" => 2285, "hubraum" => 5980, "zylinder" => 12, "preis" => 220000, "bild" => "tn-maybachs2023-650.jpg" ],
	[ "baujahr" => "2023", "name" => "Maybach GLS600 4-matic SUV", "ps" => 550, "vmax" => 250, "verbrauch" => 12.9, "beschleunigung" => 4.9, "gewicht" => 2818, "hubraum" => 3982, "zylinder" => 8, "preis" => 170000, "bild" => "tn-maybachsuvgl650.jpg" ],
	[ "baujahr" => "2022", "name" => "McLaren 720S", "ps" => 720, "vmax" => 341, "verbrauch" => 12.7, "beschleunigung" => 3.7, "gewicht" => 1890, "hubraum" => 3994, "zylinder" => 8, "preis" => 0, "bild" => "tn-mclaren720p.jpg" ],
	[ "baujahr" => "2014", "name" => "Mercedes 2014", "ps" => 156, "vmax" => 225, "verbrauch" => 5, "beschleunigung" => 8.5, "gewicht" => 1445, "hubraum" => 1595, "zylinder" => 4, "preis" => 45000, "bild" => "tn-w205-14.jpg" ],
	[ "baujahr" => "2010", "name" => "Mercedes C180CGI", "ps" => 156, "vmax" => 220, "verbrauch" => 6.4, "beschleunigung" => 8.8, "gewicht" => 1455, "hubraum" => 1497, "zylinder" => 4, "preis" => 36000, "bild" => "tn-w204mopf2009.jpg" ],
	[ "baujahr" => "2011", "name" => "Mercedes C200CDI", "ps" => 136, "vmax" => 220, "verbrauch" => 5.1, "beschleunigung" => 10, "gewicht" => 1505, "hubraum" => 2143, "zylinder" => 4, "preis" => 38000, "bild" => "tn-w204mopf2009.jpg" ],
	[ "baujahr" => "2007", "name" => "Mercedes C220 CDI", "ps" => 170, "vmax" => 229, "verbrauch" => 5.9, "beschleunigung" => 8.5, "gewicht" => 1485, "hubraum" => 2148, "zylinder" => 4, "preis" => 42000, "bild" => "tn-w204-2008.jpg" ],
	[ "baujahr" => "2011", "name" => "Mercedes C220CDI Blue-Efficiency (Mopf 2012)", "ps" => 170, "vmax" => 229, "verbrauch" => 5.9, "beschleunigung" => 8.5, "gewicht" => 1600, "hubraum" => 2143, "zylinder" => 4, "preis" => 40000, "bild" => "tn-w204-2012.jpg" ],
	[ "baujahr" => "2014", "name" => "Mercedes C220CDI", "ps" => 184, "vmax" => 235, "verbrauch" => 6.6, "beschleunigung" => 8.5, "gewicht" => 1663, "hubraum" => 1991, "zylinder" => 4, "preis" => 40000, "bild" => "tn-cklassetmodell14.jpg" ],
	[ "baujahr" => "2010", "name" => "Mercedes E 200 CDI (W212)", "ps" => 231, "vmax" => 250, "verbrauch" => 6.8, "beschleunigung" => 6.7, "gewicht" => 1730, "hubraum" => 2987, "zylinder" => 6, "preis" => 58000, "bild" => "tn-w212limo2010.jpg" ],
	[ "baujahr" => "2010", "name" => "Mercedes E 200 CGI (W212", "ps" => 231, "vmax" => 250, "verbrauch" => 6.8, "beschleunigung" => 6.7, "gewicht" => 1730, "hubraum" => 2987, "zylinder" => 6, "preis" => 58000, "bild" => "tn-w212limo2010.jpg" ],
	[ "baujahr" => "2015", "name" => "Mercedes E200 Cabrio (A207 Mopf)", "ps" => 265, "vmax" => 250, "verbrauch" => 6.2, "beschleunigung" => 6.4, "gewicht" => 1790, "hubraum" => 2987, "zylinder" => 6, "preis" => 65000, "bild" => "tn-a207mopf.jpg" ],
	[ "baujahr" => "2010", "name" => "Mercedes E200 Cabrio (A207) 350 CGI", "ps" => 265, "vmax" => 250, "verbrauch" => 6.2, "beschleunigung" => 6.4, "gewicht" => 1790, "hubraum" => 2987, "zylinder" => 6, "preis" => 65000, "bild" => "tn-a207amg.jpg" ],
	[ "baujahr" => "2012", "name" => "Mercedes E220 CDI (S212) T-Modell", "ps" => 231, "vmax" => 250, "verbrauch" => 6.8, "beschleunigung" => 6.7, "gewicht" => 1730, "hubraum" => 2987, "zylinder" => 6, "preis" => 58000, "bild" => "tn-s212grau2010.jpg" ],
	[ "baujahr" => "2012", "name" => "Mercedes E220 CDI (S212)", "ps" => 231, "vmax" => 250, "verbrauch" => 6.8, "beschleunigung" => 6.7, "gewicht" => 1730, "hubraum" => 2987, "zylinder" => 6, "preis" => 58000, "bild" => "tn-s212grau2010.jpg" ],
	[ "baujahr" => "2016", "name" => "Mercedes E220d", "ps" => 231, "vmax" => 250, "verbrauch" => 6.8, "beschleunigung" => 6.7, "gewicht" => 1730, "hubraum" => 2987, "zylinder" => 6, "preis" => 58000, "bild" => "tn-w213-eklasse2016.jpg" ],
	[ "baujahr" => "2010", "name" => "Mercedes E350 CDI (S212)", "ps" => 231, "vmax" => 250, "verbrauch" => 6.8, "beschleunigung" => 6.7, "gewicht" => 1730, "hubraum" => 2987, "zylinder" => 6, "preis" => 58000, "bild" => "tn-s212grau2010.jpg" ],
	[ "baujahr" => "2017", "name" => "Mercedes E350e", "ps" => 231, "vmax" => 250, "verbrauch" => 6.8, "beschleunigung" => 6.7, "gewicht" => 1730, "hubraum" => 2987, "zylinder" => 6, "preis" => 58000, "bild" => "tn-w213e350hybrid.jpg" ],
	[ "baujahr" => "2010", "name" => "Mercedes E500 Cabrio (A207) 500", "ps" => 265, "vmax" => 250, "verbrauch" => 6.2, "beschleunigung" => 6.4, "gewicht" => 1790, "hubraum" => 2987, "zylinder" => 6, "preis" => 65000, "bild" => "tn-a207amg.jpg" ],
	[ "baujahr" => "2010", "name" => "Mercedes E500 Cabrio (A207)", "ps" => 265, "vmax" => 250, "verbrauch" => 6.2, "beschleunigung" => 6.4, "gewicht" => 1790, "hubraum" => 2987, "zylinder" => 6, "preis" => 65000, "bild" => "tn-a207-500sw.jpg" ],
	[ "baujahr" => "2014", "name" => "Mercedes 190 SL (W121B) Oldtimer von 1955", "ps" => 105, "vmax" => 172, "verbrauch" => 10, "beschleunigung" => 13, "gewicht" => 1140, "hubraum" => 1897, "zylinder" => 4, "preis" => 250000, "bild" => "tn-190sl.jpg" ],
	[ "baujahr" => "1988", "name" => "Mercedes 190E (W201)", "ps" => 122, "vmax" => 195, "verbrauch" => 8.9, "beschleunigung" => 10.5, "gewicht" => 1170, "hubraum" => 1997, "zylinder" => 4, "preis" => 25000, "bild" => "tn-190er.jpg" ],
	[ "baujahr" => "1970", "name" => "Mercedes 250 Strich 8", "ps" => 130, "vmax" => 175, "verbrauch" => 11, "beschleunigung" => 12, "gewicht" => 1420, "hubraum" => 2496, "zylinder" => 6, "preis" => 20000, "bild" => "tn-strich8-250.jpg" ],
	[ "baujahr" => "1978", "name" => "Mercedes 280 SL (R107)", "ps" => 185, "vmax" => 200, "verbrauch" => 10.5, "beschleunigung" => 9, "gewicht" => 1500, "hubraum" => 2746, "zylinder" => 6, "preis" => 40000, "bild" => "tn-sl280-1978.jpg" ],
	[ "baujahr" => "2010", "name" => "Mercedes 300 SL (Oldtimer", "ps" => 190, "vmax" => 228, "verbrauch" => 11.6, "beschleunigung" => 9.3, "gewicht" => 1650, "hubraum" => 2960, "zylinder" => 6, "preis" => 75000, "bild" => "tn-mercedes-300sl.jpg" ],
	[ "baujahr" => "1990", "name" => "Mercedes 300 SL Roadster (R129)", "ps" => 190, "vmax" => 228, "verbrauch" => 11.6, "beschleunigung" => 9.3, "gewicht" => 1650, "hubraum" => 2960, "zylinder" => 6, "preis" => 75000, "bild" => "tn-r129-300.jpg" ],
	[ "baujahr" => "2008", "name" => "Mercedes A-Klasse A180CDI", "ps" => 109, "vmax" => 180, "verbrauch" => 5.8, "beschleunigung" => 11.3, "gewicht" => 1300, "hubraum" => 1991, "zylinder" => 4, "preis" => 28000, "bild" => "tn-w169pro.jpg" ],
	[ "baujahr" => "1999", "name" => "Mercedes A140 (W168)", "ps" => 75, "vmax" => 160, "verbrauch" => 7.7, "beschleunigung" => 14.2, "gewicht" => 1090, "hubraum" => 1397, "zylinder" => 4, "preis" => 17500, "bild" => "tn-aklasse.jpg" ],
	[ "baujahr" => "2007", "name" => "Mercedes A150 (W169)", "ps" => 95, "vmax" => 175, "verbrauch" => 7.3, "beschleunigung" => 13.4, "gewicht" => 1205, "hubraum" => 1498, "zylinder" => 4, "preis" => 24000, "bild" => "tn-w169.jpg" ],
	[ "baujahr" => "2007", "name" => "Mercedes A150", "ps" => 95, "vmax" => 175, "verbrauch" => 7.3, "beschleunigung" => 13.4, "gewicht" => 1205, "hubraum" => 1498, "zylinder" => 4, "preis" => 23500, "bild" => "tn-w169.jpg" ],
	[ "baujahr" => "2000", "name" => "Mercedes A160", "ps" => 102, "vmax" => 180, "verbrauch" => 7.2, "beschleunigung" => 10.8, "gewicht" => 1040, "hubraum" => 1598, "zylinder" => 4, "preis" => 20000, "bild" => "tn-aklasse.jpg" ],
	[ "baujahr" => "2008", "name" => "Mercedes A170 Mopf", "ps" => 122, "vmax" => 190, "verbrauch" => 7.2, "beschleunigung" => 11.5, "gewicht" => 1205, "hubraum" => 1699, "zylinder" => 4, "preis" => 26000, "bild" => "tn-w169.jpg" ],
	[ "baujahr" => "2005", "name" => "Mercedes A170", "ps" => 116, "vmax" => 188, "verbrauch" => 7.2, "beschleunigung" => 10.9, "gewicht" => 1205, "hubraum" => 1699, "zylinder" => 4, "preis" => 22500, "bild" => "tn-w169.jpg" ],
	[ "baujahr" => "2001", "name" => "Mercedes A170CDI W168", "ps" => 95, "vmax" => 175, "verbrauch" => 5.6, "beschleunigung" => 12.1, "gewicht" => 1185, "hubraum" => 1689, "zylinder" => 4, "preis" => 20000, "bild" => "tn-aklasse.jpg" ],
	[ "baujahr" => "2004", "name" => "Mercedes A170CDI", "ps" => 95, "vmax" => 175, "verbrauch" => 5.6, "beschleunigung" => 12.1, "gewicht" => 1185, "hubraum" => 1689, "zylinder" => 4, "preis" => 21500, "bild" => "tn-aklasse.jpg" ],
	[ "baujahr" => "2010", "name" => "Mercedes A180", "ps" => 109, "vmax" => 186, "verbrauch" => 5.2, "beschleunigung" => 10.8, "gewicht" => 1345, "hubraum" => 1991, "zylinder" => 4, "preis" => 23000, "bild" => "tn-w169pro.jpg" ],
	[ "baujahr" => "2009", "name" => "Mercedes A180CDI A-Klasse 5-Türer", "ps" => 109, "vmax" => 180, "verbrauch" => 5.8, "beschleunigung" => 11.3, "gewicht" => 1300, "hubraum" => 1991, "zylinder" => 4, "preis" => 29000, "bild" => "tn-w169pro.jpg" ],
	[ "baujahr" => "2007", "name" => "Mercedes A180CDI A-Klasse", "ps" => 109, "vmax" => 180, "verbrauch" => 5.8, "beschleunigung" => 11.3, "gewicht" => 1300, "hubraum" => 1991, "zylinder" => 4, "preis" => 26000, "bild" => "tn-w169.jpg" ],
	[ "baujahr" => "2005", "name" => "Mercedes A180CDI", "ps" => 109, "vmax" => 186, "verbrauch" => 5.2, "beschleunigung" => 10.8, "gewicht" => 1345, "hubraum" => 1991, "zylinder" => 4, "preis" => 23000, "bild" => "tn-w169.jpg" ],
	[ "baujahr" => "2012", "name" => "Mercedes A200 (Modell 2012)", "ps" => 156, "vmax" => 224, "verbrauch" => 5.4, "beschleunigung" => 8.3, "gewicht" => 1400, "hubraum" => 1595, "zylinder" => 4, "preis" => 25000, "bild" => "tn-a-klasse2012.jpg" ],
	[ "baujahr" => "2016", "name" => "Mercedes AMG C63S Cabriolet (A205)", "ps" => 510, "vmax" => 250, "verbrauch" => 8.9, "beschleunigung" => 4.1, "gewicht" => 1925, "hubraum" => 3982, "zylinder" => 8, "preis" => 95000, "bild" => "tn-a205-c63amg.jpg" ],
	[ "baujahr" => "2016", "name" => "Mercedes AMG C63S Cabriolet", "ps" => 503, "vmax" => 250, "verbrauch" => 10.4, "beschleunigung" => 4.1, "gewicht" => 1925, "hubraum" => 3982, "zylinder" => 8, "preis" => 95000, "bild" => "tn-a205-c63s-cabrio.jpg" ],
	[ "baujahr" => "2019", "name" => "Mercedes AMG E 53 4MATIC+ Cabrio", "ps" => 435, "vmax" => 250, "verbrauch" => 8.9, "beschleunigung" => 4.5, "gewicht" => 2173, "hubraum" => 2999, "zylinder" => 6, "preis" => 85000, "bild" => "tn-mercedes-a238-e53.jpg" ],
	[ "baujahr" => "2019", "name" => "Mercedes AMG E53 4MATIC+ Coupe", "ps" => 435, "vmax" => 250, "verbrauch" => 8.6, "beschleunigung" => 4.5, "gewicht" => 1972, "hubraum" => 2999, "zylinder" => 6, "preis" => 82000, "bild" => "tn-mercedes-c238-e53.jpg" ],
	[ "baujahr" => "2023", "name" => "Mercedes AMG GLC 200 4Matic Coupe (X 253/C 253)", "ps" => 197, "vmax" => 215, "verbrauch" => 7.1, "beschleunigung" => 7.9, "gewicht" => 1818, "hubraum" => 1991, "zylinder" => 4, "preis" => 65000, "bild" => "tn-glc2004matic-2023.jpg" ],
	[ "baujahr" => "2023", "name" => "Mercedes AMG GLC 43 4Matic Coupe (X 253/C 253)", "ps" => 390, "vmax" => 250, "verbrauch" => 10.2, "beschleunigung" => 4.9, "gewicht" => 1888, "hubraum" => 2996, "zylinder" => 6, "preis" => 85000, "bild" => "tn-glc43amg.jpg" ],
	[ "baujahr" => "2022", "name" => "Mercedes AMG GT Black series Coupe", "ps" => 720, "vmax" => 325, "verbrauch" => 14.1, "beschleunigung" => 3.1, "gewicht" => 1615, "hubraum" => 3982, "zylinder" => 8, "preis" => 190000, "bild" => "tn-merc-amggt-race.jpg" ],
	[ "baujahr" => "2017", "name" => "Mercedes AMG GT Roadster", "ps" => 530, "vmax" => 310, "verbrauch" => 12.7, "beschleunigung" => 3.8, "gewicht" => 1723, "hubraum" => 3982, "zylinder" => 8, "preis" => 145000, "bild" => "tn-gtroadster.jpg" ],
	[ "baujahr" => "2015", "name" => "Mercedes AMG GT S-Modell 2015 first Edition", "ps" => 510, "vmax" => 310, "verbrauch" => 9.4, "beschleunigung" => 3.8, "gewicht" => 1645, "hubraum" => 3982, "zylinder" => 8, "preis" => 140000, "bild" => "tn-amg-gts2015.jpg" ],
	[ "baujahr" => "2014", "name" => "Mercedes AMG GT S-Modell 2015", "ps" => 510, "vmax" => 310, "verbrauch" => 9.4, "beschleunigung" => 3.8, "gewicht" => 1645, "hubraum" => 3982, "zylinder" => 8, "preis" => 135000, "bild" => "tn-amg-gt.jpg" ],
	[ "baujahr" => "2015", "name" => "Mercedes AMG GTS", "ps" => 510, "vmax" => 310, "verbrauch" => 9.4, "beschleunigung" => 3.8, "gewicht" => 1645, "hubraum" => 3982, "zylinder" => 8, "preis" => 135000, "bild" => "tn-amg-gts2015.jpg" ],
	[ "baujahr" => "2022", "name" => "Mercedes AMG SL 63", "ps" => 577, "vmax" => 315, "verbrauch" => 13.5, "beschleunigung" => 3.6, "gewicht" => 1970, "hubraum" => 3982, "zylinder" => 8, "preis" => 170000, "bild" => "tn-r232-sl63amg.jpg" ],
	[ "baujahr" => "2008", "name" => "Mercedes AMG SL63", "ps" => 585, "vmax" => 250, "verbrauch" => 11.9, "beschleunigung" => 4.1, "gewicht" => 1845, "hubraum" => 5461, "zylinder" => 8, "preis" => 140000, "bild" => "tn-r230sl63amg.jpg" ],
	[ "baujahr" => "2008", "name" => "Mercedes AMG-Modelle", "ps" => 585, "vmax" => 250, "verbrauch" => 20.5, "beschleunigung" => 3.8, "gewicht" => 2250, "hubraum" => 5489, "zylinder" => 8, "preis" => 435000, "bild" => "tn-slr.jpg" ],
	[ "baujahr" => "2009", "name" => "Mercedes B 180 CDI (MoPf 2009)", "ps" => 109, "vmax" => 183, "verbrauch" => 5.2, "beschleunigung" => 11.3, "gewicht" => 1435, "hubraum" => 1991, "zylinder" => 4, "preis" => 27000, "bild" => "tn-b180-2009.jpg" ],
	[ "baujahr" => "2012", "name" => "Mercedes B-Klasse (Modell 2012)", "ps" => 122, "vmax" => 190, "verbrauch" => 5.9, "beschleunigung" => 10.4, "gewicht" => 1450, "hubraum" => 1595, "zylinder" => 4, "preis" => 32000, "bild" => "tn-bklasse2012.jpg" ],
	[ "baujahr" => "2013", "name" => "Mercedes B-Klasse 180CDI", "ps" => 109, "vmax" => 190, "verbrauch" => 4.4, "beschleunigung" => 11.3, "gewicht" => 1395, "hubraum" => 1461, "zylinder" => 4, "preis" => 29500, "bild" => "tn-b180-2012.jpg" ],
	[ "baujahr" => "2008", "name" => "Mercedes B-Klasse B180CDI", "ps" => 109, "vmax" => 180, "verbrauch" => 5.8, "beschleunigung" => 11.3, "gewicht" => 1300, "hubraum" => 1991, "zylinder" => 4, "preis" => 27000, "bild" => "tn-merc-b200.jpg" ],
	[ "baujahr" => "2011", "name" => "Mercedes B160", "ps" => 95, "vmax" => 175, "verbrauch" => 7.3, "beschleunigung" => 13.4, "gewicht" => 1205, "hubraum" => 1498, "zylinder" => 4, "preis" => 25500, "bild" => "tn-b180-2009.jpg" ],
	[ "baujahr" => "2008", "name" => "Mercedes B180CDI", "ps" => 109, "vmax" => 180, "verbrauch" => 5.8, "beschleunigung" => 11.3, "gewicht" => 1300, "hubraum" => 1991, "zylinder" => 4, "preis" => 27500, "bild" => "tn-merc-b200.jpg" ],
	[ "baujahr" => "2008", "name" => "Mercedes B200", "ps" => 136, "vmax" => 196, "verbrauch" => 6.7, "beschleunigung" => 10.1, "gewicht" => 1345, "hubraum" => 2034, "zylinder" => 4, "preis" => 28000, "bild" => "tn-merc-b200.jpg" ],
	[ "baujahr" => "2021", "name" => "Mercedes B250e EQdrive Plugin-Hybrid", "ps" => 218, "vmax" => 235, "verbrauch" => 1.2, "beschleunigung" => 6.8, "gewicht" => 1723, "hubraum" => 1332, "zylinder" => 4, "preis" => 45000, "bild" => "tn-mercedes-m250e-2021.jpg" ],
	[ "baujahr" => "1938", "name" => "Mercedes Benz 770 (Großer Mercedes)", "ps" => 150, "vmax" => 160, "verbrauch" => 35, "beschleunigung" => 78, "gewicht" => 1700, "hubraum" => 7655, "zylinder" => 8, "preis" => 8000000, "bild" => "tn-mercedes-oldtimer3.jpg" ],
	[ "baujahr" => "1886", "name" => "Mercedes Benz Patent-Motorwagen", "ps" => 1, "vmax" => 16, "verbrauch" => 0.9, "beschleunigung" => 6.6, "gewicht" => 265, "hubraum" => 954, "zylinder" => 1, "preis" => 9000000, "bild" => "tn-mercedes-bertha.jpg" ],
	[ "baujahr" => "1902", "name" => "Mercedes Benz Simplex 40 PS", "ps" => 40, "vmax" => 80, "verbrauch" => 21, "beschleunigung" => 90, "gewicht" => 1400, "hubraum" => 6785, "zylinder" => 4, "preis" => 7000000, "bild" => "tn-mercedes-oldtimer2.jpg" ],
	[ "baujahr" => "1929", "name" => "Mercedes Benz SSK", "ps" => 200, "vmax" => 190, "verbrauch" => 35, "beschleunigung" => 55, "gewicht" => 1929, "hubraum" => 7065, "zylinder" => 6, "preis" => 4000000, "bild" => "tn-mercedes-oldtimer3.jpg" ],
	[ "baujahr" => "2008", "name" => "Mercedes C 200 CDI", "ps" => 136, "vmax" => 220, "verbrauch" => 6.1, "beschleunigung" => 10.4, "gewicht" => 1545, "hubraum" => 2148, "zylinder" => 4, "preis" => 38000, "bild" => "tn-w204-2008.jpg" ],
	[ "baujahr" => "2018", "name" => "Mercedes C180 -Coupe", "ps" => 156, "vmax" => 223, "verbrauch" => 7, "beschleunigung" => 8.9, "gewicht" => 1480, "hubraum" => 1497, "zylinder" => 4, "preis" => 42000, "bild" => "tn-c205-ccoupeweiss.jpg" ],
	[ "baujahr" => "2012", "name" => "Mercedes C180 Coupe (C204)", "ps" => 156, "vmax" => 223, "verbrauch" => 6.5, "beschleunigung" => 8.5, "gewicht" => 1550, "hubraum" => 1595, "zylinder" => 4, "preis" => 44000, "bild" => "tn-c204-2012.jpg" ],
	[ "baujahr" => "2004", "name" => "Mercedes C180 Kompressor", "ps" => 143, "vmax" => 220, "verbrauch" => 8.7, "beschleunigung" => 9.7, "gewicht" => 1470, "hubraum" => 1796, "zylinder" => 4, "preis" => 31500, "bild" => "tn-w203.jpg" ],
	[ "baujahr" => "2012", "name" => "Mercedes C180 T-Modell MOPF 2011", "ps" => 156, "vmax" => 223, "verbrauch" => 6.5, "beschleunigung" => 8.5, "gewicht" => 1550, "hubraum" => 1595, "zylinder" => 4, "preis" => 46700, "bild" => "tn-s204mopf2012.jpg" ],
	[ "baujahr" => "2004", "name" => "Mercedes C180 T-Modell", "ps" => 156, "vmax" => 220, "verbrauch" => 6.4, "beschleunigung" => 8.8, "gewicht" => 1455, "hubraum" => 1497, "zylinder" => 4, "preis" => 36000, "bild" => "tn-w203t.jpg" ],
	[ "baujahr" => "2019", "name" => "Mercedes C180", "ps" => 156, "vmax" => 225, "verbrauch" => 5.9, "beschleunigung" => 8.5, "gewicht" => 1405, "hubraum" => 1595, "zylinder" => 4, "preis" => 38000, "bild" => "tn-c205-2018.jpg" ],
	[ "baujahr" => "2010", "name" => "Mercedes C180CGI", "ps" => 156, "vmax" => 220, "verbrauch" => 6.4, "beschleunigung" => 8.8, "gewicht" => 1455, "hubraum" => 1497, "zylinder" => 4, "preis" => 36000, "bild" => "tn-s204eleg2009sw.jpg" ],
	[ "baujahr" => "2005", "name" => "Mercedes C180Kompressor", "ps" => 143, "vmax" => 223, "verbrauch" => 7.9, "beschleunigung" => 9.7, "gewicht" => 1485, "hubraum" => 1796, "zylinder" => 4, "preis" => 35000, "bild" => "tn-w203lim.jpg" ],
	[ "baujahr" => "2018", "name" => "Mercedes C200 Cabriolet (A205)", "ps" => 184, "vmax" => 235, "verbrauch" => 6.4, "beschleunigung" => 7.9, "gewicht" => 1615, "hubraum" => 1796, "zylinder" => 4, "preis" => 45000, "bild" => "tn-a205-2018mopf.jpg" ],
	[ "baujahr" => "2000", "name" => "Mercedes C200T", "ps" => 163, "vmax" => 225, "verbrauch" => 6.9, "beschleunigung" => 8.5, "gewicht" => 1496, "hubraum" => 1991, "zylinder" => 4, "preis" => 35000, "bild" => "tn-w202.jpg" ],
	[ "baujahr" => "2007", "name" => "Mercedes C220 CDI (W204) Elegance", "ps" => 170, "vmax" => 229, "verbrauch" => 5.9, "beschleunigung" => 8.5, "gewicht" => 1485, "hubraum" => 2148, "zylinder" => 4, "preis" => 40000, "bild" => "tn-s204.jpg" ],
	[ "baujahr" => "2008", "name" => "Mercedes C220 CDI T-Modell", "ps" => 170, "vmax" => 230, "verbrauch" => 6.1, "beschleunigung" => 8.5, "gewicht" => 1615, "hubraum" => 2143, "zylinder" => 4, "preis" => 43000, "bild" => "tn-s204.jpg" ],
	[ "baujahr" => "2009", "name" => "Mercedes C220 CDI", "ps" => 170, "vmax" => 229, "verbrauch" => 5.9, "beschleunigung" => 8.5, "gewicht" => 1485, "hubraum" => 2148, "zylinder" => 4, "preis" => 40000, "bild" => "tn-w204mopf2009.jpg" ],
	[ "baujahr" => "2008", "name" => "Mercedes C220CDI (W204) Avantgarde", "ps" => 170, "vmax" => 230, "verbrauch" => 6.1, "beschleunigung" => 8.5, "gewicht" => 1615, "hubraum" => 2143, "zylinder" => 4, "preis" => 42000, "bild" => "tn-w204-2008.jpg" ],
	[ "baujahr" => "2004", "name" => "Mercedes C220CDI", "ps" => 150, "vmax" => 224, "verbrauch" => 6.1, "beschleunigung" => 10.1, "gewicht" => 1505, "hubraum" => 2148, "zylinder" => 4, "preis" => 34000, "bild" => "tn-w203.jpg" ],
	[ "baujahr" => "2015", "name" => "Mercedes C220T Bluetec", "ps" => 170, "vmax" => 234, "verbrauch" => 4, "beschleunigung" => 8.1, "gewicht" => 1550, "hubraum" => 2143, "zylinder" => 4, "preis" => 45000, "bild" => "tn-s205-2014.jpg" ],
	[ "baujahr" => "2000", "name" => "Mercedes C320", "ps" => 218, "vmax" => 234, "verbrauch" => 11.3, "beschleunigung" => 8, "gewicht" => 1540, "hubraum" => 3199, "zylinder" => 6, "preis" => 45000, "bild" => "tn-w203.jpg" ],
	[ "baujahr" => "2007", "name" => "Mercedes C350 (W204)", "ps" => 272, "vmax" => 250, "verbrauch" => 9.4, "beschleunigung" => 6.4, "gewicht" => 1615, "hubraum" => 3498, "zylinder" => 6, "preis" => 48000, "bild" => "tn-w204-2007.jpg" ],
	[ "baujahr" => "2006", "name" => "Mercedes CL500 Coupe", "ps" => 306, "vmax" => 250, "verbrauch" => 11.7, "beschleunigung" => 6.3, "gewicht" => 1885, "hubraum" => 4973, "zylinder" => 8, "preis" => 90000, "bild" => "tn-cl219-06.jpg" ],
	[ "baujahr" => "2006", "name" => "Mercedes CL55 AMG (5.5l Kompresor", "ps" => 507, "vmax" => 250, "verbrauch" => 13.5, "beschleunigung" => 4.8, "gewicht" => 1845, "hubraum" => 5439, "zylinder" => 8, "preis" => 118312, "bild" => "tn-safetycar.jpg" ],
	[ "baujahr" => "2008", "name" => "Mercedes CL65 AMG", "ps" => 612, "vmax" => 250, "verbrauch" => 14.5, "beschleunigung" => 4.4, "gewicht" => 2240, "hubraum" => 5980, "zylinder" => 12, "preis" => 190000, "bild" => "tn-c216amg.jpg" ],
	[ "baujahr" => "2013", "name" => "Mercedes CLA250 Sport", "ps" => 211, "vmax" => 240, "verbrauch" => 6.2, "beschleunigung" => 6.7, "gewicht" => 1465, "hubraum" => 1991, "zylinder" => 4, "preis" => 38500, "bild" => "tn-cla2013amg.jpg" ],
	[ "baujahr" => "2024", "name" => "Mercedes CLE450 4matic Cabrio (A236", "ps" => 381, "vmax" => 250, "verbrauch" => 7.9, "beschleunigung" => 4.7, "gewicht" => 2005, "hubraum" => 2999, "zylinder" => 6, "preis" => 89000, "bild" => "tn-merc-clecabrio.jpg" ],
	[ "baujahr" => "2023", "name" => "Mercedes CLE450 4matic Coupe (C236", "ps" => 381, "vmax" => 250, "verbrauch" => 7.8, "beschleunigung" => 4.4, "gewicht" => 1945, "hubraum" => 2999, "zylinder" => 6, "preis" => 85500, "bild" => "tn-cle450-4matic-23coupe.jpg" ],
	[ "baujahr" => "2005", "name" => "Mercedes CLK 55 AMG special Edition", "ps" => 367, "vmax" => 250, "verbrauch" => 12.4, "beschleunigung" => 5.2, "gewicht" => 1710, "hubraum" => 5439, "zylinder" => 8, "preis" => 200000, "bild" => "tn-clkamgrenn.jpg" ],
	[ "baujahr" => "2006", "name" => "Mercedes CLK-Cabrio (A209) 200Kompressor", "ps" => 163, "vmax" => 225, "verbrauch" => 8.8, "beschleunigung" => 9.8, "gewicht" => 1590, "hubraum" => 1796, "zylinder" => 4, "preis" => 42000, "bild" => "tn-a209mopf05.jpg" ],
	[ "baujahr" => "2004", "name" => "Mercedes CLK200 Kompressor Cabrio", "ps" => 163, "vmax" => 227, "verbrauch" => 9.1, "beschleunigung" => 9.2, "gewicht" => 1645, "hubraum" => 1796, "zylinder" => 4, "preis" => 42000, "bild" => "tn-clkcabrio2003.jpg" ],
	[ "baujahr" => "2004", "name" => "Mercedes CLK200 Kompressor-Cabrio", "ps" => 163, "vmax" => 227, "verbrauch" => 9.1, "beschleunigung" => 9.2, "gewicht" => 1645, "hubraum" => 1796, "zylinder" => 4, "preis" => 42000, "bild" => "tn-clkcabrio2003.jpg" ],
	[ "baujahr" => "2006", "name" => "Mercedes CLK200Kompressor Cabrio (A209)", "ps" => 163, "vmax" => 225, "verbrauch" => 8.8, "beschleunigung" => 9.8, "gewicht" => 1590, "hubraum" => 1796, "zylinder" => 4, "preis" => 45000, "bild" => "tn-a209200k06.jpg" ],
	[ "baujahr" => "1999", "name" => "Mercedes CLK230 Kompressor Cabriolet", "ps" => 193, "vmax" => 230, "verbrauch" => 10.1, "beschleunigung" => 9.1, "gewicht" => 1450, "hubraum" => 2295, "zylinder" => 4, "preis" => 48000, "bild" => "tn-a208-230k.jpg" ],
	[ "baujahr" => "2000", "name" => "Mercedes CLK320 (C208) Coupe", "ps" => 224, "vmax" => 240, "verbrauch" => 10.1, "beschleunigung" => 7.4, "gewicht" => 1395, "hubraum" => 3199, "zylinder" => 6, "preis" => 50000, "bild" => "tn-w208-320.jpg" ],
	[ "baujahr" => "2000", "name" => "Mercedes CLK320 Cabrio (A208)", "ps" => 224, "vmax" => 236, "verbrauch" => 10.6, "beschleunigung" => 8.1, "gewicht" => 1595, "hubraum" => 3199, "zylinder" => 6, "preis" => 55000, "bild" => "tn-a208-320.jpg" ],
	[ "baujahr" => "2003", "name" => "Mercedes CLK320 Cabrio (A209)", "ps" => 224, "vmax" => 236, "verbrauch" => 10.6, "beschleunigung" => 8.1, "gewicht" => 1595, "hubraum" => 3199, "zylinder" => 6, "preis" => 55000, "bild" => "tn-clkcabrio2003.jpg" ],
	[ "baujahr" => "2004", "name" => "Mercedes CLK500 Cabrio (A209)", "ps" => 306, "vmax" => 250, "verbrauch" => 12.2, "beschleunigung" => 6.2, "gewicht" => 1795, "hubraum" => 4966, "zylinder" => 8, "preis" => 68000, "bild" => "tn-a209-500.jpg" ],
	[ "baujahr" => "2006", "name" => "Mercedes CLK63 AMG", "ps" => 481, "vmax" => 250, "verbrauch" => 14.2, "beschleunigung" => 4.6, "gewicht" => 1755, "hubraum" => 6208, "zylinder" => 8, "preis" => 80000, "bild" => "tn-a209-63amg.jpg" ],
	[ "baujahr" => "2012", "name" => "Mercedes CLS 350 CDI", "ps" => 265, "vmax" => 250, "verbrauch" => 6, "beschleunigung" => 6.6, "gewicht" => 1900, "hubraum" => 2987, "zylinder" => 6, "preis" => 55000, "bild" => "tn-mercedes-cls2012.jpg" ],
	[ "baujahr" => "2008", "name" => "Mercedes CLS 63 AMG", "ps" => 514, "vmax" => 250, "verbrauch" => 14.5, "beschleunigung" => 4.5, "gewicht" => 1885, "hubraum" => 6208, "zylinder" => 8, "preis" => 110000, "bild" => "tn-c211-2008.jpg" ],
	[ "baujahr" => "2012", "name" => "Mercedes CLS Shooting Brake 350 CDI", "ps" => 265, "vmax" => 250, "verbrauch" => 6, "beschleunigung" => 6.6, "gewicht" => 1900, "hubraum" => 2987, "zylinder" => 6, "preis" => 48900, "bild" => "tn-clsshooting.jpg" ],
	[ "baujahr" => "2014", "name" => "Mercedes CLS63 AMG (Mopf 2014)", "ps" => 557, "vmax" => 250, "verbrauch" => 9.9, "beschleunigung" => 4.2, "gewicht" => 1870, "hubraum" => 5461, "zylinder" => 8, "preis" => 120000, "bild" => "tn-cls63-2014.jpg" ],
	[ "baujahr" => "2007", "name" => "Mercedes Comand NTG2", "ps" => 0, "vmax" => 0, "verbrauch" => 0, "beschleunigung" => 0, "gewicht" => 0, "hubraum" => 0, "zylinder" => 0, "preis" => 0, "bild" => "tn-navi.jpg" ],
	[ "baujahr" => "2008", "name" => "Mercedes Comand NTG2.5", "ps" => 0, "vmax" => 0, "verbrauch" => 0, "beschleunigung" => 0, "gewicht" => 0, "hubraum" => 0, "zylinder" => 0, "preis" => 0, "bild" => "tn-navi.jpg" ],
	[ "baujahr" => "2012", "name" => "Mercedes Comand Online NTG4.5 Testbericht", "ps" => 0, "vmax" => 0, "verbrauch" => 0, "beschleunigung" => 0, "gewicht" => 0, "hubraum" => 0, "zylinder" => 0, "preis" => 0, "bild" => "tn-comandonline.jpg" ],
	[ "baujahr" => "2009", "name" => "Mercedes E 200 CGI (W212)", "ps" => 184, "vmax" => 233, "verbrauch" => 6.1, "beschleunigung" => 8.2, "gewicht" => 1595, "hubraum" => 1796, "zylinder" => 4, "preis" => 46000, "bild" => "tn-w212avantgarde.jpg" ],
	[ "baujahr" => "2008", "name" => "Mercedes E 220CDI W211Mopf", "ps" => 170, "vmax" => 227, "verbrauch" => 6.9, "beschleunigung" => 9.1, "gewicht" => 1735, "hubraum" => 2148, "zylinder" => 4, "preis" => 46000, "bild" => "tn-w211miet.jpg" ],
	[ "baujahr" => "2019", "name" => "Mercedes E 300 de (T-Modell)", "ps" => 313, "vmax" => 250, "verbrauch" => 1.6, "beschleunigung" => 5.9, "gewicht" => 2025, "hubraum" => 1993, "zylinder" => 4, "preis" => 60000, "bild" => "tn-mercedes-e300de.jpg" ],
	[ "baujahr" => "2009", "name" => "Mercedes E 350 CDI Coupe (C207)", "ps" => 231, "vmax" => 250, "verbrauch" => 6.8, "beschleunigung" => 6.7, "gewicht" => 1730, "hubraum" => 2987, "zylinder" => 6, "preis" => 60000, "bild" => "tn-c207schwarz.jpg" ],
	[ "baujahr" => "2012", "name" => "Mercedes E200 BlueEfficiency T-Modell (S212MJ12)", "ps" => 184, "vmax" => 230, "verbrauch" => 7.1, "beschleunigung" => 8.1, "gewicht" => 1745, "hubraum" => 1796, "zylinder" => 4, "preis" => 48000, "bild" => "tn-s212grau2010.jpg" ],
	[ "baujahr" => "2004", "name" => "Mercedes E200 Kompressor", "ps" => 184, "vmax" => 236, "verbrauch" => 8.2, "beschleunigung" => 9.1, "gewicht" => 1580, "hubraum" => 1796, "zylinder" => 4, "preis" => 40000, "bild" => "tn-w211miet.jpg" ],
	[ "baujahr" => "2011", "name" => "Mercedes E220 CDI (S212) T-Modell", "ps" => 231, "vmax" => 250, "verbrauch" => 6.8, "beschleunigung" => 6.7, "gewicht" => 1730, "hubraum" => 2987, "zylinder" => 6, "preis" => 58000, "bild" => "tn-s212grau2010.jpg" ],
	[ "baujahr" => "2008", "name" => "Mercedes E220 CDI T-Modell (S211)", "ps" => 170, "vmax" => 227, "verbrauch" => 6.9, "beschleunigung" => 9.1, "gewicht" => 1735, "hubraum" => 2148, "zylinder" => 4, "preis" => 45000, "bild" => "tn-s211.jpg" ],
	[ "baujahr" => "2016", "name" => "Mercedes E2200d", "ps" => 194, "vmax" => 240, "verbrauch" => 5, "beschleunigung" => 7.7, "gewicht" => 1845, "hubraum" => 1950, "zylinder" => 4, "preis" => 55000, "bild" => "tn-s213-tmodell2016.jpg" ],
	[ "baujahr" => "2008", "name" => "Mercedes E220CDI (MOPF)", "ps" => 150, "vmax" => 208, "verbrauch" => 6.9, "beschleunigung" => 10.6, "gewicht" => 1785, "hubraum" => 2148, "zylinder" => 4, "preis" => 45000, "bild" => "tn-w211miet.jpg" ],
	[ "baujahr" => "2005", "name" => "Mercedes E220CDI (S211)", "ps" => 150, "vmax" => 208, "verbrauch" => 6.9, "beschleunigung" => 10.6, "gewicht" => 1785, "hubraum" => 2148, "zylinder" => 4, "preis" => 45000, "bild" => "tn-s211.jpg" ],
	[ "baujahr" => "2009", "name" => "Mercedes E220CDI (S211Mopf) T-Modell", "ps" => 150, "vmax" => 208, "verbrauch" => 6.9, "beschleunigung" => 10.6, "gewicht" => 1785, "hubraum" => 2148, "zylinder" => 4, "preis" => 45000, "bild" => "tn-s211mopf09.jpg" ],
	[ "baujahr" => "2009", "name" => "Mercedes E220CDI (S211Mopf)", "ps" => 150, "vmax" => 208, "verbrauch" => 6.9, "beschleunigung" => 10.6, "gewicht" => 1785, "hubraum" => 2148, "zylinder" => 4, "preis" => 45000, "bild" => "tn-s211mopf09.jpg" ],
	[ "baujahr" => "2005", "name" => "Mercedes E220CDI (W211)", "ps" => 150, "vmax" => 208, "verbrauch" => 6.9, "beschleunigung" => 10.6, "gewicht" => 1785, "hubraum" => 2148, "zylinder" => 4, "preis" => 45000, "bild" => "tn-w211miet.jpg" ],
	[ "baujahr" => "2014", "name" => "Mercedes E220CDI Avantgarde", "ps" => 170, "vmax" => 227, "verbrauch" => 4.7, "beschleunigung" => 8.4, "gewicht" => 1735, "hubraum" => 2143, "zylinder" => 4, "preis" => 50000, "bild" => "tn-w212-2014.jpg" ],
	[ "baujahr" => "2011", "name" => "Mercedes E220CDI Cabrio", "ps" => 170, "vmax" => 230, "verbrauch" => 5.1, "beschleunigung" => 8.8, "gewicht" => 1735, "hubraum" => 2143, "zylinder" => 4, "preis" => 52000, "bild" => "tn-a207mj2012.jpg" ],
	[ "baujahr" => "2004", "name" => "Mercedes E240", "ps" => 170, "vmax" => 216, "verbrauch" => 10.1, "beschleunigung" => 10.5, "gewicht" => 1590, "hubraum" => 2397, "zylinder" => 6, "preis" => 76200, "bild" => "tn-w211miet.jpg" ],
	[ "baujahr" => "2000", "name" => "Mercedes E240T (S210)", "ps" => 170, "vmax" => 216, "verbrauch" => 10.1, "beschleunigung" => 10.5, "gewicht" => 1590, "hubraum" => 2397, "zylinder" => 6, "preis" => 76200, "bild" => "tn-w208-240t.jpg" ],
	[ "baujahr" => "2013", "name" => "Mercedes E250CDI (W212)", "ps" => 204, "vmax" => 240, "verbrauch" => 4.9, "beschleunigung" => 7.5, "gewicht" => 1700, "hubraum" => 2143, "zylinder" => 4, "preis" => 62200, "bild" => "tn-w212mopf13.jpg" ],
	[ "baujahr" => "2006", "name" => "Mercedes E280", "ps" => 231, "vmax" => 248, "verbrauch" => 9.7, "beschleunigung" => 7.3, "gewicht" => 1645, "hubraum" => 2996, "zylinder" => 6, "preis" => 51000, "bild" => "tn-w211miet.jpg" ],
	[ "baujahr" => "2017", "name" => "Mercedes E300 Cabrio (A238)", "ps" => 245, "vmax" => 250, "verbrauch" => 7.6, "beschleunigung" => 6.6, "gewicht" => 1873, "hubraum" => 1991, "zylinder" => 4, "preis" => 68000, "bild" => "tn-a238-ecabrio.jpg" ],
	[ "baujahr" => "2009", "name" => "Mercedes E300 Coupe (C207)", "ps" => 231, "vmax" => 250, "verbrauch" => 6.8, "beschleunigung" => 6.7, "gewicht" => 1730, "hubraum" => 2987, "zylinder" => 6, "preis" => 58000, "bild" => "tn-c207.jpg" ],
	[ "baujahr" => "2017", "name" => "Mercedes E300 Coupe (C238)", "ps" => 329, "vmax" => 210, "verbrauch" => 10.7, "beschleunigung" => 5.5, "gewicht" => 1723, "hubraum" => 2996, "zylinder" => 6, "preis" => 65000, "bild" => "tn-mercedes-c238.jpg" ],
	[ "baujahr" => "2023", "name" => "Mercedes E300e und E220d Plugin Hybride", "ps" => 313, "vmax" => 235, "verbrauch" => 7, "beschleunigung" => 6.7, "gewicht" => 2105, "hubraum" => 1991, "zylinder" => 4, "preis" => 65000, "bild" => "tn-eklasse-t-2023.jpg" ],
	[ "baujahr" => "2000", "name" => "Mercedes E320CDI T-Modell S210 Kombi", "ps" => 197, "vmax" => 227, "verbrauch" => 7.9, "beschleunigung" => 8.8, "gewicht" => 1670, "hubraum" => 3199, "zylinder" => 6, "preis" => 57000, "bild" => "tn-w210t320cdi.jpg" ],
	[ "baujahr" => "2000", "name" => "Mercedes E320CDI W210", "ps" => 197, "vmax" => 230, "verbrauch" => 7.8, "beschleunigung" => 8.3, "gewicht" => 1560, "hubraum" => 3222, "zylinder" => 6, "preis" => 55000, "bild" => "tn-w210-320.jpg" ],
	[ "baujahr" => "2009", "name" => "Mercedes E350 CDI Coupe (C207)", "ps" => 231, "vmax" => 250, "verbrauch" => 6.8, "beschleunigung" => 6.7, "gewicht" => 1730, "hubraum" => 2987, "zylinder" => 6, "preis" => 58000, "bild" => "tn-c207silber.jpg" ],
	[ "baujahr" => "2010", "name" => "Mercedes E350CDI Cabrio", "ps" => 265, "vmax" => 250, "verbrauch" => 6.2, "beschleunigung" => 6.4, "gewicht" => 1790, "hubraum" => 2987, "zylinder" => 6, "preis" => 65000, "bild" => "tn-a207mj2012.jpg" ],
	[ "baujahr" => "2014", "name" => "Mercedes E500 BlueEfficiency Cabriolet (A207)", "ps" => 408, "vmax" => 250, "verbrauch" => 9.8, "beschleunigung" => 4.8, "gewicht" => 1900, "hubraum" => 4663, "zylinder" => 8, "preis" => 98000, "bild" => "tn-a207-2012pb.jpg" ],
	[ "baujahr" => "2012", "name" => "Mercedes E500 BlueEfficiency Cabriolet", "ps" => 408, "vmax" => 250, "verbrauch" => 9.8, "beschleunigung" => 4.8, "gewicht" => 1900, "hubraum" => 4663, "zylinder" => 8, "preis" => 98000, "bild" => "tn-a207-2012pb.jpg" ],
	[ "baujahr" => "2010", "name" => "Mercedes E500 Cabrio", "ps" => 408, "vmax" => 250, "verbrauch" => 9.1, "beschleunigung" => 4.9, "gewicht" => 1870, "hubraum" => 4663, "zylinder" => 8, "preis" => 75000, "bild" => "tn-a207-e500silberpb.jpg" ],
	[ "baujahr" => "2019", "name" => "Mercedes E53 AMG Cabrio ", "ps" => 435, "vmax" => 250, "verbrauch" => 8.9, "beschleunigung" => 4.5, "gewicht" => 2173, "hubraum" => 2999, "zylinder" => 6, "preis" => 88000, "bild" => "tn-a238-e53amg.jpg" ],
	[ "baujahr" => "2023", "name" => "Mercedes EQB 250", "ps" => 190, "vmax" => 160, "verbrauch" => 17.3, "beschleunigung" => 9.2, "gewicht" => 2175, "hubraum" => 0, "zylinder" => 0, "preis" => 52000, "bild" => "tn-merc-eqb.jpg" ],
	[ "baujahr" => "2024", "name" => "Mercedes EQB 350", "ps" => 292, "vmax" => 160, "verbrauch" => 17.3, "beschleunigung" => 6.2, "gewicht" => 2175, "hubraum" => 0, "zylinder" => 0, "preis" => 58000, "bild" => "tn-merc-eqb350.jpg" ],
	[ "baujahr" => "2022", "name" => "Mercedes EQE 350+", "ps" => 292, "vmax" => 210, "verbrauch" => 17.1, "beschleunigung" => 6.5, "gewicht" => 5280, "hubraum" => 0, "zylinder" => 0, "preis" => 75000, "bild" => "tn-mercedes-eqe350plus.jpg" ],
	[ "baujahr" => "2022", "name" => "Mercedes EQS 350", "ps" => 288, "vmax" => 210, "verbrauch" => 17.1, "beschleunigung" => 6.6, "gewicht" => 2465, "hubraum" => 0, "zylinder" => 0, "preis" => 98000, "bild" => "tn-merc-eqs350.jpg" ],
	[ "baujahr" => "2022", "name" => "Mercedes EQS 450+", "ps" => 333, "vmax" => 210, "verbrauch" => 19, "beschleunigung" => 6.2, "gewicht" => 2480, "hubraum" => 0, "zylinder" => 0, "preis" => 105000, "bild" => "tn-mercedes-eqs.jpg" ],
	[ "baujahr" => "2023", "name" => "Mercedes EQS 580 SUV", "ps" => 523, "vmax" => 210, "verbrauch" => 0, "beschleunigung" => 4.6, "gewicht" => 2810, "hubraum" => 0, "zylinder" => 0, "preis" => 135000, "bild" => "tn-eqe-eqs-suv.jpg" ],
	[ "baujahr" => "2016", "name" => "Mercedes G500", "ps" => 422, "vmax" => 210, "verbrauch" => 13.8, "beschleunigung" => 5.9, "gewicht" => 2468, "hubraum" => 3982, "zylinder" => 8, "preis" => 115000, "bild" => "tn-g500-monster.jpg" ],
	[ "baujahr" => "2014", "name" => "Mercedes GLA 200 CDI 4-matic", "ps" => 136, "vmax" => 205, "verbrauch" => 4.9, "beschleunigung" => 10, "gewicht" => 1555, "hubraum" => 2143, "zylinder" => 4, "preis" => 42500, "bild" => "tn-mercedesgla.jpg" ],
	[ "baujahr" => "2014", "name" => "Mercedes GLA 200 CDI", "ps" => 136, "vmax" => 205, "verbrauch" => 4.5, "beschleunigung" => 9.9, "gewicht" => 1505, "hubraum" => 2143, "zylinder" => 4, "preis" => 55000, "bild" => "tn-mercedesgla200.jpg" ],
	[ "baujahr" => "2014", "name" => "Mercedes GLA 45 AMG", "ps" => 360, "vmax" => 250, "verbrauch" => 7.5, "beschleunigung" => 4.8, "gewicht" => 1585, "hubraum" => 1991, "zylinder" => 4, "preis" => 55000, "bild" => "tn-gla45amg.jpg" ],
	[ "baujahr" => "2022", "name" => "Mercedes GLA200d (GLA MJ 2022)", "ps" => 150, "vmax" => 208, "verbrauch" => 5.3, "beschleunigung" => 8.6, "gewicht" => 1718, "hubraum" => 1950, "zylinder" => 4, "preis" => 42000, "bild" => "tn-gla200d-2022.jpg" ],
	[ "baujahr" => "2021", "name" => "Mercedes GLA250e Eqdrive", "ps" => 218, "vmax" => 235, "verbrauch" => 1.6, "beschleunigung" => 7.1, "gewicht" => 1735, "hubraum" => 1332, "zylinder" => 4, "preis" => 48000, "bild" => "tn-gla250e-2021.jpg" ],
	[ "baujahr" => "2023", "name" => "Mercedes GLB200", "ps" => 163, "vmax" => 207, "verbrauch" => 6, "beschleunigung" => 9.1, "gewicht" => 1749, "hubraum" => 1332, "zylinder" => 4, "preis" => 45000, "bild" => "tn-mercedes-glb.jpg" ],
	[ "baujahr" => "2015", "name" => "Mercedes GLC 220d SUV", "ps" => 194, "vmax" => 219, "verbrauch" => 5.2, "beschleunigung" => 8, "gewicht" => 2000, "hubraum" => 1993, "zylinder" => 4, "preis" => 48000, "bild" => "tn-glc220-2015.jpg" ],
	[ "baujahr" => "2017", "name" => "Mercedes GLC 250", "ps" => 211, "vmax" => 223, "verbrauch" => 6.5, "beschleunigung" => 7.3, "gewicht" => 1810, "hubraum" => 1991, "zylinder" => 4, "preis" => 50000, "bild" => "tn-mb-glc-x204.jpg" ],
	[ "baujahr" => "2017", "name" => "Mercedes GLC 250d", "ps" => 204, "vmax" => 223, "verbrauch" => 5, "beschleunigung" => 7.6, "gewicht" => 1845, "hubraum" => 2143, "zylinder" => 4, "preis" => 52000, "bild" => "tn-mb-glc-x204.jpg" ],
	[ "baujahr" => "2016", "name" => "Mercedes GLC Coupe", "ps" => 211, "vmax" => 223, "verbrauch" => 7, "beschleunigung" => 7.3, "gewicht" => 1710, "hubraum" => 1991, "zylinder" => 4, "preis" => 55000, "bild" => "tn-glccoupe-2016.jpg" ],
	[ "baujahr" => "2017", "name" => "Mercedes GLE 350d Coupe", "ps" => 258, "vmax" => 226, "verbrauch" => 6.9, "beschleunigung" => 7, "gewicht" => 2250, "hubraum" => 2987, "zylinder" => 6, "preis" => 75000, "bild" => "tn-glecoupe2017.jpg" ],
	[ "baujahr" => "2019", "name" => "Mercedes GLE 400", "ps" => 333, "vmax" => 247, "verbrauch" => 8.5, "beschleunigung" => 6, "gewicht" => 2450, "hubraum" => 2996, "zylinder" => 6, "preis" => 70000, "bild" => "tn-gle400-2019.jpg" ],
	[ "baujahr" => "2023", "name" => "Mercedes GLE 400d", "ps" => 330, "vmax" => 245, "verbrauch" => 7.1, "beschleunigung" => 5.7, "gewicht" => 2295, "hubraum" => 2925, "zylinder" => 6, "preis" => 80000, "bild" => "tn-mercedes-GLE400d.jpg" ],
	[ "baujahr" => "2015", "name" => "Mercedes GLE Coupe AMG 63 S-Modell", "ps" => 585, "vmax" => 250, "verbrauch" => 11.9, "beschleunigung" => 4.2, "gewicht" => 2345, "hubraum" => 5461, "zylinder" => 8, "preis" => 130000, "bild" => "tn-glecoupe.jpg" ],
	[ "baujahr" => "2017", "name" => "Mercedes GLE500e", "ps" => 442, "vmax" => 245, "verbrauch" => 3.3, "beschleunigung" => 5.3, "gewicht" => 2465, "hubraum" => 2996, "zylinder" => 6, "preis" => 80000, "bild" => "tn-gle500hybrid.jpg" ],
	[ "baujahr" => "2012", "name" => "Mercedes GLK 350 4matic", "ps" => 272, "vmax" => 230, "verbrauch" => 10.7, "beschleunigung" => 6.7, "gewicht" => 1880, "hubraum" => 3498, "zylinder" => 6, "preis" => 50000, "bild" => "tn-glktherock.jpg" ],
	[ "baujahr" => "2023", "name" => "Mercedes Hyper-Screen", "ps" => 0, "vmax" => 0, "verbrauch" => 0, "beschleunigung" => 0, "gewicht" => 0, "hubraum" => 0, "zylinder" => 0, "preis" => 0, "bild" => "tn-merc-hyperscreen.jpg" ],
	[ "baujahr" => "2014", "name" => "Mercedes ML 250 Bluetec", "ps" => 204, "vmax" => 210, "verbrauch" => 6, "beschleunigung" => 9, "gewicht" => 2135, "hubraum" => 2143, "zylinder" => 4, "preis" => 50000, "bild" => "tn-ml2014.jpg" ],
	[ "baujahr" => "2005", "name" => "Mercedes ML 320 CDI", "ps" => 224, "vmax" => 215, "verbrauch" => 9.4, "beschleunigung" => 8.6, "gewicht" => 2185, "hubraum" => 2987, "zylinder" => 6, "preis" => 55000, "bild" => "tn-mercedes-ml-2010.jpg" ],
	[ "baujahr" => "2010", "name" => "Mercedes ML350CDI", "ps" => 231, "vmax" => 220, "verbrauch" => 8.6, "beschleunigung" => 9, "gewicht" => 2110, "hubraum" => 2987, "zylinder" => 6, "preis" => 58000, "bild" => "tn-mercedes-ml-2010.jpg" ],
	[ "baujahr" => "2005", "name" => "Mercedes R-Klasse (6-Sitzer) 3", "ps" => 272, "vmax" => 230, "verbrauch" => 11.4, "beschleunigung" => 8.4, "gewicht" => 2170, "hubraum" => 3498, "zylinder" => 6, "preis" => 52000, "bild" => "tn-r350.jpg" ],
	[ "baujahr" => "2006", "name" => "Mercedes R350 4Matic", "ps" => 272, "vmax" => 230, "verbrauch" => 11.4, "beschleunigung" => 8.4, "gewicht" => 2170, "hubraum" => 3498, "zylinder" => 6, "preis" => 50000, "bild" => "tn-r3504matic.jpg" ],
	[ "baujahr" => "2000", "name" => "Mercedes S320 (W220)", "ps" => 231, "vmax" => 225, "verbrauch" => 12.8, "beschleunigung" => 8.9, "gewicht" => 1890, "hubraum" => 3199, "zylinder" => 6, "preis" => 70000, "bild" => "tn-w220.jpg" ],
	[ "baujahr" => "2014", "name" => "Mercedes S350 Bluetec", "ps" => 258, "vmax" => 250, "verbrauch" => 5.9, "beschleunigung" => 6.8, "gewicht" => 2020, "hubraum" => 2987, "zylinder" => 6, "preis" => 82500, "bild" => "tn-w222-14.jpg" ],
	[ "baujahr" => "2009", "name" => "Mercedes S400 (V221)", "ps" => 388, "vmax" => 250, "verbrauch" => 11.7, "beschleunigung" => 5.4, "gewicht" => 1940, "hubraum" => 5461, "zylinder" => 8, "preis" => 95000, "bild" => "tn-sklasse2009mopf.jpg" ],
	[ "baujahr" => "1991", "name" => "Mercedes S430 (W140)", "ps" => 279, "vmax" => 245, "verbrauch" => 11.6, "beschleunigung" => 7.3, "gewicht" => 2190, "hubraum" => 4196, "zylinder" => 8, "preis" => 80000, "bild" => "tn-w140.jpg" ],
	[ "baujahr" => "2005", "name" => "Mercedes S500 (V221) S-Klasse ", "ps" => 388, "vmax" => 250, "verbrauch" => 11.7, "beschleunigung" => 5.4, "gewicht" => 1940, "hubraum" => 5461, "zylinder" => 8, "preis" => 95000, "bild" => "tn-w221-500.jpg" ],
	[ "baujahr" => "2000", "name" => "Mercedes S500L (W220)", "ps" => 306, "vmax" => 250, "verbrauch" => 12.4, "beschleunigung" => 6.3, "gewicht" => 1855, "hubraum" => 4966, "zylinder" => 8, "preis" => 95000, "bild" => "tn-w220.jpg" ],
	[ "baujahr" => "2006", "name" => "Mercedes S600L (V221)", "ps" => 517, "vmax" => 250, "verbrauch" => 14.3, "beschleunigung" => 4.6, "gewicht" => 2215, "hubraum" => 5513, "zylinder" => 12, "preis" => 145000, "bild" => "tn-w221-600l.jpg" ],
	[ "baujahr" => "2016", "name" => "Mercedes S63 AMG-S Cabrio", "ps" => 612, "vmax" => 250, "verbrauch" => 10.1, "beschleunigung" => 3.5, "gewicht" => 2110, "hubraum" => 3982, "zylinder" => 8, "preis" => 180000, "bild" => "tn-scabrio2016.jpg" ],
	[ "baujahr" => "2014", "name" => "Mercedes S63AMG S-Klasse Coupe", "ps" => 612, "vmax" => 250, "verbrauch" => 10.1, "beschleunigung" => 3.5, "gewicht" => 2110, "hubraum" => 3982, "zylinder" => 8, "preis" => 170000, "bild" => "tn-s63amgcoupe.jpg" ],
	[ "baujahr" => "2008", "name" => "Mercedes SL350 (MOPF 2008)", "ps" => 316, "vmax" => 250, "verbrauch" => 10.1, "beschleunigung" => 6.2, "gewicht" => 1825, "hubraum" => 3498, "zylinder" => 6, "preis" => 80000, "bild" => "tn-r230-2008.jpg" ],
	[ "baujahr" => "2002", "name" => "Mercedes SL500 (R230)", "ps" => 306, "vmax" => 250, "verbrauch" => 12.2, "beschleunigung" => 6.3, "gewicht" => 1825, "hubraum" => 4966, "zylinder" => 8, "preis" => 95000, "bild" => "tn-r230miet.jpg" ],
	[ "baujahr" => "2012", "name" => "Mercedes SL500 (R231)", "ps" => 136, "vmax" => 208, "verbrauch" => 9.1, "beschleunigung" => 9.7, "gewicht" => 1170, "hubraum" => 1998, "zylinder" => 4, "preis" => 40000, "bild" => "tn-r231silber.jpg" ],
	[ "baujahr" => "2013", "name" => "Mercedes SL500 Roadster (R231MJ13)", "ps" => 435, "vmax" => 250, "verbrauch" => 9.1, "beschleunigung" => 4.6, "gewicht" => 1800, "hubraum" => 4663, "zylinder" => 8, "preis" => 103000, "bild" => "tn-r231-silber.jpg" ],
	[ "baujahr" => "2023", "name" => "Mercedes SL500 Roadster (R231MJ17 Facelift)", "ps" => 435, "vmax" => 250, "verbrauch" => 9.1, "beschleunigung" => 4.6, "gewicht" => 1800, "hubraum" => 4663, "zylinder" => 8, "preis" => 103000, "bild" => "tn-r231mopf2016-hor.jpg" ],
	[ "baujahr" => "2016", "name" => "Mercedes SL63S AMG", "ps" => 585, "vmax" => 250, "verbrauch" => 11.9, "beschleunigung" => 4.1, "gewicht" => 1845, "hubraum" => 5461, "zylinder" => 8, "preis" => 160000, "bild" => "tn-sl231mopf2016.jpg" ],
	[ "baujahr" => "2016", "name" => "Mercedes SLC 180 Roadster", "ps" => 156, "vmax" => 226, "verbrauch" => 5.6, "beschleunigung" => 7.9, "gewicht" => 1435, "hubraum" => 1595, "zylinder" => 4, "preis" => 40000, "bild" => "tn-r173-slc.jpg" ],
	[ "baujahr" => "2008", "name" => "Mercedes SLK 200 Kompressor", "ps" => 184, "vmax" => 236, "verbrauch" => 7.7, "beschleunigung" => 7.6, "gewicht" => 1390, "hubraum" => 1796, "zylinder" => 4, "preis" => 40000, "bild" => "tn-r171mopf2008.jpg" ],
	[ "baujahr" => "2008", "name" => "Mercedes SLK 280 (MOPF 2008)", "ps" => 231, "vmax" => 250, "verbrauch" => 9.4, "beschleunigung" => 6.3, "gewicht" => 1390, "hubraum" => 2996, "zylinder" => 6, "preis" => 50000, "bild" => "tn-r171-2008.jpg" ],
	[ "baujahr" => "1997", "name" => "Mercedes SLK200 (R170)", "ps" => 136, "vmax" => 208, "verbrauch" => 9.1, "beschleunigung" => 9.7, "gewicht" => 1170, "hubraum" => 1998, "zylinder" => 4, "preis" => 40000, "bild" => "tn-r170-200s.jpg" ],
	[ "baujahr" => "2013", "name" => "Mercedes SLK200 (R172)", "ps" => 136, "vmax" => 208, "verbrauch" => 9.1, "beschleunigung" => 9.7, "gewicht" => 1170, "hubraum" => 1998, "zylinder" => 4, "preis" => 40000, "bild" => "tn-slk172-2013.jpg" ],
	[ "baujahr" => "2004", "name" => "Mercedes SLK200 Kompr (R171)", "ps" => 163, "vmax" => 230, "verbrauch" => 8.7, "beschleunigung" => 7.6, "gewicht" => 1390, "hubraum" => 1796, "zylinder" => 4, "preis" => 38000, "bild" => "tn-r171-200miet.jpg" ],
	[ "baujahr" => "2005", "name" => "Mercedes SLK200 Kompressor", "ps" => 163, "vmax" => 230, "verbrauch" => 8.7, "beschleunigung" => 7.9, "gewicht" => 1390, "hubraum" => 1796, "zylinder" => 4, "preis" => 38000, "bild" => "tn-r171-200ksixt.jpg" ],
	[ "baujahr" => "2007", "name" => "Mercedes SLK200K", "ps" => 184, "vmax" => 236, "verbrauch" => 7.7, "beschleunigung" => 7.6, "gewicht" => 1390, "hubraum" => 1796, "zylinder" => 4, "preis" => 38000, "bild" => "tn-r171-2007.jpg" ],
	[ "baujahr" => "2012", "name" => "Mercedes SLS AMG Roadster", "ps" => 571, "vmax" => 317, "verbrauch" => 13.2, "beschleunigung" => 3.8, "gewicht" => 1695, "hubraum" => 6208, "zylinder" => 8, "preis" => 220000, "bild" => "tn-slsroadster.jpg" ],
	[ "baujahr" => "2010", "name" => "Mercedes SLS AMG", "ps" => 571, "vmax" => 317, "verbrauch" => 13.2, "beschleunigung" => 3.8, "gewicht" => 1620, "hubraum" => 6208, "zylinder" => 8, "preis" => 180000, "bild" => "tn-mercedes-slsamg.jpg" ],
	[ "baujahr" => "2014", "name" => "Mercedes SLS GT Final Edition", "ps" => 591, "vmax" => 320, "verbrauch" => 13.2, "beschleunigung" => 3.7, "gewicht" => 1620, "hubraum" => 6208, "zylinder" => 8, "preis" => 186000, "bild" => "tn-sls-gt.jpg" ],
	[ "baujahr" => "2014", "name" => "Morgan Plus 4 Roadster", "ps" => 154, "vmax" => 190, "verbrauch" => 7, "beschleunigung" => 7.5, "gewicht" => 927, "hubraum" => 1999, "zylinder" => 4, "preis" => 55000, "bild" => "tn-morganv4.jpg" ],
	[ "baujahr" => "2023", "name" => "NIO EL7", "ps" => 653, "vmax" => 200, "verbrauch" => 18.3, "beschleunigung" => 3.9, "gewicht" => 2500, "hubraum" => 0, "zylinder" => 0, "preis" => 90000, "bild" => "tn-nio-et7.jpg" ],
	[ "baujahr" => "2023", "name" => "NIO ET5 blau (100 kwh)", "ps" => 490, "vmax" => 200, "verbrauch" => 16.2, "beschleunigung" => 4, "gewicht" => 2350, "hubraum" => 0, "zylinder" => 0, "preis" => 67000, "bild" => "tn-nio-et5.jpg" ],
	[ "baujahr" => "2023", "name" => "NIO ET7", "ps" => 653, "vmax" => 200, "verbrauch" => 19, "beschleunigung" => 3.8, "gewicht" => 2379, "hubraum" => 0, "zylinder" => 0, "preis" => 75000, "bild" => "tn-nio-et7sw.jpg" ],
	[ "baujahr" => "1999", "name" => "Nissan Almera Kombi", "ps" => 90, "vmax" => 175, "verbrauch" => 7.5, "beschleunigung" => 12.5, "gewicht" => 1190, "hubraum" => 1497, "zylinder" => 4, "preis" => 25000, "bild" => "tn-nissanalmera.jpg" ],
	[ "baujahr" => "2013", "name" => "Nissan Leaf", "ps" => 109, "vmax" => 144, "verbrauch" => 0, "beschleunigung" => 11.5, "gewicht" => 1521, "hubraum" => 0, "zylinder" => 0, "preis" => 35000, "bild" => "tn-nissanleaf.jpg" ],
	[ "baujahr" => "2008", "name" => "Nissan X-Trail Turbodiesel", "ps" => 150, "vmax" => 190, "verbrauch" => 6.4, "beschleunigung" => 10, "gewicht" => 1650, "hubraum" => 1995, "zylinder" => 4, "preis" => 30000, "bild" => "tn-nissanxtrail.jpg" ],
	[ "baujahr" => "2006", "name" => "Opel Astra H", "ps" => 90, "vmax" => 175, "verbrauch" => 6.3, "beschleunigung" => 13, "gewicht" => 1205, "hubraum" => 1364, "zylinder" => 4, "preis" => 16500, "bild" => "tn-opelastra05blau.jpg" ],
	[ "baujahr" => "2000", "name" => "Opel Astra I", "ps" => 115, "vmax" => 193, "verbrauch" => 6.8, "beschleunigung" => 11.9, "gewicht" => 1200, "hubraum" => 1598, "zylinder" => 4, "preis" => 15000, "bild" => "tn-opelastra.jpg" ],
	[ "baujahr" => "2005", "name" => "Opel Astra II 1.6 Twinport", "ps" => 105, "vmax" => 186, "verbrauch" => 6.6, "beschleunigung" => 14.1, "gewicht" => 1395, "hubraum" => 1598, "zylinder" => 4, "preis" => 17000, "bild" => "tn-opelastra05blau.jpg" ],
	[ "baujahr" => "2005", "name" => "Opel Astra II", "ps" => 115, "vmax" => 193, "verbrauch" => 6.8, "beschleunigung" => 11.9, "gewicht" => 1200, "hubraum" => 1598, "zylinder" => 4, "preis" => 15000, "bild" => "tn-opelastra05.jpg" ],
	[ "baujahr" => "2018", "name" => "Opel Astra ST Kombi (Modell 2018)", "ps" => 110, "vmax" => 195, "verbrauch" => 4.5, "beschleunigung" => 11.4, "gewicht" => 1428, "hubraum" => 1598, "zylinder" => 4, "preis" => 25000, "bild" => "tn-opelastra-st2018.jpg" ],
	[ "baujahr" => "1997", "name" => "Opel Corsa 2", "ps" => 60, "vmax" => 150, "verbrauch" => 6.5, "beschleunigung" => 14, "gewicht" => 800, "hubraum" => 1196, "zylinder" => 4, "preis" => 11000, "bild" => "tn-opelcorsa.jpg" ],
	[ "baujahr" => "2016", "name" => "Opel Meriva", "ps" => 140, "vmax" => 196, "verbrauch" => 6.4, "beschleunigung" => 10.3, "gewicht" => 1428, "hubraum" => 1598, "zylinder" => 4, "preis" => 20000, "bild" => "tn-opelmeriva.jpg" ],
	[ "baujahr" => "2001", "name" => "Opel Zafira I Minivan", "ps" => 147, "vmax" => 200, "verbrauch" => 8.1, "beschleunigung" => 10.5, "gewicht" => 1460, "hubraum" => 1998, "zylinder" => 4, "preis" => 20000, "bild" => "tn-opelzafira.jpg" ],
	[ "baujahr" => "2023", "name" => "Pagani Huayra", "ps" => 730, "vmax" => 370, "verbrauch" => 16.6, "beschleunigung" => 2.9, "gewicht" => 1350, "hubraum" => 5980, "zylinder" => 12, "preis" => 2700000, "bild" => "tn-paganihuayra.jpg" ],
	[ "baujahr" => "2007", "name" => "Peugeot 407 Coupe", "ps" => 211, "vmax" => 240, "verbrauch" => 9.5, "beschleunigung" => 8.5, "gewicht" => 1799, "hubraum" => 2946, "zylinder" => 6, "preis" => 35000, "bild" => "tn-peugeot407c.jpg" ],
	[ "baujahr" => "2000", "name" => "Porsche 911 (996) Carrera", "ps" => 300, "vmax" => 280, "verbrauch" => 11.8, "beschleunigung" => 5.2, "gewicht" => 1320, "hubraum" => 3387, "zylinder" => 6, "preis" => 70000, "bild" => "tn-porsche911-996.jpg" ],
	[ "baujahr" => "2014", "name" => "Porsche 911 Carrera 4S Cabrio", "ps" => 400, "vmax" => 296, "verbrauch" => 9.1, "beschleunigung" => 4.3, "gewicht" => 1570, "hubraum" => 3800, "zylinder" => 6, "preis" => 100000, "bild" => "tn-911c4scabrio.jpg" ],
	[ "baujahr" => "1998", "name" => "Renault Clio (Lanzarote", "ps" => 75, "vmax" => 170, "verbrauch" => 6, "beschleunigung" => 13, "gewicht" => 975, "hubraum" => 1149, "zylinder" => 4, "preis" => 18000, "bild" => "tn-renaultclio.jpg" ],
	[ "baujahr" => "1999", "name" => "Renault Clio II", "ps" => 75, "vmax" => 170, "verbrauch" => 6, "beschleunigung" => 13, "gewicht" => 975, "hubraum" => 1149, "zylinder" => 4, "preis" => 18000, "bild" => "tn-renaultclio.jpg" ],
	[ "baujahr" => "2004", "name" => "Renault Scenic III", "ps" => 110, "vmax" => 185, "verbrauch" => 6.4, "beschleunigung" => 12.3, "gewicht" => 1400, "hubraum" => 1461, "zylinder" => 4, "preis" => 22000, "bild" => "tn-renaultscenic.jpg" ],
	[ "baujahr" => "2002", "name" => "Renault Trafic Lieferwagen ", "ps" => 101, "vmax" => 160, "verbrauch" => 8.5, "beschleunigung" => 15.7, "gewicht" => 1745, "hubraum" => 1870, "zylinder" => 4, "preis" => 20000, "bild" => "tn-renaulttrafic.jpg" ],
	[ "baujahr" => "2012", "name" => "Renault Twizy", "ps" => 17, "vmax" => 80, "verbrauch" => 6.1, "beschleunigung" => 6.1, "gewicht" => 450, "hubraum" => 0, "zylinder" => 0, "preis" => 19999, "bild" => "tn-renaulttwizyx.jpg" ],
	[ "baujahr" => "1999", "name" => "Rover 75/2.6", "ps" => 177, "vmax" => 215, "verbrauch" => 10.5, "beschleunigung" => 9.5, "gewicht" => 1445, "hubraum" => 2497, "zylinder" => 6, "preis" => 35000, "bild" => "tn-rover75.jpg" ],
	[ "baujahr" => "2005", "name" => "Saab 9-3 Cabrio", "ps" => 150, "vmax" => 210, "verbrauch" => 8.5, "beschleunigung" => 10.5, "gewicht" => 1575, "hubraum" => 1998, "zylinder" => 4, "preis" => 40000, "bild" => "tn-saab93cabrio.jpg" ],
	[ "baujahr" => "2004", "name" => "Seat Ibiza 1.9 TDI (Sixt-Mietwagen", "ps" => 100, "vmax" => 190, "verbrauch" => 5, "beschleunigung" => 10.8, "gewicht" => 1165, "hubraum" => 1896, "zylinder" => 4, "preis" => 20000, "bild" => "tn-seatibiza.jpg" ],
	[ "baujahr" => "2015", "name" => "Seat Ibiza", "ps" => 100, "vmax" => 190, "verbrauch" => 5, "beschleunigung" => 10.8, "gewicht" => 1165, "hubraum" => 1896, "zylinder" => 4, "preis" => 20000, "bild" => "tn-seatibiza2014.jpg" ],
	[ "baujahr" => "1987", "name" => "Seat Marbella", "ps" => 40, "vmax" => 140, "verbrauch" => 6.1, "beschleunigung" => 20, "gewicht" => 720, "hubraum" => 903, "zylinder" => 4, "preis" => 6000, "bild" => "tn-seatmarbella.jpg" ],
	[ "baujahr" => "2015", "name" => "Segway Elektroroller", "ps" => 5, "vmax" => 90, "verbrauch" => 3.6, "beschleunigung" => 4, "gewicht" => 115, "hubraum" => 0, "zylinder" => 0, "preis" => 7000, "bild" => "tn-segway2015.jpg" ],
	[ "baujahr" => "2006", "name" => "Skoda Octavia Kombi (Modell 2k5)", "ps" => 115, "vmax" => 196, "verbrauch" => 5.7, "beschleunigung" => 11.5, "gewicht" => 1285, "hubraum" => 1598, "zylinder" => 4, "preis" => 22000, "bild" => "tn-skodaOctaviaCombi2005.jpg" ],
	[ "baujahr" => "2012", "name" => "Skoda Superb 3.0 TDI", "ps" => 170, "vmax" => 220, "verbrauch" => 5.9, "beschleunigung" => 8.6, "gewicht" => 1600, "hubraum" => 1968, "zylinder" => 4, "preis" => 41900, "bild" => "tn-skodasuperb2.jpg" ],
	[ "baujahr" => "2012", "name" => "Smart Fortwo Cabrio", "ps" => 71, "vmax" => 145, "verbrauch" => 4.2, "beschleunigung" => 13.7, "gewicht" => 900, "hubraum" => 999, "zylinder" => 3, "preis" => 10000, "bild" => "tn-smartfor2cab.jpg" ],
	[ "baujahr" => "2012", "name" => "Smart fortwo", "ps" => 71, "vmax" => 145, "verbrauch" => 4.2, "beschleunigung" => 13.7, "gewicht" => 750, "hubraum" => 999, "zylinder" => 3, "preis" => 14000, "bild" => "tn-smart42-2012.jpg" ],
	[ "baujahr" => "2014", "name" => "Smart FourFour Prime", "ps" => 90, "vmax" => 165, "verbrauch" => 4.2, "beschleunigung" => 11.2, "gewicht" => 975, "hubraum" => 898, "zylinder" => 3, "preis" => 12000, "bild" => "tn-smart-44-2014.jpg" ],
	[ "baujahr" => "2014", "name" => "Smart FourTwo", "ps" => 90, "vmax" => 155, "verbrauch" => 4.1, "beschleunigung" => 10.4, "gewicht" => 880, "hubraum" => 898, "zylinder" => 3, "preis" => 12500, "bild" => "tn-smart-42-2014.jpg" ],
	[ "baujahr" => "2023", "name" => "Smart One", "ps" => 272, "vmax" => 180, "verbrauch" => 17.4, "beschleunigung" => 6.7, "gewicht" => 1820, "hubraum" => 0, "zylinder" => 0, "preis" => 35000, "bild" => "tn-smartone-2023.jpg" ],
	[ "baujahr" => "2001", "name" => "Spyker C8 Sportwagen", "ps" => 405, "vmax" => 300, "verbrauch" => 13.3, "beschleunigung" => 4.5, "gewicht" => 1275, "hubraum" => 4163, "zylinder" => 8, "preis" => 250000, "bild" => "tn-spyker-c8.jpg" ],
	[ "baujahr" => "2013", "name" => "Subaru Forester SUV", "ps" => 150, "vmax" => 192, "verbrauch" => 6.5, "beschleunigung" => 10.4, "gewicht" => 1560, "hubraum" => 1995, "zylinder" => 4, "preis" => 52400, "bild" => "tn-subaruforest.jpg" ],
	[ "baujahr" => "2004", "name" => "Suzuki Jeep (neues Modell)", "ps" => 45, "vmax" => 110, "verbrauch" => 8, "beschleunigung" => 20, "gewicht" => 900, "hubraum" => 970, "zylinder" => 4, "preis" => 8000, "bild" => "tn-suzukijeepneu.jpg" ],
	[ "baujahr" => "1980", "name" => "Suzuki Jeep", "ps" => 45, "vmax" => 110, "verbrauch" => 8, "beschleunigung" => 20, "gewicht" => 900, "hubraum" => 970, "zylinder" => 4, "preis" => 8000, "bild" => "tn-suzukijeep.jpg" ],
	[ "baujahr" => "2019", "name" => "Tesla Model 3 AWD Long Range", "ps" => 441, "vmax" => 233, "verbrauch" => 14, "beschleunigung" => 4.4, "gewicht" => 1906, "hubraum" => 0, "zylinder" => 0, "preis" => 55000, "bild" => "tn-teslamodel3.jpg" ],
	[ "baujahr" => "2016", "name" => "Tesla Model S P90d", "ps" => 700, "vmax" => 250, "verbrauch" => 0, "beschleunigung" => 3.3, "gewicht" => 2150, "hubraum" => 0, "zylinder" => 0, "preis" => 120000, "bild" => "tn-teslap90d.jpg" ],
	[ "baujahr" => "2019", "name" => "Tesla Model S Plaid", "ps" => 1020, "vmax" => 322, "verbrauch" => 0, "beschleunigung" => 2.1, "gewicht" => 2162, "hubraum" => 0, "zylinder" => 0, "preis" => 130500, "bild" => "tn-teslasplaid.jpg" ],
	[ "baujahr" => "1985", "name" => "Toyota Corolla", "ps" => 75, "vmax" => 160, "verbrauch" => 7.5, "beschleunigung" => 13, "gewicht" => 900, "hubraum" => 1295, "zylinder" => 4, "preis" => 10000, "bild" => "tn-toyotacorolla.jpg" ],
	[ "baujahr" => "1997", "name" => "Toyota Land Cruiser", "ps" => 204, "vmax" => 175, "verbrauch" => 12, "beschleunigung" => 11, "gewicht" => 2150, "hubraum" => 4164, "zylinder" => 6, "preis" => 40000, "bild" => "tn-toyotalandi.jpg" ],
	[ "baujahr" => "1972", "name" => "Triumph Spitfire Roadster", "ps" => 75, "vmax" => 160, "verbrauch" => 8, "beschleunigung" => 12.5, "gewicht" => 735, "hubraum" => 1296, "zylinder" => 4, "preis" => 15000, "bild" => "tn-spitfire1972.jpg" ],
	[ "baujahr" => "2004", "name" => "Volvo E40", "ps" => 163, "vmax" => 210, "verbrauch" => 7.5, "beschleunigung" => 9.5, "gewicht" => 1550, "hubraum" => 1984, "zylinder" => 4, "preis" => 32000, "bild" => "tn-volvoe40.jpg" ],
	[ "baujahr" => "2005", "name" => "Volvo S60", "ps" => 250, "vmax" => 250, "verbrauch" => 6.2, "beschleunigung" => 6.5, "gewicht" => 1770, "hubraum" => 1969, "zylinder" => 4, "preis" => 35000, "bild" => "tn-volvos60.jpg" ],
	[ "baujahr" => "2005", "name" => "Volvo V50Kombi", "ps" => 230, "vmax" => 230, "verbrauch" => 7.4, "beschleunigung" => 6.8, "gewicht" => 1538, "hubraum" => 2521, "zylinder" => 5, "preis" => 30000, "bild" => "tn-volvov50.jpg" ],
	[ "baujahr" => "2009", "name" => "Volvo V70 D5", "ps" => 205, "vmax" => 225, "verbrauch" => 6.4, "beschleunigung" => 8.2, "gewicht" => 1574, "hubraum" => 2400, "zylinder" => 5, "preis" => 40000, "bild" => "tn-volvov70-2009.jpg" ],
	[ "baujahr" => "2012", "name" => "Volvo V70 T4", "ps" => 180, "vmax" => 215, "verbrauch" => 7.5, "beschleunigung" => 8.5, "gewicht" => 1600, "hubraum" => 1596, "zylinder" => 4, "preis" => 51200, "bild" => "tn-volvov70.jpg" ],
	[ "baujahr" => "2000", "name" => "VW Beetle 2.0 FSI Cabrio", "ps" => 115, "vmax" => 185, "verbrauch" => 8.5, "beschleunigung" => 10.9, "gewicht" => 1250, "hubraum" => 1984, "zylinder" => 4, "preis" => 36000, "bild" => "tn-vwbeetle.jpg" ],
	[ "baujahr" => "2013", "name" => "VW Cross Touran 2.0 TDI", "ps" => 140, "vmax" => 200, "verbrauch" => 5.7, "beschleunigung" => 10.1, "gewicht" => 1600, "hubraum" => 1968, "zylinder" => 4, "preis" => 28500, "bild" => "tn-vwcrosstouran.jpg" ],
	[ "baujahr" => "2005", "name" => "VW Golf 5 1.9 TDI ", "ps" => 105, "vmax" => 190, "verbrauch" => 5, "beschleunigung" => 11.3, "gewicht" => 1260, "hubraum" => 1896, "zylinder" => 4, "preis" => 25000, "bild" => "tn-golf-v19.jpg" ],
	[ "baujahr" => "2011", "name" => "VW Golf 6 FSI", "ps" => 122, "vmax" => 198, "verbrauch" => 6.4, "beschleunigung" => 9.8, "gewicht" => 1280, "hubraum" => 1390, "zylinder" => 4, "preis" => 21000, "bild" => "tn-vwgolf6blumotion.jpg" ],
	[ "baujahr" => "2009", "name" => "VW Golf 6 1.9 TDI", "ps" => 105, "vmax" => 192, "verbrauch" => 4.5, "beschleunigung" => 10.9, "gewicht" => 1347, "hubraum" => 1598, "zylinder" => 4, "preis" => 19000, "bild" => "tn-golf6gws.jpg" ],
	[ "baujahr" => "2010", "name" => "VW Golf 6 TDI Bluemotion", "ps" => 105, "vmax" => 192, "verbrauch" => 4.5, "beschleunigung" => 10.9, "gewicht" => 1347, "hubraum" => 1598, "zylinder" => 4, "preis" => 22000, "bild" => "tn-golf6gws.jpg" ],
	[ "baujahr" => "2010", "name" => "VW Golf 6 TDI", "ps" => 105, "vmax" => 192, "verbrauch" => 4.5, "beschleunigung" => 10.9, "gewicht" => 1347, "hubraum" => 1598, "zylinder" => 4, "preis" => 22000, "bild" => "tn-golf6gws.jpg" ],
	[ "baujahr" => "2009", "name" => "VW Golf 6", "ps" => 105, "vmax" => 192, "verbrauch" => 4.5, "beschleunigung" => 10.9, "gewicht" => 1347, "hubraum" => 1598, "zylinder" => 4, "preis" => 19000, "bild" => "tn-golf6gws.jpg" ],
	[ "baujahr" => "2016", "name" => "VW Golf 7 bluemotion", "ps" => 110, "vmax" => 195, "verbrauch" => 4.3, "beschleunigung" => 10.7, "gewicht" => 1282, "hubraum" => 1197, "zylinder" => 4, "preis" => 23000, "bild" => "tn-vwgolfvariant2016.jpg" ],
	[ "baujahr" => "2020", "name" => "VW Golf 7 GTD", "ps" => 184, "vmax" => 230, "verbrauch" => 4.5, "beschleunigung" => 7.9, "gewicht" => 1495, "hubraum" => 1968, "zylinder" => 4, "preis" => 38000, "bild" => "tn-vwgolf7-2014.jpg" ],
	[ "baujahr" => "2014", "name" => "VW Golf 7 TDI bluemotion", "ps" => 110, "vmax" => 200, "verbrauch" => 3.2, "beschleunigung" => 10.5, "gewicht" => 1320, "hubraum" => 1598, "zylinder" => 4, "preis" => 40000, "bild" => "tn-vwgolf7-2014.jpg" ],
	[ "baujahr" => "2016", "name" => "VW Golf 7 TDI", "ps" => 110, "vmax" => 200, "verbrauch" => 3.2, "beschleunigung" => 10.5, "gewicht" => 1320, "hubraum" => 1598, "zylinder" => 4, "preis" => 40000, "bild" => "tn-vwgolf7-2014.jpg" ],
	[ "baujahr" => "2024", "name" => "VW Golf 8 GTD Kombi schwarz", "ps" => 200, "vmax" => 240, "verbrauch" => 5, "beschleunigung" => 7.1, "gewicht" => 1440, "hubraum" => 1968, "zylinder" => 4, "preis" => 42000, "bild" => "tn-golf8-gtd2022.jpg" ],
	[ "baujahr" => "1989", "name" => "VW Golf II", "ps" => 75, "vmax" => 175, "verbrauch" => 7.5, "beschleunigung" => 12.5, "gewicht" => 950, "hubraum" => 1595, "zylinder" => 4, "preis" => 12000, "bild" => "tn-golf2-13.jpg" ],
	[ "baujahr" => "1993", "name" => "VW Golf III GL", "ps" => 75, "vmax" => 175, "verbrauch" => 7.5, "beschleunigung" => 12.5, "gewicht" => 960, "hubraum" => 1595, "zylinder" => 4, "preis" => 15000, "bild" => "tn-golf3-16.jpg" ],
	[ "baujahr" => "2000", "name" => "VW Golf IV 1.9 TDI", "ps" => 90, "vmax" => 181, "verbrauch" => 4.9, "beschleunigung" => 12.1, "gewicht" => 1220, "hubraum" => 1896, "zylinder" => 4, "preis" => 17000, "bild" => "tn-golf4.jpg" ],
	[ "baujahr" => "2000", "name" => "VW Golf IV 2.0 FSI", "ps" => 110, "vmax" => 195, "verbrauch" => 5, "beschleunigung" => 11.3, "gewicht" => 1260, "hubraum" => 1896, "zylinder" => 4, "preis" => 27000, "bild" => "tn-golf4.jpg" ],
	[ "baujahr" => "2014", "name" => "VW Golf Plus 1.9 FSI", "ps" => 105, "vmax" => 185, "verbrauch" => 5.9, "beschleunigung" => 12.1, "gewicht" => 1400, "hubraum" => 1598, "zylinder" => 4, "preis" => 35200, "bild" => "tn-vwgolfplus14.jpg" ],
	[ "baujahr" => "2008", "name" => "VW Golf V 1.9 TDI", "ps" => 105, "vmax" => 188, "verbrauch" => 5.6, "beschleunigung" => 11.3, "gewicht" => 1321, "hubraum" => 1896, "zylinder" => 4, "preis" => 22000, "bild" => "tn-golf-v19.jpg" ],
	[ "baujahr" => "2003", "name" => "VW Passat 1.9 TDI", "ps" => 90, "vmax" => 184, "verbrauch" => 5.3, "beschleunigung" => 13.5, "gewicht" => 1280, "hubraum" => 1896, "zylinder" => 4, "preis" => 25000, "bild" => "tn-vwpassat2.jpg" ],
	[ "baujahr" => "2002", "name" => "VW Passat 2.0 TDI", "ps" => 136, "vmax" => 205, "verbrauch" => 6.1, "beschleunigung" => 10.2, "gewicht" => 1419, "hubraum" => 1968, "zylinder" => 4, "preis" => 24000, "bild" => "tn-vwpassat1.jpg" ],
	[ "baujahr" => "2010", "name" => "VW Passat 2.0 TSI", "ps" => 190, "vmax" => 238, "verbrauch" => 6.3, "beschleunigung" => 7.5, "gewicht" => 1648, "hubraum" => 1984, "zylinder" => 4, "preis" => 30000, "bild" => "tn-vwpassat2009.jpg" ],
	[ "baujahr" => "2015", "name" => "VW Passat 2015 2.0 TDI", "ps" => 150, "vmax" => 220, "verbrauch" => 4, "beschleunigung" => 8.7, "gewicht" => 1495, "hubraum" => 1968, "zylinder" => 4, "preis" => 32000, "bild" => "tn-vwpassat2015.jpg" ],
	[ "baujahr" => "2017", "name" => "VW Passat 4Motion TDI", "ps" => 200, "vmax" => 228, "verbrauch" => 6, "beschleunigung" => 7.2, "gewicht" => 1709, "hubraum" => 1968, "zylinder" => 4, "preis" => 40000, "bild" => "tn-vwpassatvar2015.jpg" ],
	[ "baujahr" => "2017", "name" => "VW Passat B8 2.0 TDI 4Motion", "ps" => 240, "vmax" => 238, "verbrauch" => 5.5, "beschleunigung" => 6.3, "gewicht" => 1695, "hubraum" => 1968, "zylinder" => 4, "preis" => 48000, "bild" => "tn-vwpassatvar2015.jpg" ],
	[ "baujahr" => "2010", "name" => "VW Passat Kombi (Modell 2007)", "ps" => 140, "vmax" => 200, "verbrauch" => 6, "beschleunigung" => 10.2, "gewicht" => 1600, "hubraum" => 1968, "zylinder" => 4, "preis" => 28000, "bild" => "tn-vwpassat2007.jpg" ],
	[ "baujahr" => "2013", "name" => "VW Passat Kombi Mj 2012", "ps" => 140, "vmax" => 210, "verbrauch" => 5.4, "beschleunigung" => 9.8, "gewicht" => 1500, "hubraum" => 1968, "zylinder" => 4, "preis" => 55000, "bild" => "tn-vwpassat2012v.jpg" ],
	[ "baujahr" => "2012", "name" => "VW Passat Modell 2006", "ps" => 136, "vmax" => 208, "verbrauch" => 6.1, "beschleunigung" => 10.2, "gewicht" => 1481, "hubraum" => 1968, "zylinder" => 4, "preis" => 28000, "bild" => "tn-vwpassat2009.jpg" ],
	[ "baujahr" => "2002", "name" => "VW Phaeton W12", "ps" => 450, "vmax" => 250, "verbrauch" => 14.5, "beschleunigung" => 6.1, "gewicht" => 2317, "hubraum" => 5998, "zylinder" => 12, "preis" => 100000, "bild" => "tn-phaeton.jpg" ],
	[ "baujahr" => "1974", "name" => "VW Polo 1", "ps" => 45, "vmax" => 135, "verbrauch" => 7.6, "beschleunigung" => 21.2, "gewicht" => 685, "hubraum" => 895, "zylinder" => 4, "preis" => 6000, "bild" => "tn-vwpolo1975.jpg" ],
	[ "baujahr" => "1985", "name" => "VW Polo 2", "ps" => 55, "vmax" => 150, "verbrauch" => 7, "beschleunigung" => 15.5, "gewicht" => 725, "hubraum" => 1272, "zylinder" => 4, "preis" => 8000, "bild" => "tn-vwpolo2-1984.jpg" ],
	[ "baujahr" => "2016", "name" => "VW Sharan (Modell 2014)", "ps" => 150, "vmax" => 200, "verbrauch" => 6.4, "beschleunigung" => 10.3, "gewicht" => 1780, "hubraum" => 1968, "zylinder" => 4, "preis" => 35000, "bild" => "tn-sharan2014.jpg" ],
	[ "baujahr" => "2004", "name" => "VW T4 Caravelle 2.5 TDI V6", "ps" => 102, "vmax" => 155, "verbrauch" => 9.8, "beschleunigung" => 18.4, "gewicht" => 1985, "hubraum" => 2461, "zylinder" => 5, "preis" => 35000, "bild" => "tn-vwt4.jpg" ],
	[ "baujahr" => "2007", "name" => "VW Tiguan 2.0 TDI (170 PS)", "ps" => 170, "vmax" => 201, "verbrauch" => 6.3, "beschleunigung" => 8.9, "gewicht" => 1606, "hubraum" => 1968, "zylinder" => 4, "preis" => 30000, "bild" => "tn-vwtiguan.jpg" ],
	[ "baujahr" => "2007", "name" => "VW Touareg V10 TDI", "ps" => 313, "vmax" => 225, "verbrauch" => 12.8, "beschleunigung" => 7.8, "gewicht" => 2611, "hubraum" => 4921, "zylinder" => 10, "preis" => 70000, "bild" => "tn-vwtouareg07.jpg" ],
	[ "baujahr" => "2015", "name" => "VW Touran (Modell 2012)", "ps" => 140, "vmax" => 200, "verbrauch" => 6.1, "beschleunigung" => 10.2, "gewicht" => 1558, "hubraum" => 1968, "zylinder" => 4, "preis" => 30000, "bild" => "tn-vwtouran2014.jpg" ],
	[ "baujahr" => "2007", "name" => "VW Touran 1.6 TDI", "ps" => 140, "vmax" => 200, "verbrauch" => 6.1, "beschleunigung" => 10.2, "gewicht" => 1558, "hubraum" => 1968, "zylinder" => 4, "preis" => 30000, "bild" => "tn-vwtouran.jpg" ],
	[ "baujahr" => "2005", "name" => "VW Touran 1.9 TDI", "ps" => 105, "vmax" => 183, "verbrauch" => 6.3, "beschleunigung" => 12.8, "gewicht" => 1521, "hubraum" => 1896, "zylinder" => 4, "preis" => 24500, "bild" => "tn-vwtouran.jpg" ],
	[ "baujahr" => "2004", "name" => "VW Touran 2.0 TDI", "ps" => 140, "vmax" => 200, "verbrauch" => 6.1, "beschleunigung" => 10.2, "gewicht" => 1558, "hubraum" => 1968, "zylinder" => 4, "preis" => 30000, "bild" => "tn-vwtouran.jpg" ],
	];
	// Anzahl der zufällig auszuwählenden Elemente, auch als cmdline url parameter &karten=x
	$anzahl_auswahl = isset($_GET['karten'])?intval($_GET['karten']):30;
	$anzahl_karten = count($kartenfull);
	$karten = [];
	// Wenn das ursprüngliche Array weniger als die count(karten) Elemente hat, werden alle Elemente ausgewählt.
	if ($anzahl_karten <= $anzahl_auswahl) {
		$karten = $karten;
	} else {
		// Erstelle ein Array mit den Schlüsseln des ursprünglichen Arrays
		$schluessel = array_keys($kartenfull);
		// Mische die Schlüssel zufällig
		shuffle($schluessel);
		// Nimm die ersten $anzahl_auswahl Schlüssel
		$zufaellige_schluessel = array_slice($schluessel, 0, $anzahl_auswahl);
		// Iteriere durch die zufälligen Schlüssel und füge die entsprechenden Elemente zum neuen Array hinzu
		foreach ($zufaellige_schluessel as $schluessel) {
			$karten[] = $kartenfull[$schluessel];
		}
	}


    // Kriterien, bei denen der niedrigere Wert gewinnt
    $kleinerIstBesser = ["verbrauch", "beschleunigung", "gewicht"];
    // Highscore initialisieren
    $highscore = get_option( 'autoquartett_score', true );
	if (!$highscore) update_option( 'autoquartett_score', 'noch keiner' );
	// Rundenzähler init
	$runde = isset($_POST['runde']) ? intval($_POST['runde']) : 1;

    // Daten aus POST laden oder neu starten
    if (isset($_POST['spieler']) && isset($_POST['computer'])) {
        $spieler = json_decode(stripslashes($_POST['spieler']), true);
        $computer = json_decode(stripslashes($_POST['computer']), true);
        $meldung = "";
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kriterium'])) {
            $kriterium = $_POST['kriterium'];
            $spielerkarte = $spieler[0];
            $computerskarte = $computer[0];
			$marke = strtoupper(strtok(htmlspecialchars($computerskarte['name']), ' '));
			$upload_dir = wp_upload_dir();
			$upload_basedir = $upload_dir['basedir'];
			$bfile = $upload_basedir . '/quizbilder/' . $computerskarte['bild'];
			if ( file_exists( $bfile ) ) {
				$bildlink = $upload_dir['baseurl'].'/quizbilder/'.$computerskarte['bild'];
				$bildshow = '<a href="'.$bildlink.'"><img title="'.$computerskarte['bild'].'" src="'.$bildlink.'"></a>';
			} else $bildshow='';
			$computerskarte_anzeige = "<strong>💻 Letzte Karte des Computers:</strong>
				<span class=\"headline\">" . htmlspecialchars($computerskarte['name']). "</span> &nbsp; "
				. do_shortcode('[carlogo brand="'.$marke.'"]') . "<table>
				<tr><td>PS:</td><td>" . number_format($computerskarte['ps'], 0, ',', '.')
				." (". number_format(($computerskarte['ps']*.7355), 0, ',', '.')
				. " kW)</td><td rowspan=9>".$bildshow."</td></tr>
				<tr><td>Vmax:</td><td>" . $computerskarte['vmax'] . " km/h</td></tr>
				<tr><td>Verbrauch:</td><td>" . $computerskarte['verbrauch'] . " l/100 km</td></tr>
				<tr><td>Preis:</td><td>" . number_format($computerskarte['preis'], 0, ',', '.') . " €</td></tr>
				<tr><td>0-100 km/h:</td><td>" . $computerskarte['beschleunigung'] . " s</td></tr>
				<tr><td>Baujahr:</td><td>" . $computerskarte['baujahr'] . " ".ago(mktime(2,0,0,1,1,$computerskarte['baujahr']))."</td></tr>
				<tr><td>Gewicht:</td><td>" . number_format($computerskarte['gewicht'], 0, ',', '.') . " kg</td></tr>
				<tr><td>Hubraum:</td><td>" . number_format($computerskarte['hubraum'], 0, ',', '.') . " cm³</td></tr>
				<tr><td>Zylinder:</td><td>" . $computerskarte['zylinder'] . "</td></tr>
				</table>";
            $spielerwert = $spielerkarte[$kriterium];
            $computerswert = $computerskarte[$kriterium];
            if (in_array($kriterium, $kleinerIstBesser)) {
                $spielerGewinnt = $spielerwert < $computerswert;
                $computerGewinnt = $spielerwert > $computerswert;
            } else {
                $spielerGewinnt = $spielerwert > $computerswert;
                $computerGewinnt = $spielerwert < $computerswert;
            }
            if ($spielerGewinnt) {
                $meldung = "👨 Du gewinnst die Runde!";
                array_push($spieler, $computerskarte, $spielerkarte);
            } elseif ($computerGewinnt) {
                $meldung = "💻 Computer gewinnt die Runde.";
                array_push($computer, $spielerkarte, $computerskarte);
            } else {
                $meldung = "👨💻 Unentschieden. Beide behalten ihre Karten.";
                array_push($spieler, array_shift($spieler));
                array_push($computer, array_shift($computer));
            }
            array_shift($spieler);
            array_shift($computer);
			$meldung .= " #". ($runde - 1);
        }
    } else {
        if (count($karten) % 2 !== 0) {
            array_pop($karten);
        }
        shuffle($karten);
        $spieler = array_slice($karten, 0, count($karten)/2);
        $computer = array_slice($karten, count($karten)/2);
        $meldung = "Neues Spiel gestartet.";
    }

    // Spielende prüfen
    if (count($spieler) === 0 || count($computer) === 0) {
        $gewinner = count($spieler) === 0 ? "💻 Computer" : "👨 Spieler";
        // Highscore prüfen
		$meldung = wp_date('D d. F Y H:i:s',time()).' - '.$gewinner.' hat gewonnen. Spieler: '.count($spieler).', Computer: '.count($computer).' Karten nach '.$runde.' Runden.';
		update_option( 'autoquartett_score', $meldung );
        return "<h6>Spiel vorbei<h6><p>$meldung</p>
		<form method='post'><button>Neustart</button></form>";
    }

    // HTML Ausgabe für das Spiel
    $marke = @strtoupper(strtok(htmlspecialchars($spieler[0]['name']), ' '));
	$upload_dir = wp_upload_dir();
	$upload_basedir = $upload_dir['basedir'];
	$bfile = $upload_basedir . '/quizbilder/' . $spieler[0]['bild'];
	if ( file_exists( $bfile ) ) {
		$bildlink = $upload_dir['baseurl'].'/quizbilder/'.$spieler[0]['bild'];
		$bildshow = '<a href="'.$bildlink.'"><img title="'.$spieler[0]['bild'].'" src="'.$bildlink.'"></a>';
	} else $bildshow='';
	$output = "<strong>👨 Deine aktuelle Karte:</strong> ";
	$output .= '<span class="headline">' . @htmlspecialchars($spieler[0]['name']). '</span> &nbsp; ';
    $output .= do_shortcode('[carlogo brand="'.$marke.'"]');
    $output .= "<form method='post'>";
	$output .= "<table>";
    $output .= "<tr><td style='width:20%;max-width:20%'><button style='line-height:0' name='kriterium' value='ps'>PS</button></td><td>"
		. number_format($spieler[0]['ps'], 0, ',', '.') 
		." (". number_format(($spieler[0]['ps']*.7355), 0, ',', '.')
		. " kW)</td><td rowspan=9>".$bildshow."</td></tr>";
    $output .= "<tr><td><button style='line-height:0' name='kriterium' value='vmax'>Vmax</button></td><td>" . $spieler[0]['vmax'] . " km/h max.</td></tr>";
    $output .= "<tr><td><button style='line-height:0;background-color:#a228' title='geringer ist besser' name='kriterium' value='verbrauch'>Verbrauch</button></td><td>" . $spieler[0]['verbrauch'] . " l/100 km</td></tr>";
    $output .= "<tr><td><button style='line-height:0' name='kriterium' value='preis'>Preis</button></td><td>" . number_format($spieler[0]['preis'], 0, ',', '.') . " € Liste</td></tr>";
    $output .= "<tr><td><button style='line-height:0;background-color:#a228' title='geringer ist besser' name='kriterium' value='beschleunigung'>Beschleunigung</button></td><td>" . $spieler[0]['beschleunigung'] . " s (0-100 km/h)</td></tr>";
    $output .= "<tr><td><button style='line-height:0' name='kriterium' value='baujahr'>Baujahr</button></td><td>" . $spieler[0]['baujahr'] . " ".ago(mktime(2,0,0,1,1,$spieler[0]['baujahr']))."</td></tr>";
    $output .= "<tr><td><button style='line-height:0;background-color:#a228' title='geringer ist besser' name='kriterium' value='gewicht'>Gewicht</button></td><td>" . number_format($spieler[0]['gewicht'], 0, ',', '.') . " kg</td></tr>";
    $output .= "<tr><td><button style='line-height:0' name='kriterium' value='hubraum'>Hubraum</button></td><td>" . number_format($spieler[0]['hubraum'], 0, ',', '.') . " cm³</td></tr>";
    $output .= "<tr><td><button style='line-height:0' name='kriterium' value='zylinder'>Zylinder</button></td><td>" . $spieler[0]['zylinder'] . "</td></tr>";
    $output .= "</table>";
    $output .= "<input type='hidden' name='spieler' value='" . esc_attr(json_encode($spieler)) . "'>";
    $output .= "<input type='hidden' name='computer' value='" . esc_attr(json_encode($computer)) . "'>";
	$output .= '<input type="hidden" name="runde" value='. ($runde + 1) .'">';
	$output .= "</form>";
    $output .= '<div class="headline" style="margin-top:1em">'. $meldung;
    $output .= " - 👨 Du: " . count($spieler) . " Karten, 💻 Computer: " . count($computer) . " Karten.</div>";
	if (isset($computerskarte_anzeige)) {
		$output .= $computerskarte_anzeige;
	}
    $output .= "<p style='margin-top:2em'><strong>Letzer Spielstand:</strong> $highscore</p>";
	$output .= wpd_games_bar();
    return $output;
}

// --------------------------------- Autoquartett Spiel Ende --------------------------------------------------------


?>
