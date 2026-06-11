<?php
/**
 * Plugin Name:       MarTech Spark Conversion Funnel Mapper
 * Plugin URI:        https://github.com/MartechSpark/FunnelSpark
 * Description:       Visual sales funnel builder with live GA4 conversion tracking. Build, visualize, and optimize your marketing funnels — right inside WordPress.
 * Version:           1.3.3
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            MarTech Spark
 * Author URI:        https://martechspark.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       martech-spark-conversion-funnel-mapper
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'FUNNELSPARK_VERSION',    '1.3.3' );
define( 'FUNNELSPARK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FUNNELSPARK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FUNNELSPARK_PLUGIN_FILE', __FILE__ );

// Core includes
require_once FUNNELSPARK_PLUGIN_DIR . 'includes/class-fs-settings.php';
require_once FUNNELSPARK_PLUGIN_DIR . 'includes/class-fs-post-type.php';
require_once FUNNELSPARK_PLUGIN_DIR . 'includes/class-fs-ga4-client.php';
require_once FUNNELSPARK_PLUGIN_DIR . 'includes/class-fs-promo.php';
require_once FUNNELSPARK_PLUGIN_DIR . 'includes/class-fs-ajax.php';
require_once FUNNELSPARK_PLUGIN_DIR . 'admin/class-fs-admin.php';

function funnelspark_init() {
    $post_type = new FunnelSpark_Post_Type();
    $post_type->init();

    $admin = new FunnelSpark_Admin();
    $admin->init();

    $ajax = new FunnelSpark_Ajax();
    $ajax->init();
}
add_action( 'plugins_loaded', 'funnelspark_init' );

/**
 * One-time migration for installs created before 1.3.0, when stored data
 * used the short "fs_" prefix (renamed to "funnelspark_" per WP.org
 * prefixing guidelines).
 */
function funnelspark_maybe_migrate() {
    if ( get_option( 'funnelspark_version' ) === FUNNELSPARK_VERSION ) return;

    global $wpdb;

    // Post type: fs_funnel → funnelspark_funnel
    $wpdb->update( $wpdb->posts, [ 'post_type' => 'funnelspark_funnel' ], [ 'post_type' => 'fs_funnel' ] );

    // Post meta keys
    $wpdb->update( $wpdb->postmeta, [ 'meta_key' => '_funnelspark_canvas' ],  [ 'meta_key' => '_fs_canvas' ] );
    $wpdb->update( $wpdb->postmeta, [ 'meta_key' => '_funnelspark_updated' ], [ 'meta_key' => '_fs_updated' ] );

    // User meta key
    $wpdb->update( $wpdb->usermeta, [ 'meta_key' => 'funnelspark_promo_dismissed' ], [ 'meta_key' => 'fs_promo_dismissed' ] );

    // Drop the cached remote promo from versions that fetched it externally
    delete_transient( 'funnelspark_remote_promo' );

    // Drop old short-prefix transients (they will simply be re-fetched)
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '\_transient\_fs\_%'
         OR option_name LIKE '\_transient\_timeout\_fs\_%'"
    );

    update_option( 'funnelspark_version', FUNNELSPARK_VERSION );
}
add_action( 'admin_init', 'funnelspark_maybe_migrate' );

register_activation_hook( __FILE__, function() {
    add_option( 'funnelspark_settings', [] );
    add_option( 'funnelspark_version', FUNNELSPARK_VERSION );
    flush_rewrite_rules();
});

register_deactivation_hook( __FILE__, function() {
    flush_rewrite_rules();
});
