<?php
/**
 * RationalSEO Import Manager Class
 *
 * Registry and orchestration for SEO plugin importers.
 *
 * @package RationalSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import manager class.
 */
class RationalSEO_Import_Manager {

	/**
	 * Registered importers.
	 *
	 * @var array<string, RationalSEO_Importer_Interface>
	 */
	private $importers = array();

	/**
	 * Settings instance.
	 *
	 * @var RationalSEO_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param RationalSEO_Settings $settings Settings instance.
	 */
	public function __construct( RationalSEO_Settings $settings ) {
		$this->settings = $settings;
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Allow other plugins/themes to register importers.
		add_action( 'rationalseo_register_importers', array( $this, 'register_core_importers' ), 10 );

		// Fire the registration hook after plugins are loaded.
		add_action( 'init', array( $this, 'trigger_importer_registration' ), 20 );
	}

	/**
	 * Trigger importer registration.
	 */
	public function trigger_importer_registration() {
		/**
		 * Fires when importers should be registered.
		 *
		 * @param RationalSEO_Import_Manager $manager The import manager instance.
		 */
		do_action( 'rationalseo_register_importers', $this );
	}

	/**
	 * Register core importers.
	 *
	 * This is called during the rationalseo_register_importers action.
	 * Individual importer classes register themselves here.
	 *
	 * @param RationalSEO_Import_Manager $manager The import manager instance.
	 */
	public function register_core_importers( $manager ) {
		// Core importers will be registered here in Phase 3.
		// Example: $manager->register( new RationalSEO_Yoast_Importer( $this->settings ) );
	}

	/**
	 * Register an importer.
	 *
	 * @param RationalSEO_Importer_Interface $importer Importer instance.
	 * @return bool True on success, false if already registered.
	 */
	public function register( RationalSEO_Importer_Interface $importer ) {
		$slug = $importer->get_slug();

		if ( isset( $this->importers[ $slug ] ) ) {
			return false;
		}

		$this->importers[ $slug ] = $importer;
		return true;
	}

	/**
	 * Unregister an importer.
	 *
	 * @param string $slug Importer slug.
	 * @return bool True on success, false if not found.
	 */
	public function unregister( $slug ) {
		if ( ! isset( $this->importers[ $slug ] ) ) {
			return false;
		}

		unset( $this->importers[ $slug ] );
		return true;
	}

	/**
	 * Get an importer by slug.
	 *
	 * @param string $slug Importer slug.
	 * @return RationalSEO_Importer_Interface|null Importer or null if not found.
	 */
	public function get_importer( $slug ) {
		return isset( $this->importers[ $slug ] ) ? $this->importers[ $slug ] : null;
	}

	/**
	 * Get all registered importers.
	 *
	 * @return array<string, RationalSEO_Importer_Interface>
	 */
	public function get_all_importers() {
		return $this->importers;
	}

	/**
	 * Get all available importers (those with importable data).
	 *
	 * @return array<string, RationalSEO_Importer_Interface>
	 */
	public function get_available_importers() {
		$available = array();

		foreach ( $this->importers as $slug => $importer ) {
			if ( $importer->is_available() ) {
				$available[ $slug ] = $importer;
			}
		}

		return $available;
	}

	/**
	 * Check if any importers are available.
	 *
	 * @return bool
	 */
	public function has_available_importers() {
		foreach ( $this->importers as $importer ) {
			if ( $importer->is_available() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get importer data for display.
	 *
	 * Returns an array of importer information suitable for the admin UI.
	 *
	 * @param bool $only_available Whether to only include available importers.
	 * @return array
	 */
	public function get_importers_for_display( $only_available = true ) {
		$importers = $only_available ? $this->get_available_importers() : $this->get_all_importers();
		$display   = array();

		foreach ( $importers as $slug => $importer ) {
			$display[ $slug ] = array(
				'slug'        => $slug,
				'name'        => $importer->get_name(),
				'description' => $importer->get_description(),
				'available'   => $importer->is_available(),
				'items'       => $importer->is_available() ? $importer->get_importable_items() : array(),
			);
		}

		return $display;
	}

	/**
	 * Preview an import.
	 *
	 * @param string $slug       Importer slug.
	 * @param array  $item_types Item types to preview.
	 * @return RationalSEO_Import_Result
	 */
	public function preview( $slug, $item_types = array() ) {
		$importer = $this->get_importer( $slug );

		if ( ! $importer ) {
			return RationalSEO_Import_Result::error(
				__( 'Importer not found.', 'rationalseo' )
			);
		}

		if ( ! $importer->is_available() ) {
			return RationalSEO_Import_Result::error(
				__( 'No data available to import from this source.', 'rationalseo' )
			);
		}

		return $importer->preview( $item_types );
	}

	/**
	 * Run an import.
	 *
	 * @param string $slug       Importer slug.
	 * @param array  $item_types Item types to import.
	 * @param array  $options    Import options.
	 * @return RationalSEO_Import_Result
	 */
	public function import( $slug, $item_types = array(), $options = array() ) {
		$importer = $this->get_importer( $slug );

		if ( ! $importer ) {
			return RationalSEO_Import_Result::error(
				__( 'Importer not found.', 'rationalseo' )
			);
		}

		if ( ! $importer->is_available() ) {
			return RationalSEO_Import_Result::error(
				__( 'No data available to import from this source.', 'rationalseo' )
			);
		}

		return $importer->import( $item_types, $options );
	}

	/**
	 * Get settings instance.
	 *
	 * @return RationalSEO_Settings
	 */
	public function get_settings() {
		return $this->settings;
	}
}
