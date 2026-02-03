<?php
/**
 * RationalSEO Meta Box Class
 *
 * Handles post/page editor meta box for SEO fields.
 *
 * @package RationalSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RationalSEO_Meta_Box {

	/**
	 * Settings instance.
	 *
	 * @var RationalSEO_Settings
	 */
	private $settings;

	/**
	 * Meta keys used by this plugin.
	 */
	const META_TITLE         = '_rationalseo_title';
	const META_DESC          = '_rationalseo_desc';
	const META_CANONICAL     = '_rationalseo_canonical';
	const META_NOINDEX       = '_rationalseo_noindex';
	const META_OG_IMAGE      = '_rationalseo_og_image';
	const META_FOCUS_KEYWORD = '_rationalseo_focus_keyword';

	/**
	 * Nonce action for security.
	 */
	const NONCE_ACTION = 'rationalseo_meta_box';
	const NONCE_NAME   = 'rationalseo_meta_box_nonce';

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
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Get post types that should have the meta box.
	 *
	 * @return array
	 */
	private function get_supported_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		// Remove attachment as it doesn't make sense for SEO.
		unset( $post_types['attachment'] );

		return array_values( $post_types );
	}

	/**
	 * Add meta box to supported post types.
	 */
	public function add_meta_box() {
		$post_types = $this->get_supported_post_types();

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'rationalseo_meta_box',
				__( 'RationalSEO', 'rationalseo' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Enqueue admin assets for meta box.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, $this->get_supported_post_types(), true ) ) {
			return;
		}

		wp_enqueue_style(
			'rationalseo-meta-box',
			RATIONALSEO_PLUGIN_URL . 'assets/css/meta-box.css',
			array(),
			RATIONALSEO_VERSION
		);

		wp_enqueue_script(
			'rationalseo-meta-box',
			RATIONALSEO_PLUGIN_URL . 'assets/js/meta-box.js',
			array( 'wp-data' ),
			RATIONALSEO_VERSION,
			true
		);

		wp_localize_script(
			'rationalseo-meta-box',
			'rationalseoMetaBox',
			array(
				'keywordId' => 'rationalseo_focus_keyword',
				'titleId'   => 'rationalseo_title',
				'descId'    => 'rationalseo_desc',
				'hasApiKey' => ! empty( $this->settings->get_decrypted( 'openai_api_key' ) ),
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'rationalseo_meta_box' ),
			)
		);
	}

	/**
	 * Render meta box content.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_meta_box( $post ) {
		// Get existing values.
		$title         = get_post_meta( $post->ID, self::META_TITLE, true );
		$desc          = get_post_meta( $post->ID, self::META_DESC, true );
		$canonical     = get_post_meta( $post->ID, self::META_CANONICAL, true );
		$noindex       = get_post_meta( $post->ID, self::META_NOINDEX, true );
		$og_image      = get_post_meta( $post->ID, self::META_OG_IMAGE, true );
		$focus_keyword = get_post_meta( $post->ID, self::META_FOCUS_KEYWORD, true );

		// Calculate default title for placeholder.
		$separator  = $this->settings->get( 'separator', '|' );
		$site_name  = $this->settings->get( 'site_name', get_bloginfo( 'name' ) );
		$post_title = get_the_title( $post );
		$default_title = sprintf( '%s %s %s', $post_title, $separator, $site_name );

		// Nonce for security.
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<div class="rationalseo-meta-box">
			<div class="rationalseo-field">
				<label for="rationalseo_title">
					<?php esc_html_e( 'SEO Title', 'rationalseo' ); ?>
				</label>
				<input type="text"
					id="rationalseo_title"
					name="rationalseo_title"
					value="<?php echo esc_attr( $title ); ?>"
					class="large-text"
					placeholder="<?php echo esc_attr( $default_title ); ?>">
				<p class="description">
					<?php esc_html_e( 'Custom title for search engines. Leave empty to use the default.', 'rationalseo' ); ?>
				</p>
			</div>

			<div class="rationalseo-field">
				<label for="rationalseo_focus_keyword">
					<?php esc_html_e( 'Focus Keyword', 'rationalseo' ); ?>
				</label>
				<div class="rationalseo-input-with-button">
					<input type="text"
						id="rationalseo_focus_keyword"
						name="rationalseo_focus_keyword"
						value="<?php echo esc_attr( $focus_keyword ); ?>"
						class="large-text"
						placeholder="<?php esc_attr_e( 'Enter your target keyword or phrase', 'rationalseo' ); ?>">
					<button type="button" id="rationalseo-suggest-keyword" class="button rationalseo-ai-button" style="display: none;">
						<span class="rationalseo-ai-button-text"><?php esc_html_e( 'Suggest', 'rationalseo' ); ?></span>
						<span class="rationalseo-ai-button-spinner spinner"></span>
					</button>
				</div>
				<p class="description">
					<?php esc_html_e( 'The main keyword you want this content to rank for.', 'rationalseo' ); ?>
				</p>

				<div id="rationalseo-keyword-checks" class="rationalseo-keyword-checks" style="display: none;">
					<div id="rationalseo-check-title" class="rationalseo-check">
						<span class="rationalseo-check-icon"></span>
						<span class="rationalseo-check-label"><?php esc_html_e( 'In SEO title', 'rationalseo' ); ?></span>
					</div>
					<div id="rationalseo-check-desc" class="rationalseo-check">
						<span class="rationalseo-check-icon"></span>
						<span class="rationalseo-check-label"><?php esc_html_e( 'In meta description', 'rationalseo' ); ?></span>
					</div>
					<div id="rationalseo-check-first-paragraph" class="rationalseo-check">
						<span class="rationalseo-check-icon"></span>
						<span class="rationalseo-check-label"><?php esc_html_e( 'In first paragraph', 'rationalseo' ); ?></span>
					</div>
					<div id="rationalseo-check-slug" class="rationalseo-check">
						<span class="rationalseo-check-icon"></span>
						<span class="rationalseo-check-label"><?php esc_html_e( 'In URL slug', 'rationalseo' ); ?></span>
					</div>
				</div>
			</div>

			<div class="rationalseo-field">
				<label for="rationalseo_desc">
					<?php esc_html_e( 'Meta Description', 'rationalseo' ); ?>
				</label>
				<textarea
					id="rationalseo_desc"
					name="rationalseo_desc"
					rows="3"
					class="large-text"
					placeholder="<?php esc_html_e( 'Enter a description for search results...', 'rationalseo' ); ?>"><?php echo esc_textarea( $desc ); ?></textarea>
				<div class="rationalseo-field-footer">
					<p class="description">
						<?php esc_html_e( 'Leave empty to use the excerpt or auto-generate from content.', 'rationalseo' ); ?>
					</p>
					<button type="button" id="rationalseo-generate-description" class="button rationalseo-ai-button" style="display: none;">
						<span class="rationalseo-ai-button-text"><?php esc_html_e( 'Generate', 'rationalseo' ); ?></span>
						<span class="rationalseo-ai-button-spinner spinner"></span>
					</button>
				</div>
			</div>

			<details class="rationalseo-advanced">
				<summary><?php esc_html_e( 'Advanced Settings', 'rationalseo' ); ?></summary>

				<div class="rationalseo-advanced-content">
					<div class="rationalseo-field rationalseo-checkbox-field">
						<label>
							<input type="checkbox"
								id="rationalseo_noindex"
								name="rationalseo_noindex"
								value="1"
								<?php checked( $noindex, '1' ); ?>>
							<?php esc_html_e( 'Exclude from Search Results (noindex)', 'rationalseo' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Prevents this content from appearing in search engine results.', 'rationalseo' ); ?>
						</p>
					</div>

					<div class="rationalseo-field">
						<label for="rationalseo_canonical">
							<?php esc_html_e( 'Canonical URL', 'rationalseo' ); ?>
						</label>
						<input type="url"
							id="rationalseo_canonical"
							name="rationalseo_canonical"
							value="<?php echo esc_attr( $canonical ); ?>"
							class="large-text"
							placeholder="<?php echo esc_url( get_permalink( $post ) ); ?>">
						<p class="description">
							<?php esc_html_e( 'Override the canonical URL if this content is duplicated elsewhere.', 'rationalseo' ); ?>
						</p>
					</div>

					<div class="rationalseo-field">
						<label for="rationalseo_og_image">
							<?php esc_html_e( 'Social Image Override', 'rationalseo' ); ?>
						</label>
						<input type="url"
							id="rationalseo_og_image"
							name="rationalseo_og_image"
							value="<?php echo esc_attr( $og_image ); ?>"
							class="large-text"
							placeholder="https://example.com/image.jpg">
						<p class="description">
							<?php esc_html_e( 'Custom image for social sharing. Overrides featured image.', 'rationalseo' ); ?>
						</p>
					</div>
				</div>
			</details>
		</div>
		<?php
	}

	/**
	 * Save meta box data.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_meta_box( $post_id, $post ) {
		// Verify nonce.
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE_NAME ] ), self::NONCE_ACTION ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		$post_type_obj = get_post_type_object( $post->post_type );
		if ( ! $post_type_obj || ! current_user_can( $post_type_obj->cap->edit_post, $post_id ) ) {
			return;
		}

		// Check if this is a supported post type.
		if ( ! in_array( $post->post_type, $this->get_supported_post_types(), true ) ) {
			return;
		}

		// Sanitize and save SEO Title.
		$title = isset( $_POST['rationalseo_title'] )
			? sanitize_text_field( wp_unslash( $_POST['rationalseo_title'] ) )
			: '';

		if ( ! empty( $title ) ) {
			update_post_meta( $post_id, self::META_TITLE, $title );
		} else {
			delete_post_meta( $post_id, self::META_TITLE );
		}

		// Sanitize and save Focus Keyword.
		$focus_keyword = isset( $_POST['rationalseo_focus_keyword'] )
			? sanitize_text_field( wp_unslash( $_POST['rationalseo_focus_keyword'] ) )
			: '';

		if ( ! empty( $focus_keyword ) ) {
			update_post_meta( $post_id, self::META_FOCUS_KEYWORD, $focus_keyword );
		} else {
			delete_post_meta( $post_id, self::META_FOCUS_KEYWORD );
		}

		// Sanitize and save Meta Description.
		$desc = isset( $_POST['rationalseo_desc'] )
			? sanitize_textarea_field( wp_unslash( $_POST['rationalseo_desc'] ) )
			: '';

		if ( ! empty( $desc ) ) {
			update_post_meta( $post_id, self::META_DESC, $desc );
		} else {
			delete_post_meta( $post_id, self::META_DESC );
		}

		// Sanitize and save Canonical URL.
		$canonical = isset( $_POST['rationalseo_canonical'] )
			? esc_url_raw( wp_unslash( $_POST['rationalseo_canonical'] ) )
			: '';

		if ( ! empty( $canonical ) ) {
			update_post_meta( $post_id, self::META_CANONICAL, $canonical );
		} else {
			delete_post_meta( $post_id, self::META_CANONICAL );
		}

		// Sanitize and save noindex.
		$noindex = isset( $_POST['rationalseo_noindex'] ) && '1' === $_POST['rationalseo_noindex'];

		if ( $noindex ) {
			update_post_meta( $post_id, self::META_NOINDEX, '1' );
		} else {
			delete_post_meta( $post_id, self::META_NOINDEX );
		}

		// Sanitize and save Social Image Override.
		$og_image = isset( $_POST['rationalseo_og_image'] )
			? esc_url_raw( wp_unslash( $_POST['rationalseo_og_image'] ) )
			: '';

		if ( ! empty( $og_image ) ) {
			update_post_meta( $post_id, self::META_OG_IMAGE, $og_image );
		} else {
			delete_post_meta( $post_id, self::META_OG_IMAGE );
		}
	}
}
