<?php
/**
 * RationalWP Admin Menu
 *
 * Shared file that creates a parent menu for all RationalWP plugins.
 * Include this file in each plugin - it handles deduplication automatically.
 *
 * WordPress.org Compliance: The text domain MUST match the plugin slug. The canonical
 * version of this file uses %%TEXT_DOMAIN%% as a placeholder. When integrating into
 * a plugin, replace all instances of %%TEXT_DOMAIN%% with the plugin's text domain
 * (e.g., 'rationalcontent', 'rationalcleanup'). The function_exists() guards ensure
 * only the first loaded version executes, so text domain differences don't conflict.
 *
 * @package RationalWP
 * @version 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Version of this shared menu file - highest version wins.
if ( ! defined( 'RATIONALWP_MENU_VERSION' ) ) {
	define( 'RATIONALWP_MENU_VERSION', '1.1.1' );
}

// Remote JSON URL for plugin data.
if ( ! defined( 'RATIONALWP_PLUGINS_JSON_URL' ) ) {
	define( 'RATIONALWP_PLUGINS_JSON_URL', 'https://rationalwp.com/plugins.json' );
}

// Cache duration in seconds (24 hours).
if ( ! defined( 'RATIONALWP_PLUGINS_CACHE_DURATION' ) ) {
	define( 'RATIONALWP_PLUGINS_CACHE_DURATION', DAY_IN_SECONDS );
}

if ( ! function_exists( 'rationalwp_get_menu_icon' ) ) {
	/**
	 * Get the base64-encoded SVG icon for the menu.
	 *
	 * @return string Base64-encoded SVG data URI.
	 */
	function rationalwp_get_menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024"><path fill="currentColor" d="M447,784.94c-27.5,0-54.5.05-81.5-.06-3.75-.02-6.55.99-9.25,3.76-17.93,18.34-36.01,36.53-54.1,54.72-20.03,20.15-51.14,26.65-76.63,16.09-29.21-12.1-46.58-37.29-46.94-67.01-.26-21.65,7.85-39.52,22.84-54.61,16.55-16.67,33.17-33.29,49.87-49.82,2.84-2.81,3.83-5.81,3.82-9.76-.09-57.17-.64-114.34.19-171.49.62-42.85,5.26-85.31,19.17-126.3,12.98-38.23,33.77-71.53,61.77-100.46,7.76-8.02,15.49-16.08,23.34-24.02,14.24-14.41,34.58-14.96,49.16-.93,13.33,12.82,26.2,26.12,39.28,39.19,1.04,1.04,1.88,2.38,3.26,2.7,8.43-8.16,16.88-16.13,25.1-24.34,19.92-19.89,39.73-39.89,59.58-59.84,11.4-11.46,22.61-23.11,34.24-34.32,16.55-15.94,36.44-20.86,58.12-13.98,22.15,7.03,35.53,22.75,39.08,46.11,3.2,21-4.19,38.01-19.24,52.52-13.9,13.41-27.15,27.48-40.79,41.16-23.51,23.59-47.1,47.1-70.64,70.65-1.16,1.16-2.19,2.44-3.01,3.37,34.95,34.95,69.64,69.64,104.53,104.53,2.27-1.32,4.18-3.89,6.39-6.1,34.19-34.16,68.55-68.15,102.46-102.59,20.23-20.54,47.93-22.87,67.96-13.23,36,17.31,43.62,65.36,14.33,93.69-34.97,33.83-69.17,68.45-103.67,102.76-2.21,2.2-4.78,4.17-6.32,7.64,8.87,9.09,17.75,18.3,26.76,27.38,8.57,8.63,17.27,17.13,25.92,25.68,14,13.82,14.25,34.9-.18,50.19-13.72,14.53-27.03,29.47-41.54,43.24-37.68,35.77-82.2,57.97-133.33,66.83-34.26,5.94-68.88,6.76-103.54,6.7-15.33-.03-30.67,0-46.5,0ZM215.45,755.93c-8.03,8.54-12.93,18.67-14.02,30.27-2.08,22.2,9.76,42.21,29.85,51.35,19.35,8.8,40.24,4.47,56.27-11.65,19.15-19.26,38.36-38.45,57.41-57.81,3.77-3.83,7.83-5.5,13.26-5.53,60.97-.39,121.95,1.22,182.91-.9,17.48-.61,34.87-2.46,52.08-5.47,42.64-7.44,80.44-25.31,113.28-53.58,8.93-7.69,17.69-15.53,24.86-24.85-4.1-5.7-376.52-377.85-380.13-379.92-.38.26-.83.48-1.18.81-1.31,1.28-2.65,2.54-3.87,3.9-19.63,21.91-35.3,46.31-46.2,73.68-16.57,41.62-21.8,85.37-22.4,129.62-.82,59.64-.26,117.3-.12,176.95.01,5.91-1.65,10.26-5.95,14.51-18.74,18.49-37.11,37.34-56.07,56.6Zm341.95-434.04c25.23-25.18,50.46-50.36,75.68-75.56,6.37-6.37,11.02-13.53,12.29-22.83,1.91-13.91-5.4-28.58-18.33-35.43-12.85-6.81-29.52-4.84-39.48,5.05-15.96,15.83-31.73,31.86-47.57,47.81-22.86,23.03-45.63,46.15-68.67,69-3.33,3.3-2.89,4.96.1,7.88,13.81,13.51,27.52,27.13,41.06,40.91,3.06,3.12,4.99,3.23,8.06.09,12-12.26,24.23-24.3,36.87-36.93Zm-127.86-13.96c-12.09-12.17-24.12-24.4-36.32-36.46-4.64-4.58-9.07-5.37-13.55-2.6-4.82,2.98-8.21,7.5-12.12,11.73,1.54,1.64,2.65,2.87,3.81,4.03,19.68,19.66,39.39,39.31,59.06,58.98,40.17,40.18,80.31,80.39,120.49,120.57,62.68,62.68,125.38,125.34,188.06,188.02,9.49,9.49,10.47,9.43,18.11-1.78,3.92-5.76,3.77-10.51-.88-15.88-2.28-2.63-5.01-4.87-7.47-7.35-31.52-31.61-63.01-63.25-94.53-94.85-33.18-33.25-66.35-66.51-99.59-99.7-41.49-41.43-83.04-82.79-125.06-124.7Zm369.81,144.9c5.05-5.07,10.19-10.07,15.15-15.23,14.07-14.63,13.82-36.1-.5-50.16-13.61-13.36-35.19-12.68-49.34,1.45-35.82,35.78-71.64,71.54-107.56,107.21-2.83,2.81-2.65,4.55.09,7.25,10.6,10.36,20.98,20.95,31.46,31.43,4.55,4.55,9.16,9.04,14.65,14.45,16.15-16.46,31.78-32.58,47.62-48.49,15.85-15.92,31.92-31.64,48.41-47.92Z"/></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}

if ( ! function_exists( 'rationalwp_register_parent_menu' ) ) {
	/**
	 * Register the parent menu if it doesn't exist yet.
	 */
	function rationalwp_register_parent_menu() {
		global $admin_page_hooks;

		// Check if parent menu already exists.
		if ( isset( $admin_page_hooks['rationalwp'] ) ) {
			return;
		}

		add_menu_page(
			__( 'RationalWP', 'rationalseo' ),
			__( 'RationalWP', 'rationalseo' ),
			'manage_options',
			'rationalwp',
			'rationalwp_render_parent_page',
			rationalwp_get_menu_icon(),
			81 // After Settings (80).
		);
	}

	add_action( 'admin_menu', 'rationalwp_register_parent_menu', 5 );
}

if ( ! function_exists( 'rationalwp_fetch_remote_plugins' ) ) {
	/**
	 * Fetch plugin data from remote JSON URL.
	 *
	 * @return array|false Plugin data array on success, false on failure.
	 */
	function rationalwp_fetch_remote_plugins() {
		// Add cache-busting parameter based on menu version.
		$url = add_query_arg( 'v', RATIONALWP_MENU_VERSION, RATIONALWP_PLUGINS_JSON_URL );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		// Check for errors.
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		// Validate JSON structure.
		if ( ! is_array( $data ) || empty( $data ) ) {
			return false;
		}

		// Validate each plugin has required fields.
		foreach ( $data as $plugin_key => $plugin ) {
			if ( ! is_array( $plugin ) ||
				 empty( $plugin['name'] ) ||
				 empty( $plugin['description'] ) ||
				 empty( $plugin['slug'] ) ||
				 empty( $plugin['menu_slug'] ) ||
				 empty( $plugin['url'] ) ||
				 empty( $plugin['file'] ) ) {
				return false;
			}
		}

		return $data;
	}
}

if ( ! function_exists( 'rationalwp_get_available_plugins' ) ) {
	/**
	 * Get list of all available RationalWP plugins.
	 * Fetches from remote JSON with caching.
	 *
	 * @return array List of plugin data, empty array on failure.
	 */
	function rationalwp_get_available_plugins() {
		$cache_key = 'rationalwp_plugins_list';

		// Try to get cached data first.
		$cached_plugins = get_transient( $cache_key );
		if ( false !== $cached_plugins && is_array( $cached_plugins ) ) {
			return $cached_plugins;
		}

		// Fetch fresh data from remote.
		$remote_plugins = rationalwp_fetch_remote_plugins();

		if ( false !== $remote_plugins ) {
			// Cache the successful response.
			set_transient( $cache_key, $remote_plugins, RATIONALWP_PLUGINS_CACHE_DURATION );
			return $remote_plugins;
		}

		// Return empty array on failure.
		// Cache the failure for a shorter duration (1 hour) to retry sooner.
		set_transient( $cache_key, array(), HOUR_IN_SECONDS );

		return array();
	}
}

if ( ! function_exists( 'rationalwp_clear_plugins_cache' ) ) {
	/**
	 * Clear the cached plugins list.
	 * Useful for development or when you want to force a fresh fetch.
	 *
	 * @return bool True if cache was cleared, false otherwise.
	 */
	function rationalwp_clear_plugins_cache() {
		return delete_transient( 'rationalwp_plugins_list' );
	}
}

if ( ! function_exists( 'rationalwp_render_parent_page' ) ) {
	/**
	 * Render the parent page content showing available plugins.
	 */
	function rationalwp_render_parent_page() {
		$plugins   = rationalwp_get_available_plugins();
		$installed = get_plugins();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'RationalWP Plugins', 'rationalseo' ); ?></h1>
			<p><?php esc_html_e( 'Practical tools for WordPress professionals.', 'rationalseo' ); ?></p>

			<?php if ( empty( $plugins ) ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php esc_html_e( 'Unable to load plugin information. Please check your internet connection and try again later.', 'rationalseo' ); ?>
						<?php if ( current_user_can( 'manage_options' ) ) : ?>
							<br>
							<?php /* translators: %s: URL where plugin data is fetched from */ ?>
							<small><?php printf( esc_html__( 'Plugin data is fetched from %s', 'rationalseo' ), '<code>' . esc_html( RATIONALWP_PLUGINS_JSON_URL ) . '</code>' ); ?></small>
						<?php endif; ?>
					</p>
				</div>
			<?php else : ?>
			<div class="rationalwp-plugins" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
				<?php foreach ( $plugins as $plugin_key => $plugin ) : ?>
					<?php
					$is_installed = isset( $installed[ $plugin['file'] ] );
					$is_active    = $is_installed && is_plugin_active( $plugin['file'] );
					?>
					<div class="rationalwp-plugin-card" style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px;">
						<h3 style="margin-top: 0; margin-bottom: 10px;">
							<?php echo esc_html( $plugin['name'] ); ?>
							<?php if ( $is_active ) : ?>
								<span style="background: #00a32a; color: #fff; font-size: 11px; padding: 2px 8px; border-radius: 3px; margin-left: 8px; vertical-align: middle;"><?php esc_html_e( 'Active', 'rationalseo' ); ?></span>
							<?php elseif ( $is_installed ) : ?>
								<span style="background: #dba617; color: #fff; font-size: 11px; padding: 2px 8px; border-radius: 3px; margin-left: 8px; vertical-align: middle;"><?php esc_html_e( 'Inactive', 'rationalseo' ); ?></span>
							<?php endif; ?>
						</h3>
						<p style="color: #646970; margin-bottom: 15px;"><?php echo esc_html( $plugin['description'] ); ?></p>
						<div class="rationalwp-plugin-actions">
							<?php if ( $is_active ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $plugin['menu_slug'] ) ); ?>" class="button button-primary"><?php esc_html_e( 'Settings', 'rationalseo' ); ?></a>
							<?php elseif ( $is_installed ) : ?>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $plugin['file'] ) ), 'activate-plugin_' . $plugin['file'] ) ); ?>" class="button button-primary"><?php esc_html_e( 'Activate', 'rationalseo' ); ?></a>
							<?php else : ?>
								<a href="<?php echo esc_url( $plugin['url'] ); ?>" class="button" target="_blank"><?php esc_html_e( 'Learn More', 'rationalseo' ); ?></a>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<?php
			/**
			 * Action hook for adding additional content to the parent page.
			 */
			do_action( 'rationalwp_parent_page_content' );
			?>
		</div>
		<?php
	}
}