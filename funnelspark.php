<?php
/**
 * Plugin Name:       FunnelSpark
 * Plugin URI:        https://martechspark.com
 * Description:       Visual sales funnel builder with live GA4 conversion tracking. Build, visualize, and optimize your marketing funnels — right inside WordPress.
 * Version:           1.2.8
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            MarTech Spark
 * Author URI:        https://martechspark.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       funnelspark
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'FS_VERSION',    '1.2.8' );
define( 'FS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FS_PLUGIN_FILE', __FILE__ );

// Core includes
require_once FS_PLUGIN_DIR . 'includes/class-fs-settings.php';
require_once FS_PLUGIN_DIR . 'includes/class-fs-post-type.php';
require_once FS_PLUGIN_DIR . 'includes/class-fs-ga4-client.php';
require_once FS_PLUGIN_DIR . 'includes/class-fs-promo.php';
require_once FS_PLUGIN_DIR . 'includes/class-fs-ajax.php';
require_once FS_PLUGIN_DIR . 'admin/class-fs-admin.php';

function funnelspark_init() {
    $post_type = new FS_Post_Type();
    $post_type->init();

    $admin = new FS_Admin();
    $admin->init();

    $ajax = new FS_Ajax();
    $ajax->init();
}
add_action( 'plugins_loaded', 'funnelspark_init' );

register_activation_hook( __FILE__, function() {
    add_option( 'funnelspark_settings', [] );
    add_option( 'funnelspark_version', FS_VERSION );
    flush_rewrite_rules();
});

register_deactivation_hook( __FILE__, function() {
    flush_rewrite_rules();
});
