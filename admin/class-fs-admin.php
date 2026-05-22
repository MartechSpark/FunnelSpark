<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FS_Admin {

    public function init() {
        add_action( 'admin_menu',            [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_init',            [ $this, 'handle_oauth_callback' ] );
        add_action( 'admin_head',            [ $this, 'hide_editor_submenu' ] );
    }

    public function register_menu() {
        $icon = 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#FF6E4E" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h7v7H3z"/><path d="M14 3h7v7h-7z"/><path d="M14 14h7v7h-7z"/><path d="M3 14h7v7H3z"/><path d="M10 6.5h4"/><path d="M17.5 10v4"/><path d="M10 17.5h4"/><path d="M6.5 10v4"/></svg>'
        );

        add_menu_page(
            'FunnelSpark',
            'FunnelSpark',
            'edit_posts',
            'funnelspark',
            [ $this, 'render_dashboard' ],
            $icon,
            4
        );

        add_submenu_page( 'funnelspark', 'My Funnels',  'My Funnels',  'edit_posts',      'funnelspark',          [ $this, 'render_dashboard' ] );
        add_submenu_page( 'funnelspark', 'New Funnel',  'New Funnel',  'edit_posts',      'funnelspark-new',      [ $this, 'render_editor' ] );
        add_submenu_page( 'funnelspark', 'Edit Funnel', 'Edit Funnel', 'edit_posts',      'funnelspark-editor',   [ $this, 'render_editor' ] );
        add_submenu_page( 'funnelspark', 'Settings',    'Settings',    'manage_options',  'funnelspark-settings', [ $this, 'render_settings' ] );

        // funnelspark-editor stays registered (removing it breaks WP capability checks)
        // It is hidden from nav via CSS in hide_editor_submenu().
    }

    public function enqueue_assets( $hook ) {
        $fs_pages = [ 'toplevel_page_funnelspark', 'funnelspark_page_funnelspark-new', 'funnelspark_page_funnelspark-editor', 'funnelspark_page_funnelspark-settings' ];
        if ( ! in_array( $hook, $fs_pages, true ) ) return;

        wp_enqueue_style( 'fs-fonts', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Lato:wght@400;500&display=swap', [], null );
        wp_enqueue_style( 'fs-admin', FS_PLUGIN_URL . 'assets/css/admin.css', [], FS_VERSION );

        $is_editor = in_array( $hook, [ 'funnelspark_page_funnelspark-new', 'funnelspark_page_funnelspark-editor' ], true );

        wp_enqueue_script( 'fs-admin', FS_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery' ], FS_VERSION, true );

        if ( $is_editor ) {
            wp_enqueue_style( 'fs-canvas', FS_PLUGIN_URL . 'assets/css/canvas.css', [ 'fs-admin' ], FS_VERSION );
            // fs-canvas depends on fs-admin so window.FS is guaranteed to exist when canvas.js runs
            wp_enqueue_script( 'fs-canvas',      FS_PLUGIN_URL . 'assets/js/canvas.js',      [ 'fs-admin' ],   FS_VERSION, true );
            wp_enqueue_script( 'fs-ga4-overlay', FS_PLUGIN_URL . 'assets/js/ga4-overlay.js', [ 'fs-canvas' ],  FS_VERSION, true );
        }

        $funnel_id    = (int) ( $_GET['funnel_id'] ?? 0 );
        $canvas_raw   = $funnel_id ? get_post_meta( $funnel_id, '_fs_canvas', true ) : '';
        $funnel_title = $funnel_id ? get_the_title( $funnel_id ) : '';

        $wp_pages = array_map( function( $p ) {
            return [
                'title' => $p->post_title,
                'url'   => wp_make_link_relative( get_permalink( $p->ID ) ),
            ];
        }, get_pages( [ 'post_status' => 'publish', 'sort_column' => 'post_title' ] ) ?: [] );

        wp_localize_script( 'fs-admin', 'FS', [
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'fs_nonce' ),
            'funnel_id'       => $funnel_id,
            'funnel_title'    => esc_js( $funnel_title ),
            'canvas_data'     => $canvas_raw ?: '{}',
            'ga4_configured'  => FS_Settings::is_ga4_configured(),
            'promo_dismissed' => (bool) get_user_meta( get_current_user_id(), 'fs_promo_dismissed', true ),
            'editor_url'      => admin_url( 'admin.php?page=funnelspark-editor' ),
            'dashboard_url'   => admin_url( 'admin.php?page=funnelspark' ),
            'settings_url'    => admin_url( 'admin.php?page=funnelspark-settings' ),
            'pages'           => $wp_pages,
        ]);
    }

    // ── OAuth Callback ────────────────────────────────────────────────

    public function handle_oauth_callback() {
        if ( ( $_GET['page'] ?? '' ) !== 'funnelspark-settings' ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        $settings_url = admin_url( 'admin.php?page=funnelspark-settings' );

        if ( isset( $_GET['error'] ) ) {
            $error = sanitize_text_field( $_GET['error'] );
            wp_redirect( add_query_arg( 'fs_ga4_error', urlencode( $error ), $settings_url ) );
            exit;
        }

        if ( ! isset( $_GET['code'], $_GET['state'] ) ) return;

        $state        = sanitize_text_field( $_GET['state'] );
        $stored_state = get_transient( 'fs_oauth_state' );

        if ( ! $stored_state || ! hash_equals( $stored_state, $state ) ) {
            wp_redirect( add_query_arg( 'fs_ga4_error', urlencode( 'State mismatch — please try again.' ), $settings_url ) );
            exit;
        }

        delete_transient( 'fs_oauth_state' );

        $refresh_token = FS_GA4_Client::exchange_code( sanitize_text_field( $_GET['code'] ) );

        if ( is_wp_error( $refresh_token ) ) {
            wp_redirect( add_query_arg( 'fs_ga4_error', urlencode( $refresh_token->get_error_message() ), $settings_url ) );
            exit;
        }

        FS_Settings::set( [ 'ga4_refresh_token' => $refresh_token ] );
        delete_transient( 'fs_ga4_token' );

        wp_redirect( add_query_arg( 'fs_ga4_status', 'connected', $settings_url ) );
        exit;
    }

    // ── Hide Editor Submenu Link ──────────────────────────────────────
    public function hide_editor_submenu() {
        echo '<style>#adminmenu a[href="admin.php?page=funnelspark-editor"]{display:none!important}</style>';
    }

    // ── Page Renderers ────────────────────────────────────────────────
    public function render_dashboard() {
        include FS_PLUGIN_DIR . 'templates/dashboard.php';
    }

    public function render_editor() {
        include FS_PLUGIN_DIR . 'templates/editor.php';
    }

    public function render_settings() {
        include FS_PLUGIN_DIR . 'templates/settings.php';
    }
}
