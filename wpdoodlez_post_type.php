<?php

$labels = [
    'name'               => 'WPdoodlez',
    'singular_name'      => 'WPdoodle',
    'menu_name'          => 'WPdoodle',
    'parent_item_colon'  => '',
    'all_items'          => wpd_translate( 'All WPdoodlez' ),
    'view_item'          => wpd_translate( 'Show WPdoodle' ),
    'add_new_item'       => wpd_translate( 'New WPdoodle' ),
    'add_new'            => wpd_translate( 'Add WPdoodle ' ),
    'edit_item'          => wpd_translate( 'Edit WPdoodle' ),
    'update_item'        => wpd_translate( 'Update WPdoodle' ),
    'search_items'       => wpd_translate( 'Search WPdoodlez' ),
    'not_found'          => wpd_translate( 'Not found' ),
    'not_found_in_trash' => wpd_translate( 'Not found in trash' ),
];

$args = [
    'labels'              => $labels,
    'supports'            => [ 'title', 'editor', 'thumbnail', 'comments', 'custom-fields' ],
	// 'show_in_rest' => true,   // Gutenberg Anzeige des Editors, über ... / Ansicht dann Eigene Felder einschalten!
	'taxonomies'          => ['category' ],
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
    'exclude_from_search' => TRUE,
    'publicly_queryable'  => true,
    'rewrite'             => [
        'slug'       => 'wpdoodle',
        'with_front' => true,
        'pages'      => false,
        'feeds'      => false,
    ],
    'capability_type'     => 'page',
];

register_post_type( 'WPdoodle', $args );


function create_menupages_252428() {

// https://developer.wordpress.org/reference/functions/add_submenu_page/

add_submenu_page(
    'edit.php?post_type=wpdoodle', // Parent slug
    'Dokumentation', // Page title
    'Dokumentation', // Menu title
    'manage_options', // Capability
    '',  // Slug
    'wpdoodle_doku',
);
}
add_action('admin_menu', 'create_menupages_252428');

function wpdoodle_doku() {
	echo '<h1>WPDoodlez Doku</h1>';
?>
If custom fields are named vote1...vote10, a poll is created, just displaying the vote summaries<br><br>
if custom fields are dates e.g  name: 12.12.2020    value: ja<br>
then a doodlez is created where visitors can set their name or shortcut and vote for all given event dates<br>
<br><h2>	Highlights</h2>
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


?>