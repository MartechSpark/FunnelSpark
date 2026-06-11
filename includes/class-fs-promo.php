<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * FunnelSpark_Promo
 *
 * Fetches the remote promo JSON from martechspark.com and caches it
 * for 24 hours as a WP transient. Supports instant force-refresh via
 * the nonce-protected AJAX call action=funnelspark_refresh_promo
 * (Settings page button).
 *
 * Falls back to hardcoded defaults if the remote fetch fails,
 * so the sidebar never shows blank.
 */
class FunnelSpark_Promo {

    const TRANSIENT_KEY = 'funnelspark_remote_promo';
    const CACHE_TTL     = HOUR_IN_SECONDS;
    const REMOTE_URL    = 'https://martechspark.com/funnelspark-promo.json';

    // ── Fallback defaults (shown if remote fetch fails) ────────────────
    const DEFAULTS = [
        'badge'           => 'From Martech Spark',
        'icon'            => '⚡',
        'headline'        => 'Need Help With Your Analytics?',
        'body'            => 'If you need help with your analytics or conversion rates, reach out to Martech Spark.',
        'bullets'         => [],
        'cta_text'        => 'Schedule a Consultation',
        'cta_url'         => 'https://www.martechspark.com/',
        'powered_by_text' => 'Powered by Martech Spark',
        'powered_by_url'  => 'https://martechspark.com',
    ];

    // ── Get Promo Data ────────────────────────────────────────────────
    public static function get() {
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
            'user-agent' => 'FunnelMapper/' . FUNNELSPARK_VERSION . '; ' . get_bloginfo('url'),
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
