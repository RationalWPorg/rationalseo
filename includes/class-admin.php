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
			'general' => __( 'General', 'rationalseo' ),
			'social'  => __( 'Social', 'rationalseo' ),
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

			<form action="options.php" method="post">
				<?php
				settings_fields( 'rationalseo_settings_group' );

				if ( 'social' === $current_tab ) {
					do_settings_sections( 'rationalseo_social' );
				} else {
					do_settings_sections( 'rationalseo' );
				}

				submit_button( __( 'Save Settings', 'rationalseo' ) );
				?>
			</form>
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
}
