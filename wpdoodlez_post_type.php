<?php

$labels = [
    'name'               => 'WPdoodlez',
    'singular_name'      => 'WPdoodle',
    'menu_name'          => 'Doodle',
    'parent_item_colon'  => '',
    'all_items'          => __wpd( 'All Doodles' ),
    'view_item'          => __wpd( 'Show Doodle' ),
    'add_new_item'       => __wpd( 'New Doodle' ),
    'add_new'            => __wpd( 'Add Doodle ' ),
    'edit_item'          => __wpd( 'Edit Doodle' ),
    'update_item'        => __wpd( 'Update Doodle' ),
    'search_items'       => __wpd( 'Search Doodles' ),
    'not_found'          => __wpd( 'Not found' ),
    'not_found_in_trash' => __wpd( 'Not found in trash' ),
];

$args = [
    'labels'              => $labels,
    'supports'            => [ 'title', 'editor', 'thumbnail', 'comments', 'custom-fields' ],
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
?>