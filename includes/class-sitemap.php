<?php
/**
 * RationalSEO Sitemap Class
 *
 * Handles XML sitemap generation with caching.
 *
 * @package RationalSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RationalSEO_Sitemap {

	/**
	 * Settings instance.
	 *
	 * @var RationalSEO_Settings
	 */
	private $settings;

	/**
	 * URLs per sitemap page.
	 *
	 * @var int
	 */
	const URLS_PER_PAGE = 1000;

	/**
	 * Cache expiration in seconds (1 hour).
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = 3600;

	/**
	 * Transient prefix.
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'rationalseo_sitemap_';

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
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_sitemap_request' ) );
		add_filter( 'redirect_canonical', array( $this, 'prevent_sitemap_redirect' ) );
		add_action( 'rationalseo_rebuild_sitemap', array( $this, 'rebuild_sitemap_cache' ), 10, 2 );

		// Clear cache when posts are updated.
		add_action( 'save_post', array( $this, 'clear_post_type_cache' ), 10, 2 );
		add_action( 'delete_post', array( $this, 'clear_post_type_cache' ), 10, 2 );
	}

	/**
	 * Register rewrite rules for sitemaps.
	 */
	public function register_rewrite_rules() {
		// Sitemap index.
		add_rewrite_rule(
			'^sitemap\.xml$',
			'index.php?rationalseo_sitemap=index',
			'top'
		);

		// Post type sitemaps (with optional page number).
		add_rewrite_rule(
			'^sitemap-([a-z0-9_-]+)-?(\d*)\.xml$',
			'index.php?rationalseo_sitemap=$matches[1]&rationalseo_sitemap_page=$matches[2]',
			'top'
		);
	}

	/**
	 * Add query vars.
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'rationalseo_sitemap';
		$vars[] = 'rationalseo_sitemap_page';
		return $vars;
	}

	/**
	 * Prevent WordPress from adding a trailing slash to sitemap URLs.
	 *
	 * WordPress redirect_canonical() adds trailing slashes to URLs by default,
	 * which breaks sitemap.xml by redirecting to sitemap.xml/.
	 *
	 * @since 1.0.5
	 *
	 * @param string $redirect_url The redirect URL.
	 * @return string|false The redirect URL, or false to cancel the redirect.
	 */
	public function prevent_sitemap_redirect( $redirect_url ) {
		if ( get_query_var( 'rationalseo_sitemap' ) ) {
			return false;
		}

		// Fallback: check request URI directly in case rewrite rules are not flushed.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Validated with isset, unslashed, and sanitized.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path        = trim( wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );
		if ( preg_match( '/^sitemap(-[a-z0-9_-]+)?\.xml$/', $path ) ) {
			return false;
		}

		return $redirect_url;
	}

	/**
	 * Handle sitemap request.
	 */
	public function handle_sitemap_request() {
		$sitemap = get_query_var( 'rationalseo_sitemap' );

		// Fallback: match request URI directly if rewrite rules did not set query vars.
		if ( empty( $sitemap ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Validated with isset, unslashed, and sanitized.
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$path        = trim( wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );

			if ( 'sitemap.xml' === $path ) {
				$sitemap = 'index';
			} elseif ( preg_match( '/^sitemap-([a-z0-9_-]+)-?(\d*)\.xml$/', $path, $matches ) ) {
				$sitemap = $matches[1];
				set_query_var( 'rationalseo_sitemap_page', ! empty( $matches[2] ) ? (int) $matches[2] : 1 );
			}

			if ( ! empty( $sitemap ) ) {
				set_query_var( 'rationalseo_sitemap', $sitemap );
			}
		}

		if ( empty( $sitemap ) ) {
			return;
		}

		// Check if sitemaps are enabled.
		if ( ! $this->settings->get( 'sitemap_enabled', true ) ) {
			return;
		}

		$page = (int) get_query_var( 'rationalseo_sitemap_page', 1 );
		if ( $page < 1 ) {
			$page = 1;
		}

		if ( 'index' === $sitemap ) {
			$this->render_sitemap_index();
		} else {
			$this->render_post_type_sitemap( $sitemap, $page );
		}
	}

	/**
	 * Get public post types for sitemap.
	 *
	 * @return array
	 */
	private function get_sitemap_post_types() {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		// Remove attachments.
		unset( $post_types['attachment'] );

		// Get excluded types from settings.
		$excluded = $this->settings->get( 'sitemap_exclude_types', array() );
		if ( ! is_array( $excluded ) ) {
			$excluded = array();
		}

		// Filter out excluded types.
		foreach ( $excluded as $type ) {
			unset( $post_types[ $type ] );
		}

		return $post_types;
	}

	/**
	 * Get posts for sitemap.
	 *
	 * @param string $post_type Post type.
	 * @param int    $page      Page number.
	 * @return array
	 */
	private function get_sitemap_posts( $post_type, $page = 1 ) {
		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => self::URLS_PER_PAGE,
			'paged'          => $page,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'meta_query'     => array(
				array(
					'key'     => '_rationalseo_noindex',
					'compare' => 'NOT EXISTS',
				),
			),
		);

		// Filter by freshness if set.
		$max_age = (int) $this->settings->get( 'sitemap_max_age', 0 );
		if ( $max_age > 0 ) {
			$args['date_query'] = array(
				array(
					'after'  => $max_age . ' months ago',
					'column' => 'post_modified',
				),
			);
		}

		return get_posts( $args );
	}

	/**
	 * Get total pages for a post type.
	 *
	 * @param string $post_type Post type.
	 * @return int
	 */
	private function get_total_pages( $post_type ) {
		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_rationalseo_noindex',
					'compare' => 'NOT EXISTS',
				),
			),
		);

		// Filter by freshness if set.
		$max_age = (int) $this->settings->get( 'sitemap_max_age', 0 );
		if ( $max_age > 0 ) {
			$args['date_query'] = array(
				array(
					'after'  => $max_age . ' months ago',
					'column' => 'post_modified',
				),
			);
		}

		$query = new WP_Query( $args );
		$total = $query->found_posts;

		return (int) ceil( $total / self::URLS_PER_PAGE );
	}

	/**
	 * Get last modified date for a post type.
	 *
	 * @param string $post_type Post type.
	 * @return string|null
	 */
	private function get_last_modified( $post_type ) {
		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);

		if ( empty( $posts ) ) {
			return null;
		}

		$post = get_post( $posts[0] );
		return $this->format_date( $post->post_modified_gmt );
	}

	/**
	 * Format date for sitemap.
	 *
	 * @param string $date Date string.
	 * @return string
	 */
	private function format_date( $date ) {
		return gmdate( 'c', strtotime( $date ) );
	}

	/**
	 * Get cached sitemap or serve stale while revalidating.
	 *
	 * @param string $cache_key Cache key.
	 * @param string $type      Sitemap type (index or post type).
	 * @param int    $page      Page number for post type sitemaps.
	 * @return string|null
	 */
	private function get_cached_sitemap( $cache_key, $type, $page = 1 ) {
		$cached = get_transient( self::TRANSIENT_PREFIX . $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Check for stale cache.
		$stale = get_option( self::TRANSIENT_PREFIX . $cache_key . '_stale' );

		if ( false !== $stale ) {
			// Schedule background rebuild.
			if ( ! wp_next_scheduled( 'rationalseo_rebuild_sitemap', array( $type, $page ) ) ) {
				wp_schedule_single_event( time(), 'rationalseo_rebuild_sitemap', array( $type, $page ) );
			}
			return $stale;
		}

		return null;
	}

	/**
	 * Set sitemap cache.
	 *
	 * @param string $cache_key Cache key.
	 * @param string $content   Sitemap content.
	 */
	private function set_sitemap_cache( $cache_key, $content ) {
		// Set transient with expiration.
		set_transient( self::TRANSIENT_PREFIX . $cache_key, $content, self::CACHE_EXPIRATION );

		// Also save as stale backup.
		update_option( self::TRANSIENT_PREFIX . $cache_key . '_stale', $content, false );
	}

	/**
	 * Rebuild sitemap cache (called via scheduled event).
	 *
	 * @param string $type Sitemap type.
	 * @param int    $page Page number.
	 */
	public function rebuild_sitemap_cache( $type, $page = 1 ) {
		if ( 'index' === $type ) {
			$content   = $this->generate_sitemap_index();
			$cache_key = 'index';
		} else {
			$content   = $this->generate_post_type_sitemap( $type, $page );
			$cache_key = $type . '_' . $page;
		}

		$this->set_sitemap_cache( $cache_key, $content );
	}

	/**
	 * Send XML headers.
	 *
	 * @param string $last_modified Last modified date.
	 */
	private function send_xml_headers( $last_modified = null ) {
		status_header( 200 );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'Cache-Control: max-age=' . self::CACHE_EXPIRATION . ', public' );

		if ( $last_modified ) {
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', strtotime( $last_modified ) ) . ' GMT' );
		}
	}

	/**
	 * Render sitemap index.
	 */
	private function render_sitemap_index() {
		$cache_key = 'index';
		$cached    = $this->get_cached_sitemap( $cache_key, 'index' );

		if ( null !== $cached ) {
			$this->send_xml_headers();
			echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}

		$content = $this->generate_sitemap_index();
		$this->set_sitemap_cache( $cache_key, $content );

		$this->send_xml_headers();
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Generate sitemap index content.
	 *
	 * @return string
	 */
	private function generate_sitemap_index() {
		$post_types = $this->get_sitemap_post_types();

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $post_types as $post_type ) {
			$total_pages  = $this->get_total_pages( $post_type->name );
			$last_mod     = $this->get_last_modified( $post_type->name );

			if ( $total_pages < 1 ) {
				continue;
			}

			for ( $page = 1; $page <= $total_pages; $page++ ) {
				$loc = $this->get_sitemap_url( $post_type->name, $page, $total_pages );

				$xml .= "\t<sitemap>\n";
				$xml .= "\t\t<loc>" . esc_url( $loc ) . "</loc>\n";

				if ( $last_mod ) {
					$xml .= "\t\t<lastmod>" . esc_xml( $last_mod ) . "</lastmod>\n";
				}

				$xml .= "\t</sitemap>\n";
			}
		}

		$xml .= '</sitemapindex>';

		return $xml;
	}

	/**
	 * Get sitemap URL.
	 *
	 * @param string $post_type   Post type.
	 * @param int    $page        Page number.
	 * @param int    $total_pages Total pages.
	 * @return string
	 */
	private function get_sitemap_url( $post_type, $page = 1, $total_pages = 1 ) {
		$base = home_url( '/' );

		if ( $total_pages > 1 ) {
			return $base . 'sitemap-' . $post_type . '-' . $page . '.xml';
		}

		return $base . 'sitemap-' . $post_type . '.xml';
	}

	/**
	 * Render post type sitemap.
	 *
	 * @param string $post_type Post type.
	 * @param int    $page      Page number.
	 */
	private function render_post_type_sitemap( $post_type, $page = 1 ) {
		// Verify post type is valid and not excluded.
		$valid_types = $this->get_sitemap_post_types();

		if ( ! isset( $valid_types[ $post_type ] ) ) {
			status_header( 404 );
			exit;
		}

		$cache_key = $post_type . '_' . $page;
		$cached    = $this->get_cached_sitemap( $cache_key, $post_type, $page );

		if ( null !== $cached ) {
			$this->send_xml_headers();
			echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}

		$content = $this->generate_post_type_sitemap( $post_type, $page );

		if ( empty( $content ) ) {
			status_header( 404 );
			exit;
		}

		$this->set_sitemap_cache( $cache_key, $content );

		$this->send_xml_headers();
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Generate post type sitemap content.
	 *
	 * @param string $post_type Post type.
	 * @param int    $page      Page number.
	 * @return string
	 */
	private function generate_post_type_sitemap( $post_type, $page = 1 ) {
		$posts = $this->get_sitemap_posts( $post_type, $page );

		if ( empty( $posts ) ) {
			return '';
		}

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $posts as $post ) {
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . esc_url( get_permalink( $post ) ) . "</loc>\n";
			$xml .= "\t\t<lastmod>" . esc_xml( $this->format_date( $post->post_modified_gmt ) ) . "</lastmod>\n";
			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>';

		return $xml;
	}

	/**
	 * Clear cache for a post type when post is saved/deleted.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function clear_post_type_cache( $post_id, $post = null ) {
		if ( ! $post ) {
			$post = get_post( $post_id );
		}

		if ( ! $post || 'revision' === $post->post_type ) {
			return;
		}

		// Clear all caches for this post type.
		$total_pages = $this->get_total_pages( $post->post_type );

		for ( $page = 1; $page <= max( 1, $total_pages ); $page++ ) {
			$cache_key = $post->post_type . '_' . $page;
			delete_transient( self::TRANSIENT_PREFIX . $cache_key );
		}

		// Clear index cache.
		delete_transient( self::TRANSIENT_PREFIX . 'index' );
	}

	/**
	 * Clear all sitemap caches.
	 */
	public static function clear_all_caches() {
		global $wpdb;

		// Delete all transients with our prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cache clearing requires direct query; caching the deletion would be counterproductive.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_' . self::TRANSIENT_PREFIX . '%',
				'_transient_timeout_' . self::TRANSIENT_PREFIX . '%'
			)
		);

		// Delete stale backups.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cache clearing requires direct query; caching the deletion would be counterproductive.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				self::TRANSIENT_PREFIX . '%_stale'
			)
		);
	}

	/**
	 * Flush rewrite rules.
	 */
	public static function flush_rules() {
		flush_rewrite_rules();
	}
}
