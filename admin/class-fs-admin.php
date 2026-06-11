<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FunnelSpark_Admin {

    private $page_hooks   = [];
    private $editor_hooks = [];

    public function init() {
        add_action( 'admin_menu',            [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_init',            [ $this, 'handle_oauth_callback' ] );
    }

    public function register_menu() {
        $icon = 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#FF6E4E" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h7v7H3z"/><path d="M14 3h7v7h-7z"/><path d="M14 14h7v7h-7z"/><path d="M3 14h7v7H3z"/><path d="M10 6.5h4"/><path d="M17.5 10v4"/><path d="M10 17.5h4"/><path d="M6.5 10v4"/></svg>'
        );

        $this->page_hooks[] = add_menu_page(
            'Funnel Mapper',
            'Funnel Mapper',
            'edit_posts',
            'funnelspark',
            [ $this, 'render_dashboard' ],
            $icon,
            58
        );

        // Hook suffixes are derived from the menu title by WordPress, so we
        // capture the return values instead of hardcoding hook names.
        $this->page_hooks[]   = add_submenu_page( 'funnelspark', 'My Funnels',  'My Funnels',  'edit_posts',      'funnelspark',          [ $this, 'render_dashboard' ] );
        $this->editor_hooks[] = add_submenu_page( 'funnelspark', 'New Funnel',  'New Funnel',  'edit_posts',      'funnelspark-new',      [ $this, 'render_editor' ] );
        $this->editor_hooks[] = add_submenu_page( 'funnelspark', 'Edit Funnel', 'Edit Funnel', 'edit_posts',      'funnelspark-editor',   [ $this, 'render_editor' ] );
        $this->page_hooks[]   = add_submenu_page( 'funnelspark', 'Settings',    'Settings',    'manage_options',  'funnelspark-settings', [ $this, 'render_settings' ] );

        $this->editor_hooks = array_filter( $this->editor_hooks );
        $this->page_hooks   = array_filter( array_merge( $this->page_hooks, $this->editor_hooks ) );

        // funnelspark-editor stays registered (removing it breaks WP capability checks)
        // It is hidden from nav via inline CSS added in enqueue_assets().
    }

    public function enqueue_assets( $hook ) {
        wp_add_inline_style( 'common', '#adminmenu a[href="admin.php?page=funnelspark-editor"]{display:none!important}' );

        if ( ! in_array( $hook, $this->page_hooks, true ) ) return;

        wp_enqueue_style( 'funnelspark-fonts', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Lato:wght@400;500&display=swap', [], null );
        wp_enqueue_style( 'funnelspark-admin', FUNNELSPARK_PLUGIN_URL . 'assets/css/admin.css', [], FUNNELSPARK_VERSION );

        $is_editor = in_array( $hook, $this->editor_hooks, true );

        wp_enqueue_script( 'funnelspark-admin', FUNNELSPARK_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery' ], FUNNELSPARK_VERSION, true );

        if ( $is_editor ) {
            wp_enqueue_style( 'funnelspark-canvas', FUNNELSPARK_PLUGIN_URL . 'assets/css/canvas.css', [ 'funnelspark-admin' ], FUNNELSPARK_VERSION );
            // funnelspark-canvas depends on funnelspark-admin so window.FunnelSparkData is guaranteed to exist when canvas.js runs
            wp_enqueue_script( 'funnelspark-canvas',      FUNNELSPARK_PLUGIN_URL . 'assets/js/canvas.js',      [ 'funnelspark-admin' ],   FUNNELSPARK_VERSION, true );
            wp_enqueue_script( 'funnelspark-ga4-overlay', FUNNELSPARK_PLUGIN_URL . 'assets/js/ga4-overlay.js', [ 'funnelspark-canvas' ],  FUNNELSPARK_VERSION, true );
        }

        $funnel_id    = absint( $_GET['funnel_id'] ?? 0 );
        $canvas_raw   = $funnel_id ? get_post_meta( $funnel_id, '_funnelspark_canvas', true ) : '';
        $funnel_title = $funnel_id ? get_the_title( $funnel_id ) : '';

        $wp_pages = array_map( function( $p ) {
            return [
                'title' => $p->post_title,
                'url'   => wp_make_link_relative( get_permalink( $p->ID ) ),
            ];
        }, get_pages( [ 'post_status' => 'publish', 'sort_column' => 'post_title' ] ) ?: [] );

        wp_localize_script( 'funnelspark-admin', 'FunnelSparkData', [
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'funnelspark_nonce' ),
            'funnel_id'       => $funnel_id,
            'funnel_title'    => $funnel_title,
            'canvas_data'     => $canvas_raw ?: '{}',
            'ga4_configured'  => FunnelSpark_Settings::is_ga4_configured(),
            'promo_dismissed' => (bool) get_user_meta( get_current_user_id(), 'funnelspark_promo_dismissed', true ),
            'editor_url'      => admin_url( 'admin.php?page=funnelspark-editor' ),
            'dashboard_url'   => admin_url( 'admin.php?page=funnelspark' ),
            'settings_url'    => admin_url( 'admin.php?page=funnelspark-settings' ),
            'pages'           => $wp_pages,
        ]);
    }

    // ── OAuth Callback ────────────────────────────────────────────────

    public function handle_oauth_callback() {
        if ( sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ) !== 'funnelspark-settings' ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        $settings_url = admin_url( 'admin.php?page=funnelspark-settings' );

        if ( isset( $_GET['error'] ) ) {
            $error = sanitize_text_field( wp_unslash( $_GET['error'] ) );
            wp_redirect( add_query_arg( 'funnelspark_ga4_error', urlencode( $error ), $settings_url ) );
            exit;
        }

        if ( ! isset( $_GET['code'], $_GET['state'] ) ) return;

        $state        = sanitize_text_field( wp_unslash( $_GET['state'] ) );
        $stored_state = get_transient( 'funnelspark_oauth_state' );

        if ( ! $stored_state || ! hash_equals( $stored_state, $state ) ) {
            wp_redirect( add_query_arg( 'funnelspark_ga4_error', urlencode( 'State mismatch — please try again.' ), $settings_url ) );
            exit;
        }

        delete_transient( 'funnelspark_oauth_state' );

        $refresh_token = FunnelSpark_GA4_Client::exchange_code( sanitize_text_field( wp_unslash( $_GET['code'] ) ) );

        if ( is_wp_error( $refresh_token ) ) {
            wp_redirect( add_query_arg( 'funnelspark_ga4_error', urlencode( $refresh_token->get_error_message() ), $settings_url ) );
            exit;
        }

        FunnelSpark_Settings::set( [ 'ga4_refresh_token' => $refresh_token ] );
        delete_transient( 'funnelspark_ga4_token' );

        wp_redirect( add_query_arg( 'funnelspark_ga4_status', 'connected', $settings_url ) );
        exit;
    }

    // ── Page Renderers ────────────────────────────────────────────────
    public function render_dashboard() {
        include FUNNELSPARK_PLUGIN_DIR . 'templates/dashboard.php';
    }

    public function render_editor() {
        include FUNNELSPARK_PLUGIN_DIR . 'templates/editor.php';
    }

    public function render_settings() {
        include FUNNELSPARK_PLUGIN_DIR . 'templates/settings.php';
    }
}
