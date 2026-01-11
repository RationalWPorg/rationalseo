<?php
/**
 * Main RationalSEO Class
 *
 * @package RationalSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RationalSEO {

	/**
	 * Singleton instance.
	 *
	 * @var RationalSEO|null
	 */
	private static $instance = null;

	/**
	 * Settings instance.
	 *
	 * @var RationalSEO_Settings
	 */
	private $settings;

	/**
	 * Frontend instance.
	 *
	 * @var RationalSEO_Frontend
	 */
	private $frontend;

	/**
	 * Admin instance.
	 *
	 * @var RationalSEO_Admin
	 */
	private $admin;

	/**
	 * Meta Box instance.
	 *
	 * @var RationalSEO_Meta_Box
	 */
	private $meta_box;

	/**
	 * Sitemap instance.
	 *
	 * @var RationalSEO_Sitemap
	 */
	private $sitemap;

	/**
	 * Redirects instance.
	 *
	 * @var RationalSEO_Redirects
	 */
	private $redirects;

	/**
	 * Get the singleton instance.
	 *
	 * @return RationalSEO
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->settings  = new RationalSEO_Settings();
		$this->frontend  = new RationalSEO_Frontend( $this->settings );
		$this->sitemap   = new RationalSEO_Sitemap( $this->settings );
		$this->redirects = new RationalSEO_Redirects( $this->settings );

		if ( is_admin() ) {
			$this->admin    = new RationalSEO_Admin( $this->settings, $this->redirects );
			$this->meta_box = new RationalSEO_Meta_Box( $this->settings );
		}
	}

	/**
	 * Get settings instance.
	 *
	 * @return RationalSEO_Settings
	 */
	public function get_settings() {
		return $this->settings;
	}

	/**
	 * Get frontend instance.
	 *
	 * @return RationalSEO_Frontend
	 */
	public function get_frontend() {
		return $this->frontend;
	}

	/**
	 * Get admin instance.
	 *
	 * @return RationalSEO_Admin|null
	 */
	public function get_admin() {
		return $this->admin;
	}

	/**
	 * Get meta box instance.
	 *
	 * @return RationalSEO_Meta_Box|null
	 */
	public function get_meta_box() {
		return $this->meta_box;
	}

	/**
	 * Get sitemap instance.
	 *
	 * @return RationalSEO_Sitemap
	 */
	public function get_sitemap() {
		return $this->sitemap;
	}

	/**
	 * Get redirects instance.
	 *
	 * @return RationalSEO_Redirects
	 */
	public function get_redirects() {
		return $this->redirects;
	}
}
