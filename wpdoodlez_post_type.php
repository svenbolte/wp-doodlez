<?php

$labels = [
    'name'               => 'WPdoodlez',
    'singular_name'      => 'WPdoodle',
    'menu_name'          => 'Doodle',
    'parent_item_colon'  => '',
    'all_items'          => __wpd( 'Alle Doodles' ),
    'view_item'          => __wpd( 'Doodle ansehen' ),
    'add_new_item'       => __wpd( 'Neuer Doodle' ),
    'add_new'            => __wpd( 'Doodle Hinzufügen' ),
    'edit_item'          => __wpd( 'Doodle bearbeiten' ),
    'update_item'        => __wpd( 'Update Doodle' ),
    'search_items'       => __wpd( 'Doodles suchen' ),
    'not_found'          => __wpd( 'Nicht gefunden' ),
    'not_found_in_trash' => __wpd( 'Nicht gefunden im Papierkorb' ),
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