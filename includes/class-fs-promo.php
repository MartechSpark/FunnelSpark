<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * FunnelSpark_Promo
 *
 * Provides the static promo content shown in the editor sidebar.
 * No remote requests are made — the content ships with the plugin.
 */
class FunnelSpark_Promo {

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
        return self::add_utm( self::DEFAULTS );
    }

    // ── Append UTM Attribution ────────────────────────────────────────
    // Applied at render time so every install automatically tags itself
    // with its own domain.
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
}
