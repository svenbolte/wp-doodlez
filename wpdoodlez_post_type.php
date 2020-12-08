<?php

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