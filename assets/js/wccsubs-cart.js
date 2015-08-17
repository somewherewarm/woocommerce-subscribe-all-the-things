/* global wccsubs_cart_params */
jQuery( function( $ ) {

	// wccsubs_cart_params is required to continue, ensure the object exists
	if ( typeof wccsubs_cart_params === 'undefined' ) {
		return false;
	}

	// Shipping calculator
	$( document ).on( 'change', '.wccsubs-convert-cart input[type=radio][name^=convert_to_sub]', function() {

		var selected_option = $(this).val();

		$( 'div.cart_totals' ).block( {
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6
			}
		} );

		var data = {
			security:        wccsubs_cart_params.update_cart_option_nonce,
			selected_scheme: selected_option,
			action:          'wccsubs_update_cart_option'
		};

		$.post( wccsubs_cart_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'wccsubs_update_cart_option' ), data, function( response ) {
			$( 'div.cart_totals' ).replaceWith( response );
		} );
	} );
} );
