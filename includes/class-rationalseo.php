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
	 * Import manager instance.
	 *
	 * @var RationalSEO_Import_Manager
	 */
	private $import_manager;

	/**
	 * Import admin instance.
	 *
	 * @var RationalSEO_Import_Admin
	 */
	private $import_admin;

	/**
	 * Term meta instance.
	 *
	 * @var RationalSEO_Term_Meta
	 */
	private $term_meta;

	/**
	 * AI Assistant instance.
	 *
	 * @var RationalSEO_AI_Assistant
	 */
	private $ai_assistant;

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
		$this->settings       = new RationalSEO_Settings();
		$this->frontend       = new RationalSEO_Frontend( $this->settings );
		$this->sitemap        = new RationalSEO_Sitemap( $this->settings );
		$this->import_manager = new RationalSEO_Import_Manager( $this->settings );

		if ( is_admin() ) {
			$this->admin        = new RationalSEO_Admin( $this->settings, $this->import_manager );
			$this->meta_box     = new RationalSEO_Meta_Box( $this->settings );
			$this->term_meta    = new RationalSEO_Term_Meta( $this->settings );
			$this->import_admin = new RationalSEO_Import_Admin( $this->import_manager );
			$this->ai_assistant = new RationalSEO_AI_Assistant( $this->settings );
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
	 * Get import manager instance.
	 *
	 * @return RationalSEO_Import_Manager
	 */
	public function get_import_manager() {
		return $this->import_manager;
	}

	/**
	 * Get import admin instance.
	 *
	 * @return RationalSEO_Import_Admin|null
	 */
	public function get_import_admin() {
		return $this->import_admin;
	}

	/**
	 * Get term meta instance.
	 *
	 * @return RationalSEO_Term_Meta|null
	 */
	public function get_term_meta() {
		return $this->term_meta;
	}

	/**
	 * Get AI assistant instance.
	 *
	 * @return RationalSEO_AI_Assistant|null
	 */
	public function get_ai_assistant() {
		return $this->ai_assistant;
	}
}
