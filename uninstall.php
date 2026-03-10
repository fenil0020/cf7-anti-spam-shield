<?php
/**
 * Uninstall script — removes all plugin data when the plugin is deleted.
 *
 * @package CF7_Anti_Spam_Shield
 * @since   1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'cf7as_options' );
delete_option( 'cf7as_spam_log' );

// Clean up rate-limiting transients.
global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time uninstall cleanup.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		'%cf7as_rate_%',
		'%_transient_cf7as_rate_%'
	)
);
