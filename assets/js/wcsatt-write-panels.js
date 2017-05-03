/* global wcsatt_admin_params */
jQuery( function($) {

	var $wcsatt_data_tab    = $( '#wcsatt_data' );
	var $wcsatt_schemes     = $wcsatt_data_tab.find( '.subscription_schemes' );
	var wcsatt_block_params = {
		message:    null,
		overlayCSS: {
			background: wcsatt_admin_params.post_id !== '' ? '#fff' : '#f1f1f1',
			opacity:    0.6
		}
	};


	/* ------------------------------------*/
	/* Subscription Schemes
	/* ------------------------------------*/

	$.fn.wcsatt_scripts = function() {

		$( this ).find( '.help_tip, .tips, .woocommerce-help-tip' ).tipTip( {
			'attribute': 'data-tip',
			'fadeIn':    50,
			'fadeOut':   50,
			'delay':     200
		} );
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

	// One-time shipping toggle. Shows the one time shipping option only if the product contains any subscription schemes.
	function one_time_shipping_toggle() {

		var product_type  = $( 'select#product-type' ).val();
		var schemes_count = $wcsatt_schemes.find( '.subscription_scheme' ).length;

		if ( 'subscription' !== product_type && 'variable-subscription' !== product_type ) {
			if ( schemes_count > 0 ) {
				$( '.subscription_one_time_shipping' ).show();
			} else {
				$( '.subscription_one_time_shipping' ).hide();
			}
		}
	}

	$wcsatt_data_tab.on( 'woocommerce_subscription_schemes_changed', function() {
		one_time_shipping_toggle();
	} );

	$( 'select#product-type' ).change( function() {
		one_time_shipping_toggle();
	} ).change();

	// Price override method.
	$wcsatt_schemes.on( 'change', 'select.subscription_pricing_method_input', function() {

		var override_method = $( this ).val();

		$( this ).closest( '.subscription_scheme_product_data' ).find( '.subscription_pricing_method' ).hide();
		$( this ).closest( '.subscription_scheme_product_data' ).find( '.subscription_pricing_method_' + override_method ).show();

	} );

	$wcsatt_schemes.find( 'select.subscription_pricing_method_input' ).change();

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
	} );

	// Remove.
	$wcsatt_data_tab.on( 'click', 'a.remove_row', function() {

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

			var added = $wcsatt_schemes.find( '.subscription_scheme' ).last();

			// Run scripts against added markup.
			added.wcsatt_scripts();

			// Trigger 'change' event to show/hide type-dependent inputs.
			$( 'input#_virtual' ).change();

			// Trigger 'change' event to show/hide price override method options.
			added.find( 'select.subscription_pricing_method_input' ).change();

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
