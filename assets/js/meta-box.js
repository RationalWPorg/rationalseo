/**
 * RationalSEO Meta Box JavaScript
 *
 * Handles focus keyword presence indicators in the post editor.
 *
 * @package RationalSEO
 */

( function() {
	'use strict';

	var config = window.rationalseoMetaBox || {};
	var debounceTimer = null;
	var DEBOUNCE_DELAY = 300;

	/**
	 * Check if keyword is present in text (case-insensitive).
	 *
	 * @param {string} text    The text to search in.
	 * @param {string} keyword The keyword to search for.
	 * @return {boolean} True if keyword is found.
	 */
	function keywordPresent( text, keyword ) {
		if ( ! text || ! keyword ) {
			return false;
		}
		return text.toLowerCase().indexOf( keyword.toLowerCase() ) !== -1;
	}

	/**
	 * Get content from the editor (Classic or Gutenberg).
	 *
	 * @return {string} The post content.
	 */
	function getEditorContent() {
		// Try Gutenberg first.
		if ( window.wp && window.wp.data && window.wp.data.select( 'core/editor' ) ) {
			var content = window.wp.data.select( 'core/editor' ).getEditedPostContent();
			if ( content ) {
				return content;
			}
		}

		// Fall back to Classic Editor.
		var contentField = document.getElementById( 'content' );
		if ( contentField ) {
			return contentField.value || '';
		}

		return '';
	}

	/**
	 * Extract the first paragraph from content.
	 *
	 * @param {string} content The full content.
	 * @return {string} The first paragraph text.
	 */
	function getFirstParagraph( content ) {
		if ( ! content ) {
			return '';
		}

		// Strip HTML tags and get text.
		var tempDiv = document.createElement( 'div' );
		tempDiv.innerHTML = content;

		// Try to find first paragraph element.
		var firstP = tempDiv.querySelector( 'p' );
		if ( firstP && firstP.textContent.trim() ) {
			return firstP.textContent.trim();
		}

		// Fall back to first non-empty text block.
		var text = tempDiv.textContent || tempDiv.innerText || '';
		var paragraphs = text.split( /\n\n+/ );
		for ( var i = 0; i < paragraphs.length; i++ ) {
			var trimmed = paragraphs[ i ].trim();
			if ( trimmed ) {
				return trimmed;
			}
		}

		return '';
	}

	/**
	 * Get the post slug/URL.
	 *
	 * @return {string} The post slug.
	 */
	function getPostSlug() {
		// Try Gutenberg first.
		if ( window.wp && window.wp.data && window.wp.data.select( 'core/editor' ) ) {
			var slug = window.wp.data.select( 'core/editor' ).getEditedPostAttribute( 'slug' );
			if ( slug ) {
				return slug;
			}
		}

		// Fall back to Classic Editor slug field.
		var slugField = document.getElementById( 'post_name' );
		if ( slugField ) {
			return slugField.value || '';
		}

		return '';
	}

	/**
	 * Update all keyword presence indicators.
	 */
	function updateChecks() {
		var container = document.getElementById( 'rationalseo-keyword-checks' );
		if ( ! container ) {
			return;
		}

		var keywordField = document.getElementById( config.keywordId );
		var keyword = keywordField ? keywordField.value.trim() : '';

		// Hide container if no keyword.
		if ( ! keyword ) {
			container.style.display = 'none';
			return;
		}

		container.style.display = 'block';

		// Get values to check.
		var titleField = document.getElementById( config.titleId );
		var descField = document.getElementById( config.descId );

		var seoTitle = titleField ? titleField.value : '';
		var metaDesc = descField ? descField.value : '';
		var content = getEditorContent();
		var firstParagraph = getFirstParagraph( content );
		var slug = getPostSlug();

		// Update each indicator.
		updateIndicator( 'rationalseo-check-title', keywordPresent( seoTitle, keyword ) );
		updateIndicator( 'rationalseo-check-desc', keywordPresent( metaDesc, keyword ) );
		updateIndicator( 'rationalseo-check-first-paragraph', keywordPresent( firstParagraph, keyword ) );
		updateIndicator( 'rationalseo-check-slug', keywordPresent( slug, keyword ) );
	}

	/**
	 * Update a single indicator element.
	 *
	 * @param {string}  id        The element ID.
	 * @param {boolean} isPresent Whether the keyword is present.
	 */
	function updateIndicator( id, isPresent ) {
		var element = document.getElementById( id );
		if ( ! element ) {
			return;
		}

		element.classList.remove( 'is-present', 'is-missing' );
		element.classList.add( isPresent ? 'is-present' : 'is-missing' );
	}

	/**
	 * Debounced update function.
	 */
	function debouncedUpdate() {
		clearTimeout( debounceTimer );
		debounceTimer = setTimeout( updateChecks, DEBOUNCE_DELAY );
	}

	/**
	 * Initialize event listeners.
	 */
	function init() {
		// Wait for DOM ready.
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', init );
			return;
		}

		// Verify config exists.
		if ( ! config.keywordId ) {
			return;
		}

		// Get input fields.
		var keywordField = document.getElementById( config.keywordId );
		var titleField = document.getElementById( config.titleId );
		var descField = document.getElementById( config.descId );
		var contentField = document.getElementById( 'content' );

		// Attach input listeners.
		if ( keywordField ) {
			keywordField.addEventListener( 'input', debouncedUpdate );
		}
		if ( titleField ) {
			titleField.addEventListener( 'input', debouncedUpdate );
		}
		if ( descField ) {
			descField.addEventListener( 'input', debouncedUpdate );
		}
		if ( contentField ) {
			contentField.addEventListener( 'input', debouncedUpdate );
		}

		// Listen for Gutenberg changes.
		if ( window.wp && window.wp.data && window.wp.data.subscribe ) {
			var lastContent = '';
			var lastSlug = '';

			window.wp.data.subscribe( function() {
				var editor = window.wp.data.select( 'core/editor' );
				if ( ! editor ) {
					return;
				}

				var currentContent = editor.getEditedPostContent() || '';
				var currentSlug = editor.getEditedPostAttribute( 'slug' ) || '';

				if ( currentContent !== lastContent || currentSlug !== lastSlug ) {
					lastContent = currentContent;
					lastSlug = currentSlug;
					debouncedUpdate();
				}
			} );
		}

		// Classic Editor slug field.
		var slugField = document.getElementById( 'post_name' );
		if ( slugField ) {
			slugField.addEventListener( 'input', debouncedUpdate );
		}

		// Initial check.
		updateChecks();
	}

	// Start initialization.
	init();

} )();
