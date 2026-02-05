<?php
/**
 * RationalSEO SEOPress Importer
 *
 * Imports SEO data from SEOPress.
 *
 * @package RationalSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SEOPress importer class.
 */
class RationalSEO_SEOPress_Importer implements RationalSEO_Importer_Interface {

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
	 * Post meta key mapping from SEOPress to RationalSEO.
	 *
	 * @var array
	 */
	private $meta_mapping = array(
		'_seopress_titles_title'       => '_rationalseo_title',
		'_seopress_titles_desc'        => '_rationalseo_desc',
		'_seopress_robots_canonical'   => '_rationalseo_canonical',
		'_seopress_social_fb_img'      => '_rationalseo_og_image',
		'_seopress_analysis_target_kw' => '_rationalseo_focus_keyword',
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
	 * Convert SEOPress template variables to actual values.
	 *
	 * SEOPress uses %%variable%% syntax (double percent) for dynamic content.
	 * Since RationalSEO doesn't support template variables,
	 * we convert them to actual values during import.
	 *
	 * Supported variables (site-wide, suitable for homepage):
	 * - %%sitetitle%%, %%sitename%% - Site name
	 * - %%tagline%%, %%sitedesc%% - Site tagline
	 * - %%sep%% - Title separator
	 * - %%currentyear%% - Current year
	 * - %%currentmonth%% - Current month name
	 * - %%currentday%% - Current day number
	 * - %%currentdate%% - Current formatted date
	 * - %%page%%, %%current_pagination%% - Pagination (empty for static)
	 *
	 * Post-specific variables (%%title%%, %%excerpt%%, etc.)
	 * are NOT converted - if present, the value is skipped entirely.
	 *
	 * @param string $text      Text containing SEOPress variables.
	 * @param string $separator The separator to use for %%sep%%.
	 * @return string Text with variables replaced, or empty if unrecognized variables found.
	 */
	private function convert_seopress_variables( $text, $separator = '-' ) {
		if ( empty( $text ) || strpos( $text, '%%' ) === false ) {
			return $text;
		}

		$site_name     = get_bloginfo( 'name' );
		$site_tagline  = get_bloginfo( 'description' );
		$current_year  = gmdate( 'Y' );
		$current_month = gmdate( 'F' );
		$current_day   = gmdate( 'j' );
		$current_date  = gmdate( get_option( 'date_format' ) );

		// Build replacements array with all known variations.
		$replacements = array(
			// Site info.
			'%%sitetitle%%'         => $site_name,
			'%%sitename%%'          => $site_name,
			'%%tagline%%'           => $site_tagline,
			'%%sitedesc%%'          => $site_tagline,

			// Separator.
			'%%sep%%'               => $separator,

			// Pagination (empty for static settings).
			'%%page%%'              => '',
			'%%current_pagination%%' => '',

			// Date/time.
			'%%currentyear%%'       => $current_year,
			'%%currentmonth%%'      => $current_month,
			'%%currentmonth_short%%' => gmdate( 'M' ),
			'%%currentmonth_num%%'  => gmdate( 'n' ),
			'%%currentday%%'        => $current_day,
			'%%currentdate%%'       => $current_date,
			'%%currenttime%%'       => gmdate( get_option( 'time_format' ) ),
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
		// This catches post-specific variables like %%title%%, %%excerpt%%, %%post_content%%, etc.
		if ( preg_match( '/%%[a-z_0-9]+%%/i', $text ) ) {
			return '';
		}

		return $text;
	}

	/**
	 * Get the SEOPress separator setting.
	 *
	 * @return string The separator character.
	 */
	private function get_seopress_separator() {
		$sp_titles = get_option( 'seopress_titles_option_name', array() );
		$separator = '-'; // SEOPress default.

		if ( ! empty( $sp_titles['seopress_titles_sep'] ) ) {
			$separator = $sp_titles['seopress_titles_sep'];
		}

		return $separator;
	}

	/**
	 * Convert SEOPress template variables for post meta, including post-specific variables.
	 *
	 * Unlike convert_seopress_variables() which is for site-wide settings,
	 * this method can convert post-specific variables using the provided post object.
	 *
	 * @param string   $text      Text containing SEOPress variables.
	 * @param string   $separator The separator to use for %%sep%%.
	 * @param \WP_Post $post      The post object for post-specific variables.
	 * @return string Text with variables replaced.
	 */
	private function convert_seopress_variables_for_post( $text, $separator, $post ) {
		if ( empty( $text ) || strpos( $text, '%%' ) === false ) {
			return $text;
		}

		$site_name     = get_bloginfo( 'name' );
		$site_tagline  = get_bloginfo( 'description' );
		$current_year  = gmdate( 'Y' );
		$current_month = gmdate( 'F' );
		$current_day   = gmdate( 'j' );
		$current_date  = gmdate( get_option( 'date_format' ) );

		// Get primary category if available.
		$primary_category = '';
		$categories       = get_the_category( $post->ID );
		if ( ! empty( $categories ) ) {
			$primary_category = $categories[0]->name;
		}

		// Get post excerpt or generate from content.
		$excerpt = $post->post_excerpt;
		if ( empty( $excerpt ) ) {
			$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 55, '...' );
		}

		// Get target keyword if set (SEOPress stores this as post meta).
		$target_keyword = get_post_meta( $post->ID, '_seopress_analysis_target_kw', true );

		// Get post tags.
		$tags     = get_the_tags( $post->ID );
		$tag_list = '';
		if ( ! empty( $tags ) ) {
			$tag_names = wp_list_pluck( $tags, 'name' );
			$tag_list  = implode( ', ', $tag_names );
		}

		// Build replacements array with all known variations.
		$replacements = array(
			// Site info.
			'%%sitetitle%%'          => $site_name,
			'%%sitename%%'           => $site_name,
			'%%tagline%%'            => $site_tagline,
			'%%sitedesc%%'           => $site_tagline,

			// Separator.
			'%%sep%%'                => $separator,

			// Pagination (empty for single posts).
			'%%page%%'               => '',
			'%%current_pagination%%' => '',
			'%%cpt_plural%%'         => get_post_type_object( $post->post_type )->labels->name ?? '',

			// Date/time.
			'%%currentyear%%'        => $current_year,
			'%%currentmonth%%'       => $current_month,
			'%%currentmonth_short%%' => gmdate( 'M' ),
			'%%currentmonth_num%%'   => gmdate( 'n' ),
			'%%currentday%%'         => $current_day,
			'%%currentdate%%'        => $current_date,
			'%%currenttime%%'        => gmdate( get_option( 'time_format' ) ),

			// Post-specific variables.
			'%%post_title%%'              => $post->post_title,
			'%%title%%'                   => $post->post_title,
			'%%post_excerpt%%'            => $excerpt,
			'%%excerpt%%'                 => $excerpt,
			'%%post_content%%'            => wp_trim_words( wp_strip_all_tags( $post->post_content ), 55, '...' ),
			'%%post_date%%'               => get_the_date( '', $post ),
			'%%date%%'                    => get_the_date( '', $post ),
			'%%post_modified_date%%'      => get_the_modified_date( '', $post ),
			'%%post_author%%'             => get_the_author_meta( 'display_name', $post->post_author ),
			'%%author%%'                  => get_the_author_meta( 'display_name', $post->post_author ),
			'%%author_first_name%%'       => get_the_author_meta( 'first_name', $post->post_author ),
			'%%author_last_name%%'        => get_the_author_meta( 'last_name', $post->post_author ),
			'%%author_nickname%%'         => get_the_author_meta( 'nickname', $post->post_author ),
			'%%author_bio%%'              => get_the_author_meta( 'description', $post->post_author ),
			'%%post_category%%'           => $primary_category,
			'%%_category_title%%'         => $primary_category,
			'%%post_tag%%'                => $tag_list,
			'%%tag%%'                     => $tag_list,
			'%%target_keyword%%'          => $target_keyword,
			'%%post_thumbnail_url%%'      => get_the_post_thumbnail_url( $post->ID, 'full' ) ?: '',
			'%%post_url%%'                => get_permalink( $post ),
			'%%permalink%%'               => get_permalink( $post ),
			'%%post_id%%'                 => $post->ID,
			'%%wc_single_cat%%'           => $primary_category, // WooCommerce category fallback.
			'%%wc_single_tag%%'           => $tag_list, // WooCommerce tag fallback.
		);

		// Apply replacements (case-insensitive).
		foreach ( $replacements as $var => $value ) {
			$text = str_ireplace( $var, $value, $text );
		}

		// Handle %%_cf_FIELDNAME%% pattern for custom fields.
		$text = preg_replace_callback(
			'/%%_cf_([a-zA-Z0-9_-]+)%%/i',
			function ( $matches ) use ( $post ) {
				$field_value = get_post_meta( $post->ID, $matches[1], true );
				return is_string( $field_value ) ? $field_value : '';
			},
			$text
		);

		// Handle %%_ct_TAXONOMY%% pattern for custom taxonomies.
		$text = preg_replace_callback(
			'/%%_ct_([a-zA-Z0-9_-]+)%%/i',
			function ( $matches ) use ( $post ) {
				$terms = get_the_terms( $post->ID, $matches[1] );
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					return $terms[0]->name;
				}
				return '';
			},
			$text
		);

		// Clean up any double spaces from empty replacements.
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		// Remove trailing/leading separators that might be left over.
		$text = trim( $text, ' ' . $separator );

		// If there are still unrecognized variables, remove them to avoid broken output.
		$text = preg_replace( '/%%[a-z_0-9]+%%/i', '', $text );

		// Clean up again after removing variables.
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text, ' ' . $separator );
		$text = trim( $text );

		return $text;
	}

	/**
	 * Get the unique slug for this importer.
	 *
	 * @return string
	 */
	public function get_slug() {
		return 'seopress';
	}

	/**
	 * Get the display name for this importer.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'SEOPress';
	}

	/**
	 * Get the description for this importer.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Import SEO titles, meta descriptions, and settings from SEOPress.', 'rationalseo' );
	}

	/**
	 * Check if this importer is available (source data exists).
	 *
	 * @return bool
	 */
	public function is_available() {
		// Check for SEOPress post meta.
		if ( $this->get_post_meta_count() > 0 ) {
			return true;
		}

		// Check for SEOPress settings.
		$sp_titles = get_option( 'seopress_titles_option_name', array() );
		if ( ! empty( $sp_titles ) ) {
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
		$settings_count  = $this->get_settings_count();

		return array(
			'post_meta' => array(
				'label'     => __( 'Post SEO Data', 'rationalseo' ),
				'count'     => $post_meta_count,
				'available' => $post_meta_count > 0,
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
			$item_types = array( 'post_meta', 'settings' );
		}

		if ( in_array( 'post_meta', $item_types, true ) ) {
			$preview_data['post_meta'] = $this->preview_post_meta();
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
			$item_types = array( 'post_meta', 'settings' );
		}

		$import_results = array();

		if ( in_array( 'post_meta', $item_types, true ) ) {
			$import_results['post_meta'] = $this->import_post_meta( $skip_existing );
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
					__( 'Successfully imported %d items from SEOPress.', 'rationalseo' ),
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
	 * Get count of posts with SEOPress meta data.
	 *
	 * @return int
	 */
	private function get_post_meta_count() {
		global $wpdb;

		// Include both regular meta keys and noindex meta.
		$meta_keys = array_merge(
			array_keys( $this->meta_mapping ),
			array( '_seopress_robots_index' )
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
	 * Get count of importable settings.
	 *
	 * @return int
	 */
	private function get_settings_count() {
		$sp_titles   = get_option( 'seopress_titles_option_name', array() );
		$sp_social   = get_option( 'seopress_social_option_name', array() );
		$sp_advanced = get_option( 'seopress_advanced_option_name', array() );

		$count = 0;

		// Get separator for variable conversion.
		$separator = ! empty( $sp_titles['seopress_titles_sep'] ) ? $sp_titles['seopress_titles_sep'] : '-';

		// Count settings from titles options.
		if ( ! empty( $sp_titles['seopress_titles_sep'] ) ) {
			$count++;
		}
		if ( ! empty( $sp_titles['seopress_titles_home_site_title'] ) ) {
			$home_title = $this->convert_seopress_variables( $sp_titles['seopress_titles_home_site_title'], $separator );
			if ( ! empty( $home_title ) ) {
				$count++;
			}
		}
		if ( ! empty( $sp_titles['seopress_titles_home_site_desc'] ) ) {
			$home_desc = $this->convert_seopress_variables( $sp_titles['seopress_titles_home_site_desc'], $separator );
			if ( ! empty( $home_desc ) ) {
				$count++;
			}
		}

		// Count settings from social options.
		if ( ! empty( $sp_social['seopress_social_facebook_img'] ) ) {
			$count++;
		}
		if ( ! empty( $sp_social['seopress_social_twitter_card_img_size'] ) ) {
			$count++;
		}
		if ( ! empty( $sp_social['seopress_social_knowledge_img'] ) ) {
			$count++;
		}

		// Count verification codes from advanced settings.
		if ( ! empty( $sp_advanced['seopress_advanced_advanced_google'] ) ) {
			$count++;
		}
		if ( ! empty( $sp_advanced['seopress_advanced_advanced_bing'] ) ) {
			$count++;
		}

		return $count;
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
			array( '_seopress_robots_index' )
		);
		$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

		// Get separator for variable conversion.
		$separator = $this->get_seopress_separator();

		// Get sample posts with SEOPress meta (limit to 5 for preview).
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
			foreach ( $this->meta_mapping as $sp_key => $rational_key ) {
				$value = get_post_meta( $post_id, $sp_key, true );
				if ( ! empty( $value ) ) {
					// Convert SEOPress variables for title and description fields.
					if ( in_array( $sp_key, array( '_seopress_titles_title', '_seopress_titles_desc' ), true ) ) {
						$value = $this->convert_seopress_variables_for_post( $value, $separator, $post );
					}

					// Focus keyword: take only the first keyword (SEOPress stores multiple comma-separated).
					if ( '_seopress_analysis_target_kw' === $sp_key && strpos( $value, ',' ) !== false ) {
						$value = trim( explode( ',', $value )[0] );
					}

					if ( ! empty( $value ) ) {
						$meta_preview['meta'][ $rational_key ] = $value;
					}
				}
			}

			// Handle noindex meta.
			$noindex = get_post_meta( $post_id, '_seopress_robots_index', true );
			if ( 'yes' === $noindex ) {
				$meta_preview['meta']['_rationalseo_noindex'] = '1';
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
	 * Preview settings import.
	 *
	 * @return array Preview data.
	 */
	private function preview_settings() {
		$sp_titles   = get_option( 'seopress_titles_option_name', array() );
		$sp_social   = get_option( 'seopress_social_option_name', array() );
		$sp_advanced = get_option( 'seopress_advanced_option_name', array() );

		// Setting labels for display.
		$setting_labels = array(
			'separator'            => __( 'Title Separator', 'rationalseo' ),
			'home_title'           => __( 'Front Page Title', 'rationalseo' ),
			'home_description'     => __( 'Front Page Description', 'rationalseo' ),
			'social_default_image' => __( 'Default Social Image', 'rationalseo' ),
			'twitter_card_type'    => __( 'Twitter Card Type', 'rationalseo' ),
			'site_logo'            => __( 'Site Logo', 'rationalseo' ),
			'verification_google'  => __( 'Google Verification', 'rationalseo' ),
			'verification_bing'    => __( 'Bing Verification', 'rationalseo' ),
		);

		$samples = array();

		// Separator - process first since it's needed for variable conversion.
		$separator = '-'; // Default.
		if ( ! empty( $sp_titles['seopress_titles_sep'] ) ) {
			$separator = $sp_titles['seopress_titles_sep'];
			$samples[] = array(
				'setting' => $setting_labels['separator'],
				'value'   => $separator,
			);
		}

		// Home title - show converted value.
		if ( ! empty( $sp_titles['seopress_titles_home_site_title'] ) ) {
			$home_title = $this->convert_seopress_variables( $sp_titles['seopress_titles_home_site_title'], $separator );
			if ( ! empty( $home_title ) ) {
				$samples[] = array(
					'setting' => $setting_labels['home_title'],
					'value'   => $home_title,
				);
			}
		}

		// Home description - show converted value.
		if ( ! empty( $sp_titles['seopress_titles_home_site_desc'] ) ) {
			$home_desc = $this->convert_seopress_variables( $sp_titles['seopress_titles_home_site_desc'], $separator );
			if ( ! empty( $home_desc ) ) {
				$samples[] = array(
					'setting' => $setting_labels['home_description'],
					'value'   => $home_desc,
				);
			}
		}

		// Default social image.
		if ( ! empty( $sp_social['seopress_social_facebook_img'] ) ) {
			$samples[] = array(
				'setting' => $setting_labels['social_default_image'],
				'value'   => $sp_social['seopress_social_facebook_img'],
			);
		}

		// Twitter card type.
		if ( ! empty( $sp_social['seopress_social_twitter_card_img_size'] ) ) {
			$samples[] = array(
				'setting' => $setting_labels['twitter_card_type'],
				'value'   => $sp_social['seopress_social_twitter_card_img_size'],
			);
		}

		// Site logo (knowledge graph image).
		if ( ! empty( $sp_social['seopress_social_knowledge_img'] ) ) {
			$samples[] = array(
				'setting' => $setting_labels['site_logo'],
				'value'   => $sp_social['seopress_social_knowledge_img'],
			);
		}

		// Google verification code.
		if ( ! empty( $sp_advanced['seopress_advanced_advanced_google'] ) ) {
			$samples[] = array(
				'setting' => $setting_labels['verification_google'],
				'value'   => $sp_advanced['seopress_advanced_advanced_google'],
			);
		}

		// Bing verification code.
		if ( ! empty( $sp_advanced['seopress_advanced_advanced_bing'] ) ) {
			$samples[] = array(
				'setting' => $setting_labels['verification_bing'],
				'value'   => $sp_advanced['seopress_advanced_advanced_bing'],
			);
		}

		return array(
			'total'   => count( $samples ),
			'samples' => $samples,
		);
	}

	/**
	 * Import post meta from SEOPress.
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
			array( '_seopress_robots_index' )
		);
		$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

		// Get separator for variable conversion.
		$separator = $this->get_seopress_separator();

		$offset   = 0;
		$has_more = true;

		while ( $has_more ) {
			// Get batch of post IDs with SEOPress meta.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$post_ids = $wpdb->get_col(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholders are dynamically generated from array count.
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

				// Get the post object for variable conversion.
				$post = get_post( $post_id );
				if ( ! $post ) {
					$result['failed']++;
					continue;
				}

				// Standard meta mapping.
				foreach ( $this->meta_mapping as $sp_key => $rational_key ) {
					$value = get_post_meta( $post_id, $sp_key, true );

					if ( empty( $value ) ) {
						continue;
					}

					// Convert SEOPress variables for title and description fields.
					if ( in_array( $sp_key, array( '_seopress_titles_title', '_seopress_titles_desc' ), true ) ) {
						$value = $this->convert_seopress_variables_for_post( $value, $separator, $post );
					}

					// Focus keyword: take only the first keyword (SEOPress stores multiple comma-separated).
					if ( '_seopress_analysis_target_kw' === $sp_key && strpos( $value, ',' ) !== false ) {
						$value = trim( explode( ',', $value )[0] );
					}

					// Skip if conversion resulted in empty value.
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

				// Handle noindex meta.
				$noindex = get_post_meta( $post_id, '_seopress_robots_index', true );
				if ( 'yes' === $noindex ) {
					$existing = get_post_meta( $post_id, '_rationalseo_noindex', true );
					if ( empty( $existing ) ) {
						update_post_meta( $post_id, '_rationalseo_noindex', '1' );
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
	 * Import settings from SEOPress.
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

		$sp_titles   = get_option( 'seopress_titles_option_name', array() );
		$sp_social   = get_option( 'seopress_social_option_name', array() );
		$sp_advanced = get_option( 'seopress_advanced_option_name', array() );

		$settings_to_import = array();

		// Setting labels for display.
		$setting_labels = array(
			'separator'            => __( 'Title Separator', 'rationalseo' ),
			'home_title'           => __( 'Front Page Title', 'rationalseo' ),
			'home_description'     => __( 'Front Page Description', 'rationalseo' ),
			'social_default_image' => __( 'Default Social Image', 'rationalseo' ),
			'twitter_card_type'    => __( 'Twitter Card Type', 'rationalseo' ),
			'site_logo'            => __( 'Site Logo', 'rationalseo' ),
			'verification_google'  => __( 'Google Verification', 'rationalseo' ),
			'verification_bing'    => __( 'Bing Verification', 'rationalseo' ),
		);

		// Separator - process first since it's needed for variable conversion.
		$separator = '-'; // Default.
		if ( ! empty( $sp_titles['seopress_titles_sep'] ) ) {
			$separator                         = $sp_titles['seopress_titles_sep'];
			$settings_to_import['separator'] = sanitize_text_field( $separator );
		}

		// Home title and description - write to front page post meta.
		$front_page_id = get_option( 'page_on_front' );
		if ( $front_page_id ) {
			if ( ! empty( $sp_titles['seopress_titles_home_site_title'] ) ) {
				$home_title = $this->convert_seopress_variables( $sp_titles['seopress_titles_home_site_title'], $separator );
				if ( ! empty( $home_title ) ) {
					update_post_meta( $front_page_id, '_rationalseo_title', sanitize_text_field( $home_title ) );
				}
			}

			if ( ! empty( $sp_titles['seopress_titles_home_site_desc'] ) ) {
				$home_desc = $this->convert_seopress_variables( $sp_titles['seopress_titles_home_site_desc'], $separator );
				if ( ! empty( $home_desc ) ) {
					update_post_meta( $front_page_id, '_rationalseo_desc', sanitize_text_field( $home_desc ) );
				}
			}
		}

		// Social default image.
		if ( ! empty( $sp_social['seopress_social_facebook_img'] ) ) {
			$settings_to_import['social_default_image'] = esc_url_raw( $sp_social['seopress_social_facebook_img'] );
		}

		// Twitter card type.
		if ( ! empty( $sp_social['seopress_social_twitter_card_img_size'] ) ) {
			$twitter_type = sanitize_text_field( $sp_social['seopress_social_twitter_card_img_size'] );
			// Validate against allowed values.
			if ( in_array( $twitter_type, array( 'summary', 'summary_large_image' ), true ) ) {
				$settings_to_import['twitter_card_type'] = $twitter_type;
			}
		}

		// Site logo (knowledge graph image).
		if ( ! empty( $sp_social['seopress_social_knowledge_img'] ) ) {
			$settings_to_import['site_logo'] = esc_url_raw( $sp_social['seopress_social_knowledge_img'] );
		}

		// Google verification code.
		if ( ! empty( $sp_advanced['seopress_advanced_advanced_google'] ) ) {
			$settings_to_import['verification_google'] = sanitize_text_field( $sp_advanced['seopress_advanced_advanced_google'] );
		}

		// Bing verification code.
		if ( ! empty( $sp_advanced['seopress_advanced_advanced_bing'] ) ) {
			$settings_to_import['verification_bing'] = sanitize_text_field( $sp_advanced['seopress_advanced_advanced_bing'] );
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
