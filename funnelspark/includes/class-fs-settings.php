<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FS_Settings {

    const OPTION_KEY = 'funnelspark_settings';

    public static function get( $key, $default = '' ) {
        $settings = get_option( self::OPTION_KEY, [] );
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }

    public static function set( $data ) {
        $existing = get_option( self::OPTION_KEY, [] );
        update_option( self::OPTION_KEY, array_merge( $existing, $data ) );
    }

    public static function is_ga4_configured() {
        return ! empty( self::get( 'ga4_property_id' ) )
            && ! empty( self::get( 'ga4_client_id' ) )
            && ! empty( self::get( 'ga4_client_secret' ) )
            && ! empty( self::get( 'ga4_refresh_token' ) );
    }
}
