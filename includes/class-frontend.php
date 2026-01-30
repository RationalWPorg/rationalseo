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
	 * Cached social image URL.
	 *
	 * @var string|null
	 */
	private $cached_social_image = null;

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
		if ( null === $this->post_meta_cache ) {
			$all_meta = get_post_meta( $post_id );
			$this->post_meta_cache = array(
				'title'     => isset( $all_meta['_rationalseo_title'][0] ) ? $all_meta['_rationalseo_title'][0] : '',
				'desc'      => isset( $all_meta['_rationalseo_desc'][0] ) ? $all_meta['_rationalseo_desc'][0] : '',
				'noindex'   => isset( $all_meta['_rationalseo_noindex'][0] ) ? $all_meta['_rationalseo_noindex'][0] : '',
				'canonical' => isset( $all_meta['_rationalseo_canonical'][0] ) ? $all_meta['_rationalseo_canonical'][0] : '',
				'og_image'  => isset( $all_meta['_rationalseo_og_image'][0] ) ? $all_meta['_rationalseo_og_image'][0] : '',
			);
		}
		return $this->post_meta_cache;
	}

	/**
	 * Get all RationalSEO term meta in one query.
	 *
	 * @param int $term_id Term ID.
	 * @return array Associative array of meta values.
	 */
	private function get_term_seo_meta( $term_id ) {
		if ( null === $this->term_meta_cache ) {
			$this->term_meta_cache = array(
				'title'     => get_term_meta( $term_id, '_rationalseo_term_title', true ),
				'desc'      => get_term_meta( $term_id, '_rationalseo_term_desc', true ),
				'noindex'   => get_term_meta( $term_id, '_rationalseo_term_noindex', true ),
				'canonical' => get_term_meta( $term_id, '_rationalseo_term_canonical', true ),
				'og_image'  => get_term_meta( $term_id, '_rationalseo_term_og_image', true ),
			);
		}
		return $this->term_meta_cache;
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
					$this->cached_title = $meta['title'];
					return $this->cached_title;
				}
			}
			$this->cached_title = sprintf( '%s %s %s', $site_name, $separator, get_bloginfo( 'description' ) );
			return $this->cached_title;
		}

		// Blog page (separate posts page).
		if ( is_home() ) {
			$blog_page_id = get_option( 'page_for_posts' );
			if ( $blog_page_id ) {
				$meta = $this->get_post_seo_meta( $blog_page_id );
				if ( ! empty( $meta['title'] ) ) {
					$this->cached_title = $meta['title'];
					return $this->cached_title;
				}
			}
			$this->cached_title = sprintf( '%s %s %s', __( 'Blog', 'rationalseo' ), $separator, $site_name );
			return $this->cached_title;
		}

		// Singular posts/pages.
		if ( is_singular() ) {
			$post       = get_queried_object();
			$post_title = get_the_title( $post );
			$meta       = $this->get_post_seo_meta( $post->ID );

			if ( ! empty( $meta['title'] ) ) {
				$this->cached_title = $meta['title'];
				return $this->cached_title;
			}

			$this->cached_title = sprintf( '%s %s %s', $post_title, $separator, $site_name );
			return $this->cached_title;
		}

		// Archive pages.
		if ( is_archive() ) {
			if ( is_category() || is_tag() || is_tax() ) {
				$term = get_queried_object();
				$meta = $this->get_term_seo_meta( $term->term_id );

				if ( ! empty( $meta['title'] ) ) {
					$this->cached_title = $meta['title'];
					return $this->cached_title;
				}
				$this->cached_title = sprintf( '%s %s %s', $term->name, $separator, $site_name );
				return $this->cached_title;
			}

			if ( is_post_type_archive() ) {
				$post_type = get_queried_object();
				$this->cached_title = sprintf( '%s %s %s', $post_type->labels->name, $separator, $site_name );
				return $this->cached_title;
			}

			if ( is_author() ) {
				$author = get_queried_object();
				$this->cached_title = sprintf( '%s %s %s', $author->display_name, $separator, $site_name );
				return $this->cached_title;
			}

			if ( is_date() ) {
				$date_title = get_the_archive_title();
				$this->cached_title = sprintf( '%s %s %s', $date_title, $separator, $site_name );
				return $this->cached_title;
			}
		}

		// Search results.
		if ( is_search() ) {
			/* translators: %s: Search query */
			$search_title = sprintf( __( 'Search Results for "%s"', 'rationalseo' ), get_search_query() );
			$this->cached_title = sprintf( '%s %s %s', $search_title, $separator, $site_name );
			return $this->cached_title;
		}

		// 404 page.
		if ( is_404() ) {
			$this->cached_title = sprintf( '%s %s %s', __( 'Page Not Found', 'rationalseo' ), $separator, $site_name );
			return $this->cached_title;
		}

		// Fallback.
		$this->cached_title = $title;
		return $this->cached_title;
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
		// Return cached value if available.
		if ( null !== $this->cached_description ) {
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

			if ( ! empty( $meta['desc'] ) ) {
				$this->cached_description = $this->truncate_description( $meta['desc'] );
				return $this->cached_description;
			}

			// Use excerpt or generate from content.
			if ( has_excerpt( $post ) ) {
				$this->cached_description = $this->truncate_description( get_the_excerpt( $post ) );
				return $this->cached_description;
			}

			// Generate from content.
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
		}

		$this->cached_description = '';
		return $this->cached_description;
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
		// Return cached value if available.
		if ( null !== $this->cached_canonical ) {
			return $this->cached_canonical;
		}

		// Check for custom canonical on singular posts.
		if ( is_singular() ) {
			$post = get_queried_object();
			$meta = $this->get_post_seo_meta( $post->ID );
			if ( ! empty( $meta['canonical'] ) ) {
				$this->cached_canonical = $meta['canonical'];
				return $this->cached_canonical;
			}
			$this->cached_canonical = get_permalink( $post );
			return $this->cached_canonical;
		}

		// Homepage.
		if ( is_front_page() || is_home() ) {
			$this->cached_canonical = home_url( '/' );
			return $this->cached_canonical;
		}

		// Archive pages.
		if ( is_archive() ) {
			if ( is_category() || is_tag() || is_tax() ) {
				$term = get_queried_object();
				$meta = $this->get_term_seo_meta( $term->term_id );
				if ( ! empty( $meta['canonical'] ) ) {
					$this->cached_canonical = $meta['canonical'];
					return $this->cached_canonical;
				}
				$this->cached_canonical = get_term_link( $term );
				return $this->cached_canonical;
			}

			if ( is_post_type_archive() ) {
				$post_type = get_queried_object();
				$this->cached_canonical = get_post_type_archive_link( $post_type->name );
				return $this->cached_canonical;
			}

			if ( is_author() ) {
				$author = get_queried_object();
				$this->cached_canonical = get_author_posts_url( $author->ID );
				return $this->cached_canonical;
			}
		}

		// Fallback to current URL (cleaned).
		global $wp;
		$this->cached_canonical = home_url( $wp->request );
		return $this->cached_canonical;
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
		$locale    = get_locale();
		$og_type   = is_singular() && ! is_front_page() ? 'article' : 'website';
		$title     = $this->get_title();
		$desc      = $this->get_description();
		$url       = $this->get_canonical();
		$site_name = $this->settings->get( 'site_name', get_bloginfo( 'name' ) );
		$image     = $this->get_social_image();

		printf( "<meta property=\"og:locale\" content=\"%s\" />\n", esc_attr( $locale ) );
		printf( "<meta property=\"og:type\" content=\"%s\" />\n", esc_attr( $og_type ) );

		if ( $title ) {
			printf( "<meta property=\"og:title\" content=\"%s\" />\n", esc_attr( $title ) );
		}

		if ( $desc ) {
			printf( "<meta property=\"og:description\" content=\"%s\" />\n", esc_attr( $desc ) );
		}

		if ( $url ) {
			printf( "<meta property=\"og:url\" content=\"%s\" />\n", esc_url( $url ) );
		}

		if ( $site_name ) {
			printf( "<meta property=\"og:site_name\" content=\"%s\" />\n", esc_attr( $site_name ) );
		}

		if ( $image ) {
			printf( "<meta property=\"og:image\" content=\"%s\" />\n", esc_url( $image ) );
		}
	}

	/**
	 * Output Twitter Card meta tags.
	 */
	private function output_twitter_cards() {
		$card_type = $this->settings->get( 'twitter_card_type', 'summary_large_image' );
		$title     = $this->get_title();
		$desc      = $this->get_description();
		$image     = $this->get_social_image();

		printf( "<meta name=\"twitter:card\" content=\"%s\" />\n", esc_attr( $card_type ) );

		if ( $title ) {
			printf( "<meta name=\"twitter:title\" content=\"%s\" />\n", esc_attr( $title ) );
		}

		if ( $desc ) {
			printf( "<meta name=\"twitter:description\" content=\"%s\" />\n", esc_attr( $desc ) );
		}

		if ( $image ) {
			printf( "<meta name=\"twitter:image\" content=\"%s\" />\n", esc_url( $image ) );
		}
	}

	/**
	 * Get social sharing image.
	 *
	 * Priority:
	 * 1. Custom social image override (post meta)
	 * 2. Featured image (if singular post/page)
	 * 3. Default social image from settings
	 * 4. Site logo from settings
	 *
	 * @return string Image URL or empty string.
	 */
	private function get_social_image() {
		// Return cached value if available.
		if ( null !== $this->cached_social_image ) {
			return $this->cached_social_image;
		}

		// Try custom social image override for singular posts/pages.
		if ( is_singular() ) {
			$post = get_queried_object();
			$meta = $this->get_post_seo_meta( $post->ID );

			if ( ! empty( $meta['og_image'] ) ) {
				$this->cached_social_image = $meta['og_image'];
				return $this->cached_social_image;
			}

			// Try featured image.
			if ( has_post_thumbnail( $post ) ) {
				$thumbnail_id  = get_post_thumbnail_id( $post );
				$thumbnail_url = wp_get_attachment_image_url( $thumbnail_id, 'large' );
				if ( $thumbnail_url ) {
					$this->cached_social_image = $thumbnail_url;
					return $this->cached_social_image;
				}
			}
		}

		// Try custom social image for taxonomy archives.
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			$meta = $this->get_term_seo_meta( $term->term_id );
			if ( ! empty( $meta['og_image'] ) ) {
				$this->cached_social_image = $meta['og_image'];
				return $this->cached_social_image;
			}
		}

		// Try default social image from settings.
		$default_image = $this->settings->get( 'social_default_image' );
		if ( ! empty( $default_image ) ) {
			$this->cached_social_image = $default_image;
			return $this->cached_social_image;
		}

		// Try site logo from settings.
		$site_logo = $this->settings->get( 'site_logo' );
		if ( ! empty( $site_logo ) ) {
			$this->cached_social_image = $site_logo;
			return $this->cached_social_image;
		}

		$this->cached_social_image = '';
		return $this->cached_social_image;
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
			$post  = get_queried_object();
			$image = $this->get_social_image();

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
			wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);
	}
}
