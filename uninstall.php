<?php
/**
 * Uninstall routine.
 *
 * Removes plugin settings and temporary files only. Content that was imported
 * with the plugin is regular WordPress content and is intentionally kept,
 * including the entity UUIDs stored in post and term meta, so that syncing
 * still works if the plugin is reinstalled.
 *
 * @package Cherrylift
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'cherrylift_cleanup_temp' );

require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
$cherrylift_filesystem = new WP_Filesystem_Direct( null );

// Working directories and packages uploaded for import but never imported.
$cherrylift_directories = array(
	trailingslashit( get_temp_dir() ) . 'cherrylift',
	trailingslashit( wp_upload_dir( null, false )['basedir'] ) . 'cherrylift-packages',
);
foreach ( $cherrylift_directories as $cherrylift_directory ) {
	if ( is_dir( $cherrylift_directory ) ) {
		$cherrylift_filesystem->delete( $cherrylift_directory, true );
	}
}

// Import jobs in progress, their locks, and the job index.
$cherrylift_jobs = get_option( 'cherrylift_jobs', array() );
foreach ( is_array( $cherrylift_jobs ) ? array_keys( $cherrylift_jobs ) : array() as $cherrylift_token ) {
	delete_option( 'cherrylift_job_' . $cherrylift_token );
	delete_option( 'cherrylift_job_lock_' . $cherrylift_token );
}
delete_option( 'cherrylift_jobs' );

// Ownership records of uploaded packages. With a persistent object cache they live in the cache
// instead, and expire on their own within the package lifetime (a day by default).
global $wpdb;
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off cleanup on uninstall; transients have no API to delete by prefix.
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_cherrylift_pkg_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_cherrylift_pkg_' ) . '%'
	)
);
