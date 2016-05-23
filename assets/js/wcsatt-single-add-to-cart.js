;( function( $, window, document ) {

	$( '.bundle_form .bundle_data' ).each( function() {

		function bundle_init( bundle ) {

			bundle.satt_schemes = [];

			// Move options after price.
			var $satt_options = bundle.$bundle_data.find( '.wcsatt-options-wrapper' );

			if ( $satt_options.length > 0 ) {
				if ( bundle.$addons_totals !== false ) {
					bundle.$addons_totals.after( $satt_options );
				} else {
					bundle.$bundle_price.after( $satt_options );
				}
			}

			// Store scheme data for options that override the default prices.
			var $scheme_options = bundle.$bundle_wrap.find( '.wcsatt-options-product .subscription-option' );

			$.each( $scheme_options, function( index, scheme_option ) {
				var $scheme_option = $( this ),
					scheme_data    = $( this ).find( 'input' ).data( 'custom_data' );

				if ( scheme_data[ 'overrides_price' ] === true ) {
					bundle.satt_schemes.push( { el: $scheme_option, data: scheme_data } )
				}
			} );
		}

		// Store subscription scheme data.
		$( this ).on( 'woocommerce-product-bundle-initializing', function( event, bundle ) {
			if ( ! bundle.is_composited() ) {
				bundle_init( bundle );
			}
		} );

		// Store subscription scheme data.
		$( this ).on( 'woocommerce-product-bundle-updated-totals', function( event, bundle ) {
			if ( ! bundle.is_composited() && bundle.satt_schemes.length > 0 ) {

				$.each( bundle.satt_schemes, function( index, scheme ) {

					var price_data = $.extend( true, {}, bundle.price_data );

					if ( scheme.data.subscription_scheme.subscription_pricing_method === 'inherit' && scheme.data.subscription_scheme.subscription_discount > 0 ) {

						$.each( bundle.bundled_items, function( index, bundled_item ) {
							var bundled_item_id = bundled_item.bundled_item_id;

							if ( scheme.data.discount_from_regular ) {
								price_data[ 'prices' ][ bundled_item_id ] = price_data[ 'regular_prices' ][ bundled_item_id ] * scheme.data.subscription_scheme.subscription_discount / 100;
							} else {
								price_data[ 'prices' ][ bundled_item_id ] = price_data[ 'prices' ][ bundled_item_id ] * scheme.data.subscription_scheme.subscription_discount / 100;
							}
							price_data[ 'addons_prices' ][ bundled_item_id ] = price_data[ 'addons_prices' ][ bundled_item_id ] * scheme.data.subscription_scheme.subscription_discount / 100;
						} );

						price_data[ 'base_price' ] = price_data[ 'base_price' ] * scheme.data.subscription_scheme.subscription_discount / 100;

					} else if ( scheme.data.subscription_scheme.subscription_pricing_method === 'override' ) {
						price_data[ 'base_regular_price' ] = Number( scheme.data.subscription_scheme.subscription_regular_price );
						price_data[ 'base_price' ]         = Number( scheme.data.subscription_scheme.subscription_price );
					}

					price_data        = bundle.calculate_subtotals( false, price_data );
					price_data        = bundle.calculate_totals( price_data );
					scheme_price_html = bundle.get_price_html( price_data );

					scheme.el.find( '.subscription-price' ).html( $( scheme_price_html ).html() ).find( 'span.total' ).remove();
				} );
			}
		} );

	} );

} ) ( jQuery, window, document );
