<?php
/**
Plugin Name: WP Doodlez
Plugin URI: https://github.com/svenbolte/WPdoodlez
Description: Doodle like finding meeting date, polls, quizzz with csv import
Contributors: Robert Kolatzek, PBMod
Author URI: https://github.com/svenbolte
Text Domain: WPdoodlez
Domain Path: /lang/
License: GPL 2
Author: PBMod
Version: 9.1.1.4
Stable tag: 9.1.1.4
Requires at least: 5.1
Tested up to: 5.5.3
Requires PHP: 7.2
*/

/**
 * Load plugin textdomain.
 */
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
    setcookie( 'wpdoodlez-' . (string)$_POST[ 'data' ][ 'wpdoodle' ], $name, time() + (3600 * 24 * 30), COOKIEPATH, COOKIE_DOMAIN, is_ssl() );
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
    include('wpdoodlez_post_type.php');
    foreach ( $_COOKIE as $key => $value ) {
        if ( preg_match( '/wpdoodlez\-.+/i', (string)$key ) ) {
            setcookie( (string)$key, (string)$value, time() + (3600 * 24 * 30), COOKIEPATH, COOKIE_DOMAIN, is_ssl() );
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
	echo '<h1>WPdoodlez Doku</h1>';
	?>
	* WPdoodlez can handle classic polls and doodle like appointment planning
	If custom fields are named vote1...vote10, a poll is created, just displaying the vote summaries<br><br>
	if custom fields are dates e.g  name: 12.12.2020    value: ja<br>
	then a doodlez is created where visitors can set their name or shortcut and vote for all given event dates<br>
	<br>
	User parameter /admin=1 to display alternate votes display (more features when logged in as admin)<br><br>
	<h2>Highlights</h2>
	* link to WPdoodlez is public, but post can have password <br>
	* A WPdoodlez can be in a review and be published at given time<br>
	* A WPdoodlez can have own URL <br>
	* Poll users do not need to be valid logged in wordpress users<br>
	* Users with "delete published post" rights can delete votes<br>
	* Users shortname will be stored in a cookie for 30 days (user can change only his own vote, but on the same computer)<br>
	* Every custom field set in a WPdoodle is a possible answer<br>
	* The first value of the custom field will be displayed in the row as users answer<br>
	* The last row in the table contains total votes count<br>
	<?php
}

// Mini Calendar display month 
function mini_calendar($month,$year,$eventarray){
	setlocale (LC_ALL, 'de_DE.utf8', 'de_DE@euro', 'de_DE', 'de', 'ge'); 
	/* days and weeks vars now ... */
	$calheader = date('Y-m-d',mktime(0,0,0,$month,1,$year));
	$running_day = date('w',mktime(0,0,0,$month,1,$year));
	if ( $running_day == 0 ) { $running_day = 7; }
	$days_in_month = date('t',mktime(0,0,0,$month,1,$year));
	$days_in_this_week = 1;
	$day_counter = 0;
	$dates_array = array();
	/* draw table */
	$calendar = '<table><thead><th style="text-align:center" colspan=8>' . strftime('%B %Y', mktime(0,0,0,$month,1,$year) ) . '</th></thead>';
	/* table headings */
	$headings = array('MO','DI','MI','DO','FR','SA','SO','Kw');
	$calendar.= '<tr><td style="padding:2px;text-align:center">'.implode('</td><td style="padding:2px;text-align:center">',$headings).'</td></tr>';
	/* row for week one */
	$calendar.= '<tr>';
	/* print "blank" days until the first of the current week */
	for($x = 1; $x < $running_day; $x++):
		$calendar.= '<td style="padding:2px;background:rgba(222,222,222,0.1);"></td>';
		$days_in_this_week++;
	endfor;
	/* keep going with days.... */
	for($list_day = 1; $list_day <= $days_in_month; $list_day++):
		/* add in the day number */
		$running_week = date('W',mktime(0,0,0,$month,$list_day,$year));
		/** QUERY THE DATABASE FOR AN ENTRY FOR THIS DAY !!  IF MATCHES FOUND, PRINT THEM !! **/
		$stylez= '<td style="padding:2px">';
		foreach ($eventarray as $calevent) {
			if ( date('Ymd',mktime(0,0,0,substr($calevent,3,2),substr($calevent,0,2),substr($calevent,6,4))) == date('Ymd',mktime(0,0,0,$month,$list_day,$year)) ) {
				$stylez= '<td style="padding:2px;background:#ffd800;font-weight:700">';
			}
		}	
		$calendar.= $stylez . $list_day . '</td>';
		if($running_day == 7):
			$calendar.= '<td style="padding:2px;background:rgba(222,222,222,0.1);"	>'.$running_week.'</td></tr>';
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
			$calendar.= '<td style="padding:2px"></td>';
		endfor;
		$calendar.= '<td style="padding:2px;text-align:center">'.$running_week.'</td></tr>';
	endif;
	/* end the table */
	$calendar.= '</table>';
	/* all done, return result */
	return $calendar;
}


// Doodlez Inhalte anzeigen
function get_doodlez_content() {
	global $wp;
	/* translators: %s: Name of current post */
	the_content();
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
				echo '<span style="float:right"><a href="'.add_query_arg( array('admin'=>'1' ), home_url( $wp->request ) ).'">' . __( 'poll details', 'WPdoodlez'  ) . '</a></span>';	
			} else {
				echo '<span style="float:right"><a href="'.home_url( $wp->request ) .'">' . __( 'poll results', 'WPdoodlez'  ) . '</a></span>';	
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
				if ($key != "post_views_count" && $key != "likes" ) {
					$xsum += $value;
					$pielabel.=$suggestions[$key][0].','; $piesum .= $value.','; 
				}
			}
			$hashuser = substr(md5(time()),1,20) . '-' . wd_get_the_user_ip();
			echo '<br><table id="pollselect"><thead><th colspan=3>' . __( 'your choice', 'WPdoodlez'  ) . '</th></thead>';	
			$xperc = 0;
			$votecounter = 0;
			foreach ( $suggestions as $key => $value ) {
				 if ($key != "post_views_count" && $key != "likes" ) {
						$votecounter += 1;
						if ($xsum>0) $xperc = sprintf("%.1f%%", ($votes_cout[ $key ]/$xsum) * 100);
						echo'<tr><td><label><input type="checkbox" name="'.$key.'" id="'.$key.'" onclick="selectOnlyThis(this.id)" class="wpdoodlez-input"></td><td>';
						echo $value[ 0 ] .'</label></td><td>'.$votes_cout[ $key ].' ('.$xperc.')</td></tr>';
				 }	
			 }
			echo '<tr><td>' . __( 'total votes', 'WPdoodlez' ) . '</td><td></td><td style="font-size:1.1em">'.$xsum.'</td></tr>';
			echo '<tr><td colspan=3><input type="hidden" id="wpdoodlez-name" value="'.$hashuser.'">';
			echo '<button id="wpdoodlez_poll">' . __( 'Vote!', 'WPdoodlez' ) . '</button></td></tr>';
			echo '</table>';
			// only one selection allowed
			?>
			<script>
				function selectOnlyThis(id) {
					for (var i = 1;i <= <?php echo $votecounter ?>; i++)
					{
						document.getElementById('vote'+i).checked = false;
					}
					document.getElementById(id).checked = true;
				}
			</script>	
			<?php
		} else {
			// Dies nur ausführen, wenn Feldnamen nicht vote1...20 oder Admin Details Modus
			?>
			<h4><?php echo __( 'Voting', 'WPdoodlez' ); ?></h4>
			<?php
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
							echo '<div style="font-size:0.9em;overflow:hidden;vertical-align:top;display:inline-block;max-width:32%;width:32%;margin-right:5px">'.mini_calendar(substr($key,3,2),substr($key,6,4),$xevents).'</div>';
							array_push($outputed_values, $workername);
						}
					}	
				}
			}	
			?>
			<table>
				<thead>
					<tr>
						<th><?php echo __( 'User name', 'WPdoodlez'  ); ?></th>
						<?php
							foreach ( $suggestions as $key => $value ) {
								if ($key != "post_views_count" && $key != "likes" ) {
									?><th style="overflow-wrap:anywhere"><?php	echo $key; ?></th><?php
								}	
							}
							?>
						<th><?php echo __( 'Manage vote', 'WPdoodlez'  ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					if (!empty($_COOKIE[ 'wpdoodlez-' . md5( AUTH_KEY . get_the_ID() ) ] )) {
						$myname = $_COOKIE[ 'wpdoodlez-' . md5( AUTH_KEY . get_the_ID() ) ];
					}
					?>
					<tr id="wpdoodlez-form">
						<td><input type="text" 
								   placeholder="<?php echo __( 'Your name', 'WPdoodlez'  ) ?>" 
								   class="wpdoodlez-input"
								   id="wpdoodlez-name" size="10"></td>
							<?php
							$votecounter = 0;
							foreach ( $suggestions as $key => $value ) {
								if ($key != "post_views_count" && $key != "likes"  ) {
									$votecounter += 1;
									?><td><label> <input type="checkbox" name="<?php echo $key; ?>" id="<?php echo 'doodsel'.$votecounter; ?>" class="wpdoodlez-input">
									<?php echo $value[ 0 ]; ?></label></td><?php
								}
						}
						?><td>
							<button id="wpdoodlez_vote"><?php echo __( 'Vote!', 'WPdoodlez'  ); ?>
							</button></td>
					</tr>
					<?php
					$votes = get_option( 'wpdoodlez_' . md5( AUTH_KEY . get_the_ID() ), array() );
					// Page navigation
					if ( $polli ) { $nb_elem_per_page = 20; } else { $nb_elem_per_page = 100; }
					$number_of_pages = intval(count($votes)/$nb_elem_per_page)+1;
					$page = isset($_GET['seite'])?intval($_GET['seite']):0;
					//					foreach ( $votes as $name => $vote ) {
					foreach (array_slice($votes, $page*$nb_elem_per_page, $nb_elem_per_page) as $name => $vote) { 
						?><tr id="<?php echo 'wpdoodlez_' . md5( AUTH_KEY . get_the_ID() ) . '-' . md5( $name ); ?>" 
								class="<?php echo $myname == $name ? 'myvote' : '';  ?>">
								<?php
								echo '<td>' . substr($name,0,20);
								// Wenn ipflag plugin aktiv und user angemeldet
								if( class_exists( 'ipflag' ) && is_user_logged_in() ) {
									global $ipflag;
									$nameip = substr($name,21,strlen($name)-21);
									if(isset($ipflag) && is_object($ipflag)){
										if(($info = $ipflag->get_info($nameip)) != false){
											echo ' '.$info->code .  ' ' .$info->name. ' ' . $ipflag->get_flag($info, '') ;
										} else { echo ' '. $ipflag->get_flag($info, '') . ' '; }
									} 
								}	
							echo '</td>';
							foreach ( $suggestions as $key => $value ) {
								if ($key != "post_views_count" && $key != "likes") {
									?><td>
										<?php
										if ( !empty($vote[ $key ]) ) {
											$votes_cout[ $key ] ++;
											?>
										<label 
											data-key="<?php echo $key; ?>"
											><?php echo $value[ 0 ]; ?></label><?php
										} else {
											?>
										<label></label><?php }
										?>
								</td><?php
								}
							}	
							?>
						<td><?php
						if ( current_user_can( 'delete_published_posts' ) ) {
								?>
								<button class="wpdoodlez-delete" 
										data-vote="<?php echo md5( $name ); ?>" 
										data-realname="<?php echo $name; ?>"
										><?php echo __( 'delete', 'WPdoodlez' ); ?></button><?php
									}
									if ( !empty($myname) && $myname == $name ) {
										?>
								<button class="wpdoodlez-edit" 
										data-vote="<?php echo md5( $name ); ?>" 
										data-realname="<?php echo $name; ?>"
										><?php echo __( 'edit', 'WPdoodlez' ); ?></button><?php
							}
							?></td>
						</tr><?php
					}
					?>
				</tbody>
			<tfoot>
				<tr>
					<th><?php echo __( 'total votes', 'WPdoodlez'  ); ?></th>
					<?php
						$pielabel = ''; $piesum = '';
						foreach ( $votes_cout as $key => $value ) {
							if ($key != "post_views_count" && $key != "likes" ) {
								?><th id="total-<?php echo $key; ?>"><?php echo $value;  $pielabel .=  strtoupper($key) . ','; $piesum .= $value . ','; ?></th><?php
							} }
						?>
					<td><?php echo '<b>Zeilen: ' . ($nb_elem_per_page*($page) +1 )  . ' - '.($nb_elem_per_page*($page+1) ) .'</b>';  ?></td>
				</tr>
			</tfoot>
			<?php   
			}     //    Ende Terminabstimmung oder Umfrage, nun Fusszeile
			?>
				
		</table>
				<?php
				if ( isset($_GET['admin']) || !$polli) {
					// Page navigation		
					$html='';
					for($i=0;$i<$number_of_pages;$i++){
						$seitennummer = $i+1;
						$html .= ' &nbsp;<a class="page-numbers" href="'.add_query_arg( array('admin'=>'1', 'seite'=>$i), home_url( $wp->request ) ).'">'.$seitennummer.'</a>';
					}	
					echo $html;
				}	
				?>
		<?php
		// Chart Pie anzeigen zu den Ergebnissen
		$piesum = rtrim($piesum, ",");
		$pielabel = rtrim($pielabel, ",");
		if( class_exists( 'PB_ChartsCodes' ) && !empty($pielabel) ) {
			echo do_shortcode('[chartscodes accentcolor="1" title="' . __( 'votes pie chart', 'WPdoodlez' ) . '" values="'.$piesum.'" labels="'.$pielabel.'" absolute="1"]');
		}	
	}
	/* END password protected? */
}     // end of get doodlez content	

// ------------------- quizzz code and shortcode ---------------------------------------------------------------


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
		'description'         => 'description',
		'taxonomies'          => array( 'category', 'post_tag' ),
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
		'supports'            => array(	'title', 'editor', 'thumbnail',	)
	);
	register_post_type( 'Question', $args );

	// CSV Import starten, wenn Dateiname im upload dir public_histereignisse.csv ist	
	if( isset($_REQUEST['quizzzcsv']) && ( $_REQUEST['quizzzcsv'] == true ) && isset( $_REQUEST['nonce'] ) ) {
		$nonce  = filter_input( INPUT_GET, 'nonce', FILTER_SANITIZE_STRING );
		if ( ! wp_verify_nonce( $nonce, 'dnonce' ) ) wp_die('Invalid nonce..!!');
		importposts();
		echo '<script>window.location.href="'.get_home_url().'/wp-admin/edit.php?post_type=question"</script>';
	}
}
add_action( 'init', 'create_quiz_post' );

// Shortcode Random Question

function random_quote_func( $atts ){
	$attrs = shortcode_atts( 
		array(
			'orderby'   => 'rand',
			'order'   => 'rand',
			'items'   => 1,
		), 
		$atts
	); 
    $args=array(
      'orderby'=> $attrs['orderby'],
      'order'=> $attrs['order'],
      'post_type' => 'question',
      'post_status' => 'publish',
      'posts_per_page' => $attrs['items'],
    );
    $my_query = null;
    $my_query = new WP_Query($args);
    $message = '';
    if( $my_query->have_posts() ) {
      while ($my_query->have_posts()) : $my_query->the_post(); 
        $message .= '<blockquote><a href="'.get_post_permalink().'">'.get_the_title().'</a> '.get_the_content().'</blockquote>';
      endwhile;
    }
    wp_reset_query();  
    return $message;
 
}
add_shortcode( 'random-question', 'random_quote_func' );


function importposts() {
	//Base upload path of uploads
	$upload_dir = wp_upload_dir();
	$upload_basedir = $upload_dir['basedir'] . '/public_histereignisse.csv';
	$row = 1;
	if (($handle = fopen($upload_basedir , "r")) !== FALSE) {
		while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
			if ($row > 1) {
				// id;	datum;	charakter;	land;	titel;	seitjahr;	bemerkungen
				$num = count($data);
				$edat = explode('.',$data[1]);
				$mydatum = $edat[2].'-'.$edat[1].'-'.$edat[0];
				$post_id = wp_insert_post(array (
				   'post_type' => 'Question',
				   'post_title' => $data[2].' '.$data[0],
				   'post_content' => $data[4],
				   'post_status' => 'publish',
				   'comment_status' => 'closed',
				   'ping_status' => 'closed', 
				));
				if ($post_id) {
				   // insert post meta
				  add_post_meta( $post_id, 'quizz_answer', esc_html($data[6]) );
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

function quiz_show_form( $content ) {
	setlocale (LC_ALL, 'de_DE.utf8', 'de_DE@euro', 'de_DE', 'de', 'ge'); 
	if (get_post_type()=='question'):
		global $answer;
		if (isset($_POST['answer'])) $answer = $_POST['answer'];	// user submitted answer

		// get meta values for this question
		$answers = get_post_custom_values('quizz_answer');
		$nextlevel = get_post_custom_values('quizz_nextlevel');
		$exact = get_post_custom_values('quizz_exact');
		$last_bool = get_post_custom_values('quizz_last');
		$lastpage = get_post_custom_values('quizz_lastpage');
		$rightstat = get_post_custom_values('quizz_rightstat');
		$wrongstat = get_post_custom_values('quizz_wrongstat');
		$error = "<p class='quiz_error quiz_message'>ERROR</p>";
		$lsubmittedanswer = strtolower($answer);
		$lactualanswer = strtolower($answers[0]);

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
		if ( $correct == "yes" ) {
			update_post_meta( get_the_ID(), 'quizz_rightstat', $rightstat[0] + 1 );
			if ($last_bool[0] != "last") {
				if ( !empty($nextlevel[0]) ) {
					// raise a hook for updating record
					do_action( 'quizz_level_updated', $nextlevel[0] );
					$goto = $nextlevel[0];
					wp_safe_redirect( get_post_permalink($goto) );
				} else { $error = "Richtige Antwort";$showqform = 'display:none'; }
			} else {
				// raise a hook for end of quiz
				do_action( 'quizz_ended', $lastpage[0] );
				$goto = $lastpage[0];
				wp_safe_redirect( get_home_url().'/question?ende=1' );
			}
		} else {
			if ($_SERVER['REQUEST_METHOD'] == 'POST') {
				$error = str_replace("ERROR", __('wrong answer. correct answer is','WPdoodlez').'<div style="font-size:1.2em">'.esc_html($answers[0]).'</span>', $error);
				$showqform = 'display:none';
				update_post_meta( get_the_ID(), 'quizz_wrongstat', $wrongstat[0] + 1 );
			} else { $error = "";$showqform = ''; }
		}
		$letztefrage='<br><div style="text-align:center"><ul style="list-style:none;display:inline;text-transform:uppercase;"><li style="margin-right:10px;display:inline"><a href="'.get_home_url().'/question?orderby=rand&order=rand">'. __('all questions overview','WPdoodlez').'</a></li><li style="padding:6px;display:inline">';
		if (isset($nextlevel) || isset($last_bool[0]) ) if ($last_bool[0] == "last") { $letztefrage .= '(letzte Frage)'; } else { $letztefrage .= '<a href="'.get_post_permalink($nextlevel[0]).'">(nächste Frage :'.$nextlevel[0].')</a>'; }
		if (!empty($rightstat) || !empty($wrongstat)) $letztefrage .= '</li><li style="padding:6px;display:inline">R:'. @$rightstat[0].' / F:'. @$wrongstat[0];
		$letztefrage .= '</li></ul></div>';

		if (isset($_GET['ende'])) { $ende = sanitize_text_field($_GET['ende']); } else { $ende = 0; }
		if (!$ende) {
			$antwortmaske = '<div style="font-family:monospace">' . __('answer mask','WPdoodlez'). ' [ '.preg_replace( '/[^( |aeiouAEIOU.)$]/', 'X', esc_html($answers[0])).' ]</a> ' . strlen(esc_html($answers[0])).__(' characters long. ','WPdoodlez');
			if ($exact[0]!="exact") { $antwortmaske .= __('not case sensitive','WPdoodlez'); } else { $antwortmaske .= __('case sensitive','WPdoodlez'); }
			$antwortmaske .='</div>';
			$theForm = $content . '<blockquote>'.$error . '<form id="quizform" action="" method="POST" class="quiz_form form" style="'.$showqform.'"><p>'. $antwortmaske.'</p><input type="text" name="answer" id="answer" placeholder="Ihre Antwort" class="quiz_answer answers" /><input type="submit" value="'.__('check answer','WPdoodlez').'" class="quiz_button"></form></blockquote>'. $letztefrage;
		} else {
			$theForm = '<div style="text-align:center;padding-top:20px;font-size:1.5em">'. __('all questions answered. test terminated. thanks.','WPdoodlez').'</div>'.$letztefrage;
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

  /*
   * Use get_post_meta() to retrieve an existing value
   * from the database and use the value for the form.
   */
  $value = get_post_meta( $post->ID, 'quizz_answer', true );

  echo '<label for="quizz_answer">';
       _e( "Answer", 'WPdoodlez' );
  echo '</label> ';
  echo ' <input type="text" id="quizz_answer" name="quizz_answer" value="' . esc_attr( $value ) . '" size="75" />';

  $value1 = get_post_meta( $post->ID, 'quizz_exact', true);
  echo ' <input type="checkbox" name="quizz_exact" id="quizz_exact" value="exact" ' . (($value1=="exact") ? " checked" : "") . '>'. __('exact match (also enforces case)','WPdoodlez');
  echo '<br />';

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

  /*
   * We need to verify this came from the our screen and with proper authorization,
   * because save_post can be triggered at other times.
   */

  // Check if our nonce is set.
  if ( ! isset( $_POST['quizz_inner_custom_box_nonce'] ) )
    return $post_id;

  $nonce = $_POST['quizz_inner_custom_box_nonce'];

  // Verify that the nonce is valid.
  if ( ! wp_verify_nonce( $nonce, 'quizz_inner_custom_box' ) )
      return $post_id;

  // If this is an autosave, our form has not been submitted, so we don't want to do anything.
  if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
      return $post_id;

  // Check the user's permissions.
  if ( 'page' == $_POST['post_type'] ) {

    if ( ! current_user_can( 'edit_page', $post_id ) )
        return $post_id;
  
  } else {

    if ( ! current_user_can( 'edit_post', $post_id ) )
        return $post_id;
  }

  /* OK, its safe for us to save the data now. */
  // Sanitize user input.
  $myanswer = sanitize_text_field( $_POST['quizz_answer'] );
  $fromlevel = $_POST['quizz_prevlevel'];
  $exact = $_POST['quizz_exact'];
  $lastlevel_bool = $_POST['quizz_last'];
  $lastpage = $_POST['quizz_lastpage'];

  // Update the meta field in the database.
  update_post_meta( $post_id, 'quizz_answer', $myanswer );
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
    $frageantwort = $columns['frageantwort'];  // save the tags column
    unset($columns['frageantwort']);   // remove it from the columns list

    foreach($columns as $key=>$value) {
        if($key=='categories') {  // when we find the date column
           $new['frageantwort'] = $frageantwort;  // put the tags column before it
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
            echo get_the_excerpt( $post_id ).'<br>';
			echo '<b>'.get_post_meta( $post_id , 'quizz_answer' , true ).'</b>'; 
            break;

    }
}
//   ----------------------------- Quizzz module ended -------------------------------------
?>
