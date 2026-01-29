<?php
/**
 * RationalSEO Term Meta Class
 *
 * Handles taxonomy term SEO fields.
 *
 * @package RationalSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RationalSEO_Term_Meta {

	/**
	 * Settings instance.
	 *
	 * @var RationalSEO_Settings
	 */
	private $settings;

	/**
	 * Meta keys used by this plugin for terms.
	 */
	const META_TITLE     = '_rationalseo_term_title';
	const META_DESC      = '_rationalseo_term_desc';
	const META_CANONICAL = '_rationalseo_term_canonical';
	const META_NOINDEX   = '_rationalseo_term_noindex';
	const META_OG_IMAGE  = '_rationalseo_term_og_image';

	/**
	 * Nonce action for security.
	 */
	const NONCE_ACTION = 'rationalseo_term_meta';
	const NONCE_NAME   = 'rationalseo_term_meta_nonce';

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
		// Register hooks for each public taxonomy.
		add_action( 'admin_init', array( $this, 'register_taxonomy_hooks' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register hooks for all public taxonomies.
	 */
	public function register_taxonomy_hooks() {
		$taxonomies = $this->get_supported_taxonomies();

		foreach ( $taxonomies as $taxonomy ) {
			// Add fields to edit term screen.
			add_action( "{$taxonomy}_edit_form_fields", array( $this, 'render_edit_fields' ), 10, 2 );

			// Add fields to add new term screen.
			add_action( "{$taxonomy}_add_form_fields", array( $this, 'render_add_fields' ), 10, 1 );

			// Save term meta.
			add_action( "edited_{$taxonomy}", array( $this, 'save_term_meta' ), 10, 2 );
			add_action( "created_{$taxonomy}", array( $this, 'save_term_meta' ), 10, 2 );
		}
	}

	/**
	 * Get taxonomies that should have SEO fields.
	 *
	 * @return array
	 */
	private function get_supported_taxonomies() {
		return get_taxonomies( array( 'public' => true ), 'names' );
	}

	/**
	 * Enqueue admin assets for term edit screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'term.php', 'edit-tags.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->taxonomy, $this->get_supported_taxonomies(), true ) ) {
			return;
		}

		wp_enqueue_style(
			'rationalseo-meta-box',
			RATIONALSEO_PLUGIN_URL . 'assets/css/meta-box.css',
			array(),
			RATIONALSEO_VERSION
		);
	}

	/**
	 * Render SEO fields on the edit term screen (table layout).
	 *
	 * @param WP_Term $term     Current term object.
	 * @param string  $taxonomy Current taxonomy slug.
	 */
	public function render_edit_fields( $term, $taxonomy ) {
		// Get existing values.
		$title     = get_term_meta( $term->term_id, self::META_TITLE, true );
		$desc      = get_term_meta( $term->term_id, self::META_DESC, true );
		$canonical = get_term_meta( $term->term_id, self::META_CANONICAL, true );
		$noindex   = get_term_meta( $term->term_id, self::META_NOINDEX, true );
		$og_image  = get_term_meta( $term->term_id, self::META_OG_IMAGE, true );

		// Calculate default title for placeholder.
		$separator     = $this->settings->get( 'separator', '|' );
		$site_name     = $this->settings->get( 'site_name', get_bloginfo( 'name' ) );
		$default_title = sprintf( '%s %s %s', $term->name, $separator, $site_name );
		$term_link     = get_term_link( $term );
		$term_link_url = ! is_wp_error( $term_link ) ? $term_link : '';

		// Nonce for security.
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<tr class="form-field rationalseo-term-field">
			<th scope="row">
				<label for="rationalseo_term_title"><?php esc_html_e( 'SEO Title', 'rationalseo' ); ?></label>
			</th>
			<td>
				<input type="text"
					id="rationalseo_term_title"
					name="rationalseo_term_title"
					value="<?php echo esc_attr( $title ); ?>"
					class="large-text"
					placeholder="<?php echo esc_attr( $default_title ); ?>">
				<p class="description">
					<?php esc_html_e( 'Custom title for search engines. Leave empty to use the default.', 'rationalseo' ); ?>
				</p>
			</td>
		</tr>

		<tr class="form-field rationalseo-term-field">
			<th scope="row">
				<label for="rationalseo_term_desc"><?php esc_html_e( 'Meta Description', 'rationalseo' ); ?></label>
			</th>
			<td>
				<textarea
					id="rationalseo_term_desc"
					name="rationalseo_term_desc"
					rows="3"
					class="large-text"
					placeholder="<?php esc_html_e( 'Leave empty to use the term description.', 'rationalseo' ); ?>"><?php echo esc_textarea( $desc ); ?></textarea>
				<p class="description">
					<?php esc_html_e( 'Custom description for search results. Leave empty to use the term description.', 'rationalseo' ); ?>
				</p>
			</td>
		</tr>

		<tr class="form-field rationalseo-term-field">
			<th scope="row"><?php esc_html_e( 'Advanced Settings', 'rationalseo' ); ?></th>
			<td>
				<details class="rationalseo-term-advanced">
					<summary><?php esc_html_e( 'Show Advanced Settings', 'rationalseo' ); ?></summary>

					<div class="rationalseo-term-advanced-content">
						<div class="rationalseo-term-field rationalseo-term-checkbox-field">
							<label>
								<input type="checkbox"
									id="rationalseo_term_noindex"
									name="rationalseo_term_noindex"
									value="1"
									<?php checked( $noindex, '1' ); ?>>
								<?php esc_html_e( 'Exclude from Search Results (noindex)', 'rationalseo' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Prevents this archive from appearing in search engine results.', 'rationalseo' ); ?>
							</p>
						</div>

						<div class="rationalseo-term-field">
							<label for="rationalseo_term_canonical">
								<?php esc_html_e( 'Canonical URL', 'rationalseo' ); ?>
							</label>
							<input type="url"
								id="rationalseo_term_canonical"
								name="rationalseo_term_canonical"
								value="<?php echo esc_attr( $canonical ); ?>"
								class="large-text"
								placeholder="<?php echo esc_url( $term_link_url ); ?>">
							<p class="description">
								<?php esc_html_e( 'Override the canonical URL if this archive is duplicated elsewhere.', 'rationalseo' ); ?>
							</p>
						</div>

						<div class="rationalseo-term-field">
							<label for="rationalseo_term_og_image">
								<?php esc_html_e( 'Social Image Override', 'rationalseo' ); ?>
							</label>
							<input type="url"
								id="rationalseo_term_og_image"
								name="rationalseo_term_og_image"
								value="<?php echo esc_attr( $og_image ); ?>"
								class="large-text"
								placeholder="https://example.com/image.jpg">
							<p class="description">
								<?php esc_html_e( 'Custom image for social sharing on this archive.', 'rationalseo' ); ?>
							</p>
						</div>
					</div>
				</details>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render SEO fields on the add new term screen (div layout).
	 *
	 * @param string $taxonomy Current taxonomy slug.
	 */
	public function render_add_fields( $taxonomy ) {
		// Nonce for security.
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		// Calculate default title placeholder.
		$separator = $this->settings->get( 'separator', '|' );
		$site_name = $this->settings->get( 'site_name', get_bloginfo( 'name' ) );
		?>
		<div class="form-field rationalseo-term-field">
			<label for="rationalseo_term_title"><?php esc_html_e( 'SEO Title', 'rationalseo' ); ?></label>
			<input type="text"
				id="rationalseo_term_title"
				name="rationalseo_term_title"
				value=""
				placeholder="<?php printf( esc_attr__( '{term name} %s %s', 'rationalseo' ), esc_attr( $separator ), esc_attr( $site_name ) ); ?>">
			<p class="description">
				<?php esc_html_e( 'Custom title for search engines. Leave empty to use the default.', 'rationalseo' ); ?>
			</p>
		</div>

		<div class="form-field rationalseo-term-field">
			<label for="rationalseo_term_desc"><?php esc_html_e( 'Meta Description', 'rationalseo' ); ?></label>
			<textarea
				id="rationalseo_term_desc"
				name="rationalseo_term_desc"
				rows="3"
				placeholder="<?php esc_attr_e( 'Leave empty to use the term description.', 'rationalseo' ); ?>"></textarea>
			<p class="description">
				<?php esc_html_e( 'Custom description for search results. Leave empty to use the term description.', 'rationalseo' ); ?>
			</p>
		</div>

		<div class="form-field rationalseo-term-field">
			<details class="rationalseo-term-advanced">
				<summary><?php esc_html_e( 'Advanced Settings', 'rationalseo' ); ?></summary>

				<div class="rationalseo-term-advanced-content">
					<div class="rationalseo-term-field rationalseo-term-checkbox-field">
						<label>
							<input type="checkbox"
								id="rationalseo_term_noindex"
								name="rationalseo_term_noindex"
								value="1">
							<?php esc_html_e( 'Exclude from Search Results (noindex)', 'rationalseo' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Prevents this archive from appearing in search engine results.', 'rationalseo' ); ?>
						</p>
					</div>

					<div class="rationalseo-term-field">
						<label for="rationalseo_term_canonical">
							<?php esc_html_e( 'Canonical URL', 'rationalseo' ); ?>
						</label>
						<input type="url"
							id="rationalseo_term_canonical"
							name="rationalseo_term_canonical"
							value=""
							class="regular-text"
							placeholder="https://example.com/category/slug/">
						<p class="description">
							<?php esc_html_e( 'Override the canonical URL if this archive is duplicated elsewhere.', 'rationalseo' ); ?>
						</p>
					</div>

					<div class="rationalseo-term-field">
						<label for="rationalseo_term_og_image">
							<?php esc_html_e( 'Social Image Override', 'rationalseo' ); ?>
						</label>
						<input type="url"
							id="rationalseo_term_og_image"
							name="rationalseo_term_og_image"
							value=""
							class="regular-text"
							placeholder="https://example.com/image.jpg">
						<p class="description">
							<?php esc_html_e( 'Custom image for social sharing on this archive.', 'rationalseo' ); ?>
						</p>
					</div>
				</div>
			</details>
		</div>
		<?php
	}

	/**
	 * Save term meta data.
	 *
	 * @param int $term_id Term ID.
	 * @param int $tt_id   Term taxonomy ID.
	 */
	public function save_term_meta( $term_id, $tt_id ) {
		// Verify nonce.
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE_NAME ] ), self::NONCE_ACTION ) ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		// Sanitize and save SEO Title.
		$title = isset( $_POST['rationalseo_term_title'] )
			? sanitize_text_field( wp_unslash( $_POST['rationalseo_term_title'] ) )
			: '';

		if ( ! empty( $title ) ) {
			update_term_meta( $term_id, self::META_TITLE, $title );
		} else {
			delete_term_meta( $term_id, self::META_TITLE );
		}

		// Sanitize and save Meta Description.
		$desc = isset( $_POST['rationalseo_term_desc'] )
			? sanitize_textarea_field( wp_unslash( $_POST['rationalseo_term_desc'] ) )
			: '';

		if ( ! empty( $desc ) ) {
			update_term_meta( $term_id, self::META_DESC, $desc );
		} else {
			delete_term_meta( $term_id, self::META_DESC );
		}

		// Sanitize and save Canonical URL.
		$canonical = isset( $_POST['rationalseo_term_canonical'] )
			? esc_url_raw( wp_unslash( $_POST['rationalseo_term_canonical'] ) )
			: '';

		if ( ! empty( $canonical ) ) {
			update_term_meta( $term_id, self::META_CANONICAL, $canonical );
		} else {
			delete_term_meta( $term_id, self::META_CANONICAL );
		}

		// Sanitize and save noindex.
		$noindex = isset( $_POST['rationalseo_term_noindex'] ) && '1' === $_POST['rationalseo_term_noindex'];

		if ( $noindex ) {
			update_term_meta( $term_id, self::META_NOINDEX, '1' );
		} else {
			delete_term_meta( $term_id, self::META_NOINDEX );
		}

		// Sanitize and save Social Image Override.
		$og_image = isset( $_POST['rationalseo_term_og_image'] )
			? esc_url_raw( wp_unslash( $_POST['rationalseo_term_og_image'] ) )
			: '';

		if ( ! empty( $og_image ) ) {
			update_term_meta( $term_id, self::META_OG_IMAGE, $og_image );
		} else {
			delete_term_meta( $term_id, self::META_OG_IMAGE );
		}
	}
}
