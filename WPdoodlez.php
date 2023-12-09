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
Version: 9.1.1.128
Stable tag: 9.1.1.128
Requires at least: 6.0
Tested up to: 6.4.2
Requires PHP: 8.0
*/

if (!defined('ABSPATH')) { exit; }

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


// wpdoodlez-adminstats
function wpdoodlez_stats_func($atts) {
	global $post;
	$args = shortcode_atts(array( 'id' => 0 ), $atts);
	$output ='';
	$qargs = array(
		'post_type' => array('wpdoodle'),
		'post_status' => 'publish',
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
			if ( $polli ) {
				$votes = get_option( 'wpdoodlez_' . md5( AUTH_KEY . get_the_ID() ), array() );
				$cct = 0;
				$voteip='';$firstvote='';$votezeit='';
				foreach ( $votes as $name => $vote ) {
					$cct += 1;
					foreach ( $suggestions as $key => $value ) {
						if ($key != "post_views_count" && $key != "post_views_timestamp" && $key != "likes") {
							if ( !empty($vote[ $key ]) ) {	$votes_cout[ $key ] ++; }
						}	
					}
					if (isset($vote['zeit'])) {
						$diff = time() - $vote['zeit'];
						if (round((intval($diff) / 86400), 0) < 30) { $newcolor = "yellow"; } else { $newcolor = "white"; }
						$votezeit = '<abbr title="'.__( 'vote', 'WPdoodlez' ).' '.$cct.'" class="newlabel '.$newcolor.'">'.date_i18n(get_option('date_format').' '.get_option('time_format'), $vote['zeit'] + date('Z')).' '.ago($vote['zeit']).'</abbr></br>';
						if ($cct == 1 ) $firstvote = $votezeit;
					} else { $votezeit=''; $firstvote=''; }
					if (isset($vote['ipaddr'])) $voteip = $vote['ipaddr']; else $voteip='';
				}	
				$xsum = 0;
				foreach ( $votes_cout as $key => $value ) {
					if ($key != "post_views_count" && $key != "post_views_timestamp" && $key != "likes" && $value > 0 ) {
						$xsum += $value;
					}
				}
				$titelqm = get_the_title();
				if ( substr($titelqm, -1, 1) != '?' ) $titelqm .= '?';
				$output .= '<p title="'.__( 'Vote!', 'WPdoodlez' ).'" style="font-size:1.1em;margin-bottom:8px"><i class="fa fa-lg fa-check-square-o"></i> <a href="'.get_the_permalink().'">'.get_the_id().' &nbsp; '.$titelqm.'</a></p>';
				$output .= '<table>';	
				$xperc = 0;
				$votecounter = 0;
				foreach ( $suggestions as $key => $value ) {
					 if ($key != "post_views_count" && $key != "post_views_timestamp" && $key != "likes" ) {
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
		echo '<p class="description" style="display: none;">' . __( 'Shortcode copied to clipboard.', 'WPdoodlez' ) . '</p>';
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
	Play a quiz game with one or four answers and hangman (galgenmännchen) option for finding the solution
	Quizzz supports categories images and integrates them in single and header if used in theme. It adds a custom post type "question"
	see readme.txt for more details. Put pictures in folder uploads/quizbilder do display quizfragen with a picture
	Add Shortcode [random-question] to any post, page or html-widget<br>
	#### Crossword<br>
	display a crossword game built on the quizzz words
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
					if ($key != "post_views_count" && $key != "post_views_timestamp" && $key != "likes") {
						if ( !empty($vote[ $key ]) ) {	$votes_cout[ $key ] ++; }
					}	
				}
				if (isset($vote['zeit'])) {
					$diff = time() - $vote['zeit'];
					if (round((intval($diff) / 86400), 0) < 30) { $newcolor = "yellow"; } else { $newcolor = "white"; }
					$votezeit = '<abbr title="'.__( 'vote', 'WPdoodlez' ).' '.$cct.'" class="newlabel '.$newcolor.'">'.date_i18n(get_option('date_format').' '.get_option('time_format'), $vote['zeit'] + date('Z')).' '.ago($vote['zeit']).'</abbr></br>';
					if ($cct == 1 ) $firstvote = $votezeit;
				} else { $votezeit=''; $firstvote=''; }
				if (isset($vote['ipaddr'])) $voteip = $vote['ipaddr']; else $voteip='';
			}	
			$xsum = 0;
			$pielabel = ''; $piesum = '';
			foreach ( $votes_cout as $key => $value ) {
				if ($key != "post_views_count" && $key != "post_views_timestamp" && $key != "likes" && $value > 0 ) {
					$xsum += $value;
					$pielabel.=str_replace(',','',$suggestions[$key][0]).','; $piesum .= $value.','; 
				}
			}
			$htmlout .= '<br><table id="pollselect"><thead><th colspan="4">' . __( 'your choice', 'WPdoodlez'  ) . '</th></thead>';	
			$xperc = 0;
			$votecounter = 0;
			foreach ( $suggestions as $key => $value ) {
				 if ($key != "post_views_count" && $key != "post_views_timestamp" && $key != "likes" ) {
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
					if ($key != "post_views_count" && $key != "post_views_timestamp" && $key != "likes" ) {
						array_push($xevents, $key);
					}
				}
				foreach ( $suggestions as $key => $value ) {
					if ($key != "post_views_count" && $key != "post_views_timestamp" && $key != "likes" ) {
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
				if ($key != "post_views_count" && $key != "post_views_timestamp" && $key != "likes" ) {
					$htmlout .= '<th style="word-break:break-all;overflow-wrap:anywhere">';
					// ICS Download zum Termin anbieten
					if( function_exists('export_ics') && is_singular() ) {
						$nextnth = strtotime($key);
						$nextnth1h = strtotime($key);
						$htmlout .= ' <a title="'.__("ICS add reminder to your calendar","penguin").'" href="'.home_url(add_query_arg(array($_GET, 'start' =>wp_date('Ymd\THis', $nextnth), 'ende' => wp_date('Ymd\THis', $nextnth1h) ),$wp->request.'/icalfeed/')).'"><i class="fa fa-calendar-check-o"></i></a> ';
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
				if ($key != "post_views_count" && $key != "post_views_timestamp" && $key != "likes"  ) {
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
				if (isset($vote['zeit'])) $votezeit = date_i18n(get_option('date_format').' '.get_option('time_format'), $vote['zeit'] + date('Z')); else $votezeit='';
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
					if ($key != "post_views_count" && $key != "post_views_timestamp" && $key != "likes") {
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
				if ($key != "post_views_count" && $key != "post_views_timestamp" && $key != "likes" ) {
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


// Shortcode Random Question (als Widget auf der Homepage)
function random_quote_func() {
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
					$quizkat .= '&nbsp; <i class="fa fa-folder-open"></i> <a href="'. get_term_link($term) .'">' . $term->name . '</a> &nbsp; ';
				}
			}	
			$herkunftsland = get_post_custom_values('quizz_herkunftsland');
			$answers = get_post_custom_values('quizz_answer');
			$quizbild = get_post_custom_values('quizz_bild');
			$hangrein = preg_replace("/[^A-Za-z0-9]/", '', $answers[0]);
			if (strlen($hangrein) <= 15 && strlen($hangrein) >= 5) $quizkat .= '<a title="'.__('answer with hangman','WPdoodlez').'" href="'.add_query_arg( array('hangman'=>1), get_post_permalink() ).'"><i class="fa fa-universal-access"></i> '. __('Hangman','WPdoodlez').'</a>';
			$quizkat .= ' &nbsp; <a title="'.__('play crossword','WPdoodlez').'" href="'.add_query_arg( array('crossword'=>1), get_post_permalink() ).'"><i class="fa fa-th"></i> '. __('crossword','WPdoodlez').'</a>';
			$quizkat .= ' &nbsp; <a title="'.__('play word puzzle','WPdoodlez').'" href="'.add_query_arg( array('crossword'=>2), get_post_permalink() ).'"><i class="fa fa-puzzle-piece"></i> '. __('wordsearch','WPdoodlez').'</a>';
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
				if (!empty($quizbild[0])) {
					$upload_dir = wp_upload_dir();
					$upload_basedir = $upload_dir['basedir'];
					$file = $upload_basedir . '/quizbilder/' . $quizbild[0];
					if ( file_exists( $file ) ) {
						$bildlink = $upload_dir['baseurl'].'/quizbilder/'.$quizbild[0];
						$bildshow = '<div class="middle" style="opacity:.8;top:3px"><img style="height:114px;max-height:150px;max-width:150px;min-width:150px;position:absolute;right:3px;top:0;width:150px"" title="'.$quizbild[0].'" src="'.$bildlink.'"></div>';
					} 
					$message .= $bildshow;
				} else { $bildshow=''; $bildlink='';}
				$message .= '</div>';
			}			
			$message .= '<div class="greybox" style="background-color:'.$accentcolor.'19"><a title="alle Fragen anzeigen" href="'.esc_url(site_url().'/question?orderby=rand&order=rand').'"><i class="fa fa-question-circle"></i></a>&nbsp;';
			$message .= $quizkat;
			$message .= '</div></header>';
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
		exit;
	endif;
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
				$rite = (float) get_post_meta( get_the_ID(), 'quizz_rightstat', true );
				$wrg = (float) get_post_meta( get_the_ID(), 'quizz_wrongstat', true );
				if ( $wrg == 0) { $rpct = 100; } else { $rpct = round($rite / $wrg * 100); }
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
				if ( $rite == 0) { $rpct = 100; } else { $rpct = round($wrg / $rite * 100); }
				$message .= '<span style="color:tomato;display:inline-block;width:55px">F:'.wpdoo_number_format_short( (float) get_post_meta( get_the_ID(), 'quizz_wrongstat', true ) ?? 0).'</span><span style="color:#1bab1b;display:inline-block;width:55px">R:'.wpdoo_number_format_short( (float) get_post_meta( get_the_ID(), 'quizz_rightstat', true ) ?? 0).'</span>';
				$message .= ' <progress id="rfs" value="'.$rpct.'" max="100" style="width:80px"></progress> <a href="'.get_the_permalink().'">'.substr(get_the_content(),0,80).'</a><br>';
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
			$message .= '<p>Gesamt gespielt: '.wpdoo_number_format_short( (float) intval($rct + $wct) ?? 0).' Fragen, davon richtig: ' .wpdoo_number_format_short( (float) $rct ?? 0);
			$message .= ' &nbsp;<progress id="rf" value="'.intval($rct/($rct+$wct)*100).'" max="100" style="width:100px"></progress>';
			$message .= ' &nbsp; falsch: '.wpdoo_number_format_short( (float) $wct ?? 0);
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
	global $wp;
	setlocale (LC_ALL, 'de_DE.utf8', 'de_DE@euro', 'de_DE', 'de', 'ge'); 
	if (get_post_type()=='question'):
		global $answer;
		if (isset($_POST['answer'])) $answer = esc_html($_POST['answer']);	// user submitted answer
		if (isset($_POST['ans'])) $answer = esc_html($_POST['ans']);   // Answer is radio button selection 1 of 4
		if (isset($_GET['ans']))  $answer = esc_html($_GET['ans']);  // Answer is given from shortcode
		if (isset($_GET['ende'])) { $ende = esc_html($_GET['ende']); } else { $ende = 0; }
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
		$nextlevel = get_post_custom_values('quizz_nextlevel');
		$exact = get_post_custom_values('quizz_exact');
		$last_bool = get_post_custom_values('quizz_last');
		$lastpage = get_post_custom_values('quizz_lastpage');
		$rightstat = get_post_custom_values('quizz_rightstat');
		$wrongstat = get_post_custom_values('quizz_wrongstat');
		$error = "<p class='quiz_error quiz_message'>ERROR</p>";
		$lsubmittedanswer = preg_replace("/[^A-Za-z0-9]/", '', strtolower(esc_html($answer)));
		$lactualanswer = preg_replace("/[^A-Za-z0-9]/", '', strtolower(esc_html($answers[0])));
		$hangrein = preg_replace("/[^A-Za-z0-9]/", '', $answers[0]);
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
		// Hangman spielen oder normale Beantwortung
		if ( isset($_GET['hangman']) && strlen($hangrein) <= 14 && strlen($hangrein) >= 5 ) {
			$hangwort = preg_replace("/[^A-Za-z]/", '', $answers[0]);
			$theForm = $content . play_hangman($hangwort);
			$theForm .= '<ul class="footer-menu" style="text-align:center;margin-top:20px;list-style:none;text-transform:uppercase;"><li><a href="' . $random_post_url .'"><i class="fa fa-random"></i> '. __('next random question','WPdoodlez').'</a></li></ul>';
			if ( strpos($theForm,"background-color:#1bab1b") !== false ) {	
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
				if ( empty($_POST) ) $showsubmit='inline-block'; else $showsubmit='none';
				$ansmixed .= __('answer mask','WPdoodlez'). '<span style="background-color:#eee;margin:8px 5px;font-weight:700;font-size:1.2em;padding:3px 0 3px 9px;letter-spacing:.5em;font-family:monospace">';
				$ansmixed .= preg_replace( '/[^( |aeiouAEIOU.)$]/', '_', esc_html($answers[0])).'</span>' . strlen(esc_html($answers[0])).__(' characters long. ','WPdoodlez');
				if ( empty($_POST) ) {
					if ($exact[0]!="exact") { $ansmixed .= __('not case sensitive','WPdoodlez'); } else { $ansmixed .= __('case sensitive','WPdoodlez'); }
					$ansmixed .='<input autocomplete="off" style="width:100%" type="text" name="answer" id="answer" placeholder="'. __('your answer','WPdoodlez').'" class="quiz_answer answers">';
				}
				$pollyans = esc_html(preg_replace( '/[^( |aeiouAEIOU.)$]/', '*', esc_html($answers[0])));
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
		if ( strlen($answers[0])>5 ) { $wikinachschlag = '<p><i class="fa fa-wikipedia-w"></i> &nbsp; <a title="Wiki more info" target="_blank" href="https://de.wikipedia.org/wiki/'.$answers[0].'">Wiki-Artikel</a></p>'; } else { $wikinachschlag='';}
			if ( $correct == "yes" ) {
				ob_start();
				if (isset($_COOKIE['hidecookiebannerx']) && $_COOKIE['hidecookiebannerx']==2 ) setcookie('rightscore', intval($_COOKIE['rightscore']) + 1, time()+60*60*24*30, '/');
				ob_flush();
				update_post_meta( get_the_ID(), 'quizz_rightstat', ($rightstat[0] + 1) ?? 0 );
				if ($last_bool[0] != "last") {
					if ( !empty($nextlevel[0]) ) {
						// raise a hook for updating record
						do_action( 'quizz_level_updated', $nextlevel[0] );
						$goto = $nextlevel[0];
						wp_safe_redirect( get_post_permalink($goto) );
					} else {
						$error = $ansmixed.'<blockquote class="blockbulb" style="font-size:1.2em;margin-top:30px"><i class="fa fa-lg fa-thumbs-o-up"></i> &nbsp; ' . __('correct answer: ','WPdoodlez') . ' '. $answers[0];
						if ( !empty($zusatzinfo) && strlen($zusatzinfo[0])>1 ) $error .= '<p style="margin-top:15px"><i class="fa fa-newspaper-o"></i> &nbsp; '.$zusatzinfo[0].'</p>';
						$error .= $wikinachschlag.'</blockquote>';
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
					$error = $ansmixed.'<blockquote class="blockbulb" style="font-size:1.2em;margin-top:1.6em">';
					$error .= '<i class="fa fa-lg fa-thumbs-o-down"></i> &nbsp; '. $answer;
					$error .= '<br>'. __(' is the wrong answer. Correct is','WPdoodlez').'<br><i class="fa fa-lg fa-thumbs-up"></i> &nbsp; '.esc_html($answers[0]);
					if ( !empty($zusatzinfo) && strlen($zusatzinfo[0])>1 ) $error .= '<p style="margin-top:15px"><i class="fa fa-newspaper-o"></i> &nbsp; '.$zusatzinfo[0].'</p>';
					$error .= $wikinachschlag.'</blockquote>';
					$showqform = 'display:none';
					ob_start();
					if (isset($_COOKIE['hidecookiebannerx']) && $_COOKIE['hidecookiebannerx']== 2 ) setcookie('wrongscore', (intval($_COOKIE['wrongscore']) + 1), time()+60*60*24*30, '/');
					ob_flush();
					update_post_meta( get_the_ID(), 'quizz_wrongstat', ($wrongstat[0] + 1) ?? 0 );
				} else { $error = "";$showqform = ''; }
			}
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
			$letztefrage ='<div style="text-align:center;margin-top:10px"><ul class="footer-menu" style="padding:2px 2px;text-transform:uppercase;">';
			$terms = get_the_terms(get_the_id(), 'quizcategory'); // Get all terms of a taxonomy
			$copytags = '';
			if ( $terms && !is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
						$copytags .= ' Kategorie: ' . $term->name . ' - '; 
				}
			}	
			// für die Zwischenablage 
			$copyfrage = '  ' . wp_strip_all_tags( preg_replace("/[?,:]()/", '', get_the_title() ).'  '.$copytags.' eine Frage aus '. $herkunftsland[0] .'  '. preg_replace("/[?,:()]/", '',get_the_content() ).' ? '.preg_replace("/[?:()]/", '.',$pollyans ));
			$letztefrage.= $listyle.'<input title="Frage in Zwischenablage kopieren" style="cursor:pointer;background-color:'.$accentcolor.';color:white;margin-top:5px;vertical-align:top;width:49px;height:20px;font-size:9px;padding:0" type="text" class="copy-to-clipboard" value="'.$copyfrage.'">';
			$letztefrage .= '</li>' . $listyle. '<a title="'. __('overview','WPdoodlez').'" href="'.get_home_url().'/question?orderby=rand&order=rand"><i class="fa fa-list"></i></a>';
			if (isset($nextlevel) || isset($last_bool[0]) ) {
				if ($last_bool[0] == "last") {
					$letztefrage .= '</li>'.$listyle.__('last question','WPdoodlez').'</li>';
				} else {
					$letztefrage .= '</li>'.$listyle.'<a href="'.get_post_permalink($nextlevel[0]).'"><i class="fa fa-arrow-circle-right"></i>'. __('next random question','WPdoodlez').' '.$nextlevel[0].'</a></li>';
				}
			} else {
				if (isset($_GET['timer'])) { $timerurl='?timer=1'; } else { $timerurl = ''; }
				$letztefrage.='</li>'.$listyle.'<a href="' . $random_post_url . $timerurl.'"><i class="fa fa-random"></i> '. __('next random question','WPdoodlez').'</a>';
				if (strlen($hangrein) <= 14 && strlen($hangrein) >= 5) $letztefrage.='</li>'.$listyle.'<a href="'.add_query_arg( array('hangman'=>1), get_post_permalink() ).'"><i class="fa fa-universal-access"></i> '. __('Hangman','WPdoodlez').'</a></li>';
				$letztefrage.= $listyle.'<a title="Kreuzworträtsel spielen" href="'.add_query_arg( array('crossword'=>1), get_post_permalink() ).'"><i class="fa fa-th"></i> '. __('crossword','WPdoodlez').'</a></li>';
				$letztefrage.= $listyle.'<a title="Wortsuche spielen" href="'.add_query_arg( array('crossword'=>2), get_post_permalink() ).'"><i class="fa fa-puzzle-piece"></i> '. __('wordsearch','WPdoodlez').'</a></li>';

			}
			$letztefrage.='</li>'.$listyle.'<a title="'.__('certificate','WPdoodlez').'" href="'.add_query_arg( array('ende'=>1), home_url($wp->request) ).'"><i class="fa fa-certificate"></i> '.__('certificate','WPdoodlez').'</a></li>';
			if ( @$wrongstat[0] > 0 || @$rightstat[0] >0 ) { $perct = intval(@$rightstat[0] / (@$wrongstat[0] + @$rightstat[0]) * 100); } else { $perct= 0; }
			if ( @$_COOKIE['wrongscore'] > 0 || @$_COOKIE['rightscore'] >0 ) { $sperct = intval (@$_COOKIE['rightscore'] / (@$_COOKIE['wrongscore'] + @$_COOKIE['rightscore']) * 100); } else { $sperct= 0; }
			$letztefrage .= '</ul><br><br><ul></li>'.$listyle. __('Total scores','WPdoodlez');
			$letztefrage .= ' <progress id="rf" value="'.$perct.'" max="100" style="width:100px"></progress> R: '. number_format_i18n(@$rightstat[0] ?? 0).' / F: '. number_format_i18n(@$wrongstat[0] ?? 0);
			if (isset($_COOKIE['hidecookiebannerx']) && $_COOKIE['hidecookiebannerx']==2 ) {
				$letztefrage .= '</li>'.$listyle. __('Your session','WPdoodlez');
				$letztefrage .= ' <progress id="rf" value="'.$sperct.'" max="100" style="width:100px"></progress> R: ' . number_format_i18n(@$_COOKIE['rightscore'] ?? 0). ' / F: '.number_format_i18n(@$_COOKIE['wrongscore'] ?? 0).'</li>';
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
				$theForm .= '<img src="'.plugin_dir_url(__FILE__).'/lightbulb-1000-250.jpg" style="width:100%"><div style="text-align:center;padding-top:20px;font-size:1.5em">'. __('test terminated. thanks.','WPdoodlez');
				$theForm .= '<br><br><br>'.__('you have ','WPdoodlez') . (@$_COOKIE['wrongscore'] + @$_COOKIE['rightscore']).' Fragen beantwortet,<br>davon ' .@$_COOKIE['rightscore']. '  ('.$sperct.'%) richtig und '.@$_COOKIE['wrongscore'].' ('. (100 - $sperct) .'%) falsch.';
				$theForm .= '<p style="margin-top:20px"><progress id="file" value="'.$sperct.'" max="100"> '.$sperct.' </progress></p>';
				if ( $sperct < 50 ) { $fail='<span style="color:tomato">leider nicht</span>'; } else { $fail=''; }
				$theForm .= '<p style="margin-top:20px">In Schulnoten ausgedrückt: '.get_schulnote( $sperct ).',<br>somit <strong>'.$fail.' bestanden</strong>.</p>';

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
					$theForm .= '<p style="margin:2em 0 2em 0">Insgesamt wurden gespielt: '.wpdoo_number_format_short( (float) intval($rct + $wct) ?? 0).' Fragen,<br>davon richtig: ' .wpdoo_number_format_short( (float) $rct ?? 0);
					$theForm .= ' &nbsp;<progress id="rf" value="'.intval($rct/($rct+$wct)*100).'" max="100" style="width:100px"></progress>';
					$theForm .= ' &nbsp; falsch: '.wpdoo_number_format_short( (float) $wct  ?? 0);
					$theForm .= ' &nbsp;<progress id="rf" value="'.(100 - intval($rct/($rct+$wct)*100)).'" max="100" style="width:100px"></progress> </p>';
				}	

				$theForm .= '<p style="font-size:0.7em;margin-top:2em">'.date_i18n( 'D, j. F Y, H:i:s', false, false);
				$theForm .= '<span style="font-family:Brush Script MT;font-size:2.6em;padding-left:24px">'.wp_get_current_user()->display_name.'</span></p>';
				$theForm .= '<p style="font-size:0.7em">'. get_bloginfo('name') .' &nbsp; '. get_bloginfo('url') .'<br>'.get_bloginfo('description'). '</p></div>';
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
		'AF' => 'Afghanistan',
		'AL' => 'Albanien',
		'DZ' => 'Algerien',
		'AS' => 'Amerikanisch Samoa',
		'AD' => 'Andorra',
		'AO' => 'Angola',
		'AI' => 'Anguilla',
		'AQ' => 'Antarktis',
		'AG' => 'Antigua und Barbuda',
		'AR' => 'Argentinien',
		'AM' => 'Armenien',
		'AW' => 'Aruba',
		'AT' => 'Österreich',
		'AU' => 'Australien',
		'AZ' => 'Aserbaidschan',
		'BS' => 'Bahamas',
		'BH' => 'Bahrain',
		'BD' => 'Bangladesh',
		'BB' => 'Barbados',
		'BY' => 'Weißrussland',
		'BE' => 'Belgien',
		'BZ' => 'Belize',
		'BJ' => 'Benin',
		'BM' => 'Bermuda',
		'BT' => 'Bhutan',
		'BO' => 'Bolivien',
		'BA' => 'Bosnien Herzegowina',
		'BW' => 'Botswana',
		'BV' => 'Bouvet Island',
		'BR' => 'Brasilien',
		'IO' => 'Britisch-Indischer Ozean',
		'BN' => 'Brunei Darussalam',
		'BG' => 'Bulgarien',
		'BF' => 'Burkina Faso',
		'BI' => 'Burundi',
		'KH' => 'Kambodscha',
		'CM' => 'Kamerun',
		'CA' => 'Kanada',
		'CV' => 'Kap Verde',
		'KY' => 'Cayman Inseln',
		'CF' => 'Zentralafrikanische Republik',
		'TD' => 'Tschad',
		'CL' => 'Chile',
		'CN' => 'China',
		'CC' => 'Christmas Inseln',
		'CO' => 'Kokosinseln',
		'CO' => 'Kolumbien',
		'KM' => 'Comoros',
		'CG' => 'Kongo',
		'CD' => 'Demokratische Republik Kongo',
		'CK' => 'Cook Inseln',
		'CR' => 'Costa Rica',
		'CI' => 'Elfenbeinküste',
		'HR' => 'Kroatien',
		'CU' => 'Kuba',
		'CZ' => 'Tschechien',
		'CS' => 'Tschechoslowakei (ehemals)',
		'DK' => 'Dänemark',
		'DJ' => 'Djibouti',
		'DM' => 'Dominica',
		'DO' => 'Dominikanische Republik',
		'TP' => 'Osttimor',
		'EC' => 'Ecuador',
		'EG' => 'Ägypten',
		'SV' => 'El Salvador',
		'GQ' => 'Äquatorial Guinea',
		'ER' => 'Eritrea',
		'EE' => 'Estland',
		'ET' => 'Äthiopien',
		'EU' => 'Europäische Union',
		'FK' => 'Falkland Inseln',
		'FO' => 'Faroe Inseln',
		'FJ' => 'Fiji',
		'FI' => 'Finland',
		'FR' => 'Frankreich',
		'FX' => 'Frankreich (nur in Europa)',
		'GF' => 'Französisch Guiana',
		'PF' => 'Französisch Polynesien',
		'GA' => 'Gabon',
		'GM' => 'Gambia',
		'GE' => 'Georgien',
		'DE' => 'Deutschland',
		'DD' => 'DDR (ehemals)',
		'GH' => 'Ghana',
		'GI' => 'Gibraltar',
		'GR' => 'Griechenland',
		'GS' => 'South Georgia und South Sandwich Inseln',
		'GL' => 'Grönland',
		'GD' => 'Grenada',
		'GP' => 'Guadeloupe',
		'GU' => 'Guam',
		'GT' => 'Guatemala',
		'GN' => 'Guinea',
		'GY' => 'Guinea Bissau',
		'GY' => 'Guyana',
		'HT' => 'Haiti',
		'VA' => 'Vatikan',
		'HK' => 'Hong Kong',
		'HM' => 'Heard und McDonald Inseln',
		'HN' => 'Honduras',
		'HU' => 'Ungarn',
		'IS' => 'Island',
		'IN' => 'Indien',
		'ID' => 'Indonesien',
		'IR' => 'Iran',
		'IQ' => 'Irak',
		'IE' => 'Irland',
		'IL' => 'Israel',
		'IT' => 'Italien',
		'JM' => 'Jamaika',
		'JP' => 'Japan',
		'JO' => 'Jordanien',
		'KZ' => 'Kasachstan',
		'KE' => 'Kenia',
		'KI' => 'Kiribati',
		'KW' => 'Kuwait',
		'KG' => 'Kirgistan',
		'LA' => 'Laos',
		'LV' => 'Lettland',
		'LB' => 'Libanon',
		'LC' => 'Saint Lucia',
		'LS' => 'Lesotho',
		'LI' => 'Liechtenstein',
		'LT' => 'Litauen',
		'LU' => 'Luxemburg',
		'MP' => 'Marianen',
		'MQ' => 'Martinique',
		'ME' => 'Montenegro',
		'MO' => 'Macau',
		'MK' => 'Mazedonien',
		'NM' => 'Nord-Mazedonien',
		'MG' => 'Madagaskar',
		'MW' => 'Malawi',
		'MY' => 'Malaysia',
		'MV' => 'Malediven',
		'MH' => 'Marshall Inseln',
		'ML' => 'Mali',
		'MT' => 'Malta',
		'MR' => 'Mauretanien',
		'MU' => 'Mauritius',
		'YT' => 'Mayotte',
		'MX' => 'Mexiko',
		'FM' => 'Mikronesien',
		'MD' => 'Moldavien',
		'MC' => 'Monaco',
		'MN' => 'Mongolei',
		'MS' => 'Montserrat',
		'MA' => 'Marokko',
		'MZ' => 'Mosambik',
		'MM' => 'Myanmar',
		'NA' => 'Namibia',
		'NR' => 'Nauru',
		'NP' => 'Nepal',
		'NL' => 'Niederlande',
		'NZ' => 'Neuseeland',
		'NC' => 'Neu Kaledonien',
		'NI' => 'Nicaragua',
		'NE' => 'Niger',
		'NG' => 'Nigeria',
		'NU' => 'Niue',
		'NF' => 'Norfolk Inseln',
		'KP' => 'Nord Korea',
		'NO' => 'Norwegen',
		'OM' => 'Oman',
		'PK' => 'Pakistan',
		'PW' => 'Palau',
		'PA' => 'Panama',
		'PG' => 'Papua Neu Guinea',
		'PY' => 'Paraguay',
		'PE' => 'Peru',
		'PH' => 'Philippinen',
		'PL' => 'Polen',
		'PM' => 'St. Pierre und Miquelon',
		'PN' => 'Pitcairn',
		'PT' => 'Portugal',
		'PR' => 'Puerto Rico',
		'RO' => 'Rumänien',
		'RU' => 'Russland',
		'RW' => 'Ruanda',
		'QA' => 'Qatar',
		'RE' => 'Reunion',
		'RS' => 'Serbien',
		'WS' => 'Samoa',
		'SH' => 'Saint Helena Ascension und Tristan da Cunha',
		'SM' => 'San Marino',
		'SA' => 'Saudi-Arabien',
		'SN' => 'Senegal',
		'SC' => 'Seychellen',
		'SJ' => 'Svalbard und Jan Mayen Inseln',
		'SL' => 'Sierra Leone',
		'SS' => 'Süd-Sudan',
		'SU' => 'Sowiet-Union (obsolet)',
		'ST' => 'Sao Tome and Principe',
		'SG' => 'Singapur',
		'SK' => 'Slowakei',
		'SI' => 'Slowenien',
		'SB' => 'Solomon Inseln',
		'SO' => 'Somalia',
		'KN' => 'St. Kitts Nevis Anguilla',
		'ZA' => 'Südafrika',
		'KR' => 'Südkorea',
		'ES' => 'Spanien',
		'LK' => 'Sri Lanka',
		'SD' => 'Sudan',
		'SR' => 'Suriname',
		'SZ' => 'Swasiland',
		'SE' => 'Schweden',
		'CH' => 'Schweiz',
		'SY' => 'Syrien',
		'TW' => 'Taiwan',
		'TJ' => 'Tadschikistan',
		'TL' => 'Osttimor',
		'TZ' => 'Tansania',
		'TH' => 'Thailand',
		'TF' => 'French Southern Territories',
		'TG' => 'Togo',
		'TK' => 'Tokelau',
		'TO' => 'Tonga',
		'TT' => 'Trinidad und Tobago',
		'TN' => 'Tunesien',
		'TR' => 'Türkei',
		'TM' => 'Turkmenistan',
		'TV' => 'Tuvalu',
		'UG' => 'Uganda',
		'UK' => 'Großbritannien (UK)',
		'UA' => 'Ukraine',
		'AE' => 'Vereinigte Arabische Emirate',
		'UN' => 'Vereinte Nationen',
		'GB' => 'Vereinigtes Königreich',
		'US' => 'Vereinigte Staaten von Amerika',
		'UM' => 'US - kleinere Inseln außerhalb',
		'UY' => 'Uruguay',
		'UZ' => 'Usbekistan',
		'VU' => 'Vanuatu',
		'VE' => 'Venezuela',
		'VN' => 'Vietnam',
		'VI' => 'Virgin Island (USA)',
		'VG' => 'Virgin Island (Brit.)',
		'WF' => 'Wallis et Futuna',
		'EH' => 'Westsahara',
		'YE' => 'Jemen',
		'YU' => 'Jugoslawien (ehemals)',
		'ZR' => 'Zaire',
		'ZM' => 'Sambia',
		'ZW' => 'Simbabwe',
		'ZZ' => 'weltweit',
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

// -------- hangman spiel mit Wort laden  //  echo play_hangman('Katze');

function printPage($image, $guesstemplate, $which, $guessed, $wrong) {
	global $hang;
	global $wp;
	$gtml = '<style>input[type=button][disabled],button:disabled,button[disabled] { border: 1px solid #999999;background-color:#cccccc;color: #666666;}</style>';
	$gtml .= '<div style="padding:5px;margin:5px;text-align:center;background:#fff;font-family:monospace;font-size:1.8em;letter-spacing:5px">';
	$gtml .= $guesstemplate. '<br>';
	$formurl = add_query_arg( array('hangman'=>'1' ), home_url( $wp->request ) );
	$gtml .= '</div><form name="galgen" method="post" action="'. $formurl .'">';
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
	$gtml .= '<div style="float:left;width:38%"><code style="background:transparent;font-family:monospace;font-size:1.3em;line-height:0">'.$image.'</code></div>';
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
			return '<div style="margin-top:15px;font-size:1.2em;color:white;background-color:#1bab1b;display:inline-block; width:100%; padding:5px; border:1px solid #ddd;margin-bottom:15px">Gewonnen - Gratulation. Sie haben <i>'.$word.'</i> erraten.</div>';
		} else if ($wrong >= 6) {
			return '<div style="margin-top:15px;font-size:1.2em;color:white;background-color:tomato;display:inline-block; width:100%; padding:5px; border:1px solid #ddd;margin-bottom:15px">Verloren - <i>'.$word.'</i> war die Lösung.</div>';
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
				$crossant = umlauteumwandeln(preg_replace("/[^A-Za-zäüöÄÖÜß]/", '', esc_html($answers[0]) ) );
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
	$html = ' &nbsp; <a title="'.__('play crossword','WPdoodlez').'" href="'.add_query_arg( array('crossword'=>1), get_post_permalink() ).'"><i class="fa fa-puzzle-piece"></i> '. __('start new game','WPdoodlez').'</a>';
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

// ------------------------------- Shortcode für wordsearch puzzle ----------------------------------

function umlauteumwandeln($str){
	$tempstr = Array("Ä" => "AE", "Ö" => "OE", "Ü" => "UE", "ä" => "ae", "ö" => "oe", "ü" => "ue", "ß" => "ss"); 
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
				$crossant = umlauteumwandeln(preg_replace("/[^A-Za-zäüöÄÖÜß]/", '', esc_html($answers[0]) ) );
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
		$html .= '<p>'.__('please mark words with pressed mousekey','WPdoodlez');
		$html .= ' &nbsp; <a title="'.__('play word puzzle','WPdoodlez').'" href="'.add_query_arg( array('crossword'=>2), get_post_permalink() ).'"><i class="fa fa-puzzle-piece"></i> '. __('start new game','WPdoodlez').'</a>';
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
?>
