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
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		// Reserved for future cleanup (e.g., sitemap transients).
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
