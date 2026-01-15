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
	 * Redirects instance.
	 *
	 * @var RationalSEO_Redirects
	 */
	private $redirects;

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
	 * @param RationalSEO_Redirects      $redirects      Redirects instance.
	 * @param RationalSEO_Import_Manager $import_manager Import manager instance.
	 */
	public function __construct( RationalSEO_Settings $settings, RationalSEO_Redirects $redirects = null, RationalSEO_Import_Manager $import_manager = null ) {
		$this->settings       = $settings;
		$this->redirects      = $redirects;
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

		// Homepage Section.
		add_settings_section(
			'rationalseo_homepage',
			__( 'Homepage', 'rationalseo' ),
			array( $this, 'render_section_homepage' ),
			'rationalseo'
		);

		add_settings_field(
			'home_title',
			__( 'Custom Title', 'rationalseo' ),
			array( $this, 'render_field_home_title' ),
			'rationalseo',
			'rationalseo_homepage'
		);

		add_settings_field(
			'home_description',
			__( 'Custom Description', 'rationalseo' ),
			array( $this, 'render_field_home_description' ),
			'rationalseo',
			'rationalseo_homepage'
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

		// Redirects Section.
		add_settings_section(
			'rationalseo_redirects_section',
			__( 'Redirect Settings', 'rationalseo' ),
			array( $this, 'render_section_redirects' ),
			'rationalseo_redirects'
		);

		add_settings_field(
			'redirect_auto_slug',
			__( 'Auto-Redirect on Slug Change', 'rationalseo' ),
			array( $this, 'render_field_redirect_auto_slug' ),
			'rationalseo_redirects',
			'rationalseo_redirects_section'
		);
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

		$sanitized['home_title'] = isset( $input['home_title'] )
			? sanitize_text_field( $input['home_title'] )
			: '';

		$sanitized['home_description'] = isset( $input['home_description'] )
			? sanitize_textarea_field( $input['home_description'] )
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

		$sanitized['redirect_auto_slug'] = isset( $input['redirect_auto_slug'] ) && '1' === $input['redirect_auto_slug'];

		// Flush rewrite rules if sitemap settings changed.
		$old_settings = get_option( RationalSEO_Settings::OPTION_NAME, array() );
		$sitemap_changed = (
			( isset( $old_settings['sitemap_enabled'] ) ? $old_settings['sitemap_enabled'] : true ) !== $sanitized['sitemap_enabled']
		);
		if ( $sitemap_changed ) {
			add_action( 'shutdown', array( 'RationalSEO_Sitemap', 'flush_rules' ) );
		}

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
			'general'   => __( 'General', 'rationalseo' ),
			'social'    => __( 'Social', 'rationalseo' ),
			'sitemaps'  => __( 'Sitemaps', 'rationalseo' ),
			'redirects' => __( 'Redirects', 'rationalseo' ),
			'import'    => __( 'Import', 'rationalseo' ),
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

			<?php if ( 'import' === $current_tab ) : ?>
				<?php $this->render_import_tab(); ?>
			<?php elseif ( 'redirects' === $current_tab ) : ?>
				<form action="options.php" method="post">
					<?php
					settings_fields( 'rationalseo_settings_group' );
					do_settings_sections( 'rationalseo_redirects' );
					submit_button( __( 'Save Settings', 'rationalseo' ) );
					?>
				</form>

				<?php $this->render_redirect_manager(); ?>
			<?php else : ?>
				<form action="options.php" method="post">
					<?php
					settings_fields( 'rationalseo_settings_group' );

					if ( 'social' === $current_tab ) {
						do_settings_sections( 'rationalseo_social' );
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
	 * Render Homepage section description.
	 */
	public function render_section_homepage() {
		echo '<p>' . esc_html__( 'Override the default homepage title and description.', 'rationalseo' ) . '</p>';
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
	 * Render Home Title field.
	 */
	public function render_field_home_title() {
		$value = $this->settings->get( 'home_title', '' );
		?>
		<input type="text"
			name="<?php echo esc_attr( RationalSEO_Settings::OPTION_NAME ); ?>[home_title]"
			id="home_title"
			value="<?php echo esc_attr( $value ); ?>"
			class="large-text"
			placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
		<p class="description"><?php esc_html_e( 'Custom title for the homepage. Leave empty to use site name.', 'rationalseo' ); ?></p>
		<?php
	}

	/**
	 * Render Home Description field.
	 */
	public function render_field_home_description() {
		$value = $this->settings->get( 'home_description', '' );
		?>
		<textarea
			name="<?php echo esc_attr( RationalSEO_Settings::OPTION_NAME ); ?>[home_description]"
			id="home_description"
			rows="3"
			class="large-text"
			placeholder="<?php echo esc_attr( get_bloginfo( 'description' ) ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Custom meta description for the homepage. Leave empty to use site tagline.', 'rationalseo' ); ?></p>
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
	 * Render Redirects section description.
	 */
	public function render_section_redirects() {
		echo '<p>' . esc_html__( 'Configure URL redirect settings.', 'rationalseo' ) . '</p>';
	}

	/**
	 * Render Auto Slug Redirect field.
	 */
	public function render_field_redirect_auto_slug() {
		$value = $this->settings->get( 'redirect_auto_slug', true );
		?>
		<label>
			<input type="checkbox"
				name="<?php echo esc_attr( RationalSEO_Settings::OPTION_NAME ); ?>[redirect_auto_slug]"
				id="redirect_auto_slug"
				value="1"
				<?php checked( $value, true ); ?>>
			<?php esc_html_e( 'Automatically create redirects when post slugs change', 'rationalseo' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'When enabled, changing a published post\'s URL slug will automatically create a 301 redirect from the old URL to the new one.', 'rationalseo' ); ?></p>
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

	/**
	 * Render the redirect manager interface.
	 */
	private function render_redirect_manager() {
		if ( ! $this->redirects ) {
			return;
		}

		$redirects = $this->redirects->get_all_redirects();
		$nonce     = wp_create_nonce( 'rationalseo_redirects' );
		?>
		<div class="rationalseo-redirect-header">
			<h2><?php esc_html_e( 'Redirect Manager', 'rationalseo' ); ?></h2>
			<button type="button" class="button button-secondary" id="rationalseo-import-yoast-btn">
				<?php esc_html_e( 'Import from Yoast', 'rationalseo' ); ?>
			</button>
		</div>

		<div class="rationalseo-redirect-manager">
			<table class="wp-list-table widefat fixed striped rationalseo-redirects-table">
				<thead>
					<tr>
						<th class="column-from"><?php esc_html_e( 'From URL', 'rationalseo' ); ?></th>
						<th class="column-to"><?php esc_html_e( 'To URL', 'rationalseo' ); ?></th>
						<th class="column-status"><?php esc_html_e( 'Type', 'rationalseo' ); ?></th>
						<th class="column-regex"><?php esc_html_e( 'Regex', 'rationalseo' ); ?></th>
						<th class="column-hits"><?php esc_html_e( 'Hits', 'rationalseo' ); ?></th>
						<th class="column-actions"><?php esc_html_e( 'Actions', 'rationalseo' ); ?></th>
					</tr>
				</thead>
				<tbody id="rationalseo-redirects-list">
					<tr class="rationalseo-add-row">
						<td>
							<input type="text" id="rationalseo-new-from" placeholder="/old-url/" class="regular-text">
						</td>
						<td>
							<input type="url" id="rationalseo-new-to" placeholder="<?php echo esc_attr( home_url( '/new-url/' ) ); ?>" class="regular-text">
						</td>
						<td>
							<select id="rationalseo-new-status">
								<option value="301"><?php esc_html_e( '301 Permanent', 'rationalseo' ); ?></option>
								<option value="302"><?php esc_html_e( '302 Temporary', 'rationalseo' ); ?></option>
								<option value="307"><?php esc_html_e( '307 Temporary', 'rationalseo' ); ?></option>
								<option value="410"><?php esc_html_e( '410 Gone', 'rationalseo' ); ?></option>
							</select>
						</td>
						<td class="column-regex">
							<input type="checkbox" id="rationalseo-new-regex" value="1">
						</td>
						<td class="column-hits">&mdash;</td>
						<td>
							<button type="button" class="button button-primary" id="rationalseo-add-redirect">
								<?php esc_html_e( 'Add', 'rationalseo' ); ?>
							</button>
						</td>
					</tr>
					<?php if ( empty( $redirects ) ) : ?>
						<tr class="no-redirects">
							<td colspan="6"><?php esc_html_e( 'No redirects found. Add one above.', 'rationalseo' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $redirects as $redirect ) : ?>
							<?php $is_regex = isset( $redirect->is_regex ) && (int) $redirect->is_regex === 1; ?>
							<tr data-id="<?php echo esc_attr( $redirect->id ); ?>">
								<td class="column-from"><code><?php echo esc_html( $redirect->url_from ); ?></code></td>
								<td class="column-to">
									<?php if ( 410 === (int) $redirect->status_code ) : ?>
										<em><?php esc_html_e( '(Gone)', 'rationalseo' ); ?></em>
									<?php else : ?>
										<a href="<?php echo esc_url( $redirect->url_to ); ?>" target="_blank" rel="noopener">
											<?php echo esc_html( $redirect->url_to ); ?>
										</a>
									<?php endif; ?>
								</td>
								<td class="column-status"><?php echo esc_html( $redirect->status_code ); ?></td>
								<td class="column-regex">
									<?php if ( $is_regex ) : ?>
										<span class="rationalseo-regex-badge"><?php esc_html_e( 'Yes', 'rationalseo' ); ?></span>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</td>
								<td class="column-hits"><?php echo esc_html( number_format_i18n( $redirect->count ) ); ?></td>
								<td class="column-actions">
									<button type="button" class="button button-link-delete rationalseo-delete-redirect" data-id="<?php echo esc_attr( $redirect->id ); ?>">
										<?php esc_html_e( 'Delete', 'rationalseo' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<div id="rationalseo-redirect-message" class="notice" style="display: none;"></div>
		</div>

		<!-- Yoast Import Modal -->
		<div id="rationalseo-import-modal" class="rationalseo-modal" style="display: none;">
			<div class="rationalseo-modal-content">
				<div class="rationalseo-modal-header">
					<h3><?php esc_html_e( 'Import Redirects from Yoast SEO Premium', 'rationalseo' ); ?></h3>
					<button type="button" class="rationalseo-modal-close">&times;</button>
				</div>
				<div class="rationalseo-modal-body">
					<div id="rationalseo-import-loading">
						<span class="spinner is-active"></span>
						<?php esc_html_e( 'Scanning for Yoast redirects...', 'rationalseo' ); ?>
					</div>
					<div id="rationalseo-import-preview" style="display: none;">
						<p id="rationalseo-import-summary"></p>
						<div id="rationalseo-import-details">
							<h4><?php esc_html_e( 'Redirects to Import', 'rationalseo' ); ?></h4>
							<div class="rationalseo-import-table-wrap">
								<table class="widefat striped">
									<thead>
										<tr>
											<th><?php esc_html_e( 'From', 'rationalseo' ); ?></th>
											<th><?php esc_html_e( 'To', 'rationalseo' ); ?></th>
											<th><?php esc_html_e( 'Type', 'rationalseo' ); ?></th>
											<th><?php esc_html_e( 'Regex', 'rationalseo' ); ?></th>
										</tr>
									</thead>
									<tbody id="rationalseo-import-list"></tbody>
								</table>
							</div>
						</div>
						<div id="rationalseo-import-duplicates" style="display: none;">
							<h4><?php esc_html_e( 'Duplicates (will be skipped)', 'rationalseo' ); ?></h4>
							<div class="rationalseo-import-table-wrap">
								<table class="widefat striped">
									<thead>
										<tr>
											<th><?php esc_html_e( 'From', 'rationalseo' ); ?></th>
											<th><?php esc_html_e( 'To', 'rationalseo' ); ?></th>
											<th><?php esc_html_e( 'Type', 'rationalseo' ); ?></th>
											<th><?php esc_html_e( 'Regex', 'rationalseo' ); ?></th>
										</tr>
									</thead>
									<tbody id="rationalseo-duplicates-list"></tbody>
								</table>
							</div>
						</div>
					</div>
					<div id="rationalseo-import-error" style="display: none;">
						<p></p>
					</div>
					<div id="rationalseo-import-result" style="display: none;">
						<p id="rationalseo-import-result-message"></p>
					</div>
				</div>
				<div class="rationalseo-modal-footer">
					<button type="button" class="button" id="rationalseo-import-cancel">
						<?php esc_html_e( 'Cancel', 'rationalseo' ); ?>
					</button>
					<button type="button" class="button button-primary" id="rationalseo-import-confirm" style="display: none;">
						<?php esc_html_e( 'Import Redirects', 'rationalseo' ); ?>
					</button>
					<button type="button" class="button button-primary" id="rationalseo-import-done" style="display: none;">
						<?php esc_html_e( 'Done', 'rationalseo' ); ?>
					</button>
				</div>
			</div>
		</div>

		<script type="text/javascript">
		(function($) {
			var nonce = '<?php echo esc_js( $nonce ); ?>';
			var homeUrl = '<?php echo esc_js( home_url() ); ?>';

			// Add redirect.
			$('#rationalseo-add-redirect').on('click', function() {
				var $btn = $(this);
				var urlFrom = $('#rationalseo-new-from').val().trim();
				var urlTo = $('#rationalseo-new-to').val().trim();
				var statusCode = $('#rationalseo-new-status').val();
				var isRegex = $('#rationalseo-new-regex').is(':checked') ? '1' : '0';

				if (!urlFrom) {
					showMessage('<?php echo esc_js( __( 'Please enter a source URL.', 'rationalseo' ) ); ?>', 'error');
					return;
				}

				if (statusCode !== '410' && !urlTo) {
					showMessage('<?php echo esc_js( __( 'Please enter a destination URL.', 'rationalseo' ) ); ?>', 'error');
					return;
				}

				$btn.prop('disabled', true);

				$.post(ajaxurl, {
					action: 'rationalseo_add_redirect',
					nonce: nonce,
					url_from: urlFrom,
					url_to: urlTo,
					status_code: statusCode,
					is_regex: isRegex
				}, function(response) {
					$btn.prop('disabled', false);

					if (response.success) {
						var redirect = response.data.redirect;
						var toDisplay = statusCode === '410'
							? '<em><?php echo esc_js( __( '(Gone)', 'rationalseo' ) ); ?></em>'
							: '<a href="' + redirect.url_to + '" target="_blank" rel="noopener">' + redirect.url_to + '</a>';
						var regexDisplay = redirect.is_regex == 1
							? '<span class="rationalseo-regex-badge"><?php echo esc_js( __( 'Yes', 'rationalseo' ) ); ?></span>'
							: '&mdash;';

						var newRow = '<tr data-id="' + redirect.id + '">' +
							'<td class="column-from"><code>' + redirect.url_from + '</code></td>' +
							'<td class="column-to">' + toDisplay + '</td>' +
							'<td class="column-status">' + redirect.status_code + '</td>' +
							'<td class="column-regex">' + regexDisplay + '</td>' +
							'<td class="column-hits">0</td>' +
							'<td class="column-actions">' +
								'<button type="button" class="button button-link-delete rationalseo-delete-redirect" data-id="' + redirect.id + '">' +
									'<?php echo esc_js( __( 'Delete', 'rationalseo' ) ); ?>' +
								'</button>' +
							'</td>' +
						'</tr>';

						$('.rationalseo-add-row').after(newRow);
						$('.no-redirects').remove();

						// Clear inputs.
						$('#rationalseo-new-from').val('');
						$('#rationalseo-new-to').val('');
						$('#rationalseo-new-status').val('301');
						$('#rationalseo-new-regex').prop('checked', false);

						showMessage(response.data.message, 'success');
					} else {
						showMessage(response.data.message, 'error');
					}
				}).fail(function() {
					$btn.prop('disabled', false);
					showMessage('<?php echo esc_js( __( 'An error occurred. Please try again.', 'rationalseo' ) ); ?>', 'error');
				});
			});

			// Delete redirect.
			$(document).on('click', '.rationalseo-delete-redirect', function() {
				var $btn = $(this);
				var id = $btn.data('id');

				if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete this redirect?', 'rationalseo' ) ); ?>')) {
					return;
				}

				$btn.prop('disabled', true);

				$.post(ajaxurl, {
					action: 'rationalseo_delete_redirect',
					nonce: nonce,
					id: id
				}, function(response) {
					if (response.success) {
						$btn.closest('tr').fadeOut(300, function() {
							$(this).remove();
							if ($('#rationalseo-redirects-list tr').length === 1) {
								$('.rationalseo-add-row').after(
									'<tr class="no-redirects"><td colspan="6"><?php echo esc_js( __( 'No redirects found. Add one above.', 'rationalseo' ) ); ?></td></tr>'
								);
							}
						});
						showMessage(response.data.message, 'success');
					} else {
						$btn.prop('disabled', false);
						showMessage(response.data.message, 'error');
					}
				}).fail(function() {
					$btn.prop('disabled', false);
					showMessage('<?php echo esc_js( __( 'An error occurred. Please try again.', 'rationalseo' ) ); ?>', 'error');
				});
			});

			function showMessage(message, type) {
				var $msg = $('#rationalseo-redirect-message');
				$msg.removeClass('notice-success notice-error')
					.addClass('notice-' + type)
					.html('<p>' + message + '</p>')
					.fadeIn();

				setTimeout(function() {
					$msg.fadeOut();
				}, 4000);
			}

			// Yoast Import Modal functionality.
			var $modal = $('#rationalseo-import-modal');
			var importData = null;

			// Open modal and preview.
			$('#rationalseo-import-yoast-btn').on('click', function() {
				openImportModal();
			});

			// Close modal handlers.
			$('.rationalseo-modal-close, #rationalseo-import-cancel, #rationalseo-import-done').on('click', function() {
				closeImportModal();
			});

			// Close on backdrop click.
			$modal.on('click', function(e) {
				if ($(e.target).is($modal)) {
					closeImportModal();
				}
			});

			// Confirm import.
			$('#rationalseo-import-confirm').on('click', function() {
				performImport();
			});

			function openImportModal() {
				// Reset modal state.
				$('#rationalseo-import-loading').show();
				$('#rationalseo-import-preview').hide();
				$('#rationalseo-import-error').removeClass('notice notice-error').hide();
				$('#rationalseo-import-result').removeClass('notice notice-success').hide();
				$('#rationalseo-import-confirm').hide().prop('disabled', false).text('<?php echo esc_js( __( 'Import Redirects', 'rationalseo' ) ); ?>');
				$('#rationalseo-import-done').hide();
				$('#rationalseo-import-cancel').show();
				$('#rationalseo-import-list').empty();
				$('#rationalseo-duplicates-list').empty();
				importData = null;

				$modal.css('display', 'flex');

				// Fetch preview data.
				$.post(ajaxurl, {
					action: 'rationalseo_preview_yoast_import',
					nonce: nonce
				}, function(response) {
					$('#rationalseo-import-loading').hide();

					if (response.success) {
						importData = response.data;
						showPreview(response.data);
					} else {
						showImportError(response.data.message);
					}
				}).fail(function() {
					$('#rationalseo-import-loading').hide();
					showImportError('<?php echo esc_js( __( 'Failed to connect to server. Please try again.', 'rationalseo' ) ); ?>');
				});
			}

			function closeImportModal() {
				$modal.hide();
			}

			function showPreview(data) {
				$('#rationalseo-import-summary').text(data.message);

				// Populate redirects to import.
				var $importList = $('#rationalseo-import-list');
				if (data.to_import.length > 0) {
					data.to_import.forEach(function(redirect) {
						var toDisplay = redirect.status_code == 410
							? '<em><?php echo esc_js( __( '(Gone)', 'rationalseo' ) ); ?></em>'
							: escapeHtml(redirect.url_to);
						var regexDisplay = redirect.is_regex
							? '<span class="rationalseo-regex-badge"><?php echo esc_js( __( 'Yes', 'rationalseo' ) ); ?></span>'
							: '&mdash;';
						$importList.append(
							'<tr>' +
								'<td><code>' + escapeHtml(redirect.url_from) + '</code></td>' +
								'<td>' + toDisplay + '</td>' +
								'<td>' + redirect.status_code + '</td>' +
								'<td>' + regexDisplay + '</td>' +
							'</tr>'
						);
					});
					$('#rationalseo-import-details').show();
					$('#rationalseo-import-confirm').show();
				} else {
					$('#rationalseo-import-details').hide();
				}

				// Populate duplicates.
				var $duplicatesList = $('#rationalseo-duplicates-list');
				if (data.duplicates.length > 0) {
					data.duplicates.forEach(function(redirect) {
						var toDisplay = redirect.status_code == 410
							? '<em><?php echo esc_js( __( '(Gone)', 'rationalseo' ) ); ?></em>'
							: escapeHtml(redirect.url_to);
						var regexDisplay = redirect.is_regex
							? '<span class="rationalseo-regex-badge"><?php echo esc_js( __( 'Yes', 'rationalseo' ) ); ?></span>'
							: '&mdash;';
						$duplicatesList.append(
							'<tr>' +
								'<td><code>' + escapeHtml(redirect.url_from) + '</code></td>' +
								'<td>' + toDisplay + '</td>' +
								'<td>' + redirect.status_code + '</td>' +
								'<td>' + regexDisplay + '</td>' +
							'</tr>'
						);
					});
					$('#rationalseo-import-duplicates').show();
				} else {
					$('#rationalseo-import-duplicates').hide();
				}

				$('#rationalseo-import-preview').show();

				// If nothing to import, show done button instead.
				if (data.to_import.length === 0) {
					$('#rationalseo-import-confirm').hide();
					$('#rationalseo-import-done').show();
					$('#rationalseo-import-cancel').hide();
				}
			}

			function showImportError(message) {
				$('#rationalseo-import-error').addClass('notice notice-error').show().find('p').text(message);
				$('#rationalseo-import-done').show();
				$('#rationalseo-import-cancel').hide();
			}

			function performImport() {
				$('#rationalseo-import-confirm').prop('disabled', true).text('<?php echo esc_js( __( 'Importing...', 'rationalseo' ) ); ?>');

				$.post(ajaxurl, {
					action: 'rationalseo_import_yoast_redirects',
					nonce: nonce
				}, function(response) {
					$('#rationalseo-import-preview').hide();
					$('#rationalseo-import-confirm').hide();
					$('#rationalseo-import-cancel').hide();

					if (response.success) {
						$('#rationalseo-import-result').addClass('notice notice-success').show();
						$('#rationalseo-import-result-message').text(response.data.message);
						$('#rationalseo-import-done').show();

						// Add imported redirects to the table.
						if (response.data.redirects && response.data.redirects.length > 0) {
							$('.no-redirects').remove();
							response.data.redirects.forEach(function(redirect) {
								var toDisplay = redirect.status_code == 410
									? '<em><?php echo esc_js( __( '(Gone)', 'rationalseo' ) ); ?></em>'
									: '<a href="' + redirect.url_to + '" target="_blank" rel="noopener">' + redirect.url_to + '</a>';
								var regexDisplay = redirect.is_regex == 1
									? '<span class="rationalseo-regex-badge"><?php echo esc_js( __( 'Yes', 'rationalseo' ) ); ?></span>'
									: '&mdash;';

								var newRow = '<tr data-id="' + redirect.id + '">' +
									'<td class="column-from"><code>' + redirect.url_from + '</code></td>' +
									'<td class="column-to">' + toDisplay + '</td>' +
									'<td class="column-status">' + redirect.status_code + '</td>' +
									'<td class="column-regex">' + regexDisplay + '</td>' +
									'<td class="column-hits">0</td>' +
									'<td class="column-actions">' +
										'<button type="button" class="button button-link-delete rationalseo-delete-redirect" data-id="' + redirect.id + '">' +
											'<?php echo esc_js( __( 'Delete', 'rationalseo' ) ); ?>' +
										'</button>' +
									'</td>' +
								'</tr>';

								$('.rationalseo-add-row').after(newRow);
							});
						}
					} else {
						showImportError(response.data.message);
					}
				}).fail(function() {
					$('#rationalseo-import-preview').hide();
					$('#rationalseo-import-confirm').hide();
					showImportError('<?php echo esc_js( __( 'Failed to import. Please try again.', 'rationalseo' ) ); ?>');
				});
			}

			function escapeHtml(text) {
				if (!text) return '';
				var div = document.createElement('div');
				div.appendChild(document.createTextNode(text));
				return div.innerHTML;
			}
		})(jQuery);
		</script>
		<?php
	}
}
