<?php
/**
 * RationalSEO Importer Interface
 *
 * Contract that all SEO plugin importers must implement.
 *
 * @package RationalSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for SEO plugin importers.
 */
interface RationalSEO_Importer_Interface {

	/**
	 * Get the unique slug for this importer.
	 *
	 * @return string Importer slug (e.g., 'yoast', 'rankmath').
	 */
	public function get_slug();

	/**
	 * Get the display name for this importer.
	 *
	 * @return string Importer name (e.g., 'Yoast SEO').
	 */
	public function get_name();

	/**
	 * Get the description for this importer.
	 *
	 * @return string Description of what this importer handles.
	 */
	public function get_description();

	/**
	 * Check if this importer is available (source data exists).
	 *
	 * @return bool True if importable data exists, false otherwise.
	 */
	public function is_available();

	/**
	 * Get the importable items with counts.
	 *
	 * Returns an array of item types that can be imported with their counts.
	 * Example:
	 * [
	 *     'post_meta' => ['label' => 'Post SEO Data', 'count' => 150, 'available' => true],
	 *     'redirects' => ['label' => 'Redirects', 'count' => 25, 'available' => true],
	 *     'settings'  => ['label' => 'Settings', 'count' => 1, 'available' => true],
	 * ]
	 *
	 * @return array Array of importable item types with metadata.
	 */
	public function get_importable_items();

	/**
	 * Preview the import without making changes.
	 *
	 * @param array $item_types Array of item types to preview (e.g., ['post_meta', 'redirects']).
	 * @return RationalSEO_Import_Result Preview result with sample data.
	 */
	public function preview( $item_types = array() );

	/**
	 * Perform the import.
	 *
	 * @param array $item_types   Array of item types to import.
	 * @param array $options      Import options (e.g., ['skip_existing' => true]).
	 * @return RationalSEO_Import_Result Import result with success/failure counts.
	 */
	public function import( $item_types = array(), $options = array() );
}
