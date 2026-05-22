<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FS_Ajax {

    public function init() {
        add_action( 'wp_ajax_fs_save_funnel',       [ $this, 'save_funnel' ] );
        add_action( 'wp_ajax_fs_get_ga4_overlay',   [ $this, 'get_ga4_overlay' ] );
        add_action( 'wp_ajax_fs_dismiss_promo',     [ $this, 'dismiss_promo' ] );
        add_action( 'wp_ajax_fs_save_settings',     [ $this, 'save_settings' ] );
        add_action( 'wp_ajax_fs_disconnect_ga4',      [ $this, 'disconnect_ga4' ] );
        add_action( 'wp_ajax_fs_save_data_settings', [ $this, 'save_data_settings' ] );
        add_action( 'wp_ajax_fs_get_ga4_sources',    [ $this, 'get_ga4_sources' ] );
        add_action( 'wp_ajax_fs_delete_funnel',     [ $this, 'delete_funnel' ] );
        add_action( 'wp_ajax_fs_duplicate_funnel',  [ $this, 'duplicate_funnel' ] );
        add_action( 'wp_ajax_fs_refresh_promo',        [ $this, 'refresh_promo' ] );
        add_action( 'wp_ajax_fs_get_ga4_property_info', [ $this, 'get_ga4_property_info' ] );
    }

    // ── Save Funnel Canvas ─────────────────────────────────────────────
    public function save_funnel() {
        check_ajax_referer( 'fs_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized' );

        $funnel_id = (int) ( $_POST['funnel_id'] ?? 0 );
        $title     = sanitize_text_field( $_POST['title'] ?? 'Untitled Funnel' );
        $canvas    = $_POST['canvas_data'] ?? '';

        // Validate JSON structure
        $decoded = json_decode( wp_unslash( $canvas ), true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( 'Invalid canvas data.' );
        }

        // Sanitize each node's data
        if ( ! empty( $decoded['nodes'] ) ) {
            foreach ( $decoded['nodes'] as &$node ) {
                $node['label']      = sanitize_text_field( $node['label']      ?? '' );
                $node['url']        = esc_url_raw( $node['url']            ?? '' );
                $node['source']     = sanitize_text_field( $node['source']     ?? '' );
                $node['notes']      = sanitize_text_field( $node['notes']      ?? '' );
                $node['type']       = sanitize_key( $node['type']          ?? 'page' );
                $node['conversion'] = ! empty( $node['conversion'] );
                $node['x']          = (float) ( $node['x'] ?? 0 );
                $node['y']          = (float) ( $node['y'] ?? 0 );
                $node['id']         = sanitize_key( $node['id']            ?? '' );
            }
        }

        if ( $funnel_id ) {
            wp_update_post( [ 'ID' => $funnel_id, 'post_title' => $title ] );
        } else {
            $funnel_id = wp_insert_post([
                'post_type'   => 'fs_funnel',
                'post_title'  => $title,
                'post_status' => 'publish',
            ]);
        }

        if ( is_wp_error( $funnel_id ) ) {
            wp_send_json_error( 'Could not save funnel.' );
        }

        update_post_meta( $funnel_id, '_fs_canvas', wp_json_encode( $decoded ) );
        update_post_meta( $funnel_id, '_fs_updated', current_time( 'mysql' ) );

        wp_send_json_success( [ 'funnel_id' => $funnel_id, 'title' => $title ] );
    }

    // ── GA4 Overlay ───────────────────────────────────────────────────
    public function get_ga4_overlay() {
        check_ajax_referer( 'fs_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized' );
        if ( ! FS_Settings::is_ga4_configured() ) wp_send_json_error( 'GA4 not configured.' );

        $paths      = array_map( 'sanitize_text_field', (array) ( $_POST['paths'] ?? [] ) );
        $date_range = sanitize_text_field( $_POST['date_range'] ?? '30daysAgo' );

        if ( empty( $paths ) ) wp_send_json_error( 'No page paths provided.' );

        $client  = new FS_GA4_Client();
        $raw     = $client->get_page_metrics( $paths, $date_range );

        if ( is_wp_error( $raw ) ) {
            wp_send_json_error( $raw->get_error_message() );
        }

        wp_send_json_success( $client->parse_page_metrics( $raw ) );
    }

    // ── Dismiss Promo ─────────────────────────────────────────────────
    public function dismiss_promo() {
        check_ajax_referer( 'fs_nonce', 'nonce' );
        update_user_meta( get_current_user_id(), 'fs_promo_dismissed', 1 );
        wp_send_json_success();
    }

    // ── Save Settings ─────────────────────────────────────────────────
    public function save_settings() {
        check_ajax_referer( 'fs_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $property_id   = sanitize_text_field( $_POST['ga4_property_id'] ?? '' );
        $client_id     = sanitize_text_field( $_POST['ga4_client_id'] ?? '' );
        $client_secret = sanitize_text_field( $_POST['ga4_client_secret'] ?? '' );

        FS_Settings::set([
            'ga4_property_id'   => $property_id,
            'ga4_client_id'     => $client_id,
            'ga4_client_secret' => $client_secret,
        ]);

        delete_transient( 'fs_ga4_token' );
        wp_send_json_success( 'Settings saved.' );
    }

    // ── GA4 Traffic Sources ───────────────────────────────────────────
    public function get_ga4_sources() {
        check_ajax_referer( 'fs_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized' );
        if ( ! FS_Settings::is_ga4_configured() )  wp_send_json_error( 'GA4 not configured.' );

        $date_range = sanitize_text_field( $_POST['date_range'] ?? '30daysAgo' );
        $client     = new FS_GA4_Client();
        $raw        = $client->get_traffic_sources( $date_range );

        if ( is_wp_error( $raw ) ) {
            wp_send_json_error( $raw->get_error_message() );
        }

        wp_send_json_success( $client->parse_traffic_sources( $raw ) );
    }

    // ── Data & Privacy Setting ────────────────────────────────────────
    public function save_data_settings() {
        check_ajax_referer( 'fs_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        FS_Settings::set( [
            'delete_on_uninstall' => sanitize_text_field( $_POST['delete_on_uninstall'] ?? '0' ),
        ] );

        wp_send_json_success();
    }

    // ── Disconnect GA4 ────────────────────────────────────────────────
    public function disconnect_ga4() {
        check_ajax_referer( 'fs_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $refresh_token = FS_Settings::get( 'ga4_refresh_token' );
        if ( $refresh_token ) {
            FS_GA4_Client::revoke( $refresh_token );
        }

        FS_Settings::set( [ 'ga4_refresh_token' => '' ] );
        delete_transient( 'fs_ga4_token' );
        delete_transient( 'fs_ga4_property_info' );
        wp_send_json_success( 'Disconnected.' );
    }

    // ── Delete Funnel ─────────────────────────────────────────────────
    public function delete_funnel() {
        check_ajax_referer( 'fs_nonce', 'nonce' );
        if ( ! current_user_can( 'delete_posts' ) ) wp_send_json_error( 'Unauthorized' );

        $funnel_id = (int) ( $_POST['funnel_id'] ?? 0 );
        if ( ! $funnel_id ) wp_send_json_error( 'Invalid funnel.' );

        wp_delete_post( $funnel_id, true );
        wp_send_json_success();
    }

    // ── Duplicate Funnel ──────────────────────────────────────────────
    public function duplicate_funnel() {
        check_ajax_referer( 'fs_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized' );

        $funnel_id = (int) ( $_POST['funnel_id'] ?? 0 );
        $original  = get_post( $funnel_id );
        if ( ! $original ) wp_send_json_error( 'Funnel not found.' );

        $new_id = wp_insert_post([
            'post_type'   => 'fs_funnel',
            'post_title'  => $original->post_title . ' (Copy)',
            'post_status' => 'publish',
        ]);

        $canvas = get_post_meta( $funnel_id, '_fs_canvas', true );
        update_post_meta( $new_id, '_fs_canvas', $canvas );
        update_post_meta( $new_id, '_fs_updated', current_time( 'mysql' ) );

        wp_send_json_success( [ 'funnel_id' => $new_id ] );
    }

    // ── GA4 Property & Stream Info ────────────────────────────────────
    public function get_ga4_property_info() {
        check_ajax_referer( 'fs_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        if ( ! FS_Settings::is_ga4_configured() ) wp_send_json_error( 'GA4 not configured.' );

        $cached = get_transient( 'fs_ga4_property_info' );
        if ( $cached !== false ) {
            wp_send_json_success( $cached );
            return;
        }

        $client = new FS_GA4_Client();
        $info   = $client->get_property_info();

        if ( is_wp_error( $info ) ) {
            wp_send_json_error( $info->get_error_message() );
            return;
        }

        set_transient( 'fs_ga4_property_info', $info, DAY_IN_SECONDS );
        wp_send_json_success( $info );
    }

    // ── Refresh Remote Promo ──────────────────────────────────────────
    public function refresh_promo() {
        check_ajax_referer( 'fs_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        FS_Promo::clear_cache();
        $promo = FS_Promo::fetch_and_cache();

        wp_send_json_success( [
            'promo'  => $promo,
            'status' => FS_Promo::cache_status(),
        ]);
    }
}
