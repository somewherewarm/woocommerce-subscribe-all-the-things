/* global wcsatt_admin_params */
jQuery( function($) {

	var $wcsatt_data_tab        = $( '#wcsatt_data' ),
		$wcsatt_schemes         = $wcsatt_data_tab.find( '.subscription_schemes' ),
		$general_scheme_options = $wcsatt_data_tab.find( '.general_scheme_options' ),
		wcsatt_block_params     = {
		message:    null,
		overlayCSS: {
			background: wcsatt_admin_params.post_id !== '' ? '#fff' : '#f1f1f1',
			opacity:    0.6
		}
	};


	/* ------------------------------------*/
	/* Subscription Schemes
	/* ------------------------------------*/

	$.fn.wcsatt_init_help_tips = function() {

		$( this ).find( '.help_tip, .tips, .woocommerce-help-tip' ).tipTip( {
			'attribute': 'data-tip',
			'fadeIn':    50,
			'fadeOut':   50,
			'delay':     200
		} );
	};

	$.fn.wcsatt_init_type_dependent_inputs = function() {

		var product_type = $( 'select#product-type' ).val(),
			override_option_text = 'variable' === product_type ? wcsatt_admin_params.i18n_override_option_variable : wcsatt_admin_params.i18n_override_option,
			inherit_option_text  = 'variable' === product_type ? wcsatt_admin_params.i18n_inherit_option_variable : wcsatt_admin_params.i18n_inherit_option,
			discount_description = 'variable' === product_type ? wcsatt_admin_params.i18n_discount_description_variable : wcsatt_admin_params.i18n_discount_description;

		$( this ).find( '.subscription_pricing_method_input [value="inherit"]' ).text( inherit_option_text );
		$( this ).find( '.subscription_pricing_method_input [value="override"]' ).text( override_option_text );
		$( this ).find( '.subscription_price_discount .woocommerce-help-tip' ).attr( 'data-tip', discount_description ).wcsatt_init_help_tips();
	};

	$.fn.wcsatt_refresh_scheme_lengths = function() {

		var $lengthElement    = $( this ).find( '.wc_input_subscription_length' ),
			$periodSelector   = $( this ).find( '.wc_input_subscription_period' ),
			$intervalSelector = $( this ).find( '.wc_input_subscription_period_interval' ),
			selectedLength    = $lengthElement.val(),
			billingInterval   = parseInt( $intervalSelector.val() ),
			hasSelectedLength = false;

		$lengthElement.empty();

		$.each( wcsatt_admin_params.subscription_lengths[ $periodSelector.val() ], function( length, description ) {
			if ( parseInt( length ) == 0 || 0 == ( parseInt( length ) % billingInterval ) ) {
				$lengthElement.append( $( '<option></option>' ).attr( 'value', length ).text( description ) );
			}
		} );

		$lengthElement.children( 'option' ).each( function() {
			if ( this.value == selectedLength ) {
				hasSelectedLength = true;
				return false;
			}
		} );

		if ( hasSelectedLength ) {
			$lengthElement.val( selectedLength );
		} else {
			$lengthElement.val( 0 );
		}
	};

	// Cart level settings.
	if ( wcsatt_admin_params.post_id === '' ) {

		$wcsatt_data_tab.on( 'click', 'h3', function() {

			var p = $( this ).closest( '.wc-metabox' );
			var c = p.find( '.wc-metabox-content' );

			if ( p.hasClass( 'closed' ) ) {
				c.show();
			} else {
				c.hide();
			}

			p.toggleClass( 'closed' );

		} );

		$wcsatt_data_tab.find( '.wc-metabox' ).each( function() {

			var p = $( this );
			var c = p.find( '.wc-metabox-content' );

			if ( p.hasClass( 'closed' ) ) {
				c.hide();
			}
		} );

	}

	$.fn.wcsatt_refresh_sync_options = function() {

		var $periodSelector         = $( this ).find( '.wc_input_subscription_period' ),
			$syncOptions            = $( this ).find( '.subscription_sync' ),
			$syncAnnualContainer    = $syncOptions.find( '.subscription_sync_annual' ),
			$syncWeekMonthContainer = $syncOptions.find( '.subscription_sync_week_month' ),
			$syncWeekMonthSelect    = $syncWeekMonthContainer.find( 'select' ),
			billingPeriod           = $periodSelector.val();

		if ( 'day' === billingPeriod ) {

			$syncOptions.hide();

			$syncWeekMonthSelect.val(0);
			$syncAnnualContainer.find( 'input[type="number"]' ).val(0);

		} else {

			$syncOptions.show();

			if ( 'year' === billingPeriod ) {

				$syncWeekMonthContainer.hide();

				$syncAnnualContainer.find( 'input[type="number"]' ).val(0);
				$syncWeekMonthSelect.val(0);

				$syncAnnualContainer.show();

			} else {

				$syncAnnualContainer.hide();

				$syncAnnualContainer.find( 'input[type="number"]' ).val(0);
				$syncWeekMonthSelect.empty();

				$.each( WCSubscriptions.syncOptions[ billingPeriod ], function( key, description ) {
					if ( ! key ) {
						description = wcsatt_admin_params.i18n_do_no_sync;
					}
					$syncWeekMonthSelect.append( $('<option></option>' ).attr( 'value', key ).text( description ) );
				} );

				$syncWeekMonthContainer.show();
			}
		}
	};

	// Toggle general subscription scheme options. Shows the options only if the product contains some subscription schemes.
	function toggle_general_subscription_scheme_options() {

		var schemes_count = $wcsatt_schemes.find( '.subscription_scheme' ).length;

		if ( schemes_count > 0 ) {
			$general_scheme_options.show();
		} else {
			$general_scheme_options.hide();
		}
	}

	// Populate type-specific inputs.
	function initialize_type_dependent_scheme_inputs() {

		var $schemes = $wcsatt_schemes.find( '.subscription_scheme' );

		if ( $schemes.length > 0 ) {
			$schemes.wcsatt_init_type_dependent_inputs();
		}
	}

	// Toggle one-time shipping. Shows the one time shipping option only if the product contains subscription schemes.
	function toggle_one_time_shipping() {

		var product_type  = $( 'select#product-type' ).val(),
			schemes_count = $wcsatt_schemes.find( '.subscription_scheme' ).length;

		if ( 'subscription' !== product_type && 'variable-subscription' !== product_type ) {
			if ( schemes_count > 0 ) {
				$( '.subscription_one_time_shipping' ).show();
			} else {
				$( '.subscription_one_time_shipping' ).hide();
			}
		}
	}

	// Trigger one-time shipping option toggle when switching product type.
	$( 'select#product-type' ).change( function() {
		initialize_type_dependent_scheme_inputs();
		toggle_general_subscription_scheme_options();
		toggle_one_time_shipping();
	} ).change();

	// Toggle one-time shipping.
	$wcsatt_data_tab.on( 'woocommerce_subscription_schemes_changed', function() {
		toggle_general_subscription_scheme_options();
		toggle_one_time_shipping();
	} );

	// Toggle suitable price override method fields.
	$wcsatt_schemes.on( 'change', 'select.subscription_pricing_method_input', function() {

		var override_method = $( this ).val();

		$( this ).closest( '.subscription_scheme_product_data' ).find( '.subscription_pricing_method' ).hide();
		$( this ).closest( '.subscription_scheme_product_data' ).find( '.subscription_pricing_method_' + override_method ).show();

	} );

	// Hide "default to" option when "force subscription" is checked.
	$wcsatt_data_tab.find( 'input#_wcsatt_force_subscription' ).on( 'change', function() {

		if ( $( this ).is( ':checked' ) ) {
			$wcsatt_data_tab.find( '.wcsatt_default_status' ).hide();
		} else {
			$wcsatt_data_tab.find( '.wcsatt_default_status' ).show();
		}

	} ).change();

	// Update subscription ranges when subscription period or interval is changed.
	$wcsatt_schemes.on( 'change', '.wc_input_subscription_period', function() {
		$( this ).closest( '.subscription_scheme' ).wcsatt_refresh_scheme_lengths();
		$( this ).closest( '.subscription_scheme' ).wcsatt_refresh_sync_options();
	} );

	// Remove.
	$wcsatt_data_tab.on( 'click', 'span.scheme-remove', function() {

		var $parent = $( this ).closest( '.subscription_scheme' );

		$parent.find('*').off();
		$parent.remove();
		subscription_schemes_row_indexes();

		$wcsatt_data_tab.trigger( 'woocommerce_subscription_schemes_changed' );

		return false;
	} );

	// Expand.
	$wcsatt_data_tab.on( 'click', '.expand_all', function() {
		$wcsatt_schemes.find( '.wc-metabox > .wc-metabox-content' ).show();
		return false;
	} );

	// Close.
	$wcsatt_data_tab.on( 'click', '.close_all', function() {
		$wcsatt_schemes.find( '.wc-metabox > .wc-metabox-content' ).hide();
		return false;
	} );

	// Add.
	var subscription_schemes_metabox_count = $wcsatt_data_tab.find( '.wc-metabox' ).length;

	$wcsatt_data_tab.on( 'click', 'button.add_subscription_scheme', function () {

		$wcsatt_data_tab.block( wcsatt_block_params );

		subscription_schemes_metabox_count++;

		var data = {
			action:  'wcsatt_add_subscription_scheme',
			post_id:  wcsatt_admin_params.post_id,
			index:    subscription_schemes_metabox_count,
			security: wcsatt_admin_params.add_subscription_scheme_nonce
		};

		$.post( wcsatt_admin_params.wc_ajax_url, data, function ( response ) {

			// Append markup.
			$wcsatt_schemes.append( response.markup );

			var $added_scheme = $wcsatt_schemes.find( '.subscription_scheme' ).last();

			// Run scripts against added markup.
			$added_scheme.wcsatt_init_type_dependent_inputs();
			$added_scheme.wcsatt_init_help_tips();

			// Trigger 'change' event to show/hide price override method options.
			$added_scheme.find( 'select.subscription_pricing_method_input' ).change();

			// Add indexes.
			subscription_schemes_row_indexes();

			$wcsatt_data_tab.unblock();

			$wcsatt_data_tab.trigger( 'woocommerce_subscription_scheme_added', response );

			$wcsatt_data_tab.trigger( 'woocommerce_subscription_schemes_changed' );

		}, 'json' );

		return false;
	} );

	// Init metaboxes.
	init_subscription_schemes_metaboxes();

	function subscription_schemes_row_indexes() {
		$wcsatt_schemes.find( '.subscription_scheme' ).each( function( index, el ) {
			$( '.position', el ).val( parseInt( $(el).index( '.subscription_schemes .subscription_scheme' ) ) );
		} );
	}

	function init_subscription_schemes_metaboxes() {

		$wcsatt_schemes.find( 'select.subscription_pricing_method_input' ).change();

		// Initial order.
		var subscription_schemes = $wcsatt_schemes.find( '.subscription_scheme' ).get();

		subscription_schemes.sort( function( a, b ) {
		   var compA = parseInt( $(a).attr( 'rel' ) );
		   var compB = parseInt( $(b).attr( 'rel' ) );
		   return ( compA < compB ) ? -1 : ( compA > compB ) ? 1 : 0;
		} );

		$( subscription_schemes ).each( function( idx, itm ) {
			$wcsatt_schemes.append( itm );
		} );

		// Component ordering.
		$wcsatt_schemes.sortable( {
			items:                '.subscription_scheme',
			cursor:               'move',
			axis:                 'y',
			handle:               'span.scheme-handle',
			scrollSensitivity:    40,
			forcePlaceholderSize: true,
			helper:               'clone',
			opacity:              0.65,
			placeholder:          'wc-metabox-sortable-placeholder',
			start:function( event,ui ){
				ui.item.css( 'background-color','#f6f6f6' );
			},
			stop:function( event,ui ){
				ui.item.removeAttr( 'style' );
				subscription_schemes_row_indexes();
			}
		} );
	}

} );
