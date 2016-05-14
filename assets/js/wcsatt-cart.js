/* global wcsatt_cart_params */
jQuery( function( $ ) {

	// wcsatt_cart_params is required to continue, ensure the object exists
	if ( typeof wcsatt_cart_params === 'undefined' ) {
		return false;
	}

	// Shipping calculator
	$( document ).on( 'change', '.wcsatt-options-cart input[type=radio][name^=convert_to_sub]', function() {

		var selected_option = $(this).val();
		var $cart_totals    = $( 'div.cart_totals' );
		var $cart_table     = $( 'table.shop_table.cart' );
		var $cart_wrapper   = $cart_table.closest( '.woocommerce' );

		$cart_wrapper.block( {
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6
			}
		} );

		var data = {
			security:        wcsatt_cart_params.update_cart_option_nonce,
			selected_scheme: selected_option,
			action:          'wcsatt_update_cart_option'
		};

		$.post( wcsatt_cart_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'wcsatt_update_cart_option' ), data, function( response ) {

			var $response        = $( $.parseHTML( response ) );
			var $response_totals = $response.find( 'div.cart_totals' );
			var $response_table  = $response.find( 'table.shop_table.cart' );

			$cart_totals.replaceWith( $response_totals );
			$cart_table.replaceWith( $response_table );

			$cart_wrapper.trigger( 'wcsatt_updated_cart' );
			$cart_wrapper.unblock();
		} );
	} );
} );
