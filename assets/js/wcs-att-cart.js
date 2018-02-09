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
			$options        = $( this ).closest( '.wcsatt-options-cart' ),
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
			security:            wcsatt_cart_params.update_cart_option_nonce,
			subscription_scheme: selected_scheme,
			action:              'wcsatt_update_cart_option'
		};

		$.post( wcsatt_cart_params.wc_ajax_url.toString().replace( '%%endpoint%%', data.action ), data, function( response ) {

			if ( 'success' === response.result ) {

				var $html        = $( $.parseHTML( response.html ) ),
					$html_totals = $html.find( 'div.cart_totals' ),
					$html_table  = $html.find( 'table.shop_table.cart' );

				if ( cart_referrer ) {
					$html_table.find( 'input[name="_wp_http_referer"]' ).val( cart_referrer );
				}

				$html_table.find( 'input[name="update_cart"]' ).prop( 'disabled', true );

				$cart_totals.replaceWith( $html_totals );
				$cart_table.replaceWith( $html_table );

				$cart_wrapper.trigger( 'wcsatt_updated_cart' );

			} else {

				window.alert( wcsatt_cart_params.i18n_update_cart_sub_error );
				$options.find( 'input[value="' + response.reset_to_scheme + '"]' ).prop( 'checked', true );
			}

			$cart_wrapper.unblock();

		} );
	} );
} );
