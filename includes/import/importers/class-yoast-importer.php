<?php
/**
 * RationalSEO Yoast SEO Importer
 *
 * Imports SEO data from Yoast SEO (Free and Premium).
 *
 * @package RationalSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Yoast SEO importer class.
 */
class RationalSEO_Yoast_Importer implements RationalSEO_Importer_Interface {

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
	 * Post meta key mapping from Yoast to RationalSEO.
	 *
	 * @var array
	 */
	private $meta_mapping = array(
		'_yoast_wpseo_title'              => '_rationalseo_title',
		'_yoast_wpseo_metadesc'           => '_rationalseo_desc',
		'_yoast_wpseo_canonical'          => '_rationalseo_canonical',
		'_yoast_wpseo_meta-robots-noindex' => '_rationalseo_noindex',
		'_yoast_wpseo_opengraph-image'    => '_rationalseo_og_image',
	);

	/**
	 * Yoast separator codes to actual characters.
	 *
	 * @var array
	 */
	private $separator_map = array(
		'sc-dash'   => '-',
		'sc-ndash'  => '–',
		'sc-mdash'  => '—',
		'sc-colon'  => ':',
		'sc-middot' => '·',
		'sc-bull'   => '•',
		'sc-star'   => '*',
		'sc-smstar' => '⋆',
		'sc-pipe'   => '|',
		'sc-tilde'  => '~',
		'sc-laquo'  => '«',
		'sc-raquo'  => '»',
		'sc-lt'     => '<',
		'sc-gt'     => '>',
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
	 * Convert Yoast template variables to actual values.
	 *
	 * Yoast uses %%variable%% syntax for dynamic content.
	 * Since RationalSEO doesn't support template variables,
	 * we convert them to actual values during import.
	 *
	 * Supported variables (site-wide, suitable for homepage):
	 * - %%sitename%%, %%sitetitle%% - Site name
	 * - %%sitedesc%%, %%tagline%% - Site tagline
	 * - %%sep%%, %%separator%% - Title separator
	 * - %%page%%, %%pagenumber%%, %%pagetotal%% - Pagination (empty for static)
	 * - %%currentyear%%, %%current_year%% - Current year
	 * - %%currentmonth%%, %%current_month%% - Current month name
	 * - %%currentday%%, %%current_day%% - Current day number
	 * - %%currentdate%%, %%current_date%% - Current formatted date
	 *
	 * Post-specific variables (%%title%%, %%excerpt%%, %%category%%, etc.)
	 * are NOT converted - if present, the value is skipped entirely.
	 *
	 * @param string $text      Text containing Yoast variables.
	 * @param string $separator The separator to use for %%sep%%.
	 * @return string Text with variables replaced, or empty if unrecognized variables found.
	 */
	private function convert_yoast_variables( $text, $separator = '|' ) {
		if ( empty( $text ) || strpos( $text, '%%' ) === false ) {
			return $text;
		}

		$site_name    = get_bloginfo( 'name' );
		$site_tagline = get_bloginfo( 'description' );
		$current_year = gmdate( 'Y' );
		$current_month = gmdate( 'F' );
		$current_day  = gmdate( 'j' );
		$current_date = gmdate( get_option( 'date_format' ) );

		// Build replacements array with all known variations.
		$replacements = array(
			// Site info.
			'%%sitename%%'     => $site_name,
			'%%sitetitle%%'    => $site_name,
			'%%sitedesc%%'     => $site_tagline,
			'%%tagline%%'      => $site_tagline,

			// Separator.
			'%%sep%%'          => $separator,
			'%%separator%%'    => $separator,

			// Pagination (empty for static settings).
			'%%page%%'         => '',
			'%%pagenumber%%'   => '',
			'%%pagetotal%%'    => '',

			// Date/time - both formats (with and without underscore).
			'%%currentyear%%'  => $current_year,
			'%%current_year%%' => $current_year,
			'%%currentmonth%%' => $current_month,
			'%%current_month%%' => $current_month,
			'%%currentday%%'   => $current_day,
			'%%current_day%%'  => $current_day,
			'%%currentdate%%'  => $current_date,
			'%%current_date%%' => $current_date,
			'%%currenttime%%'  => gmdate( get_option( 'time_format' ) ),
			'%%current_time%%' => gmdate( get_option( 'time_format' ) ),
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

		// If there are still unrecognized variables, return empty to avoid broken output.
		// This catches post-specific variables like %%title%%, %%excerpt%%, %%category%%, etc.
		if ( preg_match( '/%%[a-z_0-9]+%%/i', $text ) ) {
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
		return 'yoast';
	}

	/**
	 * Get the display name for this importer.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'Yoast SEO';
	}

	/**
	 * Get the description for this importer.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Import SEO titles, meta descriptions, redirects, and settings from Yoast SEO.', 'rationalseo' );
	}

	/**
	 * Check if this importer is available (source data exists).
	 *
	 * @return bool
	 */
	public function is_available() {
		// Check for Yoast post meta.
		if ( $this->get_post_meta_count() > 0 ) {
			return true;
		}

		// Check for Yoast redirects.
		if ( $this->get_redirects_count() > 0 ) {
			return true;
		}

		// Check for Yoast settings.
		$yoast_titles = get_option( 'wpseo_titles', array() );
		$yoast_social = get_option( 'wpseo_social', array() );
		if ( ! empty( $yoast_titles ) || ! empty( $yoast_social ) ) {
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
					__( 'Successfully imported %d items from Yoast SEO.', 'rationalseo' ),
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
	 * Get count of posts with Yoast meta data.
	 *
	 * @return int
	 */
	private function get_post_meta_count() {
		global $wpdb;

		$yoast_keys = array_keys( $this->meta_mapping );
		$placeholders = implode( ',', array_fill( 0, count( $yoast_keys ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders are dynamically generated from array count.
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN ($placeholders) AND meta_value != ''", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$yoast_keys
			)
		);

		return absint( $count );
	}

	/**
	 * Get count of Yoast redirects.
	 *
	 * @return int
	 */
	private function get_redirects_count() {
		$redirects = $this->get_yoast_redirects();
		return count( $redirects );
	}

	/**
	 * Check if there are importable settings.
	 *
	 * @return bool
	 */
	private function has_importable_settings() {
		return $this->get_settings_count() > 0;
	}

	/**
	 * Get count of importable settings.
	 *
	 * @return int
	 */
	private function get_settings_count() {
		$yoast_main   = get_option( 'wpseo', array() );
		$yoast_titles = get_option( 'wpseo_titles', array() );
		$yoast_social = get_option( 'wpseo_social', array() );

		$count = 0;

		// Separator.
		if ( ! empty( $yoast_titles['separator'] ) ) {
			$count++;
		}

		// Get separator for variable conversion.
		$separator = '|';
		if ( ! empty( $yoast_titles['separator'] ) ) {
			$separator = $yoast_titles['separator'];
			if ( isset( $this->separator_map[ $separator ] ) ) {
				$separator = $this->separator_map[ $separator ];
			}
		}

		// Home title (only count if variable conversion produces a result).
		if ( ! empty( $yoast_titles['title-home-wpseo'] ) ) {
			$home_title = $this->convert_yoast_variables( $yoast_titles['title-home-wpseo'], $separator );
			if ( ! empty( $home_title ) ) {
				$count++;
			}
		}

		// Home description.
		if ( ! empty( $yoast_titles['metadesc-home-wpseo'] ) ) {
			$home_desc = $this->convert_yoast_variables( $yoast_titles['metadesc-home-wpseo'], $separator );
			if ( ! empty( $home_desc ) ) {
				$count++;
			}
		}

		// Default social image.
		if ( ! empty( $yoast_social['og_default_image'] ) ) {
			$count++;
		}

		// Twitter card type.
		if ( ! empty( $yoast_social['twitter_card_type'] ) ) {
			$count++;
		}

		// Site logo (company logo).
		if ( ! empty( $yoast_titles['company_logo'] ) ) {
			$count++;
		}

		// Google verification.
		if ( ! empty( $yoast_main['googleverify'] ) ) {
			$count++;
		}

		// Bing verification.
		if ( ! empty( $yoast_main['msverify'] ) ) {
			$count++;
		}

		return $count;
	}

	/**
	 * Get Yoast redirects from options.
	 *
	 * @return array Parsed redirects array.
	 */
	private function get_yoast_redirects() {
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
				$redirects = $this->parse_yoast_redirects( $yoast_redirects );
				if ( ! empty( $redirects ) ) {
					break;
				}
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
					$redirects = $this->parse_yoast_redirects( $maybe_redirects );
				}
			}
		}

		return $redirects;
	}

	/**
	 * Parse Yoast redirect data into a normalized format.
	 *
	 * @param array $yoast_redirects Raw Yoast redirects array.
	 * @return array Normalized redirects.
	 */
	private function parse_yoast_redirects( $yoast_redirects ) {
		$parsed = array();

		foreach ( $yoast_redirects as $key => $redirect ) {
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
				$is_regex    = false;
			}

			// Skip empty or invalid entries.
			if ( empty( $url_from ) ) {
				continue;
			}

			// For non-410, require a destination.
			if ( 410 !== $status_code && empty( $url_to ) ) {
				continue;
			}

			// Validate status code (skip 451 - not supported).
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
	 * Preview post meta import.
	 *
	 * @return array Preview data.
	 */
	private function preview_post_meta() {
		global $wpdb;

		$yoast_keys = array_keys( $this->meta_mapping );
		$placeholders = implode( ',', array_fill( 0, count( $yoast_keys ), '%s' ) );

		// Get sample posts with Yoast meta (limit to 5 for preview).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_ids = $wpdb->get_col(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders are dynamically generated from array count.
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ($placeholders) AND meta_value != '' LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$yoast_keys
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

			foreach ( $this->meta_mapping as $yoast_key => $rational_key ) {
				$value = get_post_meta( $post_id, $yoast_key, true );
				if ( ! empty( $value ) ) {
					// Special handling for noindex.
					if ( '_yoast_wpseo_meta-robots-noindex' === $yoast_key ) {
						$value = ( '1' === $value || 1 === $value ) ? '1' : '';
					}
					if ( ! empty( $value ) ) {
						$meta_preview['meta'][ $rational_key ] = $value;
					}
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
		$redirects = $this->get_yoast_redirects();

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
		$yoast_main   = get_option( 'wpseo', array() );
		$yoast_titles = get_option( 'wpseo_titles', array() );
		$yoast_social = get_option( 'wpseo_social', array() );

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
		$separator = '|'; // Default.
		if ( ! empty( $yoast_titles['separator'] ) ) {
			$separator = $yoast_titles['separator'];
			if ( isset( $this->separator_map[ $separator ] ) ) {
				$separator = $this->separator_map[ $separator ];
			}
			$samples[] = array(
				'setting' => $setting_labels['separator'],
				'value'   => $separator,
			);
		}

		// Home title - show converted value.
		if ( ! empty( $yoast_titles['title-home-wpseo'] ) ) {
			$home_title = $this->convert_yoast_variables( $yoast_titles['title-home-wpseo'], $separator );
			if ( ! empty( $home_title ) ) {
				$samples[] = array(
					'setting' => $setting_labels['home_title'],
					'value'   => $home_title,
				);
			}
		}

		// Home description - show converted value.
		if ( ! empty( $yoast_titles['metadesc-home-wpseo'] ) ) {
			$home_desc = $this->convert_yoast_variables( $yoast_titles['metadesc-home-wpseo'], $separator );
			if ( ! empty( $home_desc ) ) {
				$samples[] = array(
					'setting' => $setting_labels['home_description'],
					'value'   => $home_desc,
				);
			}
		}

		// Default social image.
		if ( ! empty( $yoast_social['og_default_image'] ) ) {
			$samples[] = array(
				'setting' => $setting_labels['social_default_image'],
				'value'   => $yoast_social['og_default_image'],
			);
		}

		// Twitter card type.
		if ( ! empty( $yoast_social['twitter_card_type'] ) ) {
			$samples[] = array(
				'setting' => $setting_labels['twitter_card_type'],
				'value'   => $yoast_social['twitter_card_type'],
			);
		}

		// Site logo (company logo).
		if ( ! empty( $yoast_titles['company_logo'] ) ) {
			$samples[] = array(
				'setting' => $setting_labels['site_logo'],
				'value'   => $yoast_titles['company_logo'],
			);
		}

		// Google verification code.
		if ( ! empty( $yoast_main['googleverify'] ) ) {
			$samples[] = array(
				'setting' => $setting_labels['verification_google'],
				'value'   => $yoast_main['googleverify'],
			);
		}

		// Bing verification code.
		if ( ! empty( $yoast_main['msverify'] ) ) {
			$samples[] = array(
				'setting' => $setting_labels['verification_bing'],
				'value'   => $yoast_main['msverify'],
			);
		}

		return array(
			'total'   => count( $samples ),
			'samples' => $samples,
		);
	}

	/**
	 * Import post meta from Yoast.
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

		$yoast_keys = array_keys( $this->meta_mapping );
		$placeholders = implode( ',', array_fill( 0, count( $yoast_keys ), '%s' ) );

		$offset = 0;
		$has_more = true;

		while ( $has_more ) {
			// Get batch of post IDs with Yoast meta.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$post_ids = $wpdb->get_col(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders are dynamically generated from array count.
				$wpdb->prepare(
					"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ($placeholders) AND meta_value != '' LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					...array_merge( $yoast_keys, array( self::BATCH_SIZE, $offset ) )
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

				foreach ( $this->meta_mapping as $yoast_key => $rational_key ) {
					$value = get_post_meta( $post_id, $yoast_key, true );

					if ( empty( $value ) ) {
						continue;
					}

					// Special handling for noindex.
					if ( '_yoast_wpseo_meta-robots-noindex' === $yoast_key ) {
						if ( '1' !== $value && 1 !== $value ) {
							continue;
						}
						$value = '1';
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
		$rational_keys = array_values( $this->meta_mapping );

		foreach ( $rational_keys as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			if ( ! empty( $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Import redirects from Yoast.
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

		$redirects = $this->get_yoast_redirects();

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
	 * Import settings from Yoast.
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

		$yoast_main   = get_option( 'wpseo', array() );
		$yoast_titles = get_option( 'wpseo_titles', array() );
		$yoast_social = get_option( 'wpseo_social', array() );

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
		$separator = '|'; // Default.
		if ( ! empty( $yoast_titles['separator'] ) ) {
			$separator = $yoast_titles['separator'];
			if ( isset( $this->separator_map[ $separator ] ) ) {
				$separator = $this->separator_map[ $separator ];
			}
			$settings_to_import['separator'] = sanitize_text_field( $separator );
		}

		// Home title - convert Yoast variables to actual values.
		if ( ! empty( $yoast_titles['title-home-wpseo'] ) ) {
			$home_title = $this->convert_yoast_variables( $yoast_titles['title-home-wpseo'], $separator );
			if ( ! empty( $home_title ) ) {
				$settings_to_import['home_title'] = sanitize_text_field( $home_title );
			}
		}

		// Home description - convert Yoast variables to actual values.
		if ( ! empty( $yoast_titles['metadesc-home-wpseo'] ) ) {
			$home_desc = $this->convert_yoast_variables( $yoast_titles['metadesc-home-wpseo'], $separator );
			if ( ! empty( $home_desc ) ) {
				$settings_to_import['home_description'] = sanitize_text_field( $home_desc );
			}
		}

		// Social default image.
		if ( ! empty( $yoast_social['og_default_image'] ) ) {
			$settings_to_import['social_default_image'] = esc_url_raw( $yoast_social['og_default_image'] );
		}

		// Twitter card type.
		if ( ! empty( $yoast_social['twitter_card_type'] ) ) {
			$twitter_type = sanitize_text_field( $yoast_social['twitter_card_type'] );
			// Validate against allowed values.
			if ( in_array( $twitter_type, array( 'summary', 'summary_large_image' ), true ) ) {
				$settings_to_import['twitter_card_type'] = $twitter_type;
			}
		}

		// Site logo (company logo).
		if ( ! empty( $yoast_titles['company_logo'] ) ) {
			$settings_to_import['site_logo'] = esc_url_raw( $yoast_titles['company_logo'] );
		}

		// Google verification code.
		if ( ! empty( $yoast_main['googleverify'] ) ) {
			$settings_to_import['verification_google'] = sanitize_text_field( $yoast_main['googleverify'] );
		}

		// Bing verification code.
		if ( ! empty( $yoast_main['msverify'] ) ) {
			$settings_to_import['verification_bing'] = sanitize_text_field( $yoast_main['msverify'] );
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
