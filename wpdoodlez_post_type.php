<?php

$labels = [
    'name'               => 'WPdoodlez',
    'singular_name'      => 'WPdoodle',
    'menu_name'          => 'Doodle',
    'parent_item_colon'  => '',
    'all_items'          => wpd_translate( 'All Doodles' ),
    'view_item'          => wpd_translate( 'Show Doodle' ),
    'add_new_item'       => wpd_translate( 'New Doodle' ),
    'add_new'            => wpd_translate( 'Add Doodle ' ),
    'edit_item'          => wpd_translate( 'Edit Doodle' ),
    'update_item'        => wpd_translate( 'Update Doodle' ),
    'search_items'       => wpd_translate( 'Search Doodles' ),
    'not_found'          => wpd_translate( 'Not found' ),
    'not_found_in_trash' => wpd_translate( 'Not found in trash' ),
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