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
    'supports'            => [ 'title', 'editor', 'thumbnail', 'comments', 'custom-fields', 'post-formats' ],
	// 'show_in_rest' => true,   // Gutenberg Anzeige des Editors, über ... / Ansicht dann Eigene Felder einschalten!
	 "taxonomies"		  => array("post_tag", "category"),
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
?>