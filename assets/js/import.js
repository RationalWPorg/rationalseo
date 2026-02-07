/**
 * RationalSEO Import functionality.
 *
 * @package RationalSEO
 */

(function($) {
	var nonce = rationalseoImport.nonce;
	var i18n = rationalseoImport.i18n;
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
						'<p>' + escapeHtml(i18n.noPluginsDetected) + '</p>' +
						'<p class="description">' + escapeHtml(i18n.canImportFrom) + '</p>' +
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
				'<div class="notice notice-error"><p>' + escapeHtml(i18n.failedToLoad) + '</p></div>'
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
			itemsHtml = '<li class="no-items">' + escapeHtml(i18n.noDataToImport) + '</li>';
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
						escapeHtml(i18n.importData) +
					'</button>' :
					'<button type="button" class="button" disabled>' + escapeHtml(i18n.noData) + '</button>'
				) +
			'</div>' +
		'</div>';
	}

	function openImportModal(slug, importer) {
		currentImporter = slug;
		var $modal = $('#rationalseo-import-modal');

		// Reset modal state.
		$('#rationalseo-import-modal-title').text(
			i18n.importFrom + ' ' + importer.name
		);
		$('#rationalseo-import-modal-loading').show();
		$('#rationalseo-import-modal-loading-text').text(i18n.loadingPreview);
		$('#rationalseo-import-modal-content').hide().empty();
		$('#rationalseo-import-modal-error').removeClass('notice notice-error').hide();
		$('#rationalseo-import-modal-result').removeClass('notice notice-success').hide();
		$('#rationalseo-import-modal-confirm').hide().prop('disabled', false).text(i18n.importSelected);
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
			showModalError(i18n.failedToLoadPreview);
		});
	}

	function renderPreview(importer, data) {
		var html = '<div class="rationalseo-import-preview">';

		// Options.
		html += '<div class="rationalseo-import-options">';
		html += '<label><input type="checkbox" id="rationalseo-import-skip-existing" checked> ';
		html += escapeHtml(i18n.skipExisting) + '</label>';
		html += '</div>';

		// Item type selection.
		html += '<h4>' + escapeHtml(i18n.selectDataToImport) + '</h4>';
		html += '<div class="rationalseo-import-type-list">';

		var hasItems = false;
		for (var key in importer.items) {
			var item = importer.items[key];
			if (item.count > 0) {
				hasItems = true;
				html += '<label class="rationalseo-import-type-item">';
				html += '<input type="checkbox" name="import_types[]" value="' + escapeHtml(key) + '" checked>';
				html += '<span class="rationalseo-import-type-label">' + escapeHtml(item.label) + '</span>';
				html += '<span class="rationalseo-import-type-count">' + item.count + ' ' + escapeHtml(i18n.items) + '</span>';
				html += '</label>';
			}
		}

		if (!hasItems) {
			html += '<p class="no-items">' + escapeHtml(i18n.noDataAvailable) + '</p>';
		}

		html += '</div>';

		// Preview data if available.
		if (data.preview_data && Object.keys(data.preview_data).length > 0) {
			html += '<h4>' + escapeHtml(i18n.preview) + '</h4>';
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
						html += escapeHtml(i18n.and) + ' ' + (typeData.samples.length - 5) + ' ' + escapeHtml(i18n.more);
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
			alert(i18n.selectAtLeastOne);
			return;
		}

		var skipExisting = $('#rationalseo-import-skip-existing').is(':checked');

		// Show loading state.
		$('#rationalseo-import-modal-content').hide();
		$('#rationalseo-import-modal-loading').show();
		$('#rationalseo-import-modal-loading-text').text(i18n.importingData);
		$('#rationalseo-import-modal-confirm').prop('disabled', true).text(i18n.importing);
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
			showModalError(i18n.importFailed);
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
