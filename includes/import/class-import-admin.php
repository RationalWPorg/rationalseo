<?php
/**
 * RationalSEO Import Admin Class
 *
 * Handles admin UI and AJAX handlers for the import system.
 *
 * @package RationalSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Import admin class.
 */
class RationalSEO_Import_Admin {

	/**
	 * Import manager instance.
	 *
	 * @var RationalSEO_Import_Manager
	 */
	private $manager;

	/**
	 * Constructor.
	 *
	 * @param RationalSEO_Import_Manager $manager Import manager instance.
	 */
	public function __construct( RationalSEO_Import_Manager $manager ) {
		$this->manager = $manager;
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_rationalseo_get_importers', array( $this, 'ajax_get_importers' ) );
		add_action( 'wp_ajax_rationalseo_preview_import', array( $this, 'ajax_preview_import' ) );
		add_action( 'wp_ajax_rationalseo_run_import', array( $this, 'ajax_run_import' ) );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'rationalwp_page_rationalseo' !== $hook ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';

		if ( 'import' !== $tab ) {
			return;
		}

		wp_enqueue_style(
			'rationalseo-import',
			RATIONALSEO_PLUGIN_URL . 'assets/css/import.css',
			array(),
			RATIONALSEO_VERSION
		);

		wp_enqueue_script(
			'rationalseo-import',
			RATIONALSEO_PLUGIN_URL . 'assets/js/import.js',
			array( 'jquery' ),
			RATIONALSEO_VERSION,
			true
		);

		wp_localize_script(
			'rationalseo-import',
			'rationalseoImport',
			array(
				'nonce' => wp_create_nonce( 'rationalseo_import' ),
				'i18n'  => array(
					'noPluginsDetected'  => __( 'No SEO plugins with importable data detected.', 'rationalseo' ),
					'canImportFrom'      => __( 'RationalSEO can import data from Yoast SEO, RankMath, and All in One SEO when their data is present.', 'rationalseo' ),
					'failedToLoad'       => __( 'Failed to load importers. Please refresh the page.', 'rationalseo' ),
					'noDataToImport'     => __( 'No data to import', 'rationalseo' ),
					'importData'         => __( 'Import Data', 'rationalseo' ),
					'noData'             => __( 'No Data', 'rationalseo' ),
					'importFrom'         => __( 'Import from', 'rationalseo' ),
					'loadingPreview'     => __( 'Loading preview...', 'rationalseo' ),
					'importSelected'     => __( 'Import Selected', 'rationalseo' ),
					'failedToLoadPreview' => __( 'Failed to load preview. Please try again.', 'rationalseo' ),
					'skipExisting'       => __( 'Skip existing data (recommended)', 'rationalseo' ),
					'selectDataToImport' => __( 'Select data to import:', 'rationalseo' ),
					'items'              => __( 'items', 'rationalseo' ),
					'noDataAvailable'    => __( 'No data available to import.', 'rationalseo' ),
					'preview'            => __( 'Preview:', 'rationalseo' ),
					'and'                => __( 'And', 'rationalseo' ),
					'more'               => __( 'more...', 'rationalseo' ),
					'selectAtLeastOne'   => __( 'Please select at least one data type to import.', 'rationalseo' ),
					'importingData'      => __( 'Importing data...', 'rationalseo' ),
					'importing'          => __( 'Importing...', 'rationalseo' ),
					'importFailed'       => __( 'Import failed. Please try again.', 'rationalseo' ),
				),
			)
		);
	}

	/**
	 * Render the import tab content.
	 */
	public function render_import_tab() {
		?>
		<div class="rationalseo-import-wrap">
			<h2><?php esc_html_e( 'Import SEO Data', 'rationalseo' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Import SEO data from other plugins. Select a source below to see available data and import options.', 'rationalseo' ); ?>
			</p>

			<div id="rationalseo-import-sources" class="rationalseo-import-sources">
				<div class="rationalseo-import-loading">
					<span class="spinner is-active"></span>
					<?php esc_html_e( 'Scanning for available importers...', 'rationalseo' ); ?>
				</div>
			</div>

			<!-- Import Modal -->
			<div id="rationalseo-import-modal" class="rationalseo-modal" style="display: none;">
				<div class="rationalseo-modal-content rationalseo-import-modal-content">
					<div class="rationalseo-modal-header">
						<h3 id="rationalseo-import-modal-title"><?php esc_html_e( 'Import Data', 'rationalseo' ); ?></h3>
						<button type="button" class="rationalseo-modal-close">&times;</button>
					</div>
					<div class="rationalseo-modal-body">
						<div id="rationalseo-import-modal-loading" class="rationalseo-import-loading">
							<span class="spinner is-active"></span>
							<span id="rationalseo-import-modal-loading-text"><?php esc_html_e( 'Loading...', 'rationalseo' ); ?></span>
						</div>
						<div id="rationalseo-import-modal-content" style="display: none;"></div>
						<div id="rationalseo-import-modal-error" style="display: none;">
							<p></p>
						</div>
						<div id="rationalseo-import-modal-result" style="display: none;">
							<p></p>
						</div>
					</div>
					<div class="rationalseo-modal-footer">
						<button type="button" class="button" id="rationalseo-import-modal-cancel">
							<?php esc_html_e( 'Cancel', 'rationalseo' ); ?>
						</button>
						<button type="button" class="button button-primary" id="rationalseo-import-modal-confirm" style="display: none;">
							<?php esc_html_e( 'Import Selected', 'rationalseo' ); ?>
						</button>
						<button type="button" class="button button-primary" id="rationalseo-import-modal-done" style="display: none;">
							<?php esc_html_e( 'Done', 'rationalseo' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler for getting available importers.
	 */
	public function ajax_get_importers() {
		check_ajax_referer( 'rationalseo_import', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'rationalseo' ) ) );
		}

		$importers = $this->manager->get_importers_for_display( true );

		wp_send_json_success( array( 'importers' => $importers ) );
	}

	/**
	 * AJAX handler for previewing an import.
	 */
	public function ajax_preview_import() {
		check_ajax_referer( 'rationalseo_import', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'rationalseo' ) ) );
		}

		$importer_slug = isset( $_POST['importer'] ) ? sanitize_key( $_POST['importer'] ) : '';

		if ( empty( $importer_slug ) ) {
			wp_send_json_error( array( 'message' => __( 'No importer specified.', 'rationalseo' ) ) );
		}

		$result = $this->manager->preview( $importer_slug );

		if ( ! $result->is_success() ) {
			wp_send_json_error( array( 'message' => $result->get_message() ) );
		}

		wp_send_json_success( $result->to_array() );
	}

	/**
	 * AJAX handler for running an import.
	 */
	public function ajax_run_import() {
		check_ajax_referer( 'rationalseo_import', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'rationalseo' ) ) );
		}

		$importer_slug = isset( $_POST['importer'] ) ? sanitize_key( $_POST['importer'] ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$item_types    = isset( $_POST['item_types'] ) ? array_map( 'sanitize_key', (array) $_POST['item_types'] ) : array();
		$skip_existing = isset( $_POST['skip_existing'] ) && '1' === $_POST['skip_existing'];

		if ( empty( $importer_slug ) ) {
			wp_send_json_error( array( 'message' => __( 'No importer specified.', 'rationalseo' ) ) );
		}

		if ( empty( $item_types ) ) {
			wp_send_json_error( array( 'message' => __( 'No data types selected.', 'rationalseo' ) ) );
		}

		$options = array(
			'skip_existing' => $skip_existing,
		);

		$result = $this->manager->import( $importer_slug, $item_types, $options );

		if ( ! $result->is_success() && 0 === $result->get_imported() ) {
			wp_send_json_error( array( 'message' => $result->get_message() ) );
		}

		// Build success message.
		$message = sprintf(
			/* translators: 1: Number imported, 2: Number skipped, 3: Number failed */
			__( 'Import complete. Imported: %1$d, Skipped: %2$d, Failed: %3$d', 'rationalseo' ),
			$result->get_imported(),
			$result->get_skipped(),
			$result->get_failed()
		);

		if ( $result->get_message() ) {
			$message = $result->get_message();
		}

		wp_send_json_success(
			array(
				'message'  => $message,
				'imported' => $result->get_imported(),
				'skipped'  => $result->get_skipped(),
				'failed'   => $result->get_failed(),
				'data'     => $result->get_data(),
			)
		);
	}
}
