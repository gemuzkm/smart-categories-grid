<?php
/** Remove only this plugin's options and database-backed transients. */
defined('WP_UNINSTALL_PLUGIN') || exit;
function scg_uninstall_site(): void {
    global $wpdb;
    foreach (['scg_settings','scg_show_regenerate_notice','scg_cache_gen','scg_widget_flag'] as $key) delete_option($key);
    delete_transient('scg_widget_has_shortcode');
    $prefix = '_transient_scg_cache_';
    do {
        $names = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 100", $wpdb->esc_like($prefix) . '%'));
        $removed = 0;
        foreach ($names as $name) { if (delete_transient(substr($name, strlen('_transient_')))) $removed++; }
        if ($names && !$removed) break;
    } while (count($names) === 100);
    // Redis/Memcached grid entries cannot be enumerated portably; they expire
    // within the bounded TTL. Never flush the site's shared object cache.
}
if (is_multisite()) {
    $offset = 0;
    do {
        $sites = get_sites(['fields'=>'ids','number'=>100,'offset'=>$offset]);
        foreach ($sites as $id) { switch_to_blog($id); scg_uninstall_site(); restore_current_blog(); }
        $offset += count($sites);
    } while (count($sites) === 100);
} else {
    scg_uninstall_site();
}
// Cached answer of the release lookup used for update checks.
delete_site_transient('scg_release');
