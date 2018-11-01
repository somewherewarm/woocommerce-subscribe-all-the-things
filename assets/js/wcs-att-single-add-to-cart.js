;( function( $ ) {

	// Ensure wcsatt_single_product_params exists to continue.
	if ( typeof wcsatt_single_product_params === 'undefined' ) {
		return false;
	}

	// Subscription Schemes model.
	var Schemes_Model = function( opts ) {

		var Model = Backbone.Model.extend( {

			get_active_scheme_key: function() {
				return this.get( 'active_scheme_key' );
			},

			get_last_active_scheme_key: function() {
				return this.previous( 'active_scheme_key' );
			},

			set_active_scheme: function( key_to_set ) {
				this.set( { active_scheme_key: key_to_set !== '0' ? key_to_set : false } );
			},

			set_schemes: function( schemes_to_set ) {
				this.set( { schemes: schemes_to_set } );
			},

			is_active_scheme_prorated: function() {
				return this.get_scheme_prop( this.get_active_scheme_key(), 'is_prorated' );
			},

			was_last_active_scheme_prorated: function() {
				return this.get_scheme_prop( this.get_last_active_scheme_key(), 'is_prorated' );
			},

			get_scheme_prop: function( key, prop ) {

				var schemes = this.get( 'schemes' );

				if ( typeof schemes[ key ] === 'undefined' || typeof schemes[ key ][ prop ] === 'undefined' ) {
					return null;
				}

				return schemes[ key ][ prop ];
			},

			initialize: function() {

				var params = {
					schemes:           {},
					active_scheme_key: ''
				};

				this.set( params );
			}

		} );

		var obj = new Model( opts );
		return obj;
	};

	// Subscription Schemes view.
	var Schemes_View = function( opts ) {

		var View = Backbone.View.extend( {

			$el_options: false,

			variation: false,

			events: {
				'change .wcsatt-options-wrapper input': 'active_scheme_changed',
				'show_variation': 'variation_found',
				'reset_data': 'reset_schemes'
			},

			active_scheme_changed: function( e ) {
				this.model.set_active_scheme( e.currentTarget.value );
			},

			variation_found: function( event, variation ) {
				this.variation = variation;
				this.initialize( { $el_options: this.$el.find( '.wcsatt-options-wrapper' ) } );
			},

			variation_selected: function() {
				return false !== this.variation;
			},

			reset_schemes: function() {
				this.variation = false;
				this.model.set_schemes( {} );
				this.model.set_active_scheme( false );
			},

			has_schemes: function() {
				return this.$el_options.length > 0;
			},

			find_schemes: function() {

				var schemes = {};

				if ( this.has_schemes() ) {
					this.$el_options.find( '.subscription-option input' ).each( function() {
						var scheme_data = $( this ).data( 'custom_data' );
						schemes[ scheme_data.subscription_scheme.key ] = scheme_data.subscription_scheme;
					} );
				}

				return schemes;
			},

			initialize: function( options ) {

				this.$el_options = options.$el_options;

				this.model.set_schemes( this.find_schemes() );

				if ( this.has_schemes() ) {

					// Maintain the selected scheme between variation changes.
					var active_scheme_option = this.$el_options.find( 'input[value="' + this.model.get_active_scheme_key() + '"]' );

					if ( active_scheme_option.length > 0 ) {
						active_scheme_option.prop( 'checked', true );
					} else {
						this.$el_options.find( 'input:checked' ).change();
					}

				} else if ( ! this.variation_selected() ) {
					this.model.set_active_scheme( false );
				} else {
					this.model.set_active_scheme( null );
				}
			}

		} );

		var obj = new View( opts );
		return obj;
	};

	// Add-to-subscription model.
	var Matching_Subscriptions_Model = function( opts ) {

		var Model = Backbone.Model.extend( {

			product: false,
			xhr: false,

			cached_responses: {},

			set_scheme_key: function( scheme_key_to_set ) {
				this.set( { scheme_key: scheme_key_to_set } );
			},

			get_scheme_key: function() {
				return this.get( 'scheme_key' );
			},

			get_matching_subscriptions_html: function() {

				var model             = this,
					active_scheme_key = this.product.schemes_model.get_active_scheme_key();

				if ( this.xhr ) {
					this.xhr.abort();
				}

				active_scheme_key = false === active_scheme_key ? '0' : active_scheme_key;

				if ( typeof this.cached_responses[ active_scheme_key ] !== 'undefined' ) {

					model.set( { html: this.cached_responses[ active_scheme_key ] } );
					model.trigger( 'matching_subscriptions_loaded' );

				} else {

					var data = {
						action:              'wcsatt_load_subscriptions_matching_product',
						product_id:          this.product.get_product_id(),
						subscription_scheme: active_scheme_key
					};

					// Get matching subscriptions list via ajax.
					this.xhr = $.post( wcsatt_single_product_params.wc_ajax_url.toString().replace( '%%endpoint%%', data.action ), data, function( response ) {

						if ( 'success' === response.result ) {
							model.set( { html: response.html } );
							model.cached_responses[ data.subscription_scheme ] = response.html;
						} else {
							model.set( { html: false } );
							model.attributes.scheme_key = false;
						}

						model.trigger( 'matching_subscriptions_loaded' );

					} );
				}
			},

			// Active scheme changed.
			active_scheme_changed: function() {

				if ( this.xhr ) {
					this.xhr.abort();
				}
			},

			initialize: function( options ) {

				this.product = options.product;

				var params = {
					scheme_key: '',
					html:       false
				};

				this.set( params );

				this.listenTo( this.product.schemes_model, 'change:active_scheme_key', this.active_scheme_changed );
				this.on( 'change:scheme_key', this.get_matching_subscriptions_html );
			}

		} );

		var obj = new Model( opts );
		return obj;
	};

	// Add-to-subscription view.
	var Matching_Subscriptions_View = function( opts ) {

		var View = Backbone.View.extend( {

			$el_content: false,

			product: false,

			block_params: {
				message:    null,
				fadeIn:     0,
				fadeOut:    0,
				overlayCSS: {
					background: 'rgba( 255, 255, 255, 0 )',
					opacity:    1,
				}
			},

			events: {
				'click .wcsatt-add-to-subscription-action-input': 'action_link_clicked'
			},

			// 'Add to subscription' link 'click' event handler.
			action_link_clicked: function() {

				var model         = this.model,
					view          = this,
					state_changed = false;

				if ( ! this.matching_subscriptions_visible() ) {

					if ( this.model.get_scheme_key() === this.product.schemes_model.get_active_scheme_key() ) {
						state_changed = this.toggle();
					} else {
						state_changed = true;
						this.$el.block( this.block_params );
						setTimeout( function() {
							model.set_scheme_key( view.product.schemes_model.get_active_scheme_key() );
						}, 200 );
					}

				} else {
					state_changed = this.toggle();
				}

				if ( ! state_changed ) {
					return false;
				}
			},

			// Active scheme changed.
			active_scheme_changed: function() {

				var view         = this,
					update_model = true;

				if ( false === this.product.schemes_model.get_active_scheme_key() || this.product.schemes_model.is_active_scheme_prorated() ) {
					update_model = false;
				}

				if ( update_model ) {

					if ( view.$el.hasClass( 'open' ) && view.model.get_scheme_key() !== view.product.schemes_model.get_active_scheme_key() ) {

						view.$el.block( view.block_params );

						if ( false === view.product.schemes_model.get_last_active_scheme_key() || this.product.schemes_model.was_last_active_scheme_prorated() ) {
							view.toggle( true );
						}

						setTimeout( function() {
							view.model.set_scheme_key( view.product.schemes_model.get_active_scheme_key() );
						}, 250 );
					}

					setTimeout( function() {
						view.$el.slideDown( 200 );
					}, 50 );

				} else {

					this.$el.slideUp( 200 );
				}
			},

			// Handles add-to-subscription button clicks.
			add_to_subscription_button_clicked: function( event ) {

				var $add_to_cart_button = event.data.view.product.$form.find( '.single_add_to_cart_button' );

				// Trigger JS notice.
				if ( $add_to_cart_button.hasClass( 'disabled' ) ) {
					$add_to_cart_button.click();
					return false;
				}
			},

			// Toggles the matching subscriptions content wrapper.
			toggle: function( now ) {

				now = typeof now === 'undefined' ? false : now;

				var view     = this,
					duration = now ? 0 : 200;

				if ( view.$el.data( 'animating' ) === true ) {
					return false;
				}

				if ( view.$el.hasClass( 'closed' ) ) {

					setTimeout( function() {
						view.$el_content.slideDown( { duration: duration, queue: false, always: function() {
							view.$el.data( 'animating', false );
						} } );
					}, 10 );

					view.$el.removeClass( 'closed' ).addClass( 'open' );
					view.$el.data( 'animating', true );

				} else {

					setTimeout( function() {
						view.$el_content.slideUp( { duration: duration, queue: false, always: function() {
							view.$el.data( 'animating', false );
						} } );
					}, 10 );

					view.$el.removeClass( 'open' ).addClass( 'closed' );
					view.$el.data( 'animating', true );
				}

				return true;
			},

			// True if the matching subscriptions select wrapper is visible.
			matching_subscriptions_visible: function() {
				return this.$el_content.is( ':visible' );
			},

			// New subscriptions list loaded?
			matching_subscriptions_loaded: function() {
				this.render();
			},

			// Render the subscriptions selector.
			render: function() {

				var html = this.model.get( 'html' );

				this.$el.unblock();

				if ( false === html ) {

					window.alert( wcsatt_single_product_params.i18n_subs_load_error );

					if ( this.matching_subscriptions_visible() ) {
						this.toggle();
					}

					this.$el.find( 'input.wcsatt-add-to-subscription-action-input' ).prop( 'checked', false );

				} else {

					this.$el_content.html( html );

					if ( ! this.matching_subscriptions_visible() ) {
						this.toggle();
					}
				}
			},

			initialize: function( options ) {

				this.product     = options.product;
				this.$el_content = options.$el_content;

				this.listenTo( this.model, 'matching_subscriptions_loaded', this.matching_subscriptions_loaded );
				this.listenTo( this.product.schemes_model, 'change:active_scheme_key', this.active_scheme_changed );

				this.$el_content.on( 'click', '.wcsatt-add-to-subscription-button', { view: this }, this.add_to_subscription_button_clicked );
			}

		} );

		var obj = new View( opts );
		return obj;
	};

	// SATT Product object.
	var SATT_Product = function( $product_form ) {

		this.$form = $product_form;

		this.schemes_model = false;
		this.schemes_view  = false;

		this.matching_subscriptions_model = false;
		this.matching_subscriptions_view  = false;

		this.initialize = function() {

			this.schemes_model                = new Schemes_Model( { product: this } );
			this.matching_subscriptions_model = new Matching_Subscriptions_Model( { product: this } );

			this.schemes_view                = new Schemes_View( { model: this.schemes_model, el: $product_form, $el_options: $product_form.find( '.wcsatt-options-wrapper' ) } );
			this.matching_subscriptions_view = new Matching_Subscriptions_View( { product: this, model: this.matching_subscriptions_model, el: $product_form.find( '.wcsatt-add-to-subscription-wrapper' ), $el_content: $product_form.find( '.wcsatt-add-to-subscription-options' ) } );

			// Simple switching fix for https://github.com/woocommerce/woocommerce/commit/3340d5c7cc78d0a254dfed4c2c7f6f0d5645c8ba#diff-cb560f318dd3126e27d8499b80e71027
			if ( window.location.href.indexOf( 'switch-subscription' ) != -1 && window.location.href.indexOf( 'item' ) != -1 ) {
				$product_form.prop( 'action', '' );
			}
		};

		this.get_product_id = function() {
			return $product_form.find( '.wcsatt-add-to-subscription-wrapper' ).data( 'product_id' );
		};
	};

	// Product Bundles integration.
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

			$scheme_options.each( function() {

				var $scheme_option = $( this ),
					scheme_data    = $scheme_option.find( 'input' ).data( 'custom_data' );

				bundle.satt_schemes.push( {
					el:           $scheme_option,
					data:         scheme_data,
					price_html:   $scheme_option.find( '.subscription-price' ).html(),
					details_html: $scheme_option.find( '.subscription-details' ).prop( 'outerHTML' )
				} );

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

					var scheme_price_html       = bundle.get_price_html(),
						scheme_price_inner_html = $( scheme_price_html ).html();

					// If only a single option is present, then bundle prices are already overridden on the server side.
					// In this case, simply grab the subscription details from the option and append them to the bundle price string.
					if ( bundle.satt_schemes.length === 1 && bundle.$bundle_wrap.find( '.wcsatt-options-product .one-time-option' ).length === 0 ) {

						bundle.$bundle_price.find( '.price' ).html( scheme_price_inner_html + scheme.details_html );

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

							scheme_price_html       = bundle.get_price_html( price_data );
							scheme_price_inner_html = $( scheme_price_html ).html() + ' ';

						} else {
							scheme_price_inner_html = '';
						}

						if ( bundle.passes_validation() ) {
							$scheme_price.html( scheme_price_inner_html + scheme.details_html ).find( 'span.total' ).remove();
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

			$scheme_options.each( function() {

				var $scheme_option = $( this ),
					scheme_data    = $scheme_option.find( 'input' ).data( 'custom_data' );

				composite.satt_schemes.push( {
					el:           $scheme_option,
					data:         scheme_data,
					price_html:   $scheme_option.find( '.subscription-price' ).html(),
					details_html: $scheme_option.find( '.subscription-details' ).prop( 'outerHTML' )
				} );

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

					var scheme_price_html       = composite.composite_price_view.get_price_html(),
						scheme_price_inner_html = '',
						$scheme_price           = scheme.el.find( '.subscription-price' );

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

						price_data.totals       = totals;
						scheme_price_html       = composite.composite_price_view.get_price_html( price_data );
						scheme_price_inner_html = $( scheme_price_html ).html() + ' ';
					}

					if ( 'pass' === composite.api.get_composite_validation_status() ) {
						$scheme_price.html( scheme_price_inner_html + scheme.details_html ).find( 'span.total' ).remove();
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

	// Initialize SATT script.
	$( '.product form.cart' ).each( function() {
		var $product_form = $( this ),
			satt_script   = new SATT_Product( $product_form );

		satt_script.initialize();
		$product_form.data.satt_script = satt_script;
	} );

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
