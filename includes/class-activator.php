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
	 * Run on plugin activation.
	 */
	public static function activate() {
		self::set_default_options();
		self::create_tables();

		// Register sitemap rewrite rules and flush.
		if ( class_exists( 'RationalSEO_Sitemap' ) ) {
			$settings = new RationalSEO_Settings();
			$sitemap  = new RationalSEO_Sitemap( $settings );
			$sitemap->register_rewrite_rules();
		}
		flush_rewrite_rules();
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
