<?php
/**
 * Plugin Name: WP Doodlez
 * Plugin URI: https://github.com/svenbolte/WPdoodlez
 * Description: Doodle like finding meeting date 
 * Contributors: Robert Kolatzek, PBMod
 * Author: PBMod
 * Version: 9.1.0.10.25
 * Author URI: https://github.com/svenbolte
 * License: GPL 2
 * Tested up to: 5.5
 * Requires at least: 4.6
 * Requires PHP: 5.5
*/

/**
 * Translate string
 * @param string $text
 * @return string
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
    if ( $post->post_type == 'wpdoodle' ) {
        $single_template = dirname( __FILE__ ) . '/wpdoodle-template.php';
    }
    return $single_template;
}

add_filter( 'single_template', 'wpdoodlez_template' );

	// IP-Adresse des Users bekommen
	function get_the_user_ip() {
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
?>
