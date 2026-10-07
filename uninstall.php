<?php
/**
 * Uninstall handler for Smart Categories Grid.
 * Removes options and cached data created by the plugin.
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

delete_option('scg_settings');
delete_option('scg_show_regenerate_notice');
delete_option('scg_cache_gen');
delete_transient('scg_widget_has_shortcode');

// Remove any cached grid transients still stored in the options table.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('_transient_scg_cache_') . '%',
        $wpdb->esc_like('_transient_timeout_scg_cache_') . '%'
    )
);

wp_cache_flush();
