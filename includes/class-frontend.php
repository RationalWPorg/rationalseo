<?php
/**
 * RationalSEO Frontend Class
 *
 * Handles frontend meta tag output.
 *
 * @package RationalSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RationalSEO_Frontend {

	/**
	 * Settings instance.
	 *
	 * @var RationalSEO_Settings
	 */
	private $settings;

	/**
	 * Cached computed title.
	 *
	 * @var string|null
	 */
	private $cached_title = null;

	/**
	 * Cached computed description.
	 *
	 * @var string|null
	 */
	private $cached_description = null;

	/**
	 * Cached canonical URL.
	 *
	 * @var string|null
	 */
	private $cached_canonical = null;

	/**
	 * Cached social image data.
	 *
	 * @var array|null
	 */
	private $cached_social_image_data = null;

	/**
	 * Cached post meta for current context.
	 *
	 * @var array|null
	 */
	private $post_meta_cache = null;

	/**
	 * Cached term meta for current context.
	 *
	 * @var array|null
	 */
	private $term_meta_cache = null;

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
		// Remove default WordPress title handling.
		add_action( 'after_setup_theme', array( $this, 'remove_title_tag_support' ), 99 );

		// Output our SEO tags early in wp_head.
		add_action( 'wp_head', array( $this, 'output_seo_tags' ), 1 );

		// Filter the document title.
		add_filter( 'pre_get_document_title', array( $this, 'filter_document_title' ), 99 );
		add_filter( 'document_title_parts', array( $this, 'filter_title_parts' ), 99 );
	}

	/**
	 * Remove theme title-tag support to prevent duplicate titles.
	 */
	public function remove_title_tag_support() {
		remove_theme_support( 'title-tag' );
	}

	/**
	 * Filter document title.
	 *
	 * @param string $title Current title.
	 * @return string
	 */
	public function filter_document_title( $title ) {
		return $this->get_title();
	}

	/**
	 * Filter title parts (fallback for themes that use document_title_parts).
	 *
	 * @param array $title_parts Title parts array.
	 * @return array
	 */
	public function filter_title_parts( $title_parts ) {
		$custom_title = $this->get_title();
		if ( $custom_title ) {
			return array( 'title' => $custom_title );
		}
		return $title_parts;
	}

	/**
	 * Output SEO tags in wp_head.
	 */
	public function output_seo_tags() {
		echo "\n<!-- RationalSEO -->\n";

		$this->output_title_tag();
		$this->output_description();
		$this->output_robots();
		$this->output_canonical();
		$this->output_verification_tags();
		$this->output_open_graph();
		$this->output_twitter_cards();
		$this->output_schema();

		echo "<!-- /RationalSEO -->\n\n";
	}

	/**
	 * Output title tag.
	 */
	private function output_title_tag() {
		$title = $this->get_title();
		if ( $title ) {
			printf( "<title>%s</title>\n", esc_html( $title ) );
		}
	}

	/**
	 * Get all RationalSEO post meta in one query.
	 *
	 * @param int $post_id Post ID.
	 * @return array Associative array of meta values.
	 */
	private function get_post_seo_meta( $post_id ) {
		if ( null !== $this->post_meta_cache ) {
			return $this->post_meta_cache;
		}

		/**
		 * Short-circuit all RationalSEO post meta resolution for a given post.
		 *
		 * Return non-null array to short-circuit default resolution; return null to fall
		 * through to internal logic. Missing keys are filled with safe defaults via
		 * wp_parse_args(), so a partial array will not produce PHP warnings.
		 *
		 * @since 1.0.6
		 *
		 * @param array|null $pre     Return an array to short-circuit, or null to continue.
		 * @param int        $post_id The post ID being resolved.
		 */
		$pre = apply_filters( 'rationalseo_post_seo_meta', null, $post_id );
		if ( null !== $pre && is_array( $pre ) ) {
			$this->post_meta_cache = wp_parse_args(
				$pre,
				array(
					'title'         => '',
					'desc'          => '',
					'noindex'       => '',
					'canonical'     => '',
					'og_image'      => '',
					'focus_keyword' => '',
				)
			);
			return $this->post_meta_cache;
		}

		$all_meta = get_post_meta( $post_id );
		$this->post_meta_cache = array(
			'title'         => isset( $all_meta['_rationalseo_title'][0] ) ? $all_meta['_rationalseo_title'][0] : '',
			'desc'          => isset( $all_meta['_rationalseo_desc'][0] ) ? $all_meta['_rationalseo_desc'][0] : '',
			'noindex'       => isset( $all_meta['_rationalseo_noindex'][0] ) ? $all_meta['_rationalseo_noindex'][0] : '',
			'canonical'     => isset( $all_meta['_rationalseo_canonical'][0] ) ? $all_meta['_rationalseo_canonical'][0] : '',
			'og_image'      => isset( $all_meta['_rationalseo_og_image'][0] ) ? $all_meta['_rationalseo_og_image'][0] : '',
			'focus_keyword' => isset( $all_meta['_rationalseo_focus_keyword'][0] ) ? $all_meta['_rationalseo_focus_keyword'][0] : '',
		);

		return $this->post_meta_cache;
	}

	/**
	 * Get all RationalSEO term meta in one query.
	 *
	 * @param int $term_id Term ID.
	 * @return array Associative array of meta values.
	 */
	private function get_term_seo_meta( $term_id ) {
		if ( null !== $this->term_meta_cache ) {
			return $this->term_meta_cache;
		}

		/**
		 * Short-circuit all RationalSEO term meta resolution for a given term.
		 *
		 * Return non-null array to short-circuit default resolution; return null to fall
		 * through to internal logic. Missing keys are filled with safe defaults via
		 * wp_parse_args(), so a partial array will not produce PHP warnings.
		 *
		 * @since 1.0.6
		 *
		 * @param array|null $pre     Return an array to short-circuit, or null to continue.
		 * @param int        $term_id The term ID being resolved.
		 */
		$pre = apply_filters( 'rationalseo_term_seo_meta', null, $term_id );
		if ( null !== $pre && is_array( $pre ) ) {
			$this->term_meta_cache = wp_parse_args(
				$pre,
				array(
					'title'     => '',
					'desc'      => '',
					'noindex'   => '',
					'canonical' => '',
					'og_image'  => '',
				)
			);
			return $this->term_meta_cache;
		}

		$this->term_meta_cache = array(
			'title'     => get_term_meta( $term_id, '_rationalseo_term_title', true ),
			'desc'      => get_term_meta( $term_id, '_rationalseo_term_desc', true ),
			'noindex'   => get_term_meta( $term_id, '_rationalseo_term_noindex', true ),
			'canonical' => get_term_meta( $term_id, '_rationalseo_term_canonical', true ),
			'og_image'  => get_term_meta( $term_id, '_rationalseo_term_og_image', true ),
		);

		return $this->term_meta_cache;
	}

	/**
	 * Finalize title: apply filter, cache, and return.
	 *
	 * Called at each exit point of get_title() so the filter fires exactly once
	 * per request regardless of which branch resolved the title.
	 *
	 * @param string $title Resolved title value before filtering.
	 * @return string Filtered title.
	 */
	private function finalize_title( $title ) {
		/**
		 * Filter the resolved document title just before it is cached and returned.
		 *
		 * @since 1.0.6
		 *
		 * @param string $title   Resolved title string.
		 * @param array  $context Standard context array from build_context().
		 */
		$title = (string) apply_filters( 'rationalseo_document_title', (string) $title, $this->build_context() );
		$this->cached_title = $title;
		return $title;
	}

	/**
	 * Finalize canonical: apply filter, cache, and return.
	 *
	 * @param string $canonical Resolved canonical URL before filtering.
	 * @return string Filtered canonical URL.
	 */
	private function finalize_canonical( $canonical ) {
		/**
		 * Filter the resolved canonical URL just before it is cached and returned.
		 *
		 * @since 1.0.6
		 *
		 * @param string $canonical Resolved canonical URL string.
		 * @param array  $context   Standard context array from build_context().
		 */
		$canonical = (string) apply_filters( 'rationalseo_canonical_url', (string) $canonical, $this->build_context() );
		$this->cached_canonical = $canonical;
		return $canonical;
	}

	/**
	 * Get the SEO title.
	 *
	 * @return string
	 */
	private function get_title() {
		// Return cached value if available.
		if ( null !== $this->cached_title ) {
			return $this->cached_title;
		}

		$separator = $this->settings->get( 'separator', '|' );
		$site_name = $this->settings->get( 'site_name', get_bloginfo( 'name' ) );
		$title     = $site_name; // Default fallback.

		// Homepage (static front page or default homepage).
		if ( is_front_page() ) {
			$front_page_id = get_option( 'page_on_front' );
			if ( $front_page_id ) {
				$meta = $this->get_post_seo_meta( $front_page_id );
				if ( ! empty( $meta['title'] ) ) {
					return $this->finalize_title( $meta['title'] );
				}
			}
			$tagline = get_bloginfo( 'description' );
			if ( ! empty( $tagline ) ) {
				return $this->finalize_title( sprintf( '%s %s %s', $site_name, $separator, $tagline ) );
			}
			return $this->finalize_title( $site_name );
		}

		// Blog page (separate posts page).
		if ( is_home() ) {
			$blog_page_id = get_option( 'page_for_posts' );
			if ( $blog_page_id ) {
				$meta = $this->get_post_seo_meta( $blog_page_id );
				if ( ! empty( $meta['title'] ) ) {
					return $this->finalize_title( $meta['title'] );
				}
			}
			return $this->finalize_title( sprintf( '%s %s %s', __( 'Blog', 'rationalseo' ), $separator, $site_name ) );
		}

		// Singular posts/pages.
		if ( is_singular() ) {
			$post       = get_queried_object();
			$post_title = get_the_title( $post );
			$meta       = $this->get_post_seo_meta( $post->ID );

			if ( ! empty( $meta['title'] ) ) {
				return $this->finalize_title( $meta['title'] );
			}

			return $this->finalize_title( sprintf( '%s %s %s', $post_title, $separator, $site_name ) );
		}

		// Archive pages.
		if ( is_archive() ) {
			if ( is_category() || is_tag() || is_tax() ) {
				$term = get_queried_object();
				$meta = $this->get_term_seo_meta( $term->term_id );

				if ( ! empty( $meta['title'] ) ) {
					return $this->finalize_title( $meta['title'] );
				}
				return $this->finalize_title( sprintf( '%s %s %s', $term->name, $separator, $site_name ) );
			}

			if ( is_post_type_archive() ) {
				$post_type    = get_queried_object();
				$custom_title = $this->settings->get( 'archive_title_' . $post_type->name, '' );
				if ( ! empty( $custom_title ) ) {
					return $this->finalize_title( $custom_title );
				}
				return $this->finalize_title( sprintf( '%s %s %s', $post_type->labels->name, $separator, $site_name ) );
			}

			if ( is_author() ) {
				$author = get_queried_object();
				return $this->finalize_title( sprintf( '%s %s %s', $author->display_name, $separator, $site_name ) );
			}

			if ( is_date() ) {
				$date_title = get_the_archive_title();
				return $this->finalize_title( sprintf( '%s %s %s', $date_title, $separator, $site_name ) );
			}
		}

		// Search results.
		if ( is_search() ) {
			/* translators: %s: Search query */
			$search_title = sprintf( __( 'Search Results for "%s"', 'rationalseo' ), get_search_query() );
			return $this->finalize_title( sprintf( '%s %s %s', $search_title, $separator, $site_name ) );
		}

		// 404 page.
		if ( is_404() ) {
			return $this->finalize_title( sprintf( '%s %s %s', __( 'Page Not Found', 'rationalseo' ), $separator, $site_name ) );
		}

		// Fallback.
		return $this->finalize_title( $title );
	}

	/**
	 * Output meta description.
	 */
	private function output_description() {
		$context = $this->build_context();

		/**
		 * Skip the meta description block entirely when truthy.
		 *
		 * @since 1.0.6
		 *
		 * @param bool  $skip    Whether to skip output. Default false.
		 * @param array $context Standard context array from build_context().
		 */
		if ( apply_filters( 'rationalseo_skip_meta_description', false, $context ) ) {
			return;
		}

		$description = $this->get_description();
		if ( $description ) {
			printf( "<meta name=\"description\" content=\"%s\" />\n", esc_attr( $description ) );
		}
	}

	/**
	 * Get the meta description.
	 *
	 * @return string
	 */
	private function get_description() {
		// Return cached value if available.
		if ( null !== $this->cached_description ) {
			return $this->cached_description;
		}

		/**
		 * Short-circuit the meta description resolution.
		 *
		 * Return non-null to short-circuit default resolution; return null to fall
		 * through to internal logic. Returning an empty string '' is a valid
		 * short-circuit meaning "explicitly no description" — output_description()
		 * skips the tag when description is empty.
		 *
		 * @since 1.0.6
		 *
		 * @param string|null $pre     Return a string to short-circuit, or null to continue.
		 * @param array       $context Standard context array from build_context().
		 */
		$pre = apply_filters( 'rationalseo_meta_description', null, $this->build_context() );
		if ( null !== $pre ) {
			$this->cached_description = (string) $pre;
			return $this->cached_description;
		}

		// Homepage (static front page or default homepage).
		if ( is_front_page() ) {
			$front_page_id = get_option( 'page_on_front' );
			if ( $front_page_id ) {
				$meta = $this->get_post_seo_meta( $front_page_id );
				if ( ! empty( $meta['desc'] ) ) {
					$this->cached_description = $this->truncate_description( $meta['desc'] );
					return $this->cached_description;
				}
			}
			$this->cached_description = $this->truncate_description( get_bloginfo( 'description' ) );
			return $this->cached_description;
		}

		// Blog page (separate posts page).
		if ( is_home() ) {
			$blog_page_id = get_option( 'page_for_posts' );
			if ( $blog_page_id ) {
				$meta = $this->get_post_seo_meta( $blog_page_id );
				if ( ! empty( $meta['desc'] ) ) {
					$this->cached_description = $this->truncate_description( $meta['desc'] );
					return $this->cached_description;
				}
			}
			$this->cached_description = $this->truncate_description( get_bloginfo( 'description' ) );
			return $this->cached_description;
		}

		// Singular posts/pages.
		if ( is_singular() ) {
			$post = get_queried_object();
			$meta = $this->get_post_seo_meta( $post->ID );

			// Priority 1: Custom meta description.
			if ( ! empty( $meta['desc'] ) ) {
				$this->cached_description = $this->truncate_description( $meta['desc'] );
				return $this->cached_description;
			}

			// Priority 2: Excerpt.
			if ( has_excerpt( $post ) ) {
				$this->cached_description = $this->truncate_description( get_the_excerpt( $post ) );
				return $this->cached_description;
			}

			// Priority 3: Sentence containing focus keyword.
			if ( ! empty( $meta['focus_keyword'] ) ) {
				$keyword_sentence = $this->find_keyword_sentence( $post->post_content, $meta['focus_keyword'] );
				if ( $keyword_sentence ) {
					$this->cached_description = $keyword_sentence;
					return $this->cached_description;
				}
			}

			// Priority 4: Generate from content beginning.
			$content = wp_strip_all_tags( $post->post_content );
			$content = preg_replace( '/\s+/', ' ', $content );
			$this->cached_description = $this->truncate_description( $content );
			return $this->cached_description;
		}

		// Archive pages.
		if ( is_archive() ) {
			if ( is_category() || is_tag() || is_tax() ) {
				$term = get_queried_object();
				$meta = $this->get_term_seo_meta( $term->term_id );

				if ( ! empty( $meta['desc'] ) ) {
					$this->cached_description = $this->truncate_description( $meta['desc'] );
					return $this->cached_description;
				}
				if ( ! empty( $term->description ) ) {
					$this->cached_description = $this->truncate_description( $term->description );
					return $this->cached_description;
				}
			}

			if ( is_post_type_archive() ) {
				$post_type          = get_queried_object();
				$custom_description = $this->settings->get( 'archive_description_' . $post_type->name, '' );
				if ( ! empty( $custom_description ) ) {
					$this->cached_description = $this->truncate_description( $custom_description );
					return $this->cached_description;
				}
			}
		}

		$this->cached_description = '';
		return $this->cached_description;
	}

	/**
	 * Find a sentence containing the focus keyword.
	 *
	 * @param string $content    The content to search.
	 * @param string $keyword    The keyword to find.
	 * @param int    $max_length Maximum length for the result (default 160).
	 * @return string|null The sentence containing the keyword, or null if not found.
	 */
	private function find_keyword_sentence( $content, $keyword, $max_length = 160 ) {
		if ( empty( $keyword ) || empty( $content ) ) {
			return null;
		}

		// Strip HTML and normalize whitespace.
		$content = wp_strip_all_tags( $content );
		$content = preg_replace( '/\s+/', ' ', $content );
		$content = trim( $content );

		if ( empty( $content ) ) {
			return null;
		}

		// Split into sentences (handle ., !, ?).
		$sentences = preg_split( '/(?<=[.!?])\s+/', $content, -1, PREG_SPLIT_NO_EMPTY );

		if ( empty( $sentences ) ) {
			return null;
		}

		// Find first sentence containing keyword (case-insensitive).
		$keyword_lower = mb_strtolower( $keyword );
		foreach ( $sentences as $sentence ) {
			$sentence = trim( $sentence );
			if ( false !== mb_stripos( $sentence, $keyword_lower ) ) {
				// Found a match - truncate if needed.
				return $this->truncate_description( $sentence, $max_length );
			}
		}

		return null;
	}

	/**
	 * Truncate description to appropriate length.
	 *
	 * @param string $text Text to truncate.
	 * @param int    $max_length Maximum length (default 160).
	 * @return string
	 */
	private function truncate_description( $text, $max_length = 160 ) {
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		if ( strlen( $text ) <= $max_length ) {
			return $text;
		}

		$text = substr( $text, 0, $max_length );
		$text = substr( $text, 0, strrpos( $text, ' ' ) );
		$text = rtrim( $text, '.,!?' );
		$text .= '...';

		return $text;
	}

	/**
	 * Output meta robots tag.
	 */
	private function output_robots() {
		$robots = $this->get_robots();
		if ( ! empty( $robots ) ) {
			printf( "<meta name=\"robots\" content=\"%s\" />\n", esc_attr( implode( ', ', $robots ) ) );
		}
	}

	/**
	 * Get robots directives.
	 *
	 * @return array
	 */
	private function get_robots() {
		$robots = array( 'index', 'follow' );

		// Check for noindex on singular posts.
		if ( is_singular() ) {
			$post = get_queried_object();
			$meta = $this->get_post_seo_meta( $post->ID );
			if ( ! empty( $meta['noindex'] ) ) {
				$robots[0] = 'noindex';
			}
		}

		// Check for noindex on taxonomy archives.
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			$meta = $this->get_term_seo_meta( $term->term_id );
			if ( ! empty( $meta['noindex'] ) ) {
				$robots[0] = 'noindex';
			}
		}

		// Search pages should not be indexed.
		if ( is_search() ) {
			$robots[0] = 'noindex';
		}

		// 404 pages should not be indexed.
		if ( is_404() ) {
			$robots[0] = 'noindex';
		}

		// Paged archives after page 1 should not be indexed.
		if ( is_paged() ) {
			$robots[0] = 'noindex';
		}

		// Add max-image-preview for better image previews in search.
		$robots[] = 'max-image-preview:large';

		/**
		 * Filter the resolved robots directives array just before output.
		 *
		 * Receives the array of directive strings (e.g. ['index', 'follow',
		 * 'max-image-preview:large']). Return value is cast to array; an empty
		 * array suppresses the robots meta tag entirely.
		 *
		 * @since 1.0.6
		 *
		 * @param array $robots  Array of robots directive strings.
		 * @param array $context Standard context array from build_context().
		 */
		$robots = (array) apply_filters( 'rationalseo_robots', $robots, $this->build_context() );

		return $robots;
	}

	/**
	 * Output canonical URL.
	 */
	private function output_canonical() {
		$context = $this->build_context();

		/**
		 * Skip the canonical link tag entirely when truthy.
		 *
		 * @since 1.0.6
		 *
		 * @param bool  $skip    Whether to skip output. Default false.
		 * @param array $context Standard context array from build_context().
		 */
		if ( apply_filters( 'rationalseo_skip_canonical', false, $context ) ) {
			return;
		}

		$canonical = $this->get_canonical();
		if ( $canonical ) {
			printf( "<link rel=\"canonical\" href=\"%s\" />\n", esc_url( $canonical ) );
		}
	}

	/**
	 * Get canonical URL.
	 *
	 * @return string
	 */
	private function get_canonical() {
		// Return cached value if available.
		if ( null !== $this->cached_canonical ) {
			return $this->cached_canonical;
		}

		// Check for custom canonical on singular posts.
		if ( is_singular() ) {
			$post = get_queried_object();
			$meta = $this->get_post_seo_meta( $post->ID );
			if ( ! empty( $meta['canonical'] ) ) {
				return $this->finalize_canonical( $meta['canonical'] );
			}
			return $this->finalize_canonical( get_permalink( $post ) );
		}

		// Homepage.
		if ( is_front_page() || is_home() ) {
			return $this->finalize_canonical( home_url( '/' ) );
		}

		// Archive pages.
		if ( is_archive() ) {
			if ( is_category() || is_tag() || is_tax() ) {
				$term = get_queried_object();
				$meta = $this->get_term_seo_meta( $term->term_id );
				if ( ! empty( $meta['canonical'] ) ) {
					return $this->finalize_canonical( $meta['canonical'] );
				}
				return $this->finalize_canonical( get_term_link( $term ) );
			}

			if ( is_post_type_archive() ) {
				$post_type = get_queried_object();
				return $this->finalize_canonical( get_post_type_archive_link( $post_type->name ) );
			}

			if ( is_author() ) {
				$author = get_queried_object();
				return $this->finalize_canonical( get_author_posts_url( $author->ID ) );
			}
		}

		// Fallback to current URL (cleaned).
		global $wp;
		return $this->finalize_canonical( home_url( $wp->request ) );
	}

	/**
	 * Output verification meta tags.
	 */
	private function output_verification_tags() {
		$google = $this->settings->get( 'verification_google' );
		if ( ! empty( $google ) ) {
			printf( "<meta name=\"google-site-verification\" content=\"%s\" />\n", esc_attr( $google ) );
		}

		$bing = $this->settings->get( 'verification_bing' );
		if ( ! empty( $bing ) ) {
			printf( "<meta name=\"msvalidate.01\" content=\"%s\" />\n", esc_attr( $bing ) );
		}
	}

	/**
	 * Output Open Graph meta tags.
	 */
	private function output_open_graph() {
		$context = $this->build_context();

		/**
		 * Skip the Open Graph block entirely when truthy.
		 *
		 * @since 1.0.6
		 *
		 * @param bool  $skip    Whether to skip output. Default false.
		 * @param array $context Standard context array from build_context().
		 */
		if ( apply_filters( 'rationalseo_skip_open_graph', false, $context ) ) {
			return;
		}

		$locale     = get_locale();
		$og_type    = is_singular() && ! is_front_page() ? 'article' : 'website';
		$title      = $this->get_title();
		$desc       = $this->get_description();
		$url        = $this->get_canonical();
		$site_name  = $this->settings->get( 'site_name', get_bloginfo( 'name' ) );
		$image_data = $this->get_social_image_data();

		/**
		 * Filter the resolved og:locale value just before output.
		 *
		 * @since 1.0.6
		 *
		 * @param string $locale  Resolved locale string.
		 * @param array  $context Standard context array from build_context().
		 */
		$locale = apply_filters( 'rationalseo_og_locale', $locale, $context );
		printf( "<meta property=\"og:locale\" content=\"%s\" />\n", esc_attr( $locale ) );

		/**
		 * Filter the resolved og:type value just before output.
		 *
		 * @since 1.0.6
		 *
		 * @param string $og_type Resolved OG type string (e.g. 'article', 'website').
		 * @param array  $context Standard context array from build_context().
		 */
		$og_type = apply_filters( 'rationalseo_og_type', $og_type, $context );
		printf( "<meta property=\"og:type\" content=\"%s\" />\n", esc_attr( $og_type ) );

		/**
		 * Filter the resolved og:title value just before output.
		 *
		 * Note: this is a separate filter from rationalseo_document_title — themes
		 * may want different OG titles vs <title> tag values.
		 *
		 * @since 1.0.6
		 *
		 * @param string $title   Resolved OG title string.
		 * @param array  $context Standard context array from build_context().
		 */
		$title = apply_filters( 'rationalseo_og_title', $title, $context );
		if ( $title ) {
			printf( "<meta property=\"og:title\" content=\"%s\" />\n", esc_attr( $title ) );
		}

		/**
		 * Filter the resolved og:description value just before output.
		 *
		 * @since 1.0.6
		 *
		 * @param string $desc    Resolved OG description string.
		 * @param array  $context Standard context array from build_context().
		 */
		$desc = apply_filters( 'rationalseo_og_description', $desc, $context );
		if ( $desc ) {
			printf( "<meta property=\"og:description\" content=\"%s\" />\n", esc_attr( $desc ) );
		}

		/**
		 * Filter the resolved og:url value just before output.
		 *
		 * @since 1.0.6
		 *
		 * @param string $url     Resolved canonical URL string.
		 * @param array  $context Standard context array from build_context().
		 */
		$url = apply_filters( 'rationalseo_og_url', $url, $context );
		if ( $url ) {
			printf( "<meta property=\"og:url\" content=\"%s\" />\n", esc_url( $url ) );
		}

		/**
		 * Filter the resolved og:site_name value just before output.
		 *
		 * @since 1.0.6
		 *
		 * @param string $site_name Resolved site name string.
		 * @param array  $context   Standard context array from build_context().
		 */
		$site_name = apply_filters( 'rationalseo_og_site_name', $site_name, $context );
		if ( $site_name ) {
			printf( "<meta property=\"og:site_name\" content=\"%s\" />\n", esc_attr( $site_name ) );
		}

		if ( ! empty( $image_data['url'] ) ) {
			printf( "<meta property=\"og:image\" content=\"%s\" />\n", esc_url( $image_data['url'] ) );

			if ( ! empty( $image_data['secure_url'] ) ) {
				printf( "<meta property=\"og:image:secure_url\" content=\"%s\" />\n", esc_url( $image_data['secure_url'] ) );
			}

			if ( ! empty( $image_data['type'] ) ) {
				printf( "<meta property=\"og:image:type\" content=\"%s\" />\n", esc_attr( $image_data['type'] ) );
			}

			if ( $image_data['width'] > 0 ) {
				printf( "<meta property=\"og:image:width\" content=\"%d\" />\n", (int) $image_data['width'] );
			}

			if ( $image_data['height'] > 0 ) {
				printf( "<meta property=\"og:image:height\" content=\"%d\" />\n", (int) $image_data['height'] );
			}

			if ( ! empty( $image_data['alt'] ) ) {
				printf( "<meta property=\"og:image:alt\" content=\"%s\" />\n", esc_attr( $image_data['alt'] ) );
			}
		}
	}

	/**
	 * Output Twitter Card meta tags.
	 */
	private function output_twitter_cards() {
		$context = $this->build_context();

		/**
		 * Skip the Twitter Cards block entirely when truthy.
		 *
		 * @since 1.0.6
		 *
		 * @param bool  $skip    Whether to skip output. Default false.
		 * @param array $context Standard context array from build_context().
		 */
		if ( apply_filters( 'rationalseo_skip_twitter_cards', false, $context ) ) {
			return;
		}

		$card_type  = $this->settings->get( 'twitter_card_type', 'summary_large_image' );
		$title      = $this->get_title();
		$desc       = $this->get_description();
		$image_data = $this->get_social_image_data();

		/**
		 * Filter the resolved twitter:card type value just before output.
		 *
		 * @since 1.0.6
		 *
		 * @param string $card_type Resolved Twitter card type string.
		 * @param array  $context   Standard context array from build_context().
		 */
		$card_type = apply_filters( 'rationalseo_twitter_card_type', $card_type, $context );
		printf( "<meta name=\"twitter:card\" content=\"%s\" />\n", esc_attr( $card_type ) );

		/**
		 * Filter the resolved twitter:title value just before output.
		 *
		 * @since 1.0.6
		 *
		 * @param string $title   Resolved Twitter title string.
		 * @param array  $context Standard context array from build_context().
		 */
		$title = apply_filters( 'rationalseo_twitter_title', $title, $context );
		if ( $title ) {
			printf( "<meta name=\"twitter:title\" content=\"%s\" />\n", esc_attr( $title ) );
		}

		/**
		 * Filter the resolved twitter:description value just before output.
		 *
		 * @since 1.0.6
		 *
		 * @param string $desc    Resolved Twitter description string.
		 * @param array  $context Standard context array from build_context().
		 */
		$desc = apply_filters( 'rationalseo_twitter_description', $desc, $context );
		if ( $desc ) {
			printf( "<meta name=\"twitter:description\" content=\"%s\" />\n", esc_attr( $desc ) );
		}

		if ( ! empty( $image_data['url'] ) ) {
			printf( "<meta name=\"twitter:image\" content=\"%s\" />\n", esc_url( $image_data['url'] ) );

			if ( ! empty( $image_data['alt'] ) ) {
				printf( "<meta name=\"twitter:image:alt\" content=\"%s\" />\n", esc_attr( $image_data['alt'] ) );
			}
		}
	}

	/**
	 * Build the standard context array passed to all filters and actions.
	 *
	 * The mode string reflects the current WordPress conditional hierarchy.
	 * queried_object is passed through raw from get_queried_object() — callers
	 * receive a WP_Post, WP_Term, WP_Post_Type, WP_User, or null depending on
	 * the current route.
	 *
	 * @since 1.0.6
	 *
	 * @return array {
	 *     @type string $mode           Resolution mode: 'front_page' | 'home' | 'singular' |
	 *                                  'archive_term' | 'archive_post_type' | 'archive_author' |
	 *                                  'archive_date' | 'search' | '404' | 'fallback'.
	 *     @type mixed  $queried_object Result of get_queried_object() — WP_Post, WP_Term,
	 *                                  WP_Post_Type, WP_User, or null.
	 *     @type int    $post_id        Queried post ID when is_singular(); 0 otherwise.
	 *     @type int    $term_id        Queried term ID when is a taxonomy archive; 0 otherwise.
	 * }
	 */
	private function build_context() {
		if ( is_front_page() ) {
			$mode = 'front_page';
		} elseif ( is_home() ) {
			$mode = 'home';
		} elseif ( is_singular() ) {
			$mode = 'singular';
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$mode = 'archive_term';
		} elseif ( is_post_type_archive() ) {
			$mode = 'archive_post_type';
		} elseif ( is_author() ) {
			$mode = 'archive_author';
		} elseif ( is_date() ) {
			$mode = 'archive_date';
		} elseif ( is_search() ) {
			$mode = 'search';
		} elseif ( is_404() ) {
			$mode = '404';
		} else {
			$mode = 'fallback';
		}

		$is_tax_archive = ( is_category() || is_tag() || is_tax() );

		return array(
			'mode'           => $mode,
			'queried_object' => get_queried_object(),
			'post_id'        => is_singular() ? (int) get_queried_object_id() : 0,
			'term_id'        => $is_tax_archive ? (int) get_queried_object_id() : 0,
		);
	}

	/**
	 * Normalize image data array, filling any missing keys with safe defaults.
	 *
	 * Accepts a partial or non-array value from external filter callbacks and
	 * ensures the returned array always contains every key in the canonical shape.
	 *
	 * @since 1.0.6
	 *
	 * @param mixed $data Partial image data array, or any non-array value.
	 * @return array Normalized image data with all canonical keys present.
	 */
	private function normalize_image_data( $data ) {
		return wp_parse_args(
			(array) $data,
			array(
				'url'        => '',
				'secure_url' => '',
				'type'       => '',
				'width'      => 0,
				'height'     => 0,
				'alt'        => '',
				'id'         => 0,
			)
		);
	}

	/**
	 * Get social sharing image data.
	 *
	 * Returns a structured array describing the social image for the current
	 * page. All keys are always present; unknown values use safe defaults.
	 *
	 * Resolution priority:
	 * 1. is_home()     — blog page og_image meta, then blog page featured image.
	 * 2. is_singular() — post og_image meta, then post featured image.
	 * 3. is_category()/is_tag()/is_tax() — term og_image meta.
	 * 4. Settings social_default_image.
	 * 5. Settings site_logo.
	 * 6. Empty (all defaults).
	 *
	 * @since 1.0.6
	 *
	 * @return array {
	 *     @type string $url        Canonical image URL; https when site is https.
	 *     @type string $secure_url HTTPS URL; matches $url when site is https; '' when no clear https variant.
	 *     @type string $type       MIME type, e.g. 'image/jpeg'; '' when unknown.
	 *     @type int    $width      Image width in pixels; 0 when unknown.
	 *     @type int    $height     Image height in pixels; 0 when unknown.
	 *     @type string $alt        Attachment alt text; '' when unknown.
	 *     @type int    $id         Attachment post ID; 0 when image is a raw URL.
	 * }
	 */
	private function get_social_image_data() {
		// Return cached value if available.
		if ( null !== $this->cached_social_image_data ) {
			return $this->cached_social_image_data;
		}

		/**
		 * Short-circuit the social image data resolution.
		 *
		 * Return non-null to short-circuit default resolution; return null to fall
		 * through to internal logic. A partial array is accepted — missing keys are
		 * filled with safe defaults by normalize_image_data().
		 *
		 * @since 1.0.6
		 *
		 * @param array|null $pre     Return a (partial) image data array to short-circuit, or null to continue.
		 * @param array      $context Standard context array from build_context().
		 */
		$pre = apply_filters( 'rationalseo_og_image_data', null, $this->build_context() );
		if ( null !== $pre ) {
			$this->cached_social_image_data = $this->normalize_image_data( $pre );
			return $this->cached_social_image_data;
		}

		$empty = $this->normalize_image_data( array() );

		// Determine whether the site uses https for secure_url logic.
		$site_is_https = ( 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME ) );

		/**
		 * Build image data from an attachment ID (featured image).
		 * Resolves width, height, mime type, alt, and URL from WP attachment APIs.
		 *
		 * @param int $attachment_id Attachment post ID.
		 * @return array Normalized image data.
		 */
		$build_from_attachment = function( $attachment_id ) use ( $empty, $site_is_https ) {
			$url = wp_get_attachment_url( $attachment_id );
			if ( ! $url ) {
				return $empty;
			}

			$meta   = wp_get_attachment_metadata( $attachment_id );
			$width  = ( isset( $meta['width'] ) ) ? (int) $meta['width'] : 0;
			$height = ( isset( $meta['height'] ) ) ? (int) $meta['height'] : 0;
			$type   = (string) get_post_mime_type( $attachment_id );
			$alt    = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

			if ( $site_is_https ) {
				$secure_url = $url;
			} else {
				$parsed_scheme = wp_parse_url( $url, PHP_URL_SCHEME );
				$secure_url    = ( 'http' === $parsed_scheme || 'https' === $parsed_scheme )
					? set_url_scheme( $url, 'https' )
					: '';
			}

			return array(
				'url'        => $url,
				'secure_url' => $secure_url,
				'type'       => $type,
				'width'      => $width,
				'height'     => $height,
				'alt'        => $alt,
				'id'         => (int) $attachment_id,
			);
		};

		/**
		 * Build image data from a raw URL string (meta value, settings).
		 * Width, height, type, and alt are unknown without a remote fetch.
		 *
		 * @param string $url Raw image URL.
		 * @return array Normalized image data.
		 */
		$build_from_url = function( $url ) use ( $site_is_https ) {
			if ( $site_is_https ) {
				$secure_url = $url;
			} else {
				$parsed_scheme = wp_parse_url( $url, PHP_URL_SCHEME );
				$secure_url    = ( 'http' === $parsed_scheme || 'https' === $parsed_scheme )
					? set_url_scheme( $url, 'https' )
					: '';
			}

			return array(
				'url'        => $url,
				'secure_url' => $secure_url,
				'type'       => '',
				'width'      => 0,
				'height'     => 0,
				'alt'        => '',
				'id'         => 0,
			);
		};

		// 1. Blog page: og_image meta or featured image.
		if ( is_home() ) {
			$blog_page_id = get_option( 'page_for_posts' );
			if ( $blog_page_id ) {
				$meta = $this->get_post_seo_meta( $blog_page_id );
				if ( ! empty( $meta['og_image'] ) ) {
					$this->cached_social_image_data = $build_from_url( $meta['og_image'] );
					return $this->cached_social_image_data;
				}
				if ( has_post_thumbnail( $blog_page_id ) ) {
					$thumbnail_id = get_post_thumbnail_id( $blog_page_id );
					if ( $thumbnail_id ) {
						$data = $build_from_attachment( $thumbnail_id );
						if ( ! empty( $data['url'] ) ) {
							$this->cached_social_image_data = $data;
							return $this->cached_social_image_data;
						}
					}
				}
			}
		}

		// 2. Singular post/page: og_image meta or featured image.
		if ( is_singular() ) {
			$post = get_queried_object();
			$meta = $this->get_post_seo_meta( $post->ID );

			if ( ! empty( $meta['og_image'] ) ) {
				$this->cached_social_image_data = $build_from_url( $meta['og_image'] );
				return $this->cached_social_image_data;
			}

			if ( has_post_thumbnail( $post ) ) {
				$thumbnail_id = get_post_thumbnail_id( $post );
				if ( $thumbnail_id ) {
					$data = $build_from_attachment( $thumbnail_id );
					if ( ! empty( $data['url'] ) ) {
						$this->cached_social_image_data = $data;
						return $this->cached_social_image_data;
					}
				}
			}
		}

		// 3. Taxonomy archive: term og_image meta.
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			$meta = $this->get_term_seo_meta( $term->term_id );
			if ( ! empty( $meta['og_image'] ) ) {
				$this->cached_social_image_data = $build_from_url( $meta['og_image'] );
				return $this->cached_social_image_data;
			}
		}

		// 4. Default social image from settings.
		$default_image = $this->settings->get( 'social_default_image' );
		if ( ! empty( $default_image ) ) {
			$this->cached_social_image_data = $build_from_url( $default_image );
			return $this->cached_social_image_data;
		}

		// 5. Site logo from settings.
		$site_logo = $this->settings->get( 'site_logo' );
		if ( ! empty( $site_logo ) ) {
			$this->cached_social_image_data = $build_from_url( $site_logo );
			return $this->cached_social_image_data;
		}

		// 6. Empty fallback.
		$this->cached_social_image_data = $empty;
		return $this->cached_social_image_data;
	}

	/**
	 * Output JSON-LD structured data.
	 */
	private function output_schema() {
		$graph     = array();
		$site_url  = home_url( '/' );
		$site_name = $this->settings->get( 'site_name', get_bloginfo( 'name' ) );
		$site_type = $this->settings->get( 'site_type', 'organization' );
		$site_logo = $this->settings->get( 'site_logo' );

		// Build Organization or Person entity.
		$publisher_type = 'organization' === $site_type ? 'Organization' : 'Person';
		$publisher      = array(
			'@type' => $publisher_type,
			'@id'   => $site_url . '#' . strtolower( $publisher_type ),
			'name'  => $site_name,
		);

		// Add logo to publisher if available.
		if ( ! empty( $site_logo ) ) {
			$publisher['logo'] = array(
				'@type' => 'ImageObject',
				'@id'   => $site_url . '#logo',
				'url'   => $site_logo,
			);
		}

		$graph[] = $publisher;

		// Build WebSite entity.
		$website = array(
			'@type'     => 'WebSite',
			'@id'       => $site_url . '#website',
			'url'       => $site_url,
			'name'      => $site_name,
			'publisher' => array(
				'@id' => $site_url . '#' . strtolower( $publisher_type ),
			),
		);

		$graph[] = $website;

		// Build WebPage entity.
		$page_url = $this->get_canonical();
		$webpage  = array(
			'@type'    => 'WebPage',
			'@id'      => $page_url . '#webpage',
			'url'      => $page_url,
			'isPartOf' => array(
				'@id' => $site_url . '#website',
			),
		);

		$graph[] = $webpage;

		// Build Article entity for singular posts/pages.
		if ( is_singular() && ! is_front_page() ) {
			$post       = get_queried_object();
			$image_data = $this->get_social_image_data();
			$image      = $image_data['url'];

			$article = array(
				'@type'            => 'Article',
				'@id'              => $page_url . '#article',
				'headline'         => $this->get_title(),
				'mainEntityOfPage' => array(
					'@id' => $page_url . '#webpage',
				),
				'publisher'        => array(
					'@id' => $site_url . '#' . strtolower( $publisher_type ),
				),
				'datePublished'    => get_the_date( 'c', $post ),
				'dateModified'     => get_the_modified_date( 'c', $post ),
			);

			// Add image if available.
			if ( ! empty( $image ) ) {
				$article['image'] = array(
					'@type' => 'ImageObject',
					'@id'   => $page_url . '#primaryimage',
					'url'   => $image,
				);
			}

			// Add description if available.
			$description = $this->get_description();
			if ( ! empty( $description ) ) {
				$article['description'] = $description;
			}

			// Add author if available.
			$author = get_the_author_meta( 'display_name', $post->post_author );
			if ( ! empty( $author ) ) {
				$article['author'] = array(
					'@type' => 'Person',
					'name'  => $author,
				);
			}

			$graph[] = $article;
		}

		// Build the full schema object.
		$schema = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);

		// Output the JSON-LD.
		printf(
			"<script type=\"application/ld+json\">\n%s\n</script>\n",
			wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG )
		);
	}
}
