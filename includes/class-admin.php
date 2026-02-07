<?php
/**
 * RationalSEO Admin Class
 *
 * Handles admin settings page and settings registration.
 *
 * @package RationalSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RationalSEO_Admin {

	/**
	 * Settings instance.
	 *
	 * @var RationalSEO_Settings
	 */
	private $settings;

	/**
	 * Import manager instance.
	 *
	 * @var RationalSEO_Import_Manager
	 */
	private $import_manager;

	/**
	 * Constructor.
	 *
	 * @param RationalSEO_Settings       $settings       Settings instance.
	 * @param RationalSEO_Import_Manager $import_manager Import manager instance.
	 */
	public function __construct( RationalSEO_Settings $settings, RationalSEO_Import_Manager $import_manager = null ) {
		$this->settings       = $settings;
		$this->import_manager = $import_manager;
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add settings page to admin menu.
	 */
	public function add_settings_page() {
		add_submenu_page(
			'rationalwp',
			__( 'SEO', 'rationalseo' ),
			__( 'SEO', 'rationalseo' ),
			'manage_options',
			'rationalseo',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings with WordPress Settings API.
	 */
	public function register_settings() {
		register_setting(
			'rationalseo_settings_group',
			RationalSEO_Settings::OPTION_NAME,
			array( $this, 'sanitize_settings' )
		);

		// Identity Section.
		add_settings_section(
			'rationalseo_identity',
			__( 'Identity', 'rationalseo' ),
			array( $this, 'render_section_identity' ),
			'rationalseo'
		);

		add_settings_field(
			'site_type',
			__( 'Site Type', 'rationalseo' ),
			array( $this, 'render_field_site_type' ),
			'rationalseo',
			'rationalseo_identity'
		);

		add_settings_field(
			'site_name',
			__( 'Site Name', 'rationalseo' ),
			array( $this, 'render_field_site_name' ),
			'rationalseo',
			'rationalseo_identity'
		);

		add_settings_field(
			'site_logo',
			__( 'Logo URL', 'rationalseo' ),
			array( $this, 'render_field_site_logo' ),
			'rationalseo',
			'rationalseo_identity'
		);

		add_settings_field(
			'separator',
			__( 'Title Separator', 'rationalseo' ),
			array( $this, 'render_field_separator' ),
			'rationalseo',
			'rationalseo_identity'
		);

		// Webmaster Section.
		add_settings_section(
			'rationalseo_webmaster',
			__( 'Webmaster Tools', 'rationalseo' ),
			array( $this, 'render_section_webmaster' ),
			'rationalseo'
		);

		add_settings_field(
			'verification_google',
			__( 'Google Verification', 'rationalseo' ),
			array( $this, 'render_field_verification_google' ),
			'rationalseo',
			'rationalseo_webmaster'
		);

		add_settings_field(
			'verification_bing',
			__( 'Bing Verification', 'rationalseo' ),
			array( $this, 'render_field_verification_bing' ),
			'rationalseo',
			'rationalseo_webmaster'
		);

		// AI Assistant Section.
		add_settings_section(
			'rationalseo_ai',
			__( 'AI Assistant', 'rationalseo' ),
			array( $this, 'render_section_ai' ),
			'rationalseo'
		);

		add_settings_field(
			'openai_api_key',
			__( 'OpenAI API Key', 'rationalseo' ),
			array( $this, 'render_field_openai_api_key' ),
			'rationalseo',
			'rationalseo_ai'
		);

		// Social Section.
		add_settings_section(
			'rationalseo_social_section',
			__( 'Social Media', 'rationalseo' ),
			array( $this, 'render_section_social' ),
			'rationalseo_social'
		);

		add_settings_field(
			'social_default_image',
			__( 'Default Social Image', 'rationalseo' ),
			array( $this, 'render_field_social_default_image' ),
			'rationalseo_social',
			'rationalseo_social_section'
		);

		add_settings_field(
			'twitter_card_type',
			__( 'Twitter Card Type', 'rationalseo' ),
			array( $this, 'render_field_twitter_card_type' ),
			'rationalseo_social',
			'rationalseo_social_section'
		);

		// Sitemaps Section.
		add_settings_section(
			'rationalseo_sitemap_section',
			__( 'XML Sitemaps', 'rationalseo' ),
			array( $this, 'render_section_sitemap' ),
			'rationalseo_sitemaps'
		);

		add_settings_field(
			'sitemap_enabled',
			__( 'Enable Sitemaps', 'rationalseo' ),
			array( $this, 'render_field_sitemap_enabled' ),
			'rationalseo_sitemaps',
			'rationalseo_sitemap_section'
		);

		add_settings_field(
			'sitemap_max_age',
			__( 'Content Freshness', 'rationalseo' ),
			array( $this, 'render_field_sitemap_max_age' ),
			'rationalseo_sitemaps',
			'rationalseo_sitemap_section'
		);

		add_settings_field(
			'sitemap_exclude_types',
			__( 'Exclude Post Types', 'rationalseo' ),
			array( $this, 'render_field_sitemap_exclude_types' ),
			'rationalseo_sitemaps',
			'rationalseo_sitemap_section'
		);

		// Archives Sections — one per post type with has_archive.
		$archive_types = get_post_types( array( 'public' => true, 'has_archive' => true ), 'objects' );
		foreach ( $archive_types as $post_type ) {
			$section_id = 'rationalseo_archive_' . $post_type->name;

			add_settings_section(
				$section_id,
				$post_type->labels->name,
				array( $this, 'render_section_archive' ),
				'rationalseo_archives',
				array( 'post_type' => $post_type->name )
			);

			add_settings_field(
				'archive_title_' . $post_type->name,
				__( 'SEO Title', 'rationalseo' ),
				array( $this, 'render_field_archive_title' ),
				'rationalseo_archives',
				$section_id,
				array( 'post_type' => $post_type )
			);

			add_settings_field(
				'archive_description_' . $post_type->name,
				__( 'Meta Description', 'rationalseo' ),
				array( $this, 'render_field_archive_description' ),
				'rationalseo_archives',
				$section_id,
				array( 'post_type' => $post_type )
			);
		}
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		$sanitized['separator'] = isset( $input['separator'] )
			? sanitize_text_field( $input['separator'] )
			: '|';

		$sanitized['site_type'] = isset( $input['site_type'] ) && in_array( $input['site_type'], array( 'organization', 'person' ), true )
			? $input['site_type']
			: 'organization';

		$sanitized['site_name'] = isset( $input['site_name'] )
			? sanitize_text_field( $input['site_name'] )
			: '';

		$sanitized['site_logo'] = isset( $input['site_logo'] )
			? esc_url_raw( $input['site_logo'] )
			: '';

		$sanitized['verification_google'] = isset( $input['verification_google'] )
			? sanitize_text_field( $input['verification_google'] )
			: '';

		$sanitized['verification_bing'] = isset( $input['verification_bing'] )
			? sanitize_text_field( $input['verification_bing'] )
			: '';

		$sanitized['social_default_image'] = isset( $input['social_default_image'] )
			? esc_url_raw( $input['social_default_image'] )
			: '';

		$sanitized['twitter_card_type'] = isset( $input['twitter_card_type'] ) && in_array( $input['twitter_card_type'], array( 'summary', 'summary_large_image' ), true )
			? $input['twitter_card_type']
			: 'summary_large_image';

		$sanitized['sitemap_enabled'] = isset( $input['sitemap_enabled'] ) && '1' === $input['sitemap_enabled'];

		$sanitized['sitemap_max_age'] = isset( $input['sitemap_max_age'] )
			? absint( $input['sitemap_max_age'] )
			: 0;

		$sanitized['sitemap_exclude_types'] = array();
		if ( isset( $input['sitemap_exclude_types'] ) && is_array( $input['sitemap_exclude_types'] ) ) {
			$sanitized['sitemap_exclude_types'] = array_map( 'sanitize_key', $input['sitemap_exclude_types'] );
		}

		// Handle OpenAI API key with encryption.
		$old_settings = get_option( RationalSEO_Settings::OPTION_NAME, array() );
		$existing_key = isset( $old_settings['openai_api_key'] ) ? $old_settings['openai_api_key'] : '';

		if ( ! empty( $input['openai_api_key_clear'] ) ) {
			// Clear checkbox is checked - remove the key.
			$sanitized['openai_api_key'] = '';
		} elseif ( isset( $input['openai_api_key'] ) ) {
			$new_key = trim( $input['openai_api_key'] );
			// Check if it's placeholder bullets (all bullet characters).
			if ( '' === $new_key || preg_match( '/^[\x{2022}\x{25CF}\x{25A0}•]+$/u', $new_key ) ) {
				// Keep existing key.
				$sanitized['openai_api_key'] = $existing_key;
			} else {
				// Encrypt and store new key.
				$sanitized['openai_api_key'] = $this->settings->encrypt_value( $new_key );
			}
		} else {
			// Keep existing key if field not submitted.
			$sanitized['openai_api_key'] = $existing_key;
		}

		// Handle archive title/description fields.
		$archive_types = get_post_types( array( 'public' => true, 'has_archive' => true ), 'objects' );
		foreach ( $archive_types as $post_type ) {
			$title_key = 'archive_title_' . $post_type->name;
			$desc_key  = 'archive_description_' . $post_type->name;

			if ( isset( $input[ $title_key ] ) ) {
				$sanitized[ $title_key ] = sanitize_text_field( $input[ $title_key ] );
			} elseif ( isset( $old_settings[ $title_key ] ) ) {
				$sanitized[ $title_key ] = $old_settings[ $title_key ];
			}

			if ( isset( $input[ $desc_key ] ) ) {
				$sanitized[ $desc_key ] = sanitize_textarea_field( $input[ $desc_key ] );
			} elseif ( isset( $old_settings[ $desc_key ] ) ) {
				$sanitized[ $desc_key ] = $old_settings[ $desc_key ];
			}
		}

		// Flush rewrite rules if sitemap settings changed.
		$sitemap_changed = (
			( isset( $old_settings['sitemap_enabled'] ) ? $old_settings['sitemap_enabled'] : true ) !== $sanitized['sitemap_enabled']
		);
		if ( $sitemap_changed ) {
			add_action( 'shutdown', array( 'RationalSEO_Sitemap', 'flush_rules' ) );
		}

		// Set a transient to show success message on redirect.
		set_transient( 'rationalseo_settings_saved', true, 30 );

		return $sanitized;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'rationalwp_page_rationalseo' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'rationalseo-admin',
			RATIONALSEO_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			RATIONALSEO_VERSION
		);

		// Enqueue settings JS on non-import tabs.
		if ( 'import' !== $this->get_current_tab() ) {
			wp_enqueue_script(
				'rationalseo-admin-settings',
				RATIONALSEO_PLUGIN_URL . 'assets/js/admin-settings.js',
				array( 'jquery' ),
				RATIONALSEO_VERSION,
				true
			);

			wp_localize_script(
				'rationalseo-admin-settings',
				'rationalseoAdmin',
				array(
					'page' => 'rationalseo',
				)
			);
		}
	}

	/**
	 * Get current tab.
	 *
	 * @return string
	 */
	private function get_current_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_tab = $this->get_current_tab();
		$tabs        = array(
			'general'  => __( 'General', 'rationalseo' ),
			'archives' => __( 'Archives', 'rationalseo' ),
			'social'   => __( 'Social', 'rationalseo' ),
			'sitemaps' => __( 'Sitemaps', 'rationalseo' ),
			'import'   => __( 'Import', 'rationalseo' ),
		);
		?>
		<div class="wrap rationalseo-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=rationalseo&tab=' . $tab_key ) ); ?>"
						class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php if ( get_transient( 'rationalseo_settings_saved' ) ) : ?>
				<?php delete_transient( 'rationalseo_settings_saved' ); ?>
				<div id="rationalseo-settings-message" class="notice notice-success rationalseo-settings-saved">
					<p><?php esc_html_e( 'Settings saved successfully.', 'rationalseo' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( 'import' === $current_tab ) : ?>
				<?php $this->render_import_tab(); ?>
			<?php else : ?>
				<form action="options.php" method="post">
					<?php
					settings_fields( 'rationalseo_settings_group' );

					if ( 'social' === $current_tab ) {
						do_settings_sections( 'rationalseo_social' );
					} elseif ( 'archives' === $current_tab ) {
						$archive_types = get_post_types( array( 'public' => true, 'has_archive' => true ), 'objects' );
						if ( empty( $archive_types ) ) {
							echo '<p>' . esc_html__( 'No post types with archives found.', 'rationalseo' ) . '</p>';
						} else {
							do_settings_sections( 'rationalseo_archives' );
						}
					} elseif ( 'sitemaps' === $current_tab ) {
						do_settings_sections( 'rationalseo_sitemaps' );
					} else {
						do_settings_sections( 'rationalseo' );
					}

					submit_button( __( 'Save Settings', 'rationalseo' ) );
					?>
				</form>
			<?php endif; ?>
		</div>

		<?php
	}

	/**
	 * Render Identity section description.
	 */
	public function render_section_identity() {
		echo '<p>' . esc_html__( 'Configure your site identity for search engines.', 'rationalseo' ) . '</p>';
	}

	/**
	 * Render Webmaster section description.
	 */
	public function render_section_webmaster() {
		echo '<p>' . esc_html__( 'Enter verification codes for webmaster tools.', 'rationalseo' ) . '</p>';
	}

	/**
	 * Render AI Assistant section description.
	 */
	public function render_section_ai() {
		echo '<p>' . esc_html__( 'Configure AI-powered features for content optimization.', 'rationalseo' ) . '</p>';
	}

	/**
	 * Render OpenAI API Key field.
	 */
	public function render_field_openai_api_key() {
		$encrypted_key = $this->settings->get( 'openai_api_key', '' );
		$has_key       = ! empty( $encrypted_key );
		?>
		<input type="password"
			name="<?php echo esc_attr( RationalSEO_Settings::OPTION_NAME ); ?>[openai_api_key]"
			id="openai_api_key"
			value="<?php echo $has_key ? '••••••••••••••••' : ''; ?>"
			class="regular-text"
			placeholder="sk-..."
			autocomplete="off">
		<?php if ( $has_key ) : ?>
			<span class="rationalseo-api-status rationalseo-api-configured">
				<?php esc_html_e( 'API key is configured', 'rationalseo' ); ?>
			</span>
			<br><br>
			<label>
				<input type="checkbox"
					name="<?php echo esc_attr( RationalSEO_Settings::OPTION_NAME ); ?>[openai_api_key_clear]"
					value="1">
				<?php esc_html_e( 'Clear API key', 'rationalseo' ); ?>
			</label>
		<?php endif; ?>
		<p class="description">
			<?php
			printf(
				/* translators: %s: Link to OpenAI API keys page */
				esc_html__( 'Enter your API key from %s to enable AI-powered keyword suggestions and description generation.', 'rationalseo' ),
				'<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">OpenAI</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render Site Type field.
	 */
	public function render_field_site_type() {
		$value = $this->settings->get( 'site_type', 'organization' );
		?>
		<select name="<?php echo esc_attr( RationalSEO_Settings::OPTION_NAME ); ?>[site_type]" id="site_type">
			<option value="organization" <?php selected( $value, 'organization' ); ?>>
				<?php esc_html_e( 'Organization', 'rationalseo' ); ?>
			</option>
			<option value="person" <?php selected( $value, 'person' ); ?>>
				<?php esc_html_e( 'Person', 'rationalseo' ); ?>
			</option>
		</select>
		<p class="description"><?php esc_html_e( 'Choose whether this site represents an organization or a person.', 'rationalseo' ); ?></p>
		<?php
	}

	/**
	 * Render Site Name field.
	 */
	public function render_field_site_name() {
		$value = $this->settings->get( 'site_name', '' );
		?>
		<input type="text"
			name="<?php echo esc_attr( RationalSEO_Settings::OPTION_NAME ); ?>[site_name]"
			id="site_name"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
		<p class="description"><?php esc_html_e( 'The name that appears in title tags and structured data.', 'rationalseo' ); ?></p>
		<?php
	}

	/**
	 * Render Site Logo field.
	 */
	public function render_field_site_logo() {
		$value = $this->settings->get( 'site_logo', '' );
		?>
		<input type="url"
			name="<?php echo esc_attr( RationalSEO_Settings::OPTION_NAME ); ?>[site_logo]"
			id="site_logo"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="https://example.com/logo.png">
		<p class="description"><?php esc_html_e( 'URL to your site logo for structured data (recommended: 112x112px minimum).', 'rationalseo' ); ?></p>
		<?php
	}

	/**
	 * Render Title Separator field.
	 */
	public function render_field_separator() {
		$value = $this->settings->get( 'separator', '|' );
		?>
		<input type="text"
			name="<?php echo esc_attr( RationalSEO_Settings::OPTION_NAME ); ?>[separator]"
			id="separator"
			value="<?php echo esc_attr( $value ); ?>"
			class="small-text"
			maxlength="5">
		<p class="description"><?php esc_html_e( 'Character(s) used between page title and site name.', 'rationalseo' ); ?></p>
		<?php
	}

	/**
	 * Render Google Verification field.
	 */
	public function render_field_verification_google() {
		$value = $this->settings->get( 'verification_google', '' );
		?>
		<input type="text"
			name="<?php echo esc_attr( RationalSEO_Settings::OPTION_NAME ); ?>[verification_google]"
			id="verification_google"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="abc123...">
		<p class="description">
			<?php
			printf(
				/* translators: %s: Link to Google Search Console */
				esc_html__( 'Enter the verification code from %s (content value only).', 'rationalseo' ),
				'<a href="https://search.google.com/search-console" target="_blank" rel="noopener">Google Search Console</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render Bing Verification field.
	 */
	public function render_field_verification_bing() {
		$value = $this->settings->get( 'verification_bing', '' );
		?>
		<input type="text"
			name="<?php echo esc_attr( RationalSEO_Settings::OPTION_NAME ); ?>[verification_bing]"
			id="verification_bing"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="abc123...">
		<p class="description">
			<?php
			printf(
				/* translators: %s: Link to Bing Webmaster Tools */
				esc_html__( 'Enter the verification code from %s (content value only).', 'rationalseo' ),
				'<a href="https://www.bing.com/webmasters" target="_blank" rel="noopener">Bing Webmaster Tools</a>'
			);
			?>
		</p>
		<?php
	}


	/**
	 * Render Social section description.
	 */
	public function render_section_social() {
		echo '<p>' . esc_html__( 'Configure Open Graph and Twitter Card settings for social media sharing.', 'rationalseo' ) . '</p>';
	}

	/**
	 * Render Default Social Image field.
	 */
	public function render_field_social_default_image() {
		$value = $this->settings->get( 'social_default_image', '' );
		?>
		<input type="url"
			name="<?php echo esc_attr( RationalSEO_Settings::OPTION_NAME ); ?>[social_default_image]"
			id="social_default_image"
			value="<?php echo esc_attr( $value ); ?>"
			class="large-text"
			placeholder="https://example.com/image.jpg">
		<p class="description"><?php esc_html_e( 'Fallback image used when a post or page has no featured image. Recommended size: 1200x630 pixels.', 'rationalseo' ); ?></p>
		<?php
	}

	/**
	 * Render Twitter Card Type field.
	 */
	public function render_field_twitter_card_type() {
		$value = $this->settings->get( 'twitter_card_type', 'summary_large_image' );
		?>
		<select name="<?php echo esc_attr( RationalSEO_Settings::OPTION_NAME ); ?>[twitter_card_type]" id="twitter_card_type">
			<option value="summary" <?php selected( $value, 'summary' ); ?>>
				<?php esc_html_e( 'Summary', 'rationalseo' ); ?>
			</option>
			<option value="summary_large_image" <?php selected( $value, 'summary_large_image' ); ?>>
				<?php esc_html_e( 'Summary with Large Image', 'rationalseo' ); ?>
			</option>
		</select>
		<p class="description"><?php esc_html_e( 'Choose how Twitter displays your content when shared.', 'rationalseo' ); ?></p>
		<?php
	}

	/**
	 * Render Sitemap section description.
	 */
	public function render_section_sitemap() {
		$sitemap_url = home_url( '/sitemap.xml' );
		echo '<p>' . esc_html__( 'Configure XML sitemap settings for search engines.', 'rationalseo' ) . '</p>';
		if ( $this->settings->get( 'sitemap_enabled', true ) ) {
			echo '<p>';
			printf(
				/* translators: %s: Sitemap URL */
				esc_html__( 'Your sitemap is available at: %s', 'rationalseo' ),
				'<a href="' . esc_url( $sitemap_url ) . '" target="_blank" rel="noopener">' . esc_html( $sitemap_url ) . '</a>'
			);
			echo '</p>';
		}
	}

	/**
	 * Render archive section description.
	 *
	 * @param array $args Section arguments including 'post_type'.
	 */
	public function render_section_archive( $args ) {
		if ( ! empty( $args['post_type'] ) ) {
			$archive_url = get_post_type_archive_link( $args['post_type'] );
			if ( $archive_url ) {
				printf(
					'<p>%s <a href="%s" target="_blank" rel="noopener">%s</a></p>',
					esc_html__( 'Customize SEO settings for this archive.', 'rationalseo' ),
					esc_url( $archive_url ),
					esc_html__( 'View archive', 'rationalseo' )
				);
			}
		}
	}

	/**
	 * Render archive title field.
	 *
	 * @param array $args Field arguments including 'post_type' object.
	 */
	public function render_field_archive_title( $args ) {
		$post_type   = $args['post_type'];
		$key         = 'archive_title_' . $post_type->name;
		$value       = $this->settings->get( $key, '' );
		$separator   = $this->settings->get( 'separator', '|' );
		$site_name   = $this->settings->get( 'site_name', get_bloginfo( 'name' ) );
		$placeholder = sprintf( '%s %s %s', $post_type->labels->name, $separator, $site_name );
		?>
		<input type="text"
			name="<?php echo esc_attr( RationalSEO_Settings::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]"
			id="<?php echo esc_attr( $key ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="large-text"
			placeholder="<?php echo esc_attr( $placeholder ); ?>">
		<p class="description"><?php esc_html_e( 'Leave blank to use the default title pattern.', 'rationalseo' ); ?></p>
		<?php
	}

	/**
	 * Render archive description field.
	 *
	 * @param array $args Field arguments including 'post_type' object.
	 */
	public function render_field_archive_description( $args ) {
		$post_type = $args['post_type'];
		$key       = 'archive_description_' . $post_type->name;
		$value     = $this->settings->get( $key, '' );
		?>
		<textarea
			name="<?php echo esc_attr( RationalSEO_Settings::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]"
			id="<?php echo esc_attr( $key ); ?>"
			class="large-text"
			rows="3"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Leave blank for no description on the archive page.', 'rationalseo' ); ?></p>
		<?php
	}

	/**
	 * Render Sitemap Enabled field.
	 */
	public function render_field_sitemap_enabled() {
		$value = $this->settings->get( 'sitemap_enabled', true );
		?>
		<label>
			<input type="checkbox"
				name="<?php echo esc_attr( RationalSEO_Settings::OPTION_NAME ); ?>[sitemap_enabled]"
				id="sitemap_enabled"
				value="1"
				<?php checked( $value, true ); ?>>
			<?php esc_html_e( 'Enable XML sitemaps', 'rationalseo' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Generate XML sitemaps to help search engines discover your content.', 'rationalseo' ); ?></p>
		<?php
	}

	/**
	 * Render Sitemap Max Age field.
	 */
	public function render_field_sitemap_max_age() {
		$value = $this->settings->get( 'sitemap_max_age', 0 );
		?>
		<select name="<?php echo esc_attr( RationalSEO_Settings::OPTION_NAME ); ?>[sitemap_max_age]" id="sitemap_max_age">
			<option value="0" <?php selected( $value, 0 ); ?>>
				<?php esc_html_e( 'Include all content', 'rationalseo' ); ?>
			</option>
			<option value="6" <?php selected( $value, 6 ); ?>>
				<?php esc_html_e( 'Last 6 months', 'rationalseo' ); ?>
			</option>
			<option value="12" <?php selected( $value, 12 ); ?>>
				<?php esc_html_e( 'Last 12 months', 'rationalseo' ); ?>
			</option>
			<option value="24" <?php selected( $value, 24 ); ?>>
				<?php esc_html_e( 'Last 24 months', 'rationalseo' ); ?>
			</option>
			<option value="36" <?php selected( $value, 36 ); ?>>
				<?php esc_html_e( 'Last 36 months', 'rationalseo' ); ?>
			</option>
		</select>
		<p class="description"><?php esc_html_e( 'Exclude older content from sitemaps to focus on fresh content.', 'rationalseo' ); ?></p>
		<?php
	}

	/**
	 * Render Sitemap Exclude Types field.
	 */
	public function render_field_sitemap_exclude_types() {
		$excluded = $this->settings->get( 'sitemap_exclude_types', array() );
		if ( ! is_array( $excluded ) ) {
			$excluded = array();
		}

		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		// Remove attachments from the list.
		unset( $post_types['attachment'] );

		if ( empty( $post_types ) ) {
			echo '<p>' . esc_html__( 'No public post types found.', 'rationalseo' ) . '</p>';
			return;
		}

		foreach ( $post_types as $post_type ) {
			$checked = in_array( $post_type->name, $excluded, true );
			?>
			<label style="display: block; margin-bottom: 5px;">
				<input type="checkbox"
					name="<?php echo esc_attr( RationalSEO_Settings::OPTION_NAME ); ?>[sitemap_exclude_types][]"
					value="<?php echo esc_attr( $post_type->name ); ?>"
					<?php checked( $checked, true ); ?>>
				<?php echo esc_html( $post_type->label ); ?> <code><?php echo esc_html( $post_type->name ); ?></code>
			</label>
			<?php
		}
		?>
		<p class="description"><?php esc_html_e( 'Select post types to exclude from the sitemap.', 'rationalseo' ); ?></p>
		<?php
	}

	/**
	 * Render the import tab content.
	 */
	private function render_import_tab() {
		// Delegate to the import admin if available.
		$import_admin = RationalSEO::get_instance()->get_import_admin();
		if ( $import_admin ) {
			$import_admin->render_import_tab();
		} else {
			echo '<p>' . esc_html__( 'Import system not available.', 'rationalseo' ) . '</p>';
		}
	}
}
