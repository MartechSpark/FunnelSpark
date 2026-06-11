<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FunnelSpark_Post_Type {

    const POST_TYPE = 'funnelspark_funnel';

    public function init() {
        add_action( 'init', [ $this, 'register' ] );
    }

    public function register() {
        register_post_type( self::POST_TYPE, [
            'labels' => [
                'name'               => __( 'Funnels', 'martech-spark-conversion-funnel-mapper' ),
                'singular_name'      => __( 'Funnel', 'martech-spark-conversion-funnel-mapper' ),
                'add_new'            => __( 'New Funnel', 'martech-spark-conversion-funnel-mapper' ),
                'add_new_item'       => __( 'Add New Funnel', 'martech-spark-conversion-funnel-mapper' ),
                'edit_item'          => __( 'Edit Funnel', 'martech-spark-conversion-funnel-mapper' ),
                'all_items'          => __( 'All Funnels', 'martech-spark-conversion-funnel-mapper' ),
                'search_items'       => __( 'Search Funnels', 'martech-spark-conversion-funnel-mapper' ),
                'not_found'          => __( 'No funnels found.', 'martech-spark-conversion-funnel-mapper' ),
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
