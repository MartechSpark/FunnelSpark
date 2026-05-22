<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FS_Post_Type {

    const POST_TYPE = 'fs_funnel';

    public function init() {
        add_action( 'init', [ $this, 'register' ] );
    }

    public function register() {
        register_post_type( self::POST_TYPE, [
            'labels' => [
                'name'               => __( 'Funnels', 'funnelspark' ),
                'singular_name'      => __( 'Funnel', 'funnelspark' ),
                'add_new'            => __( 'New Funnel', 'funnelspark' ),
                'add_new_item'       => __( 'Add New Funnel', 'funnelspark' ),
                'edit_item'          => __( 'Edit Funnel', 'funnelspark' ),
                'all_items'          => __( 'All Funnels', 'funnelspark' ),
                'search_items'       => __( 'Search Funnels', 'funnelspark' ),
                'not_found'          => __( 'No funnels found.', 'funnelspark' ),
            ],
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => false, // We handle menu ourselves
            'show_in_rest'       => false,
            'supports'           => [ 'title' ],
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
        ]);
    }
}
