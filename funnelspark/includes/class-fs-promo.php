<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * FS_Promo
 *
 * Fetches the remote promo JSON from martechspark.com and caches it
 * for 24 hours as a WP transient. Supports instant force-refresh via:
 *   - URL param:  ?fs_promo_refresh=1  (admin only)
 *   - AJAX call:  action=fs_refresh_promo  (Settings page button)
 *
 * Falls back to hardcoded defaults if the remote fetch fails,
 * so the sidebar never shows blank.
 */
class FS_Promo {

    const TRANSIENT_KEY = 'fs_remote_promo';
    const CACHE_TTL     = DAY_IN_SECONDS;
    const REMOTE_URL    = 'https://martechspark.com/funnelspark-promo.json';

    // ── Fallback defaults (shown if remote fetch fails) ────────────────
    const DEFAULTS = [
        'badge'           => 'From the Maker of FunnelSpark',
        'icon'            => '⚡',
        'headline'        => 'Is Your Funnel Leaking Leads?',
        'body'            => 'Get a personal video audit of your website — 7 specific things costing you leads, fixed in plain English.',
        'bullets'         => [
            '✓ Delivered in 48 hours',
            '✓ Specific, actionable fixes',
            '✓ No fluff. No upsell pressure.',
        ],
        'cta_text'        => 'Get the $27 Audit →',
        'cta_url'         => 'https://martechspark.com/lp/homepage-audit/',
        'powered_by_text' => 'Powered by MarTech Spark',
        'powered_by_url'  => 'https://martechspark.com',
    ];

    // ── Get Promo Data ────────────────────────────────────────────────
    public static function get() {
        // Force-refresh via URL param (admin only, no nonce needed — read-only action)
        if (
            is_admin() &&
            current_user_can( 'manage_options' ) &&
            ! empty( $_GET['fs_promo_refresh'] )
        ) {
            self::clear_cache();
        }

        $cached = get_transient( self::TRANSIENT_KEY );
        if ( $cached !== false ) {
            return self::add_utm( $cached );
        }

        return self::add_utm( self::fetch_and_cache() );
    }

    // ── Append UTM Attribution ────────────────────────────────────────
    // Applied at render time (not cached) so the clean URL is always stored
    // and every install automatically tags itself with its own domain.
    private static function add_utm( $promo ) {
        if ( empty( $promo['cta_url'] ) ) return $promo;

        $domain = wp_parse_url( home_url(), PHP_URL_HOST );

        $promo['cta_url'] = add_query_arg( [
            'utm_source'   => $domain,
            'utm_medium'   => 'funnelspark-plugin',
            'utm_campaign' => 'promo-sidebar',
        ], $promo['cta_url'] );

        return $promo;
    }

    // ── Fetch Remote JSON ──────────────────────────────────────────────
    public static function fetch_and_cache() {
        $response = wp_remote_get( self::REMOTE_URL, [
            'timeout'    => 8,
            'user-agent' => 'FunnelSpark/' . FS_VERSION . '; ' . get_bloginfo('url'),
        ]);

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            // Cache the fallback for 1 hour so we retry sooner on failure
            set_transient( self::TRANSIENT_KEY, self::DEFAULTS, HOUR_IN_SECONDS );
            return self::DEFAULTS;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE || empty( $data ) ) {
            set_transient( self::TRANSIENT_KEY, self::DEFAULTS, HOUR_IN_SECONDS );
            return self::DEFAULTS;
        }

        // Sanitize every field before caching
        $clean = self::sanitize( $data );

        // Merge with defaults so missing keys never break the template
        $merged = array_merge( self::DEFAULTS, $clean );

        set_transient( self::TRANSIENT_KEY, $merged, self::CACHE_TTL );
        return $merged;
    }

    // ── Clear Cache ────────────────────────────────────────────────────
    public static function clear_cache() {
        delete_transient( self::TRANSIENT_KEY );
    }

    // ── Sanitize Remote Data ───────────────────────────────────────────
    private static function sanitize( $data ) {
        $clean = [];

        $text_fields = [ 'badge', 'icon', 'headline', 'body', 'cta_text', 'powered_by_text' ];
        foreach ( $text_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $clean[ $field ] = sanitize_text_field( $data[ $field ] );
            }
        }

        $url_fields = [ 'cta_url', 'powered_by_url' ];
        foreach ( $url_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $clean[ $field ] = esc_url_raw( $data[ $field ] );
            }
        }

        if ( isset( $data['bullets'] ) && is_array( $data['bullets'] ) ) {
            $clean['bullets'] = array_map( 'sanitize_text_field', array_slice( $data['bullets'], 0, 5 ) );
        }

        return $clean;
    }

    // ── Cache Status (for Settings page) ──────────────────────────────
    public static function cache_status() {
        $cached = get_transient( self::TRANSIENT_KEY );
        if ( $cached === false ) return [ 'status' => 'empty', 'label' => 'Not cached — will fetch on next load' ];

        // WP doesn't expose transient expiry directly, so we store it ourselves
        $timeout = get_option( '_transient_timeout_' . self::TRANSIENT_KEY );
        if ( $timeout ) {
            $expires_in = $timeout - time();
            $hours      = floor( $expires_in / 3600 );
            $mins       = floor( ( $expires_in % 3600 ) / 60 );
            return [ 'status' => 'cached', 'label' => "Cached · expires in {$hours}h {$mins}m" ];
        }

        return [ 'status' => 'cached', 'label' => 'Cached (expiry unknown)' ];
    }
}
