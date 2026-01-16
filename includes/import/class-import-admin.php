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
	}

	/**
	 * Render the import tab content.
	 */
	public function render_import_tab() {
		$nonce = wp_create_nonce( 'rationalseo_import' );
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

		<script type="text/javascript">
		(function($) {
			var nonce = '<?php echo esc_js( $nonce ); ?>';
			var currentImporter = null;

			// Load importers on page load.
			$(document).ready(function() {
				loadImporters();
			});

			function loadImporters() {
				$.post(ajaxurl, {
					action: 'rationalseo_get_importers',
					nonce: nonce
				}, function(response) {
					var $container = $('#rationalseo-import-sources');
					$container.empty();

					if (!response.success) {
						$container.html('<div class="notice notice-error"><p>' + escapeHtml(response.data.message) + '</p></div>');
						return;
					}

					var importers = response.data.importers;
					if (Object.keys(importers).length === 0) {
						$container.html(
							'<div class="rationalseo-import-empty">' +
								'<span class="dashicons dashicons-info"></span>' +
								'<p><?php echo esc_js( __( 'No SEO plugins with importable data detected.', 'rationalseo' ) ); ?></p>' +
								'<p class="description"><?php echo esc_js( __( 'RationalSEO can import data from Yoast SEO, RankMath, and All in One SEO when their data is present.', 'rationalseo' ) ); ?></p>' +
							'</div>'
						);
						return;
					}

					// Render importer cards.
					var html = '<div class="rationalseo-import-grid">';
					for (var slug in importers) {
						var importer = importers[slug];
						html += renderImporterCard(importer);
					}
					html += '</div>';
					$container.html(html);

					// Bind click handlers.
					$('.rationalseo-import-card-action').on('click', function() {
						var slug = $(this).data('slug');
						openImportModal(slug, importers[slug]);
					});
				}).fail(function() {
					$('#rationalseo-import-sources').html(
						'<div class="notice notice-error"><p><?php echo esc_js( __( 'Failed to load importers. Please refresh the page.', 'rationalseo' ) ); ?></p></div>'
					);
				});
			}

			function renderImporterCard(importer) {
				var itemsHtml = '';
				var totalItems = 0;

				for (var key in importer.items) {
					var item = importer.items[key];
					if (item.count > 0) {
						totalItems += item.count;
						itemsHtml += '<li><span class="dashicons dashicons-yes"></span> ' +
							escapeHtml(item.label) + ': <strong>' + item.count + '</strong></li>';
					}
				}

				if (!itemsHtml) {
					itemsHtml = '<li class="no-items"><?php echo esc_js( __( 'No data to import', 'rationalseo' ) ); ?></li>';
				}

				return '<div class="rationalseo-import-card">' +
					'<div class="rationalseo-import-card-header">' +
						'<h3>' + escapeHtml(importer.name) + '</h3>' +
					'</div>' +
					'<div class="rationalseo-import-card-body">' +
						'<p class="description">' + escapeHtml(importer.description) + '</p>' +
						'<ul class="rationalseo-import-items">' + itemsHtml + '</ul>' +
					'</div>' +
					'<div class="rationalseo-import-card-footer">' +
						(totalItems > 0 ?
							'<button type="button" class="button button-primary rationalseo-import-card-action" data-slug="' + escapeHtml(importer.slug) + '">' +
								'<?php echo esc_js( __( 'Import Data', 'rationalseo' ) ); ?>' +
							'</button>' :
							'<button type="button" class="button" disabled><?php echo esc_js( __( 'No Data', 'rationalseo' ) ); ?></button>'
						) +
					'</div>' +
				'</div>';
			}

			function openImportModal(slug, importer) {
				currentImporter = slug;
				var $modal = $('#rationalseo-import-modal');

				// Reset modal state.
				$('#rationalseo-import-modal-title').text(
					'<?php echo esc_js( __( 'Import from', 'rationalseo' ) ); ?> ' + importer.name
				);
				$('#rationalseo-import-modal-loading').show();
				$('#rationalseo-import-modal-loading-text').text('<?php echo esc_js( __( 'Loading preview...', 'rationalseo' ) ); ?>');
				$('#rationalseo-import-modal-content').hide().empty();
				$('#rationalseo-import-modal-error').removeClass('notice notice-error').hide();
				$('#rationalseo-import-modal-result').removeClass('notice notice-success').hide();
				$('#rationalseo-import-modal-confirm').hide().prop('disabled', false).text('<?php echo esc_js( __( 'Import Selected', 'rationalseo' ) ); ?>');
				$('#rationalseo-import-modal-done').hide();
				$('#rationalseo-import-modal-cancel').show();

				$modal.css('display', 'flex');

				// Load preview.
				$.post(ajaxurl, {
					action: 'rationalseo_preview_import',
					nonce: nonce,
					importer: slug
				}, function(response) {
					$('#rationalseo-import-modal-loading').hide();

					if (!response.success) {
						showModalError(response.data.message);
						return;
					}

					renderPreview(importer, response.data);
				}).fail(function() {
					$('#rationalseo-import-modal-loading').hide();
					showModalError('<?php echo esc_js( __( 'Failed to load preview. Please try again.', 'rationalseo' ) ); ?>');
				});
			}

			function renderPreview(importer, data) {
				var html = '<div class="rationalseo-import-preview">';

				// Options.
				html += '<div class="rationalseo-import-options">';
				html += '<label><input type="checkbox" id="rationalseo-import-skip-existing" checked> ';
				html += '<?php echo esc_js( __( 'Skip existing data (recommended)', 'rationalseo' ) ); ?></label>';
				html += '</div>';

				// Item type selection.
				html += '<h4><?php echo esc_js( __( 'Select data to import:', 'rationalseo' ) ); ?></h4>';
				html += '<div class="rationalseo-import-type-list">';

				var hasItems = false;
				for (var key in importer.items) {
					var item = importer.items[key];
					if (item.count > 0) {
						hasItems = true;
						html += '<label class="rationalseo-import-type-item">';
						html += '<input type="checkbox" name="import_types[]" value="' + escapeHtml(key) + '" checked>';
						html += '<span class="rationalseo-import-type-label">' + escapeHtml(item.label) + '</span>';
						html += '<span class="rationalseo-import-type-count">' + item.count + ' <?php echo esc_js( __( 'items', 'rationalseo' ) ); ?></span>';
						html += '</label>';
					}
				}

				if (!hasItems) {
					html += '<p class="no-items"><?php echo esc_js( __( 'No data available to import.', 'rationalseo' ) ); ?></p>';
				}

				html += '</div>';

				// Preview data if available.
				if (data.preview_data && Object.keys(data.preview_data).length > 0) {
					html += '<h4><?php echo esc_js( __( 'Preview:', 'rationalseo' ) ); ?></h4>';
					html += '<div class="rationalseo-import-preview-data">';

					for (var type in data.preview_data) {
						var typeData = data.preview_data[type];
						if (typeData.samples && typeData.samples.length > 0) {
							html += '<div class="rationalseo-import-preview-section">';
							html += '<h5>' + escapeHtml(typeData.label || type) + '</h5>';
							html += '<table class="widefat striped"><thead><tr>';

							// Headers.
							var firstSample = typeData.samples[0];
							for (var col in firstSample) {
								html += '<th>' + escapeHtml(col) + '</th>';
							}
							html += '</tr></thead><tbody>';

							// Rows (max 5).
							var showCount = Math.min(typeData.samples.length, 5);
							for (var i = 0; i < showCount; i++) {
								html += '<tr>';
								for (var col in typeData.samples[i]) {
									var val = typeData.samples[i][col];
									html += '<td>' + formatPreviewValue(val) + '</td>';
								}
								html += '</tr>';
							}

							if (typeData.samples.length > 5) {
								html += '<tr><td colspan="100" class="more-items">';
								html += '<?php echo esc_js( __( 'And', 'rationalseo' ) ); ?> ' + (typeData.samples.length - 5) + ' <?php echo esc_js( __( 'more...', 'rationalseo' ) ); ?>';
								html += '</td></tr>';
							}

							html += '</tbody></table></div>';
						}
					}

					html += '</div>';
				}

				html += '</div>';

				$('#rationalseo-import-modal-content').html(html).show();

				if (hasItems) {
					$('#rationalseo-import-modal-confirm').show();
				} else {
					$('#rationalseo-import-modal-done').show();
					$('#rationalseo-import-modal-cancel').hide();
				}
			}

			function showModalError(message) {
				$('#rationalseo-import-modal-error').addClass('notice notice-error').show().find('p').text(message);
				$('#rationalseo-import-modal-done').show();
				$('#rationalseo-import-modal-cancel').hide();
			}

			function showModalSuccess(message) {
				$('#rationalseo-import-modal-result').addClass('notice notice-success').show().find('p').text(message);
				$('#rationalseo-import-modal-done').show();
				$('#rationalseo-import-modal-cancel').hide();
				$('#rationalseo-import-modal-confirm').hide();
			}

			// Modal close handlers.
			$('.rationalseo-modal-close, #rationalseo-import-modal-cancel, #rationalseo-import-modal-done').on('click', function() {
				$('#rationalseo-import-modal').hide();
				currentImporter = null;
			});

			$('#rationalseo-import-modal').on('click', function(e) {
				if ($(e.target).is('#rationalseo-import-modal')) {
					$(this).hide();
					currentImporter = null;
				}
			});

			// Import confirmation.
			$('#rationalseo-import-modal-confirm').on('click', function() {
				if (!currentImporter) return;

				var selectedTypes = [];
				$('input[name="import_types[]"]:checked').each(function() {
					selectedTypes.push($(this).val());
				});

				if (selectedTypes.length === 0) {
					alert('<?php echo esc_js( __( 'Please select at least one data type to import.', 'rationalseo' ) ); ?>');
					return;
				}

				var skipExisting = $('#rationalseo-import-skip-existing').is(':checked');

				// Show loading state.
				$('#rationalseo-import-modal-content').hide();
				$('#rationalseo-import-modal-loading').show();
				$('#rationalseo-import-modal-loading-text').text('<?php echo esc_js( __( 'Importing data...', 'rationalseo' ) ); ?>');
				$('#rationalseo-import-modal-confirm').prop('disabled', true).text('<?php echo esc_js( __( 'Importing...', 'rationalseo' ) ); ?>');
				$('#rationalseo-import-modal-cancel').hide();

				$.post(ajaxurl, {
					action: 'rationalseo_run_import',
					nonce: nonce,
					importer: currentImporter,
					item_types: selectedTypes,
					skip_existing: skipExisting ? '1' : '0'
				}, function(response) {
					$('#rationalseo-import-modal-loading').hide();

					if (!response.success) {
						showModalError(response.data.message);
						return;
					}

					showModalSuccess(response.data.message);

					// Reload importers to update counts.
					loadImporters();
				}).fail(function() {
					$('#rationalseo-import-modal-loading').hide();
					showModalError('<?php echo esc_js( __( 'Import failed. Please try again.', 'rationalseo' ) ); ?>');
				});
			});

			function escapeHtml(text) {
				if (!text) return '';
				var div = document.createElement('div');
				div.appendChild(document.createTextNode(text));
				return div.innerHTML;
			}

			function formatPreviewValue(val) {
				if (val === null || val === undefined || val === '') {
					return '&mdash;';
				}

				// Handle objects (like the meta field which contains key-value pairs).
				if (typeof val === 'object' && val !== null) {
					var parts = [];
					for (var key in val) {
						if (val.hasOwnProperty(key)) {
							var label = key.replace(/^_rationalseo_/, '').replace(/_/g, ' ');
							var value = String(val[key]).substring(0, 30);
							if (String(val[key]).length > 30) {
								value += '...';
							}
							parts.push('<strong>' + escapeHtml(label) + ':</strong> ' + escapeHtml(value));
						}
					}
					return parts.length > 0 ? parts.join('<br>') : '&mdash;';
				}

				// Handle primitive values.
				var strVal = String(val);
				if (strVal.length > 50) {
					return escapeHtml(strVal.substring(0, 50)) + '...';
				}
				return escapeHtml(strVal);
			}
		})(jQuery);
		</script>
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
