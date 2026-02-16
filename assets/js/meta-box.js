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
	 * Show or hide AI buttons based on API key availability.
	 */
	function maybeShowAI() {
		if ( ! config.hasApiKey ) {
			return;
		}

		var suggestBtn = document.getElementById( 'rationalseo-suggest-keyword' );
		var generateDescBtn = document.getElementById( 'rationalseo-generate-description' );
		var generateTitleBtn = document.getElementById( 'rationalseo-generate-title' );
		var suggestAllWrapper = document.querySelector( '.rationalseo-suggest-all-wrapper' );

		if ( suggestBtn ) {
			suggestBtn.style.display = '';
		}
		if ( generateDescBtn ) {
			generateDescBtn.style.display = '';
		}
		if ( generateTitleBtn ) {
			generateTitleBtn.style.display = '';
		}
		if ( suggestAllWrapper ) {
			suggestAllWrapper.style.display = '';
		}
	}

	/**
	 * Set loading state on a button.
	 *
	 * @param {HTMLElement} button    The button element.
	 * @param {boolean}     isLoading Whether to show loading state.
	 */
	function setButtonLoading( button, isLoading ) {
		if ( ! button ) {
			return;
		}

		button.disabled = isLoading;
		if ( isLoading ) {
			button.classList.add( 'is-loading' );
		} else {
			button.classList.remove( 'is-loading' );
		}
	}

	/**
	 * Get the post title.
	 *
	 * @return {string} The post title.
	 */
	function getPostTitle() {
		// Try Gutenberg first.
		if ( window.wp && window.wp.data && window.wp.data.select( 'core/editor' ) ) {
			var title = window.wp.data.select( 'core/editor' ).getEditedPostAttribute( 'title' );
			if ( title ) {
				return title;
			}
		}

		// Fall back to Classic Editor.
		var titleField = document.getElementById( 'title' );
		if ( titleField ) {
			return titleField.value || '';
		}

		return '';
	}

	/**
	 * Suggest a focus keyword using AI.
	 */
	function suggestKeyword() {
		var button = document.getElementById( 'rationalseo-suggest-keyword' );
		var keywordField = document.getElementById( config.keywordId );

		if ( ! button || ! keywordField ) {
			return;
		}

		var content = getEditorContent();
		var title = getPostTitle();

		if ( ! content && ! title ) {
			alert( 'Please add some content or a title first.' );
			return;
		}

		setButtonLoading( button, true );

		var formData = new FormData();
		formData.append( 'action', 'rationalseo_suggest_keyword' );
		formData.append( 'nonce', config.nonce );
		formData.append( 'content', content );
		formData.append( 'title', title );

		fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		} )
		.then( function( response ) {
			return response.json();
		} )
		.then( function( data ) {
			setButtonLoading( button, false );

			if ( data.success && data.data.keyword ) {
				keywordField.value = data.data.keyword;
				// Trigger input event to update indicators.
				keywordField.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			} else {
				var message = data.data && data.data.message ? data.data.message : 'Failed to suggest keyword.';
				alert( message );
			}
		} )
		.catch( function( error ) {
			setButtonLoading( button, false );
			alert( 'An error occurred. Please try again.' );
		} );
	}

	/**
	 * Generate a meta description using AI.
	 */
	function generateDescription() {
		var button = document.getElementById( 'rationalseo-generate-description' );
		var descField = document.getElementById( config.descId );
		var keywordField = document.getElementById( config.keywordId );

		if ( ! button || ! descField ) {
			return;
		}

		var content = getEditorContent();
		var title = getPostTitle();
		var keyword = keywordField ? keywordField.value.trim() : '';

		if ( ! content && ! title ) {
			alert( 'Please add some content or a title first.' );
			return;
		}

		setButtonLoading( button, true );

		var formData = new FormData();
		formData.append( 'action', 'rationalseo_generate_description' );
		formData.append( 'nonce', config.nonce );
		formData.append( 'content', content );
		formData.append( 'title', title );
		formData.append( 'keyword', keyword );

		fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		} )
		.then( function( response ) {
			return response.json();
		} )
		.then( function( data ) {
			setButtonLoading( button, false );

			if ( data.success && data.data.description ) {
				descField.value = data.data.description;
				// Trigger input event to update indicators.
				descField.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			} else {
				var message = data.data && data.data.message ? data.data.message : 'Failed to generate description.';
				alert( message );
			}
		} )
		.catch( function( error ) {
			setButtonLoading( button, false );
			alert( 'An error occurred. Please try again.' );
		} );
	}

	/**
	 * Generate an SEO title using AI.
	 */
	function generateTitle() {
		var button = document.getElementById( 'rationalseo-generate-title' );
		var titleField = document.getElementById( config.titleId );
		var keywordField = document.getElementById( config.keywordId );

		if ( ! button || ! titleField ) {
			return;
		}

		var content = getEditorContent();
		var title = getPostTitle();
		var keyword = keywordField ? keywordField.value.trim() : '';

		if ( ! content && ! title ) {
			alert( 'Please add some content or a title first.' );
			return;
		}

		setButtonLoading( button, true );

		var formData = new FormData();
		formData.append( 'action', 'rationalseo_generate_title' );
		formData.append( 'nonce', config.nonce );
		formData.append( 'content', content );
		formData.append( 'title', title );
		formData.append( 'keyword', keyword );

		fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		} )
		.then( function( response ) {
			return response.json();
		} )
		.then( function( data ) {
			setButtonLoading( button, false );

			if ( data.success && data.data.title ) {
				titleField.value = data.data.title;
				titleField.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			} else {
				var message = data.data && data.data.message ? data.data.message : 'Failed to generate title.';
				alert( message );
			}
		} )
		.catch( function() {
			setButtonLoading( button, false );
			alert( 'An error occurred. Please try again.' );
		} );
	}

	/**
	 * Suggest keyword, title, and description together using AI.
	 */
	function suggestAll() {
		var button = document.getElementById( 'rationalseo-suggest-all' );
		var keywordField = document.getElementById( config.keywordId );
		var titleField = document.getElementById( config.titleId );
		var descField = document.getElementById( config.descId );

		if ( ! button || ! keywordField || ! titleField || ! descField ) {
			return;
		}

		var content = getEditorContent();
		var title = getPostTitle();
		var keyword = keywordField ? keywordField.value.trim() : '';

		if ( ! content && ! title ) {
			alert( 'Please add some content or a title first.' );
			return;
		}

		setButtonLoading( button, true );

		var formData = new FormData();
		formData.append( 'action', 'rationalseo_suggest_all' );
		formData.append( 'nonce', config.nonce );
		formData.append( 'content', content );
		formData.append( 'title', title );
		formData.append( 'keyword', keyword );

		fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		} )
		.then( function( response ) {
			return response.json();
		} )
		.then( function( data ) {
			setButtonLoading( button, false );

			if ( data.success && data.data.keyword && data.data.title && data.data.description ) {
				keywordField.value = data.data.keyword;
				titleField.value = data.data.title;
				descField.value = data.data.description;

				keywordField.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				titleField.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				descField.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			} else {
				var message = data.data && data.data.message ? data.data.message : 'Failed to generate suggestions.';
				alert( message );
			}
		} )
		.catch( function() {
			setButtonLoading( button, false );
			alert( 'An error occurred. Please try again.' );
		} );
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

		// Initialize AI buttons.
		maybeShowAI();

		var suggestBtn = document.getElementById( 'rationalseo-suggest-keyword' );
		var generateDescBtn = document.getElementById( 'rationalseo-generate-description' );
		var generateTitleBtn = document.getElementById( 'rationalseo-generate-title' );
		var suggestAllBtn = document.getElementById( 'rationalseo-suggest-all' );

		if ( suggestBtn ) {
			suggestBtn.addEventListener( 'click', suggestKeyword );
		}
		if ( generateDescBtn ) {
			generateDescBtn.addEventListener( 'click', generateDescription );
		}
		if ( generateTitleBtn ) {
			generateTitleBtn.addEventListener( 'click', generateTitle );
		}
		if ( suggestAllBtn ) {
			suggestAllBtn.addEventListener( 'click', suggestAll );
		}

		// Initial check.
		updateChecks();
	}

	// Start initialization.
	init();

} )();
