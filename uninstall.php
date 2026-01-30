<?php
/**
 * RationalSEO Uninstall
 *
 * Fired when the plugin is uninstalled.
 *
 * @package RationalSEO
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete plugin options.
delete_option( 'rationalseo_settings' );

// Delete transients.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		'_transient_rationalseo_%',
		'_transient_timeout_rationalseo_%'
	)
);

// Note: Post meta (_rationalseo_*) is intentionally left intact.
// This allows users to retain their SEO data if they reinstall the plugin.
