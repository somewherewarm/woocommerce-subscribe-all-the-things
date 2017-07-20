/* global wcsatt_cart_params */
jQuery( function( $ ) {

	// Ensure wcsatt_cart_params exists to continue.
	if ( typeof wcsatt_cart_params === 'undefined' ) {
		return false;
	}

	// Reload cart elements when (de-)selecting a cart subscription option.
	$( document ).on( 'change', '.wcsatt-options-cart input[type=radio][name^=convert_to_sub]', function() {

		var $cart_totals    = $( 'div.cart_totals' ),
			$cart_table     = $( 'table.shop_table.cart' ),
			$cart_wrapper   = $cart_table.closest( '.woocommerce' ),
			selected_option = $( this ).val(),
			cart_referrer   = $cart_table.find( 'input[name="_wp_http_referer"]' ).val();

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

			var $response        = $( $.parseHTML( response ) ),
				$response_totals = $response.find( 'div.cart_totals' ),
				$response_table  = $response.find( 'table.shop_table.cart' );

			if ( cart_referrer ) {
				$response_table.find( 'input[name="_wp_http_referer"]' ).val( cart_referrer );
			}

			$response_table.find( 'input[name="update_cart"]' ).prop( 'disabled', true );

			$cart_totals.replaceWith( $response_totals );
			$cart_table.replaceWith( $response_table );

			$cart_wrapper.trigger( 'wcsatt_updated_cart' );
			$cart_wrapper.unblock();
		} );
	} );
} );
