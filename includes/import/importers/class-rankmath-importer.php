<?php
/**
 * RationalSEO Rank Math Importer
 *
 * Imports SEO data from Rank Math SEO.
 *
 * @package RationalSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rank Math SEO importer class.
 */
class RationalSEO_RankMath_Importer implements RationalSEO_Importer_Interface {

	/**
	 * Settings instance.
	 *
	 * @var RationalSEO_Settings
	 */
	private $settings;

	/**
	 * Batch size for post meta imports.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 100;

	/**
	 * Post meta key mapping from Rank Math to RationalSEO.
	 *
	 * @var array
	 */
	private $meta_mapping = array(
		'rank_math_title'         => '_rationalseo_title',
		'rank_math_description'   => '_rationalseo_desc',
		'rank_math_canonical_url' => '_rationalseo_canonical',
		'rank_math_facebook_image' => '_rationalseo_og_image',
	);

	/**
	 * Constructor.
	 *
	 * @param RationalSEO_Settings $settings Settings instance.
	 */
	public function __construct( RationalSEO_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Convert Rank Math template variables to actual values.
	 *
	 * Rank Math uses %variable% syntax (single percent) for dynamic content.
	 * Since RationalSEO doesn't support template variables,
	 * we convert them to actual values during import.
	 *
	 * Supported variables (site-wide, suitable for homepage):
	 * - %sitename%, %site_title% - Site name
	 * - %sitedesc% - Site tagline
	 * - %sep%, %separator% - Title separator
	 * - %currentyear%, %current_year% - Current year
	 * - %currentmonth%, %current_month% - Current month name
	 * - %currentday%, %current_day% - Current day number
	 * - %currentdate%, %current_date% - Current formatted date
	 * - %currenttime%, %currenttime(format)% - Current time
	 * - %page%, %pagenumber%, %pagetotal% - Pagination (empty for static)
	 * - %org_name%, %org_url%, %org_logo% - Organization info from Local SEO
	 *
	 * Post-specific variables (%title%, %excerpt%, %category%, etc.)
	 * are NOT converted - if present, the value is skipped entirely.
	 *
	 * @param string $text      Text containing Rank Math variables.
	 * @param string $separator The separator to use for %sep%.
	 * @return string Text with variables replaced, or empty if unrecognized variables found.
	 */
	private function convert_rankmath_variables( $text, $separator = '|' ) {
		if ( empty( $text ) || strpos( $text, '%' ) === false ) {
			return $text;
		}

		$site_name    = get_bloginfo( 'name' );
		$site_tagline = get_bloginfo( 'description' );
		$site_url     = get_bloginfo( 'url' );
		$current_year = gmdate( 'Y' );
		$current_month = gmdate( 'F' );
		$current_day  = gmdate( 'j' );
		$current_date = gmdate( get_option( 'date_format' ) );

		// Get organization info from Rank Math Local SEO settings.
		$rm_titles = get_option( 'rank-math-options-titles', array() );
		$org_name  = ! empty( $rm_titles['knowledgegraph_name'] ) ? $rm_titles['knowledgegraph_name'] : $site_name;
		$org_url   = ! empty( $rm_titles['url'] ) ? $rm_titles['url'] : $site_url;
		$org_logo  = ! empty( $rm_titles['knowledgegraph_logo'] ) ? $rm_titles['knowledgegraph_logo'] : '';

		// Build replacements array with all known variations.
		$replacements = array(
			// Site info.
			'%sitename%'     => $site_name,
			'%site_title%'   => $site_name,
			'%sitedesc%'     => $site_tagline,

			// Separator.
			'%sep%'          => $separator,
			'%separator%'    => $separator,

			// Pagination (empty for static settings).
			'%page%'         => '',
			'%pagenumber%'   => '',
			'%pagetotal%'    => '',

			// Date/time - both formats (with and without underscore).
			'%currentyear%'  => $current_year,
			'%current_year%' => $current_year,
			'%currentmonth%' => $current_month,
			'%current_month%' => $current_month,
			'%currentday%'   => $current_day,
			'%current_day%'  => $current_day,
			'%currentdate%'  => $current_date,
			'%current_date%' => $current_date,
			'%currenttime%'  => gmdate( get_option( 'time_format' ) ),
			'%current_time%' => gmdate( get_option( 'time_format' ) ),

			// Organization info (Local SEO).
			'%org_name%'     => $org_name,
			'%org_url%'      => $org_url,
			'%org_logo%'     => $org_logo,
		);

		// Apply replacements (case-insensitive).
		foreach ( $replacements as $var => $value ) {
			$text = str_ireplace( $var, $value, $text );
		}

		// Handle %currenttime(format)% with custom date format.
		$text = preg_replace_callback(
			'/%currenttime\(([^)]+)\)%/i',
			function ( $matches ) {
				return gmdate( $matches[1] );
			},
			$text
		);

		// Clean up any double spaces from empty replacements.
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		// Remove trailing/leading separators that might be left over.
		$text = trim( $text, ' ' . $separator );

		// If there are still unrecognized variables, return empty to avoid broken output.
		// This catches post-specific variables like %title%, %excerpt%, %category%, etc.
		if ( preg_match( '/%[a-z_0-9]+%/i', $text ) ) {
			return '';
		}

		return $text;
	}

	/**
	 * Get the unique slug for this importer.
	 *
	 * @return string
	 */
	public function get_slug() {
		return 'rankmath';
	}

	/**
	 * Get the display name for this importer.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'Rank Math';
	}

	/**
	 * Get the description for this importer.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Import SEO titles, meta descriptions, redirects, and settings from Rank Math SEO.', 'rationalseo' );
	}

	/**
	 * Check if this importer is available (source data exists).
	 *
	 * @return bool
	 */
	public function is_available() {
		// Check for Rank Math post meta.
		if ( $this->get_post_meta_count() > 0 ) {
			return true;
		}

		// Check for Rank Math redirects.
		if ( $this->get_redirects_count() > 0 ) {
			return true;
		}

		// Check for Rank Math settings.
		$rm_titles = get_option( 'rank-math-options-titles', array() );
		if ( ! empty( $rm_titles ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the importable items with counts.
	 *
	 * @return array
	 */
	public function get_importable_items() {
		$post_meta_count = $this->get_post_meta_count();
		$redirects_count = $this->get_redirects_count();
		$settings_count  = $this->get_settings_count();

		return array(
			'post_meta' => array(
				'label'     => __( 'Post SEO Data', 'rationalseo' ),
				'count'     => $post_meta_count,
				'available' => $post_meta_count > 0,
			),
			'redirects' => array(
				'label'     => __( 'Redirects', 'rationalseo' ),
				'count'     => $redirects_count,
				'available' => $redirects_count > 0,
			),
			'settings'  => array(
				'label'     => __( 'Settings', 'rationalseo' ),
				'count'     => $settings_count,
				'available' => $settings_count > 0,
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
		$result = RationalSEO_Import_Result::success( __( 'Preview generated successfully.', 'rationalseo' ) );
		$preview_data = array();

		// If no specific types requested, preview all available.
		if ( empty( $item_types ) ) {
			$item_types = array( 'post_meta', 'redirects', 'settings' );
		}

		if ( in_array( 'post_meta', $item_types, true ) ) {
			$preview_data['post_meta'] = $this->preview_post_meta();
		}

		if ( in_array( 'redirects', $item_types, true ) ) {
			$preview_data['redirects'] = $this->preview_redirects();
		}

		if ( in_array( 'settings', $item_types, true ) ) {
			$preview_data['settings'] = $this->preview_settings();
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
		$result = RationalSEO_Import_Result::success();
		$skip_existing = ! empty( $options['skip_existing'] );

		// If no specific types requested, import all available.
		if ( empty( $item_types ) ) {
			$item_types = array( 'post_meta', 'redirects', 'settings' );
		}

		$import_results = array();

		if ( in_array( 'post_meta', $item_types, true ) ) {
			$import_results['post_meta'] = $this->import_post_meta( $skip_existing );
		}

		if ( in_array( 'redirects', $item_types, true ) ) {
			$import_results['redirects'] = $this->import_redirects( $skip_existing );
		}

		if ( in_array( 'settings', $item_types, true ) ) {
			$import_results['settings'] = $this->import_settings();
		}

		// Aggregate results.
		$total_imported = 0;
		$total_skipped = 0;
		$total_failed = 0;

		foreach ( $import_results as $type => $type_result ) {
			$total_imported += $type_result['imported'];
			$total_skipped += $type_result['skipped'];
			$total_failed += $type_result['failed'];

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
					__( 'Successfully imported %d items from Rank Math.', 'rationalseo' ),
					$total_imported
				)
			);
		} elseif ( $total_skipped > 0 ) {
			$result->set_message( __( 'All items were skipped (already exist).', 'rationalseo' ) );
		} else {
			$result->set_message( __( 'No items were imported.', 'rationalseo' ) );
		}

		return $result;
	}

	/**
	 * Get count of posts with Rank Math meta data.
	 *
	 * @return int
	 */
	private function get_post_meta_count() {
		global $wpdb;

		// Include both regular meta keys and robots meta.
		$meta_keys = array_merge(
			array_keys( $this->meta_mapping ),
			array( 'rank_math_robots' )
		);
		$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders are dynamically generated from array count.
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN ($placeholders) AND meta_value != ''", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$meta_keys
			)
		);

		return absint( $count );
	}

	/**
	 * Get count of Rank Math redirects.
	 *
	 * @return int
	 */
	private function get_redirects_count() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rank_math_redirections';

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		if ( ! $table_exists ) {
			return 0;
		}

		// Count active redirects.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is safely constructed from $wpdb->prefix and a hardcoded string.
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_name} WHERE status = 'active'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return absint( $count );
	}

	/**
	 * Check if there are importable settings.
	 *
	 * @return bool
	 */
	private function has_importable_settings() {
		$rm_titles  = get_option( 'rank-math-options-titles', array() );
		$rm_general = get_option( 'rank-math-options-general', array() );

		// Check for specific settings we can import from titles.
		if ( ! empty( $rm_titles['title_separator'] ) ) {
			return true;
		}
		if ( ! empty( $rm_titles['homepage_title'] ) ) {
			return true;
		}
		if ( ! empty( $rm_titles['homepage_description'] ) ) {
			return true;
		}
		if ( ! empty( $rm_titles['open_graph_image'] ) ) {
			return true;
		}
		if ( ! empty( $rm_titles['twitter_card_type'] ) ) {
			return true;
		}
		if ( ! empty( $rm_titles['knowledgegraph_logo'] ) ) {
			return true;
		}

		// Check for verification codes from general settings.
		if ( ! empty( $rm_general['google_verify'] ) ) {
			return true;
		}
		if ( ! empty( $rm_general['bing_verify'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get count of importable settings.
	 *
	 * @return int
	 */
	private function get_settings_count() {
		$rm_titles  = get_option( 'rank-math-options-titles', array() );
		$rm_general = get_option( 'rank-math-options-general', array() );

		$count = 0;

		// Count settings from titles options.
		if ( ! empty( $rm_titles['title_separator'] ) ) {
			$count++;
		}
		if ( ! empty( $rm_titles['homepage_title'] ) ) {
			// Only count if variable conversion produces a result.
			$separator  = ! empty( $rm_titles['title_separator'] ) ? $rm_titles['title_separator'] : '-';
			$home_title = $this->convert_rankmath_variables( $rm_titles['homepage_title'], $separator );
			if ( ! empty( $home_title ) ) {
				$count++;
			}
		}
		if ( ! empty( $rm_titles['homepage_description'] ) ) {
			$separator = ! empty( $rm_titles['title_separator'] ) ? $rm_titles['title_separator'] : '-';
			$home_desc = $this->convert_rankmath_variables( $rm_titles['homepage_description'], $separator );
			if ( ! empty( $home_desc ) ) {
				$count++;
			}
		}
		if ( ! empty( $rm_titles['open_graph_image'] ) ) {
			$count++;
		}
		if ( ! empty( $rm_titles['twitter_card_type'] ) ) {
			$count++;
		}
		if ( ! empty( $rm_titles['knowledgegraph_logo'] ) ) {
			$count++;
		}

		// Count verification codes from general settings.
		if ( ! empty( $rm_general['google_verify'] ) ) {
			$count++;
		}
		if ( ! empty( $rm_general['bing_verify'] ) ) {
			$count++;
		}

		return $count;
	}

	/**
	 * Get Rank Math redirects from database table.
	 *
	 * @return array Parsed redirects array.
	 */
	private function get_rankmath_redirects() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'rank_math_redirections';

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		if ( ! $table_exists ) {
			return array();
		}

		// Get active redirects.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is safely constructed from $wpdb->prefix and a hardcoded string.
		$redirects_raw = $wpdb->get_results(
			"SELECT sources, url_to, header_code FROM {$table_name} WHERE status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( empty( $redirects_raw ) ) {
			return array();
		}

		return $this->parse_rankmath_redirects( $redirects_raw );
	}

	/**
	 * Parse Rank Math redirect data into a normalized format.
	 *
	 * @param array $rankmath_redirects Raw Rank Math redirects from database.
	 * @return array Normalized redirects.
	 */
	private function parse_rankmath_redirects( $rankmath_redirects ) {
		$parsed = array();

		foreach ( $rankmath_redirects as $redirect ) {
			$sources = maybe_unserialize( $redirect['sources'] );

			if ( ! is_array( $sources ) || empty( $sources ) ) {
				continue;
			}

			$url_to      = isset( $redirect['url_to'] ) ? $redirect['url_to'] : '';
			$status_code = isset( $redirect['header_code'] ) ? absint( $redirect['header_code'] ) : 301;

			// Validate status code (skip unsupported codes).
			$valid_codes = array( 301, 302, 307, 410 );
			if ( ! in_array( $status_code, $valid_codes, true ) ) {
				$status_code = 301;
			}

			// For non-410, require a destination.
			if ( 410 !== $status_code && empty( $url_to ) ) {
				continue;
			}

			// Each redirect can have multiple sources.
			foreach ( $sources as $source ) {
				$pattern    = isset( $source['pattern'] ) ? $source['pattern'] : '';
				$comparison = isset( $source['comparison'] ) ? $source['comparison'] : 'exact';

				if ( empty( $pattern ) ) {
					continue;
				}

				// Determine if this is a regex redirect.
				// Rank Math supports: exact, contains, start, end, regex.
				$is_regex = ( 'regex' === $comparison );

				// For non-regex patterns with contains/start/end, we convert to regex.
				if ( 'contains' === $comparison ) {
					$pattern = '.*' . preg_quote( $pattern, '/' ) . '.*';
					$is_regex = true;
				} elseif ( 'start' === $comparison ) {
					$pattern = '^' . preg_quote( $pattern, '/' ) . '.*';
					$is_regex = true;
				} elseif ( 'end' === $comparison ) {
					$pattern = '.*' . preg_quote( $pattern, '/' ) . '$';
					$is_regex = true;
				}

				$parsed[] = array(
					'url_from'    => sanitize_text_field( $pattern ),
					'url_to'      => esc_url_raw( $url_to ),
					'status_code' => $status_code,
					'is_regex'    => $is_regex,
				);
			}
		}

		return $parsed;
	}

	/**
	 * Preview post meta import.
	 *
	 * @return array Preview data.
	 */
	private function preview_post_meta() {
		global $wpdb;

		$meta_keys = array_merge(
			array_keys( $this->meta_mapping ),
			array( 'rank_math_robots' )
		);
		$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

		// Get sample posts with Rank Math meta (limit to 5 for preview).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_ids = $wpdb->get_col(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders are dynamically generated from array count.
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ($placeholders) AND meta_value != '' LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$meta_keys
			)
		);

		$preview = array();

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$meta_preview = array(
				'post_id'    => $post_id,
				'post_title' => $post->post_title,
				'meta'       => array(),
			);

			// Standard meta mapping.
			foreach ( $this->meta_mapping as $rm_key => $rational_key ) {
				$value = get_post_meta( $post_id, $rm_key, true );
				if ( ! empty( $value ) ) {
					$meta_preview['meta'][ $rational_key ] = $value;
				}
			}

			// Handle robots meta (noindex).
			$robots = get_post_meta( $post_id, 'rank_math_robots', true );
			if ( ! empty( $robots ) ) {
				$robots = maybe_unserialize( $robots );
				if ( is_array( $robots ) && in_array( 'noindex', $robots, true ) ) {
					$meta_preview['meta']['_rationalseo_noindex'] = '1';
				}
			}

			if ( ! empty( $meta_preview['meta'] ) ) {
				$preview[] = $meta_preview;
			}
		}

		return array(
			'total'   => $this->get_post_meta_count(),
			'samples' => $preview,
		);
	}

	/**
	 * Preview redirects import.
	 *
	 * @return array Preview data.
	 */
	private function preview_redirects() {
		$redirects = $this->get_rankmath_redirects();

		// Return first 5 for preview.
		$samples = array_slice( $redirects, 0, 5 );

		return array(
			'total'   => count( $redirects ),
			'samples' => $samples,
		);
	}

	/**
	 * Preview settings import.
	 *
	 * @return array Preview data.
	 */
	private function preview_settings() {
		$rm_titles  = get_option( 'rank-math-options-titles', array() );
		$rm_general = get_option( 'rank-math-options-general', array() );

		// Setting labels for display.
		$setting_labels = array(
			'separator'            => __( 'Title Separator', 'rationalseo' ),
			'home_title'           => __( 'Home Title', 'rationalseo' ),
			'home_description'     => __( 'Home Description', 'rationalseo' ),
			'social_default_image' => __( 'Default Social Image', 'rationalseo' ),
			'twitter_card_type'    => __( 'Twitter Card Type', 'rationalseo' ),
			'site_logo'            => __( 'Site Logo', 'rationalseo' ),
			'verification_google'  => __( 'Google Verification', 'rationalseo' ),
			'verification_bing'    => __( 'Bing Verification', 'rationalseo' ),
		);

		$samples = array();

		// Separator - process first since it's needed for variable conversion.
		$separator = '-'; // Rank Math default.
		if ( ! empty( $rm_titles['title_separator'] ) ) {
			$separator = $rm_titles['title_separator'];
			$samples[] = array(
				'setting' => $setting_labels['separator'],
				'value'   => $separator,
			);
		}

		// Home title - show converted value.
		if ( ! empty( $rm_titles['homepage_title'] ) ) {
			$home_title = $this->convert_rankmath_variables( $rm_titles['homepage_title'], $separator );
			if ( ! empty( $home_title ) ) {
				$samples[] = array(
					'setting' => $setting_labels['home_title'],
					'value'   => $home_title,
				);
			}
		}

		// Home description - show converted value.
		if ( ! empty( $rm_titles['homepage_description'] ) ) {
			$home_desc = $this->convert_rankmath_variables( $rm_titles['homepage_description'], $separator );
			if ( ! empty( $home_desc ) ) {
				$samples[] = array(
					'setting' => $setting_labels['home_description'],
					'value'   => $home_desc,
				);
			}
		}

		// Default social image.
		if ( ! empty( $rm_titles['open_graph_image'] ) ) {
			$samples[] = array(
				'setting' => $setting_labels['social_default_image'],
				'value'   => $rm_titles['open_graph_image'],
			);
		}

		// Twitter card type.
		if ( ! empty( $rm_titles['twitter_card_type'] ) ) {
			$samples[] = array(
				'setting' => $setting_labels['twitter_card_type'],
				'value'   => $rm_titles['twitter_card_type'],
			);
		}

		// Site logo (knowledge graph logo).
		if ( ! empty( $rm_titles['knowledgegraph_logo'] ) ) {
			$samples[] = array(
				'setting' => $setting_labels['site_logo'],
				'value'   => $rm_titles['knowledgegraph_logo'],
			);
		}

		// Google verification code.
		if ( ! empty( $rm_general['google_verify'] ) ) {
			$samples[] = array(
				'setting' => $setting_labels['verification_google'],
				'value'   => $rm_general['google_verify'],
			);
		}

		// Bing verification code.
		if ( ! empty( $rm_general['bing_verify'] ) ) {
			$samples[] = array(
				'setting' => $setting_labels['verification_bing'],
				'value'   => $rm_general['bing_verify'],
			);
		}

		return array(
			'total'   => count( $samples ),
			'samples' => $samples,
		);
	}

	/**
	 * Import post meta from Rank Math.
	 *
	 * @param bool $skip_existing Whether to skip posts that already have RationalSEO data.
	 * @return array Import results.
	 */
	private function import_post_meta( $skip_existing = false ) {
		global $wpdb;

		$result = array(
			'imported' => 0,
			'skipped'  => 0,
			'failed'   => 0,
			'errors'   => array(),
		);

		$meta_keys = array_merge(
			array_keys( $this->meta_mapping ),
			array( 'rank_math_robots' )
		);
		$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

		$offset = 0;
		$has_more = true;

		while ( $has_more ) {
			// Get batch of post IDs with Rank Math meta.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$post_ids = $wpdb->get_col(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders are dynamically generated from array count.
				$wpdb->prepare(
					"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ($placeholders) AND meta_value != '' LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					...array_merge( $meta_keys, array( self::BATCH_SIZE, $offset ) )
				)
			);

			if ( empty( $post_ids ) ) {
				$has_more = false;
				break;
			}

			foreach ( $post_ids as $post_id ) {
				$post_imported = false;

				// Check if post already has RationalSEO data.
				if ( $skip_existing && $this->post_has_rationalseo_meta( $post_id ) ) {
					$result['skipped']++;
					continue;
				}

				// Standard meta mapping.
				foreach ( $this->meta_mapping as $rm_key => $rational_key ) {
					$value = get_post_meta( $post_id, $rm_key, true );

					if ( empty( $value ) ) {
						continue;
					}

					// Sanitize based on key type.
					if ( '_rationalseo_canonical' === $rational_key || '_rationalseo_og_image' === $rational_key ) {
						$value = esc_url_raw( $value );
					} else {
						$value = sanitize_text_field( $value );
					}

					// Skip if already has this specific meta (even if skip_existing is false).
					$existing = get_post_meta( $post_id, $rational_key, true );
					if ( ! empty( $existing ) ) {
						continue;
					}

					update_post_meta( $post_id, $rational_key, $value );
					$post_imported = true;
				}

				// Handle robots meta (noindex).
				$robots = get_post_meta( $post_id, 'rank_math_robots', true );
				if ( ! empty( $robots ) ) {
					$robots = maybe_unserialize( $robots );
					if ( is_array( $robots ) && in_array( 'noindex', $robots, true ) ) {
						$existing = get_post_meta( $post_id, '_rationalseo_noindex', true );
						if ( empty( $existing ) ) {
							update_post_meta( $post_id, '_rationalseo_noindex', '1' );
							$post_imported = true;
						}
					}
				}

				if ( $post_imported ) {
					$result['imported']++;
				}
			}

			$offset += self::BATCH_SIZE;

			// Safety check to prevent infinite loops.
			if ( $offset > 100000 ) {
				$has_more = false;
			}
		}

		return $result;
	}

	/**
	 * Check if a post already has RationalSEO meta data.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function post_has_rationalseo_meta( $post_id ) {
		$rational_keys = array_merge(
			array_values( $this->meta_mapping ),
			array( '_rationalseo_noindex' )
		);

		foreach ( $rational_keys as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			if ( ! empty( $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Import redirects from Rank Math.
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

		$redirects = $this->get_rankmath_redirects();

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

	/**
	 * Import settings from Rank Math.
	 *
	 * @return array Import results.
	 */
	private function import_settings() {
		$result = array(
			'imported' => 0,
			'skipped'  => 0,
			'failed'   => 0,
			'errors'   => array(),
			'items'    => array(),
		);

		$rm_titles  = get_option( 'rank-math-options-titles', array() );
		$rm_general = get_option( 'rank-math-options-general', array() );

		$settings_to_import = array();

		// Setting labels for display.
		$setting_labels = array(
			'separator'            => __( 'Title Separator', 'rationalseo' ),
			'home_title'           => __( 'Home Title', 'rationalseo' ),
			'home_description'     => __( 'Home Description', 'rationalseo' ),
			'social_default_image' => __( 'Default Social Image', 'rationalseo' ),
			'twitter_card_type'    => __( 'Twitter Card Type', 'rationalseo' ),
			'site_logo'            => __( 'Site Logo', 'rationalseo' ),
			'verification_google'  => __( 'Google Verification', 'rationalseo' ),
			'verification_bing'    => __( 'Bing Verification', 'rationalseo' ),
		);

		// Separator - process first since it's needed for variable conversion.
		$separator = '-'; // Rank Math default.
		if ( ! empty( $rm_titles['title_separator'] ) ) {
			$separator = $rm_titles['title_separator'];
			$settings_to_import['separator'] = sanitize_text_field( $separator );
		}

		// Home title - convert Rank Math variables to actual values.
		if ( ! empty( $rm_titles['homepage_title'] ) ) {
			$home_title = $this->convert_rankmath_variables( $rm_titles['homepage_title'], $separator );
			if ( ! empty( $home_title ) ) {
				$settings_to_import['home_title'] = sanitize_text_field( $home_title );
			}
		}

		// Home description - convert Rank Math variables to actual values.
		if ( ! empty( $rm_titles['homepage_description'] ) ) {
			$home_desc = $this->convert_rankmath_variables( $rm_titles['homepage_description'], $separator );
			if ( ! empty( $home_desc ) ) {
				$settings_to_import['home_description'] = sanitize_text_field( $home_desc );
			}
		}

		// Social default image.
		if ( ! empty( $rm_titles['open_graph_image'] ) ) {
			$settings_to_import['social_default_image'] = esc_url_raw( $rm_titles['open_graph_image'] );
		}

		// Twitter card type.
		if ( ! empty( $rm_titles['twitter_card_type'] ) ) {
			$twitter_type = sanitize_text_field( $rm_titles['twitter_card_type'] );
			// Validate against allowed values.
			if ( in_array( $twitter_type, array( 'summary', 'summary_large_image' ), true ) ) {
				$settings_to_import['twitter_card_type'] = $twitter_type;
			}
		}

		// Site logo (knowledge graph logo).
		if ( ! empty( $rm_titles['knowledgegraph_logo'] ) ) {
			$settings_to_import['site_logo'] = esc_url_raw( $rm_titles['knowledgegraph_logo'] );
		}

		// Google verification code.
		if ( ! empty( $rm_general['google_verify'] ) ) {
			$settings_to_import['verification_google'] = sanitize_text_field( $rm_general['google_verify'] );
		}

		// Bing verification code.
		if ( ! empty( $rm_general['bing_verify'] ) ) {
			$settings_to_import['verification_bing'] = sanitize_text_field( $rm_general['bing_verify'] );
		}

		if ( empty( $settings_to_import ) ) {
			return $result;
		}

		// Import settings.
		if ( $this->settings->set_multiple( $settings_to_import ) ) {
			$result['imported'] = count( $settings_to_import );

			// Build items list with labels and values.
			foreach ( $settings_to_import as $key => $value ) {
				$result['items'][] = array(
					'key'   => $key,
					'label' => isset( $setting_labels[ $key ] ) ? $setting_labels[ $key ] : $key,
					'value' => $value,
				);
			}
		} else {
			$result['failed'] = count( $settings_to_import );
			$result['errors'][] = __( 'Failed to save settings.', 'rationalseo' );
		}

		return $result;
	}
}
