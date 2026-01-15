<?php
/**
 * RationalSEO AIOSEO Importer
 *
 * Imports SEO data from All in One SEO (AIOSEO).
 *
 * @package RationalSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All in One SEO importer class.
 */
class RationalSEO_AIOSEO_Importer implements RationalSEO_Importer_Interface {

	/**
	 * Settings instance.
	 *
	 * @var RationalSEO_Settings
	 */
	private $settings;

	/**
	 * Batch size for post imports.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 100;

	/**
	 * Constructor.
	 *
	 * @param RationalSEO_Settings $settings Settings instance.
	 */
	public function __construct( RationalSEO_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Convert AIOSEO template variables to actual values.
	 *
	 * AIOSEO uses #variable syntax (hash prefix) for dynamic content.
	 * Since RationalSEO doesn't support template variables,
	 * we convert them to actual values during import.
	 *
	 * Supported variables (site-wide, suitable for homepage):
	 * - #site_title - Site name
	 * - #tagline - Site tagline
	 * - #separator_sa - Title separator
	 * - #current_year - Current year
	 * - #current_month - Current month name
	 * - #current_day - Current day number
	 * - #current_date - Current formatted date
	 * - #page_number - Pagination (empty for static)
	 *
	 * Post-specific variables (#post_title, #post_excerpt, etc.)
	 * are NOT converted - if present, the value is skipped entirely.
	 *
	 * @param string $text      Text containing AIOSEO variables.
	 * @param string $separator The separator to use for #separator_sa.
	 * @return string Text with variables replaced, or empty if unrecognized variables found.
	 */
	private function convert_aioseo_variables( $text, $separator = '-' ) {
		if ( empty( $text ) || strpos( $text, '#' ) === false ) {
			return $text;
		}

		$site_name     = get_bloginfo( 'name' );
		$site_tagline  = get_bloginfo( 'description' );
		$current_year  = gmdate( 'Y' );
		$current_month = gmdate( 'F' );
		$current_day   = gmdate( 'j' );
		$current_date  = gmdate( get_option( 'date_format' ) );

		// Build replacements array.
		$replacements = array(
			// Site info.
			'#site_title'    => $site_name,
			'#tagline'       => $site_tagline,

			// Separator.
			'#separator_sa'  => $separator,

			// Pagination (empty for static settings).
			'#page_number'   => '',

			// Date/time.
			'#current_year'  => $current_year,
			'#current_month' => $current_month,
			'#current_day'   => $current_day,
			'#current_date'  => $current_date,
		);

		// Apply replacements (case-insensitive).
		foreach ( $replacements as $var => $value ) {
			$text = str_ireplace( $var, $value, $text );
		}

		// Clean up any double spaces from empty replacements.
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		// Remove trailing/leading separators that might be left over.
		$text = trim( $text, ' ' . $separator );

		// If there are still unrecognized variables (starting with #), return empty.
		// This catches post-specific variables like #post_title, #post_excerpt, etc.
		if ( preg_match( '/#[a-z_0-9]+/i', $text ) ) {
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
		return 'aioseo';
	}

	/**
	 * Get the display name for this importer.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'All in One SEO';
	}

	/**
	 * Get the description for this importer.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Import SEO titles, meta descriptions, redirects, and settings from All in One SEO (AIOSEO).', 'rationalseo' );
	}

	/**
	 * Check if this importer is available (source data exists).
	 *
	 * @return bool
	 */
	public function is_available() {
		// Check for AIOSEO posts table.
		if ( $this->get_post_meta_count() > 0 ) {
			return true;
		}

		// Check for AIOSEO redirects.
		if ( $this->get_redirects_count() > 0 ) {
			return true;
		}

		// Check for AIOSEO settings.
		$aioseo_options = get_option( 'aioseo_options', '' );
		if ( ! empty( $aioseo_options ) ) {
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
		$result       = RationalSEO_Import_Result::success( __( 'Preview generated successfully.', 'rationalseo' ) );
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
		$result        = RationalSEO_Import_Result::success();
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
					__( 'Successfully imported %d items from All in One SEO.', 'rationalseo' ),
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
	 * Get count of posts with AIOSEO data.
	 *
	 * AIOSEO stores post data in a custom table (aioseo_posts), not post meta.
	 *
	 * @return int
	 */
	private function get_post_meta_count() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'aioseo_posts';

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

		// Count posts with any SEO data (title, description, canonical, or robots_noindex).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_name} WHERE (title IS NOT NULL AND title != '') OR (description IS NOT NULL AND description != '') OR (canonical_url IS NOT NULL AND canonical_url != '') OR robots_noindex = 1 OR (og_image_custom_url IS NOT NULL AND og_image_custom_url != '')" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return absint( $count );
	}

	/**
	 * Get count of AIOSEO redirects.
	 *
	 * Redirects are a Pro feature and may not exist.
	 *
	 * @return int
	 */
	private function get_redirects_count() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'aioseo_redirects';

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

		// Count enabled redirects.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_name} WHERE enabled = 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return absint( $count );
	}

	/**
	 * Get parsed AIOSEO options from JSON.
	 *
	 * @return object|null Decoded options or null if not available.
	 */
	private function get_aioseo_options() {
		$options_json = get_option( 'aioseo_options', '' );

		if ( empty( $options_json ) ) {
			return null;
		}

		$options = json_decode( $options_json );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}

		return $options;
	}

	/**
	 * Get count of importable settings.
	 *
	 * @return int
	 */
	private function get_settings_count() {
		$options = $this->get_aioseo_options();

		if ( ! $options ) {
			return 0;
		}

		$count = 0;

		// Get separator for variable conversion.
		$separator = $this->get_option_value( $options, 'searchAppearance.global.separator', '-' );
		// Decode HTML entity if present.
		$separator = html_entity_decode( $separator, ENT_QUOTES, 'UTF-8' );

		// Check each setting.
		if ( ! empty( $separator ) ) {
			$count++;
		}

		// Home title.
		$site_title = $this->get_option_value( $options, 'searchAppearance.global.siteTitle', '' );
		if ( ! empty( $site_title ) ) {
			$converted = $this->convert_aioseo_variables( $site_title, $separator );
			if ( ! empty( $converted ) ) {
				$count++;
			}
		}

		// Home description.
		$meta_desc = $this->get_option_value( $options, 'searchAppearance.global.metaDescription', '' );
		if ( ! empty( $meta_desc ) ) {
			$converted = $this->convert_aioseo_variables( $meta_desc, $separator );
			if ( ! empty( $converted ) ) {
				$count++;
			}
		}

		// Default social image.
		$social_image = $this->get_option_value( $options, 'social.facebook.general.defaultImagePosts', '' );
		if ( ! empty( $social_image ) ) {
			$count++;
		}

		// Twitter card type.
		$twitter_card = $this->get_option_value( $options, 'social.twitter.general.defaultCardType', '' );
		if ( ! empty( $twitter_card ) ) {
			$count++;
		}

		// Site logo.
		$site_logo = $this->get_option_value( $options, 'searchAppearance.global.schema.organizationLogo', '' );
		if ( ! empty( $site_logo ) ) {
			$count++;
		}

		// Google verification.
		$google_verify = $this->get_option_value( $options, 'webmasterTools.google', '' );
		if ( ! empty( $google_verify ) ) {
			$count++;
		}

		// Bing verification.
		$bing_verify = $this->get_option_value( $options, 'webmasterTools.bing', '' );
		if ( ! empty( $bing_verify ) ) {
			$count++;
		}

		return $count;
	}

	/**
	 * Get a nested option value using dot notation.
	 *
	 * @param object $options The options object.
	 * @param string $path    Dot-separated path (e.g., 'searchAppearance.global.separator').
	 * @param mixed  $default Default value if not found.
	 * @return mixed The value or default.
	 */
	private function get_option_value( $options, $path, $default = '' ) {
		$keys    = explode( '.', $path );
		$current = $options;

		foreach ( $keys as $key ) {
			if ( is_object( $current ) && isset( $current->$key ) ) {
				$current = $current->$key;
			} elseif ( is_array( $current ) && isset( $current[ $key ] ) ) {
				$current = $current[ $key ];
			} else {
				return $default;
			}
		}

		return $current;
	}

	/**
	 * Get AIOSEO redirects from database table.
	 *
	 * @return array Parsed redirects array.
	 */
	private function get_aioseo_redirects() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'aioseo_redirects';

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

		// Get enabled redirects.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$redirects_raw = $wpdb->get_results(
			"SELECT source_url, target_url, type, regex FROM {$table_name} WHERE enabled = 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( empty( $redirects_raw ) ) {
			return array();
		}

		return $this->parse_aioseo_redirects( $redirects_raw );
	}

	/**
	 * Parse AIOSEO redirect data into a normalized format.
	 *
	 * @param array $aioseo_redirects Raw AIOSEO redirects from database.
	 * @return array Normalized redirects.
	 */
	private function parse_aioseo_redirects( $aioseo_redirects ) {
		$parsed = array();

		foreach ( $aioseo_redirects as $redirect ) {
			$source_url  = isset( $redirect['source_url'] ) ? $redirect['source_url'] : '';
			$target_url  = isset( $redirect['target_url'] ) ? $redirect['target_url'] : '';
			$status_code = isset( $redirect['type'] ) ? absint( $redirect['type'] ) : 301;
			$is_regex    = isset( $redirect['regex'] ) ? (bool) $redirect['regex'] : false;

			if ( empty( $source_url ) ) {
				continue;
			}

			// Validate status code (skip unsupported codes).
			$valid_codes = array( 301, 302, 307, 410 );
			if ( ! in_array( $status_code, $valid_codes, true ) ) {
				$status_code = 301;
			}

			// For non-410, require a destination.
			if ( 410 !== $status_code && empty( $target_url ) ) {
				continue;
			}

			$parsed[] = array(
				'url_from'    => sanitize_text_field( $source_url ),
				'url_to'      => esc_url_raw( $target_url ),
				'status_code' => $status_code,
				'is_regex'    => $is_regex,
			);
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

		$table_name = $wpdb->prefix . 'aioseo_posts';

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		if ( ! $table_exists ) {
			return array(
				'total'   => 0,
				'samples' => array(),
			);
		}

		// Get sample posts with AIOSEO data (limit to 5 for preview).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT post_id, title, description, canonical_url, robots_noindex, og_image_custom_url FROM {$table_name} WHERE (title IS NOT NULL AND title != '') OR (description IS NOT NULL AND description != '') OR (canonical_url IS NOT NULL AND canonical_url != '') OR robots_noindex = 1 OR (og_image_custom_url IS NOT NULL AND og_image_custom_url != '') LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$preview = array();

		foreach ( $rows as $row ) {
			$post_id = absint( $row['post_id'] );
			$post    = get_post( $post_id );

			if ( ! $post ) {
				continue;
			}

			$meta_preview = array(
				'post_id'    => $post_id,
				'post_title' => $post->post_title,
				'meta'       => array(),
			);

			// Map AIOSEO columns to RationalSEO meta keys.
			if ( ! empty( $row['title'] ) ) {
				$meta_preview['meta']['_rationalseo_title'] = $row['title'];
			}
			if ( ! empty( $row['description'] ) ) {
				$meta_preview['meta']['_rationalseo_desc'] = $row['description'];
			}
			if ( ! empty( $row['canonical_url'] ) ) {
				$meta_preview['meta']['_rationalseo_canonical'] = $row['canonical_url'];
			}
			if ( ! empty( $row['robots_noindex'] ) && '1' === $row['robots_noindex'] ) {
				$meta_preview['meta']['_rationalseo_noindex'] = '1';
			}
			if ( ! empty( $row['og_image_custom_url'] ) ) {
				$meta_preview['meta']['_rationalseo_og_image'] = $row['og_image_custom_url'];
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
		$redirects = $this->get_aioseo_redirects();

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
		$options = $this->get_aioseo_options();

		if ( ! $options ) {
			return array(
				'total'   => 0,
				'samples' => array(),
			);
		}

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
		$separator = $this->get_option_value( $options, 'searchAppearance.global.separator', '' );
		if ( ! empty( $separator ) ) {
			// Decode HTML entity.
			$separator = html_entity_decode( $separator, ENT_QUOTES, 'UTF-8' );
			$samples[] = array(
				'setting' => $setting_labels['separator'],
				'value'   => $separator,
			);
		} else {
			$separator = '-'; // Default for variable conversion.
		}

		// Home title - show converted value.
		$site_title = $this->get_option_value( $options, 'searchAppearance.global.siteTitle', '' );
		if ( ! empty( $site_title ) ) {
			$home_title = $this->convert_aioseo_variables( $site_title, $separator );
			if ( ! empty( $home_title ) ) {
				$samples[] = array(
					'setting' => $setting_labels['home_title'],
					'value'   => $home_title,
				);
			}
		}

		// Home description - show converted value.
		$meta_desc = $this->get_option_value( $options, 'searchAppearance.global.metaDescription', '' );
		if ( ! empty( $meta_desc ) ) {
			$home_desc = $this->convert_aioseo_variables( $meta_desc, $separator );
			if ( ! empty( $home_desc ) ) {
				$samples[] = array(
					'setting' => $setting_labels['home_description'],
					'value'   => $home_desc,
				);
			}
		}

		// Default social image.
		$social_image = $this->get_option_value( $options, 'social.facebook.general.defaultImagePosts', '' );
		if ( ! empty( $social_image ) ) {
			$samples[] = array(
				'setting' => $setting_labels['social_default_image'],
				'value'   => $social_image,
			);
		}

		// Twitter card type.
		$twitter_card = $this->get_option_value( $options, 'social.twitter.general.defaultCardType', '' );
		if ( ! empty( $twitter_card ) ) {
			$samples[] = array(
				'setting' => $setting_labels['twitter_card_type'],
				'value'   => $twitter_card,
			);
		}

		// Site logo.
		$site_logo = $this->get_option_value( $options, 'searchAppearance.global.schema.organizationLogo', '' );
		if ( ! empty( $site_logo ) ) {
			$samples[] = array(
				'setting' => $setting_labels['site_logo'],
				'value'   => $site_logo,
			);
		}

		// Google verification code.
		$google_verify = $this->get_option_value( $options, 'webmasterTools.google', '' );
		if ( ! empty( $google_verify ) ) {
			$samples[] = array(
				'setting' => $setting_labels['verification_google'],
				'value'   => $google_verify,
			);
		}

		// Bing verification code.
		$bing_verify = $this->get_option_value( $options, 'webmasterTools.bing', '' );
		if ( ! empty( $bing_verify ) ) {
			$samples[] = array(
				'setting' => $setting_labels['verification_bing'],
				'value'   => $bing_verify,
			);
		}

		return array(
			'total'   => count( $samples ),
			'samples' => $samples,
		);
	}

	/**
	 * Import post meta from AIOSEO.
	 *
	 * AIOSEO stores data in a custom table (aioseo_posts), not post meta.
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

		$table_name = $wpdb->prefix . 'aioseo_posts';

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		if ( ! $table_exists ) {
			return $result;
		}

		$offset   = 0;
		$has_more = true;

		while ( $has_more ) {
			// Get batch of posts with AIOSEO data.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_id, title, description, canonical_url, robots_noindex, og_image_custom_url FROM {$table_name} WHERE (title IS NOT NULL AND title != '') OR (description IS NOT NULL AND description != '') OR (canonical_url IS NOT NULL AND canonical_url != '') OR robots_noindex = 1 OR (og_image_custom_url IS NOT NULL AND og_image_custom_url != '') LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					self::BATCH_SIZE,
					$offset
				),
				ARRAY_A
			);

			if ( empty( $rows ) ) {
				$has_more = false;
				break;
			}

			foreach ( $rows as $row ) {
				$post_id       = absint( $row['post_id'] );
				$post_imported = false;

				// Check if post already has RationalSEO data.
				if ( $skip_existing && $this->post_has_rationalseo_meta( $post_id ) ) {
					$result['skipped']++;
					continue;
				}

				// Import title.
				if ( ! empty( $row['title'] ) ) {
					$existing = get_post_meta( $post_id, '_rationalseo_title', true );
					if ( empty( $existing ) ) {
						update_post_meta( $post_id, '_rationalseo_title', sanitize_text_field( $row['title'] ) );
						$post_imported = true;
					}
				}

				// Import description.
				if ( ! empty( $row['description'] ) ) {
					$existing = get_post_meta( $post_id, '_rationalseo_desc', true );
					if ( empty( $existing ) ) {
						update_post_meta( $post_id, '_rationalseo_desc', sanitize_text_field( $row['description'] ) );
						$post_imported = true;
					}
				}

				// Import canonical URL.
				if ( ! empty( $row['canonical_url'] ) ) {
					$existing = get_post_meta( $post_id, '_rationalseo_canonical', true );
					if ( empty( $existing ) ) {
						update_post_meta( $post_id, '_rationalseo_canonical', esc_url_raw( $row['canonical_url'] ) );
						$post_imported = true;
					}
				}

				// Import noindex.
				if ( ! empty( $row['robots_noindex'] ) && '1' === $row['robots_noindex'] ) {
					$existing = get_post_meta( $post_id, '_rationalseo_noindex', true );
					if ( empty( $existing ) ) {
						update_post_meta( $post_id, '_rationalseo_noindex', '1' );
						$post_imported = true;
					}
				}

				// Import OG image.
				if ( ! empty( $row['og_image_custom_url'] ) ) {
					$existing = get_post_meta( $post_id, '_rationalseo_og_image', true );
					if ( empty( $existing ) ) {
						update_post_meta( $post_id, '_rationalseo_og_image', esc_url_raw( $row['og_image_custom_url'] ) );
						$post_imported = true;
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
		$rational_keys = array(
			'_rationalseo_title',
			'_rationalseo_desc',
			'_rationalseo_canonical',
			'_rationalseo_noindex',
			'_rationalseo_og_image',
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
	 * Import redirects from AIOSEO.
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

		$redirects = $this->get_aioseo_redirects();

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
	 * Import settings from AIOSEO.
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

		$options = $this->get_aioseo_options();

		if ( ! $options ) {
			return $result;
		}

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
		$separator = $this->get_option_value( $options, 'searchAppearance.global.separator', '' );
		if ( ! empty( $separator ) ) {
			// Decode HTML entity.
			$separator                         = html_entity_decode( $separator, ENT_QUOTES, 'UTF-8' );
			$settings_to_import['separator'] = sanitize_text_field( $separator );
		} else {
			$separator = '-'; // Default for variable conversion.
		}

		// Home title - convert AIOSEO variables to actual values.
		$site_title = $this->get_option_value( $options, 'searchAppearance.global.siteTitle', '' );
		if ( ! empty( $site_title ) ) {
			$home_title = $this->convert_aioseo_variables( $site_title, $separator );
			if ( ! empty( $home_title ) ) {
				$settings_to_import['home_title'] = sanitize_text_field( $home_title );
			}
		}

		// Home description - convert AIOSEO variables to actual values.
		$meta_desc = $this->get_option_value( $options, 'searchAppearance.global.metaDescription', '' );
		if ( ! empty( $meta_desc ) ) {
			$home_desc = $this->convert_aioseo_variables( $meta_desc, $separator );
			if ( ! empty( $home_desc ) ) {
				$settings_to_import['home_description'] = sanitize_text_field( $home_desc );
			}
		}

		// Social default image.
		$social_image = $this->get_option_value( $options, 'social.facebook.general.defaultImagePosts', '' );
		if ( ! empty( $social_image ) ) {
			$settings_to_import['social_default_image'] = esc_url_raw( $social_image );
		}

		// Twitter card type.
		$twitter_card = $this->get_option_value( $options, 'social.twitter.general.defaultCardType', '' );
		if ( ! empty( $twitter_card ) ) {
			$twitter_type = sanitize_text_field( $twitter_card );
			// Validate against allowed values.
			if ( in_array( $twitter_type, array( 'summary', 'summary_large_image' ), true ) ) {
				$settings_to_import['twitter_card_type'] = $twitter_type;
			}
		}

		// Site logo (organization logo from schema).
		$site_logo = $this->get_option_value( $options, 'searchAppearance.global.schema.organizationLogo', '' );
		if ( ! empty( $site_logo ) ) {
			$settings_to_import['site_logo'] = esc_url_raw( $site_logo );
		}

		// Google verification code.
		$google_verify = $this->get_option_value( $options, 'webmasterTools.google', '' );
		if ( ! empty( $google_verify ) ) {
			$settings_to_import['verification_google'] = sanitize_text_field( $google_verify );
		}

		// Bing verification code.
		$bing_verify = $this->get_option_value( $options, 'webmasterTools.bing', '' );
		if ( ! empty( $bing_verify ) ) {
			$settings_to_import['verification_bing'] = sanitize_text_field( $bing_verify );
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
			$result['failed']   = count( $settings_to_import );
			$result['errors'][] = __( 'Failed to save settings.', 'rationalseo' );
		}

		return $result;
	}
}
