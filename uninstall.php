<?php
// Runs only when the plugin is deleted from WP admin — not on deactivation.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

$settings = get_option( 'funnelspark_settings', [] );

// Default behaviour: keep all data so the plugin can be reinstalled and
// pick up exactly where it left off. Only wipe if the user explicitly
// enabled "Delete data on uninstall" in Settings → Data & Privacy.
if ( empty( $settings['delete_on_uninstall'] ) ) {
    return;
}

// ── User opted in — remove everything ────────────────────────────────

delete_option( 'funnelspark_settings' );
delete_option( 'funnelspark_version' );

$funnels = get_posts([
    'post_type'      => 'fs_funnel',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'fields'         => 'ids',
]);

foreach ( $funnels as $id ) {
    wp_delete_post( $id, true );
}

global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_fs_%'
     OR option_name LIKE '_transient_timeout_fs_%'"
);

$wpdb->query(
    "DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'fs_promo_dismissed'"
);
