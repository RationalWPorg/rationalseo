/**
 * RationalSEO Admin Settings
 *
 * Handles auto-hide of settings saved message and URL cleanup.
 *
 * @package RationalSEO
 */

jQuery( document ).ready( function( $ ) {
	// Auto-hide settings saved message after 4 seconds.
	var $msg = $( '.rationalseo-settings-saved' );
	if ( $msg.length ) {
		setTimeout( function() {
			$msg.fadeOut( 300 );
		}, 4000 );

		// Clean URL without page reload.
		var newUrl = window.location.pathname + '?page=' + rationalseoAdmin.page;
		var urlParams = new URLSearchParams( window.location.search );
		var tab = urlParams.get( 'tab' );
		if ( tab ) {
			newUrl += '&tab=' + tab;
		}
		window.history.replaceState( {}, '', newUrl );
	}
} );
