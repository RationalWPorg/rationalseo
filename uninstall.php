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

// Drop the redirects table.
$rationalseo_table_name = $wpdb->prefix . 'rationalseo_redirects';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is safely constructed from $wpdb->prefix and a hardcoded string.
$wpdb->query( "DROP TABLE IF EXISTS {$rationalseo_table_name}" );

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
