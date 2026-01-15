<?php
/**
 * RationalSEO Redirects Class
 *
 * Handles URL redirects including auto-redirects on slug changes.
 *
 * @package RationalSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RationalSEO_Redirects {

	/**
	 * Table name without prefix.
	 *
	 * @var string
	 */
	const TABLE_NAME = 'rationalseo_redirects';

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
		// Execute redirects early in template_redirect.
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );

		// Auto-redirect on slug change.
		add_action( 'post_updated', array( $this, 'maybe_create_auto_redirect' ), 10, 3 );

		// Admin AJAX handlers.
		add_action( 'wp_ajax_rationalseo_add_redirect', array( $this, 'ajax_add_redirect' ) );
		add_action( 'wp_ajax_rationalseo_delete_redirect', array( $this, 'ajax_delete_redirect' ) );
		add_action( 'wp_ajax_rationalseo_preview_yoast_import', array( $this, 'ajax_preview_yoast_import' ) );
		add_action( 'wp_ajax_rationalseo_import_yoast_redirects', array( $this, 'ajax_import_yoast_redirects' ) );
	}

	/**
	 * Get the full table name with prefix.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create the redirects table.
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			url_from VARCHAR(255) NOT NULL,
			url_to TEXT NOT NULL,
			status_code INT(3) NOT NULL DEFAULT 301,
			is_regex TINYINT(1) NOT NULL DEFAULT 0,
			count INT(11) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY url_from (url_from),
			KEY is_regex (is_regex)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drop the redirects table.
	 */
	public static function drop_table() {
		global $wpdb;

		$table_name = self::get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}

	/**
	 * Check if a redirect exists and execute it.
	 */
	public function maybe_redirect() {
		global $wpdb;

		// Get the current request URI.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( empty( $request_uri ) ) {
			return;
		}

		// Normalize the URL (remove query string for matching).
		$url_path = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( empty( $url_path ) ) {
			return;
		}

		// Normalize: ensure leading slash, remove trailing slash (except for root).
		$url_path = '/' . ltrim( $url_path, '/' );
		if ( strlen( $url_path ) > 1 ) {
			$url_path = rtrim( $url_path, '/' );
		}

		$table_name = self::get_table_name();

		// First pass: exact match on indexed column (fast).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$redirect = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id, url_to, status_code FROM {$table_name} WHERE url_from = %s AND is_regex = 0 LIMIT 1",
				$url_path
			)
		);

		$destination = null;

		if ( $redirect ) {
			$destination = $redirect->url_to;
		} else {
			// Second pass: check regex redirects.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$regex_redirects = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id, url_from, url_to, status_code FROM {$table_name} WHERE is_regex = 1"
			);

			if ( $regex_redirects ) {
				foreach ( $regex_redirects as $regex_redirect ) {
					$pattern = $this->prepare_regex_pattern( $regex_redirect->url_from );
					if ( preg_match( $pattern, $url_path, $matches ) ) {
						$redirect    = $regex_redirect;
						$destination = $this->apply_regex_replacements( $regex_redirect->url_to, $matches );
						break;
					}
				}
			}
		}

		if ( ! $redirect ) {
			return;
		}

		// Increment hit counter.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$table_name} SET count = count + 1 WHERE id = %d",
				$redirect->id
			)
		);

		$status_code = absint( $redirect->status_code );

		// Handle 410 Gone.
		if ( 410 === $status_code ) {
			status_header( 410 );
			nocache_headers();
			exit;
		}

		// Perform redirect.
		$valid_codes = array( 301, 302, 307 );
		if ( ! in_array( $status_code, $valid_codes, true ) ) {
			$status_code = 301;
		}

		wp_safe_redirect( $destination, $status_code, 'RationalSEO' );
		exit;
	}

	/**
	 * Prepare a regex pattern for preg_match.
	 *
	 * @param string $pattern The pattern from the database.
	 * @return string The prepared regex pattern with delimiters.
	 */
	private function prepare_regex_pattern( $pattern ) {
		// Escape delimiters if present in the pattern.
		$pattern = str_replace( '~', '\~', $pattern );
		// Add delimiters and anchors for full path matching.
		return '~^' . $pattern . '$~';
	}

	/**
	 * Apply regex capture group replacements to the destination URL.
	 *
	 * @param string $destination The destination URL with potential $1, $2, etc placeholders.
	 * @param array  $matches     The preg_match matches array.
	 * @return string The destination URL with replacements applied.
	 */
	private function apply_regex_replacements( $destination, $matches ) {
		// Replace $1, $2, etc. with captured groups.
		foreach ( $matches as $index => $match ) {
			if ( 0 === $index ) {
				continue; // Skip full match.
			}
			$destination = str_replace( '$' . $index, $match, $destination );
		}
		return $destination;
	}

	/**
	 * Maybe create auto-redirect when post slug changes.
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  Post object after update.
	 * @param WP_Post $post_before Post object before update.
	 */
	public function maybe_create_auto_redirect( $post_id, $post_after, $post_before ) {
		// Check if auto-redirect is enabled.
		if ( ! $this->settings->get( 'redirect_auto_slug', true ) ) {
			return;
		}

		// Only for published posts.
		if ( 'publish' !== $post_before->post_status || 'publish' !== $post_after->post_status ) {
			return;
		}

		// Check if slug changed.
		if ( $post_before->post_name === $post_after->post_name ) {
			return;
		}

		// Get old and new permalinks.
		$old_permalink = get_permalink( $post_before );
		$new_permalink = get_permalink( $post_after );

		if ( ! $old_permalink || ! $new_permalink || $old_permalink === $new_permalink ) {
			return;
		}

		// Extract path from old permalink.
		$old_path = wp_parse_url( $old_permalink, PHP_URL_PATH );
		if ( empty( $old_path ) ) {
			return;
		}

		// Normalize path.
		$old_path = '/' . ltrim( $old_path, '/' );
		if ( strlen( $old_path ) > 1 ) {
			$old_path = rtrim( $old_path, '/' );
		}

		// Check if redirect already exists for this path.
		if ( $this->get_redirect_by_from( $old_path ) ) {
			return;
		}

		// Create the redirect.
		$this->add_redirect( $old_path, $new_permalink, 301 );
	}

	/**
	 * Add a new redirect.
	 *
	 * @param string $url_from    Source URL path or regex pattern.
	 * @param string $url_to      Destination URL.
	 * @param int    $status_code HTTP status code.
	 * @param bool   $is_regex    Whether this is a regex redirect.
	 * @return int|false Insert ID on success, false on failure.
	 */
	public function add_redirect( $url_from, $url_to, $status_code = 301, $is_regex = false ) {
		global $wpdb;

		// Normalize url_from (only for non-regex redirects).
		if ( ! $is_regex ) {
			$url_from = '/' . ltrim( $url_from, '/' );
			if ( strlen( $url_from ) > 1 ) {
				$url_from = rtrim( $url_from, '/' );
			}
		}

		// Validate status code.
		$valid_codes = array( 301, 302, 307, 410 );
		if ( ! in_array( $status_code, $valid_codes, true ) ) {
			$status_code = 301;
		}

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table_name,
			array(
				'url_from'    => $url_from,
				'url_to'      => $url_to,
				'status_code' => $status_code,
				'is_regex'    => $is_regex ? 1 : 0,
				'count'       => 0,
			),
			array( '%s', '%s', '%d', '%d', '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get a redirect by its from URL.
	 *
	 * @param string $url_from Source URL path.
	 * @return object|null Redirect object or null.
	 */
	public function get_redirect_by_from( $url_from ) {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table_name} WHERE url_from = %s LIMIT 1",
				$url_from
			)
		);
	}

	/**
	 * Get all redirects.
	 *
	 * @return array Array of redirect objects.
	 */
	public function get_all_redirects() {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT * FROM {$table_name} ORDER BY id DESC"
		);
	}

	/**
	 * Delete a redirect by ID.
	 *
	 * @param int $id Redirect ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_redirect( $id ) {
		global $wpdb;

		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$table_name,
			array( 'id' => $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * AJAX handler for adding a redirect.
	 */
	public function ajax_add_redirect() {
		check_ajax_referer( 'rationalseo_redirects', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'rationalseo' ) ) );
		}

		$url_from    = isset( $_POST['url_from'] ) ? sanitize_text_field( wp_unslash( $_POST['url_from'] ) ) : '';
		$url_to      = isset( $_POST['url_to'] ) ? esc_url_raw( wp_unslash( $_POST['url_to'] ) ) : '';
		$status_code = isset( $_POST['status_code'] ) ? absint( $_POST['status_code'] ) : 301;
		$is_regex    = isset( $_POST['is_regex'] ) && '1' === $_POST['is_regex'];

		if ( empty( $url_from ) ) {
			wp_send_json_error( array( 'message' => __( 'Source URL is required.', 'rationalseo' ) ) );
		}

		// For 410 Gone, url_to can be empty.
		if ( 410 !== $status_code && empty( $url_to ) ) {
			wp_send_json_error( array( 'message' => __( 'Destination URL is required.', 'rationalseo' ) ) );
		}

		// Validate regex pattern if is_regex is true.
		if ( $is_regex ) {
			$test_pattern = '~^' . str_replace( '~', '\~', $url_from ) . '$~';
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( @preg_match( $test_pattern, '' ) === false ) {
				wp_send_json_error( array( 'message' => __( 'Invalid regex pattern.', 'rationalseo' ) ) );
			}
		}

		// Normalize url_from for non-regex redirects.
		$normalized_from = $url_from;
		if ( ! $is_regex ) {
			$normalized_from = '/' . ltrim( $url_from, '/' );
			if ( strlen( $normalized_from ) > 1 ) {
				$normalized_from = rtrim( $normalized_from, '/' );
			}
		}

		// Check if redirect already exists (only for non-regex).
		if ( ! $is_regex && $this->get_redirect_by_from( $normalized_from ) ) {
			wp_send_json_error( array( 'message' => __( 'A redirect for this URL already exists.', 'rationalseo' ) ) );
		}

		$id = $this->add_redirect( $url_from, $url_to, $status_code, $is_regex );

		if ( false === $id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to add redirect.', 'rationalseo' ) ) );
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Redirect added successfully.', 'rationalseo' ),
				'redirect' => array(
					'id'          => $id,
					'url_from'    => $normalized_from,
					'url_to'      => $url_to,
					'status_code' => $status_code,
					'is_regex'    => $is_regex ? 1 : 0,
					'count'       => 0,
				),
			)
		);
	}

	/**
	 * AJAX handler for deleting a redirect.
	 */
	public function ajax_delete_redirect() {
		check_ajax_referer( 'rationalseo_redirects', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'rationalseo' ) ) );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid redirect ID.', 'rationalseo' ) ) );
		}

		if ( ! $this->delete_redirect( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete redirect.', 'rationalseo' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Redirect deleted successfully.', 'rationalseo' ) ) );
	}

	/**
	 * Get Yoast SEO Premium redirects from wp_options.
	 *
	 * Yoast stores redirects in various option keys depending on version.
	 *
	 * @return array Array of Yoast redirect objects, or empty array if none found.
	 */
	public function get_yoast_redirects() {
		$redirects = array();

		// Try different option keys Yoast has used over versions.
		$option_keys = array(
			'wpseo-premium-redirects-base',
			'wpseo_redirect',
			'wpseo-premium-redirects-export-plain',
		);

		foreach ( $option_keys as $key ) {
			$yoast_redirects = get_option( $key, array() );
			if ( ! empty( $yoast_redirects ) && is_array( $yoast_redirects ) ) {
				$redirects = $yoast_redirects;
				break;
			}
		}

		// If still empty, try to find any wpseo redirect options.
		if ( empty( $redirects ) ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$option_row = $wpdb->get_row(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE 'wpseo%redirect%' AND option_value != '' LIMIT 1"
			);
			if ( $option_row && ! empty( $option_row->option_value ) ) {
				$maybe_redirects = maybe_unserialize( $option_row->option_value );
				if ( is_array( $maybe_redirects ) ) {
					$redirects = $maybe_redirects;
				}
			}
		}

		return $redirects;
	}

	/**
	 * Parse Yoast redirect data into a normalized format.
	 *
	 * @param array $yoast_redirects Raw Yoast redirects array.
	 * @return array Normalized redirects with url_from, url_to, status_code, is_regex keys.
	 */
	private function parse_yoast_redirects( $yoast_redirects ) {
		$parsed = array();

		foreach ( $yoast_redirects as $key => $redirect ) {
			// Handle different Yoast formats.
			// Format 1: Array with 'origin', 'url', 'type', 'format' keys (indexed array).
			// Format 2: Key is the origin URL, value is array with 'url', 'type' (associative).
			$url_from    = '';
			$url_to      = '';
			$status_code = 301;
			$is_regex    = false;

			if ( isset( $redirect['origin'] ) ) {
				// Format 1: Standard Yoast format (wpseo-premium-redirects-base).
				$url_from    = isset( $redirect['origin'] ) ? $redirect['origin'] : '';
				$url_to      = isset( $redirect['url'] ) ? $redirect['url'] : '';
				$status_code = isset( $redirect['type'] ) ? absint( $redirect['type'] ) : 301;
				$is_regex    = isset( $redirect['format'] ) && 'regex' === $redirect['format'];
			} elseif ( is_string( $key ) && isset( $redirect['url'] ) ) {
				// Format 2: Key-based format (wpseo-premium-redirects-export-plain).
				$url_from    = $key;
				$url_to      = isset( $redirect['url'] ) ? $redirect['url'] : '';
				$status_code = isset( $redirect['type'] ) ? absint( $redirect['type'] ) : 301;
				$is_regex    = false; // This format doesn't typically use regex.
			}

			// Skip empty or invalid entries.
			if ( empty( $url_from ) ) {
				continue;
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
				'url_to'      => esc_url_raw( $url_to ),
				'status_code' => $status_code,
				'is_regex'    => $is_regex,
			);
		}

		return $parsed;
	}

	/**
	 * Check if a redirect already exists in our table.
	 *
	 * @param string $url_from Source URL path.
	 * @param bool   $is_regex Whether this is a regex redirect.
	 * @return bool True if exists, false otherwise.
	 */
	public function redirect_exists( $url_from, $is_regex = false ) {
		// Normalize non-regex URLs for comparison.
		if ( ! $is_regex ) {
			$url_from = '/' . ltrim( $url_from, '/' );
			if ( strlen( $url_from ) > 1 ) {
				$url_from = rtrim( $url_from, '/' );
			}
		}

		$existing = $this->get_redirect_by_from( $url_from );
		return ! empty( $existing );
	}

	/**
	 * AJAX handler for previewing Yoast redirect import.
	 */
	public function ajax_preview_yoast_import() {
		check_ajax_referer( 'rationalseo_redirects', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'rationalseo' ) ) );
		}

		$yoast_redirects = $this->get_yoast_redirects();

		if ( empty( $yoast_redirects ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No Yoast SEO Premium redirects found. Make sure Yoast SEO Premium is installed and has redirects configured.', 'rationalseo' ),
				)
			);
		}

		$parsed = $this->parse_yoast_redirects( $yoast_redirects );

		if ( empty( $parsed ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No valid redirects found in Yoast data.', 'rationalseo' ),
				)
			);
		}

		// Check for duplicates.
		$to_import = array();
		$duplicates = array();

		foreach ( $parsed as $redirect ) {
			if ( $this->redirect_exists( $redirect['url_from'], $redirect['is_regex'] ) ) {
				$duplicates[] = $redirect;
			} else {
				$to_import[] = $redirect;
			}
		}

		wp_send_json_success(
			array(
				'to_import'   => $to_import,
				'duplicates'  => $duplicates,
				'total_found' => count( $parsed ),
				'message'     => sprintf(
					/* translators: 1: Number of redirects to import, 2: Number of duplicates to skip */
					__( 'Found %1$d redirects to import (%2$d duplicates will be skipped).', 'rationalseo' ),
					count( $to_import ),
					count( $duplicates )
				),
			)
		);
	}

	/**
	 * AJAX handler for importing Yoast redirects.
	 */
	public function ajax_import_yoast_redirects() {
		check_ajax_referer( 'rationalseo_redirects', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'rationalseo' ) ) );
		}

		$yoast_redirects = $this->get_yoast_redirects();

		if ( empty( $yoast_redirects ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No Yoast SEO Premium redirects found.', 'rationalseo' ),
				)
			);
		}

		$parsed = $this->parse_yoast_redirects( $yoast_redirects );

		if ( empty( $parsed ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No valid redirects found in Yoast data.', 'rationalseo' ),
				)
			);
		}

		$imported = 0;
		$skipped  = 0;
		$failed   = 0;
		$imported_redirects = array();

		foreach ( $parsed as $redirect ) {
			// Skip duplicates.
			if ( $this->redirect_exists( $redirect['url_from'], $redirect['is_regex'] ) ) {
				$skipped++;
				continue;
			}

			// Validate regex pattern if needed.
			if ( $redirect['is_regex'] ) {
				$test_pattern = '~^' . str_replace( '~', '\~', $redirect['url_from'] ) . '$~';
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( @preg_match( $test_pattern, '' ) === false ) {
					$failed++;
					continue;
				}
			}

			$id = $this->add_redirect(
				$redirect['url_from'],
				$redirect['url_to'],
				$redirect['status_code'],
				$redirect['is_regex']
			);

			if ( false !== $id ) {
				$imported++;
				// Normalize url_from for display.
				$display_from = $redirect['url_from'];
				if ( ! $redirect['is_regex'] ) {
					$display_from = '/' . ltrim( $display_from, '/' );
					if ( strlen( $display_from ) > 1 ) {
						$display_from = rtrim( $display_from, '/' );
					}
				}
				$imported_redirects[] = array(
					'id'          => $id,
					'url_from'    => $display_from,
					'url_to'      => $redirect['url_to'],
					'status_code' => $redirect['status_code'],
					'is_regex'    => $redirect['is_regex'] ? 1 : 0,
					'count'       => 0,
				);
			} else {
				$failed++;
			}
		}

		if ( 0 === $imported ) {
			wp_send_json_error(
				array(
					'message' => __( 'No redirects were imported. They may all be duplicates or invalid.', 'rationalseo' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'imported'  => $imported,
				'skipped'   => $skipped,
				'failed'    => $failed,
				'redirects' => $imported_redirects,
				'message'   => sprintf(
					/* translators: 1: Number imported, 2: Number skipped, 3: Number failed */
					__( 'Successfully imported %1$d redirects. Skipped %2$d duplicates. %3$d failed.', 'rationalseo' ),
					$imported,
					$skipped,
					$failed
				),
			)
		);
	}
}
