<?php
/** Remove only this plugin's options and database-backed transients. */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
function scg_uninstall_site(): void {
	global $wpdb;
	foreach ( array( 'scg_settings', 'scg_show_regenerate_notice', 'scg_cache_gen', 'scg_widget_flag' ) as $key ) {
		delete_option( $key );
	}
	delete_transient( 'scg_widget_has_shortcode' );
	// Remove cached grid rows directly: with a persistent object cache delete_transient()
	// never touches rows left in the options table from before that cache was enabled.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_scg_cache_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_scg_cache_' ) . '%'
		)
	);
	// Redis/Memcached grid entries cannot be enumerated portably; they expire
	// within the bounded TTL. Never flush the site's shared object cache.
}
if ( is_multisite() ) {
	$offset = 0;
	do {
		$sites = get_sites(
			array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $offset,
			)
		);
		foreach ( $sites as $id ) {
			switch_to_blog( $id );
			scg_uninstall_site();
			restore_current_blog(); }
		$offset += count( $sites );
	} while ( count( $sites ) === 100 );
} else {
	scg_uninstall_site();
}
// Cached answer of the release lookup used for update checks.
delete_site_transient( 'scg_release' );
