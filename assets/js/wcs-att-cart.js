/* global wcsatt_cart_params */
jQuery( function( $ ) {

	// Ensure wcsatt_cart_params exists to continue.
	if ( typeof wcsatt_cart_params === 'undefined' ) {
		return false;
	}

	var $document = $( document );

	// Reload cart elements when (de-)selecting a cart subscription option.
	$document.on( 'change', '.wcsatt-options-cart [name^=convert_to_sub]', function() {

		var $scheme_input        = $( this ),
			$cart_totals         = $( 'div.cart_totals' ),
			$cart_table          = $( 'table.shop_table.cart' ),
			$options             = $scheme_input.closest( '.wcsatt-options-cart' ),
			$cart_wrapper        = $cart_table.closest( '.woocommerce' ),
			$add_to_subscription = $cart_totals.find( 'input.wcsatt-add-cart-to-subscription-action-input' ),
			selected_scheme      = $scheme_input.val(),
			cart_referrer        = $cart_table.find( 'input[name="_wp_http_referer"]' ).val();

		$cart_wrapper.block( {
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6
			}
		} );

		var data = {
			security:                    wcsatt_cart_params.update_cart_option_nonce,
			subscription_scheme:         selected_scheme,
			add_to_subscription_checked: $add_to_subscription.is( ':checked' ) ? 'yes' : 'no',
			action:                      'wcsatt_update_cart_option'
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

				if ( response.reset_to_scheme ) {
					if ( $scheme_input.is( ':radio' ) ) {
						$options.find( 'input[value="' + response.reset_to_scheme + '"]' ).prop( 'checked', true );
					} else {
						$scheme_input.val( response.reset_to_scheme );
					}
				}
			}

			$cart_wrapper.unblock();

		} );
	} );

	// Load matching subscription schemes when checking the "Add to subscription" box.
	$document.on( 'change', '.wcsatt-add-cart-to-subscription-action-input', function() {

		var $cart_totals                 = $( 'div.cart_totals' ),
			$add_to_subscription         = $( this ),
			$add_to_subscription_wrapper = $add_to_subscription.closest( '.wcsatt-add-cart-to-subscription-wrapper' ),
			$add_to_subscription_options = $add_to_subscription_wrapper.find( '.wcsatt-add-cart-to-subscription-options' ),
			$scheme_input                = $cart_totals.find( '.wcsatt-options-cart [name^=convert_to_sub]' ),
			is_checked                   = $add_to_subscription.is( ':checked' ),
			selected_scheme              = $scheme_input.val();

		if ( is_checked ) {

			$add_to_subscription_wrapper.block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			} );

			var data = {
				subscription_scheme:         selected_scheme,
				add_to_subscription_checked: $add_to_subscription.is( ':checked' ) ? 'yes' : 'no',
				action:                      'wcsatt_load_subscriptions_matching_cart'
			};

			$.post( wcsatt_cart_params.wc_ajax_url.toString().replace( '%%endpoint%%', data.action ), data, function( response ) {

				if ( 'success' === response.result ) {

					$add_to_subscription_options.html( response.html );
					$add_to_subscription_wrapper.removeClass( 'closed' );
					$add_to_subscription_wrapper.addClass( 'open' );
					$add_to_subscription_options.slideDown( 200 );

				} else {

					window.alert( wcsatt_cart_params.i18n_subs_load_error );
				}

				$add_to_subscription_wrapper.unblock();

			} );

		} else {

			$add_to_subscription_wrapper.removeClass( 'open' );
			$add_to_subscription_wrapper.addClass( 'closed' );
			$add_to_subscription_options.slideUp( 200 );
		}

	} );

} );
