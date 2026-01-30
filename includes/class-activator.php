<?php
/**
 * RationalSEO Activator Class
 *
 * Handles plugin activation and deactivation.
 *
 * @package RationalSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RationalSEO_Activator {

	/**
	 * Current database schema version.
	 *
	 * Increment this when making schema changes.
	 *
	 * @var int
	 */
	const DB_VERSION = 2;

	/**
	 * Option name for storing the database version.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'rationalseo_db_version';

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		self::set_default_options();
		self::create_tables();
		self::run_upgrades();

		// Register sitemap rewrite rules and flush.
		if ( class_exists( 'RationalSEO_Sitemap' ) ) {
			$settings = new RationalSEO_Settings();
			$sitemap  = new RationalSEO_Sitemap( $settings );
			$sitemap->register_rewrite_rules();
		}
		flush_rewrite_rules();
	}

	/**
	 * Check and run upgrades if needed.
	 *
	 * Called on plugins_loaded to handle upgrades without reactivation.
	 */
	public static function maybe_upgrade() {
		$installed_version = (int) get_option( self::DB_VERSION_OPTION, 0 );

		if ( $installed_version < self::DB_VERSION ) {
			self::run_upgrades();
		}
	}

	/**
	 * Run all pending database upgrades.
	 */
	private static function run_upgrades() {
		$installed_version = (int) get_option( self::DB_VERSION_OPTION, 0 );

		// Version 1 -> 2: Add is_regex column to redirects table.
		if ( $installed_version < 2 ) {
			self::upgrade_to_v2();
		}

		// Update stored version.
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Upgrade to database version 2.
	 *
	 * Adds is_regex column to redirects table if missing.
	 */
	private static function upgrade_to_v2() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rationalseo_redirects';

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		if ( ! $table_exists ) {
			// Table doesn't exist, create_table() will handle it.
			if ( class_exists( 'RationalSEO_Redirects' ) ) {
				RationalSEO_Redirects::create_table();
			}
			return;
		}

		// Check if is_regex column exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$column_exists = $wpdb->get_var(
			"SHOW COLUMNS FROM {$table_name} LIKE 'is_regex'"
		);

		if ( ! $column_exists ) {
			// Add the missing column.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				"ALTER TABLE {$table_name} ADD COLUMN is_regex TINYINT(1) NOT NULL DEFAULT 0 AFTER status_code"
			);

			// Add index for is_regex.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				"ALTER TABLE {$table_name} ADD INDEX is_regex (is_regex)"
			);
		}
	}

	/**
	 * Create required database tables.
	 */
	private static function create_tables() {
		if ( class_exists( 'RationalSEO_Redirects' ) ) {
			RationalSEO_Redirects::create_table();
		}
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		// Clear sitemap transients.
		if ( class_exists( 'RationalSEO_Sitemap' ) ) {
			RationalSEO_Sitemap::clear_all_caches();
		}

		// Flush rewrite rules to remove sitemap routes.
		flush_rewrite_rules();
	}

	/**
	 * Set default options if they don't exist.
	 */
	private static function set_default_options() {
		if ( false === get_option( RationalSEO_Settings::OPTION_NAME ) ) {
			$settings = new RationalSEO_Settings();
			add_option( RationalSEO_Settings::OPTION_NAME, $settings->get_defaults() );
		}
	}
}
