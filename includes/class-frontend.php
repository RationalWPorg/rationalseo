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
	 * Get the SEO title.
	 *
	 * @return string
	 */
	private function get_title() {
		$separator = $this->settings->get( 'separator', '|' );
		$site_name = $this->settings->get( 'site_name', get_bloginfo( 'name' ) );

		// Homepage.
		if ( is_front_page() || is_home() ) {
			$home_title = $this->settings->get( 'home_title' );
			if ( ! empty( $home_title ) ) {
				return $home_title;
			}
			return $site_name;
		}

		// Singular posts/pages.
		if ( is_singular() ) {
			$post       = get_queried_object();
			$post_title = get_the_title( $post );

			// Check for custom SEO title (for future post meta integration).
			$custom_title = get_post_meta( $post->ID, '_rationalseo_title', true );
			if ( ! empty( $custom_title ) ) {
				return $custom_title;
			}

			return sprintf( '%s %s %s', $post_title, $separator, $site_name );
		}

		// Archive pages.
		if ( is_archive() ) {
			if ( is_category() || is_tag() || is_tax() ) {
				$term = get_queried_object();
				return sprintf( '%s %s %s', $term->name, $separator, $site_name );
			}

			if ( is_post_type_archive() ) {
				$post_type = get_queried_object();
				return sprintf( '%s %s %s', $post_type->labels->name, $separator, $site_name );
			}

			if ( is_author() ) {
				$author = get_queried_object();
				return sprintf( '%s %s %s', $author->display_name, $separator, $site_name );
			}

			if ( is_date() ) {
				$date_title = get_the_archive_title();
				return sprintf( '%s %s %s', $date_title, $separator, $site_name );
			}
		}

		// Search results.
		if ( is_search() ) {
			/* translators: %s: Search query */
			$search_title = sprintf( __( 'Search Results for "%s"', 'rationalseo' ), get_search_query() );
			return sprintf( '%s %s %s', $search_title, $separator, $site_name );
		}

		// 404 page.
		if ( is_404() ) {
			return sprintf( '%s %s %s', __( 'Page Not Found', 'rationalseo' ), $separator, $site_name );
		}

		// Fallback.
		return $site_name;
	}

	/**
	 * Output meta description.
	 */
	private function output_description() {
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
		// Homepage.
		if ( is_front_page() || is_home() ) {
			$home_description = $this->settings->get( 'home_description' );
			if ( ! empty( $home_description ) ) {
				return $this->truncate_description( $home_description );
			}
			return $this->truncate_description( get_bloginfo( 'description' ) );
		}

		// Singular posts/pages.
		if ( is_singular() ) {
			$post = get_queried_object();

			// Check for custom SEO description (for future post meta integration).
			$custom_desc = get_post_meta( $post->ID, '_rationalseo_desc', true );
			if ( ! empty( $custom_desc ) ) {
				return $this->truncate_description( $custom_desc );
			}

			// Use excerpt or generate from content.
			if ( has_excerpt( $post ) ) {
				return $this->truncate_description( get_the_excerpt( $post ) );
			}

			// Generate from content.
			$content = wp_strip_all_tags( $post->post_content );
			$content = preg_replace( '/\s+/', ' ', $content );
			return $this->truncate_description( $content );
		}

		// Archive pages.
		if ( is_archive() ) {
			if ( is_category() || is_tag() || is_tax() ) {
				$term = get_queried_object();
				if ( ! empty( $term->description ) ) {
					return $this->truncate_description( $term->description );
				}
			}
		}

		return '';
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
			$post    = get_queried_object();
			$noindex = get_post_meta( $post->ID, '_rationalseo_noindex', true );
			if ( $noindex ) {
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

		return $robots;
	}

	/**
	 * Output canonical URL.
	 */
	private function output_canonical() {
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
		// Check for custom canonical on singular posts.
		if ( is_singular() ) {
			$post           = get_queried_object();
			$custom_canonical = get_post_meta( $post->ID, '_rationalseo_canonical', true );
			if ( ! empty( $custom_canonical ) ) {
				return $custom_canonical;
			}
			return get_permalink( $post );
		}

		// Homepage.
		if ( is_front_page() || is_home() ) {
			return home_url( '/' );
		}

		// Archive pages.
		if ( is_archive() ) {
			if ( is_category() || is_tag() || is_tax() ) {
				$term = get_queried_object();
				return get_term_link( $term );
			}

			if ( is_post_type_archive() ) {
				$post_type = get_queried_object();
				return get_post_type_archive_link( $post_type->name );
			}

			if ( is_author() ) {
				$author = get_queried_object();
				return get_author_posts_url( $author->ID );
			}
		}

		// Fallback to current URL (cleaned).
		global $wp;
		return home_url( $wp->request );
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
}
