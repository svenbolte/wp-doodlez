<?php
/**
Plugin Name: WP Doodlez
Plugin URI: https://github.com/svenbolte/WPdoodlez
Description: Doodle like finding meeting date 
Contributors: Robert Kolatzek, PBMod
Author URI: https://github.com/svenbolte
License: GPL 2
Author: PBMod
Version: 9.1.0.10.29
Stable tag: 9.1.0.10.29
Requires at least: 5.1
Tested up to: 5.5.1
Requires PHP: 7.2
*/

/**
 * Translate string @param string $text  @return string
 */
function wpd_translate( $text ) {
    return __( $text, 'WPdoodlez' );
}
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
                'msg' => wpd_translate( 'You have already voted but your vote was deleted. Your name was: ' ).$nameInCookie ) 
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
	echo '<h1>WPDoodlez Doku</h1>';
	?>
	If custom fields are named vote1...vote10, a poll is created, just displaying the vote summaries<br><br>
	if custom fields are dates e.g  name: 12.12.2020    value: ja<br>
	then a doodlez is created where visitors can set their name or shortcut and vote for all given event dates<br>
	<br>
	User parameter /admin=1 to display alternate votes display (more features when logged in as admin)<br><br>
	<h2>	Highlights</h2>
	* A link to WPdoodle is public but not published everywhere<br>
	* A WPdoodle can be in a review and be published at given time<br>
	* A WPdoodle can have own URL <br>
	* Poll users must not be valid logged in wordpress users<br>
	* Users with "delete published post" rights can delete votes<br>
	* Users name will be stored in a cookie for 30 days (user can change only his own vote, but on the same computer)<br>
	* Every custom field set in a WPdoodle is a possible answer<br>
	* The first value of the custom field will be displayed in the row as users answer<br>
	* The last row in the table contains total votes count<br>
	<?php
}


// Doodlez Inhalte anzeigen
function get_doodlez_content() {
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
		global $wp;
		if (!isset($_GET['admin']) ) {
			echo '<span style="float:right"><a href="'.add_query_arg( array('admin'=>'1' ), home_url( $wp->request ) ).'">' . wpd_translate( 'poll details' ) . '</a></span>';	
		} else {
			echo '<span style="float:right"><a href="'.home_url( $wp->request ) .'">' . wpd_translate( 'poll results' ) . '</a></span>';	
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
			$pielabel = ''; $piesum = '';
			foreach ( $votes_cout as $key => $value ) {
				if ($key != "post_views_count" && $key != "likes" ) {
					$pielabel.=$key.','; $piesum .= $value.','; 
				}
			}
			$hashuser = substr(md5(time()),1,20) . '-' . wd_get_the_user_ip();
			echo '<br><table id="pollselect"><thead><th colspan=3>' . wpd_translate( 'your choice' ) . '</th></thead>';	
			foreach ( $suggestions as $key => $value ) {
				 if ($key != "post_views_count" && $key != "likes" ) {
						echo'<tr><td><label><input type="checkbox" name="'.$key.'" class="wpdoodlez-input"></td><td>';
						echo $value[ 0 ] .'</label></td><td>'.$votes_cout[ $key ].'</td></tr>';
				 }	
			 }
			echo '<tr><td colspan=3><input type="hidden" id="wpdoodlez-name" value="'.$hashuser.'">';
			echo '<button id="wpdoodlez_poll">' . wpd_translate( 'Vote!' ) . '</button></td></tr>';
			echo '</table>';
		} else {
		// Dies nur ausführen, wenn Feldnamen nicht vote1...20
			?>
			<h4><?php echo wpd_translate( 'Voting' ); ?></h4>
			<table>
				<thead>
					<tr>
						<th><?php echo wpd_translate( 'User name' ); ?></th>
						<?php
							foreach ( $suggestions as $key => $value ) {
								if ($key != "post_views_count" && $key != "likes" ) {
									?><th><?php echo $key; ?></th><?php
								}	
							}
							?>
						<th><?php echo wpd_translate( 'Manage vote' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$myname = $_COOKIE[ 'wpdoodlez-' . md5( AUTH_KEY . get_the_ID() ) ];
					?>
					<tr id="wpdoodlez-form">
						<td><input type="text" 
								   placeholder="<?php echo wpd_translate( 'Your name' ) ?>" 
								   class="wpdoodlez-input"
								   id="wpdoodlez-name" size="10"></td>
							<?php
							foreach ( $suggestions as $key => $value ) {
								if ($key != "post_views_count" && $key != "likes"  ) {
									?><td><label> <input type="checkbox" name="<?php echo $key; ?>" class="wpdoodlez-input">
							<?php
									echo $value[ 0 ]; ?></label>
							</td><?php
								}
						}
						?><td>
							<button id="wpdoodlez_vote"><?php echo wpd_translate( 'Vote!' ); ?>
							</button></td>
					</tr>
					<?php
					$votes = get_option( 'wpdoodlez_' . md5( AUTH_KEY . get_the_ID() ), array() );
					foreach ( $votes as $name => $vote ) {
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
										><?php echo wpd_translate( 'delete' ); ?></button><?php
									}
									if ( $myname == $name ) {
										?>
								<button class="wpdoodlez-edit" 
										data-vote="<?php echo md5( $name ); ?>" 
										data-realname="<?php echo $name; ?>"
										><?php echo wpd_translate( 'edit' ); ?></button><?php
							}
							?></td>
						</tr><?php
					}
					?>
				</tbody>
			<tfoot>
				<tr>
					<th><?php echo wpd_translate( 'total votes' ); ?></th>
					<?php
						$pielabel = ''; $piesum = '';
						foreach ( $votes_cout as $key => $value ) {
							if ($key != "post_views_count" && $key != "likes" ) {
								?><th id="total-<?php echo $key; ?>"><?php echo $value;  $pielabel.=$key.','; $piesum .= $value.','; ?></th><?php
							} }
						?>
					<td></td>
				</tr>
			</tfoot>
			<?php   
			}     //    Ende Terminabstimmung oder Umfrage, nun Fusszeile
			?>
				
		</table>
		<?php
		// Chart Pie anzeigen zu den Ergebnissen
		$piesum = rtrim($piesum, ",");
		$pielabel = rtrim($pielabel, ",");
		if( class_exists( 'PB_ChartsCodes' ) && !empty($pielabel) ) {
			echo do_shortcode('[chartscodes accentcolor="1" title="' . wpd_translate( 'votes pie chart' ) . '" values="'.$piesum.'" labels="'.$pielabel.'" absolute="1"]');
		}	
	}
	/* END password protected? */
}     // end of get doodlez content	
?>
