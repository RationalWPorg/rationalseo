<?php
/**
 * RationalSEO Redirection Plugin Importer
 *
 * Imports redirects from the Redirection plugin.
 *
 * @package RationalSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirection plugin importer class.
 */
class RationalSEO_Redirection_Importer implements RationalSEO_Importer_Interface {

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
	}

	/**
	 * Get the unique slug for this importer.
	 *
	 * @return string
	 */
	public function get_slug() {
		return 'redirection';
	}

	/**
	 * Get the display name for this importer.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'Redirection';
	}

	/**
	 * Get the description for this importer.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Import redirects from the Redirection plugin by John Godley.', 'rationalseo' );
	}

	/**
	 * Check if this importer is available (source data exists).
	 *
	 * @return bool
	 */
	public function is_available() {
		return $this->get_redirects_count() > 0;
	}

	/**
	 * Get the importable items with counts.
	 *
	 * @return array
	 */
	public function get_importable_items() {
		$redirects_count = $this->get_redirects_count();

		return array(
			'redirects' => array(
				'label'     => __( 'Redirects', 'rationalseo' ),
				'count'     => $redirects_count,
				'available' => $redirects_count > 0,
			),
		);
	}

	/**
	 * Preview the import without making changes.
	 *
	 * @param array $item_types Array of item types to preview.
	 * @return RationalSEO_Import_Result
	 */
	public function preview( $item_types = array() ) {
		$result       = RationalSEO_Import_Result::success( __( 'Preview generated successfully.', 'rationalseo' ) );
		$preview_data = array();

		// If no specific types requested, preview all available.
		if ( empty( $item_types ) ) {
			$item_types = array( 'redirects' );
		}

		if ( in_array( 'redirects', $item_types, true ) ) {
			$preview_data['redirects'] = $this->preview_redirects();
		}

		$result->set_preview_data( $preview_data );
		return $result;
	}

	/**
	 * Perform the import.
	 *
	 * @param array $item_types Array of item types to import.
	 * @param array $options    Import options.
	 * @return RationalSEO_Import_Result
	 */
	public function import( $item_types = array(), $options = array() ) {
		$result        = RationalSEO_Import_Result::success();
		$skip_existing = ! empty( $options['skip_existing'] );

		// If no specific types requested, import all available.
		if ( empty( $item_types ) ) {
			$item_types = array( 'redirects' );
		}

		$import_results = array();

		if ( in_array( 'redirects', $item_types, true ) ) {
			$import_results['redirects'] = $this->import_redirects( $skip_existing );
		}

		// Aggregate results.
		$total_imported = 0;
		$total_skipped  = 0;
		$total_failed   = 0;

		foreach ( $import_results as $type => $type_result ) {
			$total_imported += $type_result['imported'];
			$total_skipped  += $type_result['skipped'];
			$total_failed   += $type_result['failed'];

			if ( ! empty( $type_result['errors'] ) ) {
				foreach ( $type_result['errors'] as $error ) {
					$result->add_error( $error );
				}
			}
		}

		$result->set_imported( $total_imported )
			   ->set_skipped( $total_skipped )
			   ->set_failed( $total_failed )
			   ->set_data( $import_results );

		// Set appropriate message.
		if ( $total_imported > 0 ) {
			$result->set_message(
				sprintf(
					/* translators: %d: number of items imported */
					__( 'Successfully imported %d redirects from Redirection.', 'rationalseo' ),
					$total_imported
				)
			);
		} elseif ( $total_skipped > 0 ) {
			$result->set_message( __( 'All redirects were skipped (already exist).', 'rationalseo' ) );
		} else {
			$result->set_message( __( 'No redirects were imported.', 'rationalseo' ) );
		}

		return $result;
	}

	/**
	 * Check if the Redirection plugin table exists.
	 *
	 * @return bool
	 */
	private function table_exists() {
		global $wpdb;

		$table = $wpdb->prefix . 'redirection_items';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s',
				DB_NAME,
				$table
			)
		);

		return (int) $result > 0;
	}

	/**
	 * Get count of importable redirects.
	 *
	 * @return int
	 */
	private function get_redirects_count() {
		if ( ! $this->table_exists() ) {
			return 0;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'redirection_items';

		// Only count URL-type redirects with simple URL matching.
		// Skip complex match types (referrer, agent, login, header, cookie, role, server, ip, page, language).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is safely constructed from $wpdb->prefix and a hardcoded string.
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE action_type = 'url' AND match_type = 'url' AND status = 'enabled'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return absint( $count );
	}

	/**
	 * Get redirects from Redirection plugin.
	 *
	 * @param int $limit Maximum number of redirects to retrieve. 0 for all.
	 * @return array Parsed redirects array.
	 */
	private function get_redirection_redirects( $limit = 0 ) {
		if ( ! $this->table_exists() ) {
			return array();
		}

		global $wpdb;

		$table = $wpdb->prefix . 'redirection_items';

		$sql = "SELECT url, action_data, action_code, regex FROM {$table} WHERE action_type = 'url' AND match_type = 'url' AND status = 'enabled'"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d', $limit );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is safely constructed from $wpdb->prefix and a hardcoded string.
		$results = $wpdb->get_results( $sql );

		if ( empty( $results ) ) {
			return array();
		}

		return $this->parse_redirection_redirects( $results );
	}

	/**
	 * Parse Redirection plugin data into a normalized format.
	 *
	 * @param array $redirects Raw redirects from database.
	 * @return array Normalized redirects.
	 */
	private function parse_redirection_redirects( $redirects ) {
		$parsed = array();

		foreach ( $redirects as $redirect ) {
			$url_from = isset( $redirect->url ) ? $redirect->url : '';

			// Skip empty source URLs.
			if ( empty( $url_from ) ) {
				continue;
			}

			// Parse target URL from action_data.
			// Redirection stores action_data in multiple formats:
			// 1. Plain URL string (most common for URL match type)
			// 2. JSON object like {"url": "/target"}
			// 3. Serialized PHP array
			$url_to = $this->parse_action_data( $redirect->action_data );

			$status_code = isset( $redirect->action_code ) ? absint( $redirect->action_code ) : 301;
			$is_regex    = isset( $redirect->regex ) && (int) $redirect->regex === 1;

			// Handle status codes.
			// 308 is not supported by RationalSEO, convert to 301.
			if ( 308 === $status_code ) {
				$status_code = 301;
			}

			// For non-410, require a destination.
			if ( 410 !== $status_code && empty( $url_to ) ) {
				continue;
			}

			// Validate status code.
			$valid_codes = array( 301, 302, 307, 410 );
			if ( ! in_array( $status_code, $valid_codes, true ) ) {
				$status_code = 301;
			}

			$parsed[] = array(
				'url_from'    => sanitize_text_field( $url_from ),
				'url_to'      => $this->sanitize_target_url( $url_to ),
				'status_code' => $status_code,
				'is_regex'    => $is_regex,
			);
		}

		return $parsed;
	}

	/**
	 * Parse action_data field to extract target URL.
	 *
	 * Redirection stores action_data in multiple formats:
	 * - Plain URL string (most common)
	 * - JSON object: {"url": "/target"}
	 * - Serialized PHP array: a:1:{s:3:"url";s:7:"/target";}
	 *
	 * @param string $action_data The action_data field from database.
	 * @return string The target URL.
	 */
	private function parse_action_data( $action_data ) {
		if ( empty( $action_data ) ) {
			return '';
		}

		// Check if it's a serialized PHP array.
		$unserialized = @unserialize( $action_data ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false !== $unserialized && is_array( $unserialized ) ) {
			return isset( $unserialized['url'] ) ? $unserialized['url'] : '';
		}

		// Check if it's JSON.
		$json_data = json_decode( $action_data, true );
		if ( json_last_error() === JSON_ERROR_NONE && is_array( $json_data ) ) {
			return isset( $json_data['url'] ) ? $json_data['url'] : '';
		}

		// Assume it's a plain URL string (most common case).
		return $action_data;
	}

	/**
	 * Sanitize target URL, preserving relative paths.
	 *
	 * esc_url_raw() mangles relative paths like /testing-two into http://testing-two.
	 * This method preserves relative paths while sanitizing absolute URLs.
	 *
	 * @param string $url The target URL to sanitize.
	 * @return string Sanitized URL.
	 */
	private function sanitize_target_url( $url ) {
		if ( empty( $url ) ) {
			return '';
		}

		// If it's a relative path (starts with /), preserve it.
		if ( strpos( $url, '/' ) === 0 ) {
			return sanitize_text_field( $url );
		}

		// If it's an absolute URL, use esc_url_raw.
		if ( preg_match( '/^https?:\/\//i', $url ) ) {
			return esc_url_raw( $url );
		}

		// For other cases (e.g., protocol-relative URLs), sanitize as text.
		return sanitize_text_field( $url );
	}

	/**
	 * Preview redirects import.
	 *
	 * @return array Preview data.
	 */
	private function preview_redirects() {
		$redirects = $this->get_redirection_redirects( 5 );

		return array(
			'total'   => $this->get_redirects_count(),
			'samples' => $redirects,
		);
	}

	/**
	 * Import redirects from Redirection plugin.
	 *
	 * @param bool $skip_existing Whether to skip redirects that already exist.
	 * @return array Import results.
	 */
	private function import_redirects( $skip_existing = false ) {
		$result = array(
			'imported' => 0,
			'skipped'  => 0,
			'failed'   => 0,
			'errors'   => array(),
		);

		$redirects = $this->get_redirection_redirects();

		if ( empty( $redirects ) ) {
			return $result;
		}

		// Get the redirects instance.
		$redirects_manager = RationalSEO::get_instance()->get_redirects();

		foreach ( $redirects as $redirect ) {
			// Check if redirect already exists.
			if ( $skip_existing && $redirects_manager->redirect_exists( $redirect['url_from'], $redirect['is_regex'] ) ) {
				$result['skipped']++;
				continue;
			}

			// Add the redirect.
			$insert_id = $redirects_manager->add_redirect(
				$redirect['url_from'],
				$redirect['url_to'],
				$redirect['status_code'],
				$redirect['is_regex']
			);

			if ( false !== $insert_id ) {
				$result['imported']++;
			} else {
				$result['failed']++;
				$result['errors'][] = sprintf(
					/* translators: %s: source URL */
					__( 'Failed to import redirect: %s', 'rationalseo' ),
					$redirect['url_from']
				);
			}
		}

		return $result;
	}
}
