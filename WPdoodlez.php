<?php
/**
 * Plugin Name: WP doodlez
 * Plugin URI: https://github.com/Kolatzek/WPdoodlez
 * Description: Doodle like finding meeting date 
 * Author: Robert Kolatzek
 * Version: 1.0
 * Author URI: http://robert.kolatzek.org
 * License: GPL 2
 */

/**
 * Translate string
 * @param string $text
 * @return string
 */
function __wpd( $text ) {
    return __( $text, 'WPdoodlez' );
}

/**
 * Register WPdoodle post type and refresh rewrite rules
 */
function wpdoodlez_rewrite_flush() {
    wpdoodlez_init();
    flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'wpdoodlez_rewrite_flush' );

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

/**
 * Save a single vote as ajax request and set cookie with given user name
 */
function save_vote() {
    $values = get_option( 'wpdoodlez_' . $_POST[ 'data' ][ 'wpdoodle' ], array() );
    $name   = $_POST[ 'data' ][ 'name' ];
    /* insert only without cookie (or empty name in cookie)
     * update only with same name in cookie
     */
    if ( (isset( $values[ $name ] ) && $_COOKIE[ 'wpdoodlez-' . $_POST[ 'data' ][ 'wpdoodle' ] ] == $name) ||
    (!isset( $values[ $name ] ) && empty( $_COOKIE[ 'wpdoodlez-' . $_POST[ 'data' ][ 'wpdoodle' ] ] ))
    ) {
        $values[ $name ] = array();
        foreach ( $_POST[ 'data' ][ 'vote' ] as $option ) {
            $values[ $name ][ $option[ 'name' ] ] = $option[ 'value' ];
        }
    } else {
        echo json_encode( array( 'save' => false ) );
        wp_die();
    }
    update_option( 'wpdoodlez_' . $_POST[ 'data' ][ 'wpdoodle' ], $values );
    setcookie( 'wpdoodlez-' . $_POST[ 'data' ][ 'wpdoodle' ], $name, time() + (3600 * 24 * 30), COOKIEPATH, COOKIE_DOMAIN, is_ssl() );
    echo json_encode( array( 'save' => true ) );
    wp_die();
}

add_action( 'wp_ajax_wpdoodlez_save', 'save_vote' );
add_action( 'wp_ajax_nopriv_wpdoodlez_save', 'save_vote' );

/**
 * Delete a given vote identified by user name. Possible for all wp user with *delete_published_posts* right
 */
function delete_vote() {
    if ( !current_user_can( 'delete_published_posts' ) ) {
        echo json_encode( array( 'delete' => false ) );
        wp_die();
    }
    $values    = get_option( 'wpdoodlez_' . $_POST[ 'data' ][ 'wpdoodle' ], array() );
    $newvalues = [ ];
    foreach ( $values as $key => $value ) {
        if ( $key != $_POST[ 'data' ][ 'name' ] ) {
            $newvalues[ $key ] = $value;
        }
    }
    update_option( 'wpdoodlez_' . $_POST[ 'data' ][ 'wpdoodle' ], $newvalues );
    echo json_encode( array( 'delete' => true ) );
    wp_die();
}

add_action( 'wp_ajax_nopriv_wpdoodlez_delete', 'delete_vote' );
add_action( 'wp_ajax_wpdoodlez_delete', 'delete_vote' );

/**
 * Register WPdoodle post type
 * Set cookie with the name of user (used by voting)
 */
function wpdoodlez_cookie() {
    include('wpdoodlez_post_type.php');
    foreach ( $_COOKIE as $key => $value ) {
        if ( preg_match( '/wpdoodlez\-.+/i', $key ) ) {
            setcookie( $key, $value, time() + (3600 * 24 * 30), COOKIEPATH, COOKIE_DOMAIN, is_ssl() );
        }
    }
}

add_action( 'init', 'wpdoodlez_cookie' );
?>