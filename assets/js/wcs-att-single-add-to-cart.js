;( function( $ ) {

	var PB_Integration = function( bundle ) {

		var self = this;

		// Moves SATT options after the price.
		this.initialize_ui = function() {

			var $satt_options = bundle.$bundle_data.find( '.wcsatt-options-wrapper' );

			if ( $satt_options.length > 0 ) {
				if ( bundle.$addons_totals !== false ) {
					bundle.$addons_totals.after( $satt_options );
				} else {
					bundle.$bundle_price.after( $satt_options );
				}
			}
		};

		// Scans for SATT schemes attached on the Bundle.
		this.initialize_schemes = function() {

			bundle.satt_schemes = [];

			// Store scheme data for options that override the default prices.
			var $scheme_options = bundle.$bundle_wrap.find( '.wcsatt-options-product .subscription-option' );

			$.each( $scheme_options, function( index, scheme_option ) {

				var $scheme_option = $( this ),
					scheme_data    = $( this ).find( 'input' ).data( 'custom_data' );

				bundle.satt_schemes.push( { el: $scheme_option, data: scheme_data, price_html: $scheme_option.find( '.subscription-price' ).html(), details_html: $( '<div>' ).html( $scheme_option.find( '.subscription-details' ) ).html() } );
			} );
		};

		// Init.
		this.integrate = function() {

			self.initialize_ui();
			self.initialize_schemes();

			bundle.$bundle_data.on( 'woocommerce-product-bundle-updated-totals', self.update_subscription_totals );
			bundle.$bundle_data.on( 'woocommerce-product-bundle-validation-status-changed', self.update_subscription_totals );
		};

		// Update totals displayed in SATT options.
		this.update_subscription_totals = function( event, bundle ) {

			if ( bundle.satt_schemes.length > 0 ) {

				$.each( bundle.satt_schemes, function( index, scheme ) {

					var scheme_price_html = bundle.get_price_html();

					// If only a single option is present, then bundle prices are already overridden on the server side.
					// In this case, simply grab the subscription details from the option and append them to the bundle price string.
					if ( bundle.satt_schemes.length === 1 && bundle.$bundle_wrap.find( '.wcsatt-options-product .one-time-option' ).length === 0 ) {

						bundle.$bundle_price.find( '.price' ).html( $( scheme_price_html ).html() + scheme.details_html );

					// If multiple options are present, then calculate the subscription price for each option that overrides default prices and update its html string.
					} else {

						var $scheme_price = scheme.el.find( '.subscription-price' );

						if ( scheme.data.overrides_price === true ) {

							var price_data = $.extend( true, {}, bundle.price_data );

							if ( scheme.data.subscription_scheme.pricing_mode === 'inherit' && scheme.data.subscription_scheme.discount > 0 ) {

								$.each( bundle.bundled_items, function( index, bundled_item ) {
									var bundled_item_id = bundled_item.bundled_item_id;

									if ( scheme.data.discount_from_regular ) {
										price_data.prices[ bundled_item_id ] = price_data.regular_prices[ bundled_item_id ] * ( 1 - scheme.data.subscription_scheme.discount / 100 );
									} else {
										price_data.prices[ bundled_item_id ] = price_data.prices[ bundled_item_id ] * ( 1 - scheme.data.subscription_scheme.discount / 100 );
									}
									price_data.addons_prices[ bundled_item_id ] = price_data.addons_prices[ bundled_item_id ] * ( 1 - scheme.data.subscription_scheme.discount / 100 );
								} );

								price_data.base_price = price_data.base_price * ( 1 - scheme.data.subscription_scheme.discount / 100 );

							} else if ( scheme.data.subscription_scheme.pricing_mode === 'override' ) {
								price_data.base_regular_price = Number( scheme.data.subscription_scheme.regular_price );
								price_data.base_price         = Number( scheme.data.subscription_scheme.price );
							}

							price_data = bundle.calculate_subtotals( false, price_data );
							price_data = bundle.calculate_totals( price_data );

							scheme_price_html = bundle.get_price_html( price_data );
						}

						if ( bundle.passes_validation() ) {
							$scheme_price.html( $( scheme_price_html ).html() + scheme.details_html ).find( 'span.total' ).remove();
						} else {
							$scheme_price.html( scheme.price_html );
						}

						$scheme_price.trigger( 'wcsatt-updated-bundle-price', [ scheme_price_html, scheme, bundle, self ] );
					}
				} );
			}
		};

		// Lights on.
		this.integrate();
	};

	var CP_Integration = function( composite ) {

		var self = this;

		// Moves SATT options after the price.
		this.initialize_ui = function() {

			var $satt_options = composite.$composite_data.find( '.wcsatt-options-wrapper' );

			if ( $satt_options.length > 0 ) {
				if ( composite.composite_price_view.$addons_totals !== false ) {
					composite.composite_price_view.$addons_totals.after( $satt_options );
				} else {
					composite.$composite_price.after( $satt_options );
				}
			}
		};

		// Scans for SATT schemes attached on the Composite.
		this.initialize_schemes = function() {

			composite.satt_schemes = [];

			// Store scheme data for options that override the default prices.
			var $scheme_options = composite.$composite_data.find( '.wcsatt-options-product .subscription-option' );

			$.each( $scheme_options, function( index, scheme_option ) {

				var $scheme_option = $( this ),
					scheme_data    = $( this ).find( 'input' ).data( 'custom_data' );

				composite.satt_schemes.push( { el: $scheme_option, data: scheme_data, price_html: $scheme_option.find( '.subscription-price' ).html(), details_html: $( '<div>' ).html( $scheme_option.find( '.subscription-details' ) ).html() } );
			} );
		};

		// Init.
		this.integrate = function() {

			self.initialize_schemes();

			composite.actions.add_action( 'initialize_composite', function() {
				self.initialize_ui();

				if ( composite.satt_schemes.length > 0 ) {
					if ( composite.satt_schemes.length > 1 || composite.$composite_data.find( '.composite_wrap .wcsatt-options-product .one-time-option' ).length > 0 ) {
						composite.actions.add_action( 'composite_totals_changed', self.update_subscription_totals, 101, self );
						composite.actions.add_action( 'composite_validation_status_changed', self.update_subscription_totals, 101, self );
					} else {
						composite.filters.add_filter( 'composite_price_html', self.filter_price_html, 10, self );
					}
				}
			}, 51, this );
		};

		// If only a single option is present, then composite prices are already overridden on the server side.
		// In this case, simply grab the subscription details from the option and append them to the composite price string.
		this.filter_price_html = function( price, view, price_data ) {

			var scheme_details_html = composite.satt_schemes[0].details_html;

			price = $( price ).append( scheme_details_html ).prop( 'outerHTML' );

			return price;
		};

		// Update totals displayed in SATT options.
		this.update_subscription_totals = function() {

			if ( composite.satt_schemes.length > 0 ) {

				$.each( composite.satt_schemes, function( index, scheme ) {

					var scheme_price_html = composite.composite_price_view.get_price_html();
						$scheme_price     = scheme.el.find( '.subscription-price' );

					// Calculate the subscription price for each option that overrides default prices and update its html string.
					if ( scheme.data.overrides_price === true ) {

						var price_data = $.extend( true, {}, composite.data_model.price_data );

						if ( scheme.data.subscription_scheme.pricing_mode === 'inherit' && scheme.data.subscription_scheme.discount > 0 ) {

							$.each( composite.get_components(), function( index, component ) {
								var component_id = component.component_id;

								if ( scheme.data.discount_from_regular ) {
									price_data.prices[ component_id ] = price_data.regular_prices[ component_id ] * ( 1 - scheme.data.subscription_scheme.discount / 100 );
								} else {
									price_data.prices[ component_id ] = price_data.prices[ component_id ] * ( 1 - scheme.data.subscription_scheme.discount / 100 );
								}
								price_data.addons_prices[ component_id ] = price_data.addons_prices[ component_id ] * ( 1 - scheme.data.subscription_scheme.discount / 100 );
							} );

							price_data.base_price = price_data.base_price * ( 1 - scheme.data.subscription_scheme.discount / 100 );

						} else if ( scheme.data.subscription_scheme.pricing_mode === 'override' ) {
							price_data.base_regular_price = Number( scheme.data.subscription_scheme.regular_price );
							price_data.base_price         = Number( scheme.data.subscription_scheme.price );
						}

						price_data = composite.data_model.calculate_subtotals( false, price_data );

						var totals = composite.data_model.calculate_totals( price_data );

						price_data.totals = totals;
						scheme_price_html = composite.composite_price_view.get_price_html( price_data );
					}

					if ( 'pass' === composite.api.get_composite_validation_status() ) {
						$scheme_price.html( $( scheme_price_html ).html() + scheme.details_html ).find( 'span.total' ).remove();
					} else {
						$scheme_price.html( scheme.price_html );
					}

					$scheme_price.trigger( 'wcsatt-updated-composite-price', [ scheme_price_html, scheme, composite, self ] );

				} );
			}
		};

		// Lights on.
		this.integrate();
	};

	// Hook into Bundles.
	$( '.bundle_form .bundle_data' ).each( function() {
		$( this ).on( 'woocommerce-product-bundle-initializing', function( event, bundle ) {
			if ( ! bundle.is_composited() ) {
				new PB_Integration( bundle );
			}
		} );
	} );

	// Hook into Composites.
	$( '.composite_form .composite_data' ).each( function() {
		$( this ).on( 'wc-composite-initializing', function( event, composite ) {
			new CP_Integration( composite );
		} );
	} );

} ) ( jQuery );
