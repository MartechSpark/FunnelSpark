<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FS_GA4_Client {

    const TOKEN_URL  = 'https://oauth2.googleapis.com/token';
    const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';
    const AUTH_URL   = 'https://accounts.google.com/o/oauth2/v2/auth';
    const API_BASE   = 'https://analyticsdata.googleapis.com/v1beta/properties/';
    const SCOPE      = 'https://www.googleapis.com/auth/analytics.readonly';

    private $property_id;
    private $client_id;
    private $client_secret;
    private $refresh_token;

    public function __construct() {
        $this->property_id   = FS_Settings::get( 'ga4_property_id' );
        $this->client_id     = FS_Settings::get( 'ga4_client_id' );
        $this->client_secret = FS_Settings::get( 'ga4_client_secret' );
        $this->refresh_token = FS_Settings::get( 'ga4_refresh_token' );
    }

    // ── OAuth Helpers (static) ────────────────────────────────────────

    public static function get_redirect_uri() {
        return admin_url( 'admin.php?page=funnelspark-settings' );
    }

    public static function get_auth_url() {
        $state = wp_generate_password( 24, false );
        set_transient( 'fs_oauth_state', $state, 600 );

        return add_query_arg([
            'client_id'     => FS_Settings::get( 'ga4_client_id' ),
            'redirect_uri'  => self::get_redirect_uri(),
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $state,
        ], self::AUTH_URL );
    }

    public static function exchange_code( $code ) {
        $resp = wp_remote_post( self::TOKEN_URL, [
            'body' => [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'client_id'     => FS_Settings::get( 'ga4_client_id' ),
                'client_secret' => FS_Settings::get( 'ga4_client_secret' ),
                'redirect_uri'  => self::get_redirect_uri(),
            ],
            'timeout' => 15,
        ]);

        if ( is_wp_error( $resp ) ) return $resp;

        $body = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( empty( $body['refresh_token'] ) ) {
            $detail = $body['error_description'] ?? $body['error'] ?? 'Unknown error';
            return new WP_Error( 'fs_oauth', 'No refresh token received: ' . $detail );
        }

        return $body['refresh_token'];
    }

    public static function revoke( $refresh_token ) {
        wp_remote_post( self::REVOKE_URL, [
            'body'    => [ 'token' => $refresh_token ],
            'timeout' => 10,
        ]);
    }

    // ── Access Token ──────────────────────────────────────────────────

    private function get_token() {
        $cached = get_transient( 'fs_ga4_token' );
        if ( $cached ) return $cached;

        $resp = wp_remote_post( self::TOKEN_URL, [
            'body' => [
                'grant_type'    => 'refresh_token',
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $this->refresh_token,
            ],
            'timeout' => 15,
        ]);

        if ( is_wp_error( $resp ) ) return $resp;

        $body = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( empty( $body['access_token'] ) ) {
            $detail = $body['error_description'] ?? $body['error'] ?? 'Unknown error';
            return new WP_Error( 'fs_token', 'Could not retrieve GA4 access token: ' . $detail );
        }

        $ttl = max( 60, (int) ( $body['expires_in'] ?? 3600 ) - 60 );
        set_transient( 'fs_ga4_token', $body['access_token'], $ttl );
        return $body['access_token'];
    }

    // ── API Request ───────────────────────────────────────────────────

    private function request( $body ) {
        $token = $this->get_token();
        if ( is_wp_error( $token ) ) return $token;

        $resp = wp_remote_post(
            self::API_BASE . $this->property_id . ':runReport',
            [
                'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ],
                'body'    => wp_json_encode( $body ),
                'timeout' => 20,
            ]
        );

        if ( is_wp_error( $resp ) ) return $resp;
        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( isset( $data['error'] ) ) return new WP_Error( 'fs_api', $data['error']['message'] );
        return $data;
    }

    // ── Public Methods ────────────────────────────────────────────────

    public function get_traffic_sources( $date_range = '30daysAgo' ) {
        $cache_key = 'fs_ga4_srcs_v2_' . md5( $date_range );
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) return $cached;

        // Fetch ALL sources (no medium filter) so we can compute an accurate
        // grand-total denominator for "% of traffic" on ad nodes.
        $body = [
            'dateRanges' => [[ 'startDate' => $date_range, 'endDate' => 'today' ]],
            'dimensions' => [
                [ 'name' => 'sessionSource' ],
                [ 'name' => 'sessionMedium' ],
            ],
            'metrics'  => [ [ 'name' => 'sessions' ] ],
            'orderBys' => [ [ 'metric' => [ 'metricName' => 'sessions' ], 'desc' => true ] ],
            'limit'    => 100,
        ];

        $result = $this->request( $body );
        if ( ! is_wp_error( $result ) ) {
            set_transient( $cache_key, $result, HOUR_IN_SECONDS * 4 );
        }
        return $result;
    }

    public function parse_traffic_sources( $raw ) {
        $paid_sources  = [];
        $total_sessions = 0;

        if ( empty( $raw['rows'] ) ) {
            return [ 'sources' => [], 'total_sessions' => 0 ];
        }

        $paid_mediums = [ 'cpc', 'ppc', 'paid', 'paidsocial', 'paid_social', 'paid-social' ];

        foreach ( $raw['rows'] as $row ) {
            $source   = $row['dimensionValues'][0]['value'] ?? '';
            $medium   = $row['dimensionValues'][1]['value'] ?? '';
            $sessions = (int) ( $row['metricValues'][0]['value'] ?? 0 );

            if ( ! $source || ! $medium || $source === '(not set)' || $medium === '(not set)' ) continue;

            $total_sessions += $sessions;

            $is_paid = in_array( strtolower( $medium ), $paid_mediums, true )
                    || stripos( $medium, 'paid' ) !== false;

            if ( $is_paid ) {
                $paid_sources[] = [
                    'label'    => $source . ' / ' . $medium,
                    'source'   => $source,
                    'medium'   => $medium,
                    'sessions' => $sessions,
                ];
            }
        }

        return [
            'sources'        => $paid_sources,
            'total_sessions' => $total_sessions,
        ];
    }

    public function get_page_metrics( array $page_paths, $date_range = '30daysAgo' ) {
        $cache_key = 'fs_sess_' . md5( implode( ',', $page_paths ) . $date_range );
        $cached    = get_transient( $cache_key );
        if ( $cached ) return $cached;

        $body = [
            'dateRanges'      => [[ 'startDate' => $date_range, 'endDate' => 'today' ]],
            'dimensions'      => [[ 'name' => 'pagePath' ]],
            'metrics'         => [
                [ 'name' => 'sessions' ],
                [ 'name' => 'conversions' ],
                [ 'name' => 'totalUsers' ],
                [ 'name' => 'bounceRate' ],
            ],
            'dimensionFilter' => [
                'orGroup' => [
                    'expressions' => array_map( function( $path ) {
                        return [
                            'filter' => [
                                'fieldName'    => 'pagePath',
                                'stringFilter' => [ 'matchType' => 'CONTAINS', 'value' => $path ],
                            ],
                        ];
                    }, $page_paths ),
                ],
            ],
        ];

        $result = $this->request( $body );
        if ( ! is_wp_error( $result ) ) {
            set_transient( $cache_key, $result, HOUR_IN_SECONDS * 4 );
        }
        return $result;
    }

    public function parse_page_metrics( $raw ) {
        $out = [];
        if ( empty( $raw['rows'] ) ) return $out;

        foreach ( $raw['rows'] as $row ) {
            $path        = $row['dimensionValues'][0]['value'] ?? '';
            $m           = $row['metricValues'];
            $sessions    = (int) ( $m[0]['value'] ?? 0 );
            $conversions = (int) ( $m[1]['value'] ?? 0 );
            $out[ $path ] = [
                'sessions'        => $sessions,
                'conversions'     => $conversions,
                'users'           => (int) ( $m[2]['value'] ?? 0 ),
                'bounce_rate'     => round( (float) ( $m[3]['value'] ?? 0 ) * 100, 1 ),
                'conversion_rate' => $sessions > 0 ? round( ( $conversions / $sessions ) * 100, 1 ) : 0,
            ];
        }
        return $out;
    }

    // ── Property & Stream Info (Admin API) ───────────────────────────

    public function get_property_info() {
        $token = $this->get_token();
        if ( is_wp_error( $token ) ) return $token;

        $admin_base = 'https://analyticsadmin.googleapis.com/v1beta/properties/';
        $headers    = [ 'Authorization' => 'Bearer ' . $token ];

        $prop_resp = wp_remote_get( $admin_base . $this->property_id, [
            'headers' => $headers,
            'timeout' => 10,
        ]);

        if ( is_wp_error( $prop_resp ) ) return $prop_resp;

        $property = json_decode( wp_remote_retrieve_body( $prop_resp ), true );
        if ( isset( $property['error'] ) ) {
            return new WP_Error( 'fs_admin_api', $property['error']['message'] ?? 'Admin API error' );
        }

        $streams_resp = wp_remote_get( $admin_base . $this->property_id . '/dataStreams', [
            'headers' => $headers,
            'timeout' => 10,
        ]);

        $streams = [];
        if ( ! is_wp_error( $streams_resp ) ) {
            $streams_data = json_decode( wp_remote_retrieve_body( $streams_resp ), true );
            foreach ( $streams_data['dataStreams'] ?? [] as $stream ) {
                $info = [
                    'name' => $stream['displayName'] ?? '',
                    'type' => $stream['type']        ?? '',
                ];
                if ( ! empty( $stream['webStreamData'] ) ) {
                    $info['measurement_id'] = $stream['webStreamData']['measurementId'] ?? '';
                    $info['uri']            = $stream['webStreamData']['defaultUri']    ?? '';
                }
                $streams[] = $info;
            }
        }

        return [
            'property_id'   => $this->property_id,
            'property_name' => $property['displayName'] ?? '',
            'streams'       => $streams,
        ];
    }

    public function clear_cache() {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_fs_%'
             OR option_name LIKE '_transient_timeout_fs_%'"
        );
    }
}
