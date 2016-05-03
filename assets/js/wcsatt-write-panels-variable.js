/* global wcsatt_admin_params */
jQuery( function($) {

	"use strict";

	var wrapper                     = $( '#woocommerce-product-data' );
	var $variations_product_options = $( '#variable_product_options' ).find( '.woocommerce_variations' );
	var wcsatt_block_params         = {
			message: null,
			overlayCSS: {
				background: wcsatt_admin_params.post_id !== '' ? '#fff' : '#f1f1f1',
				opacity: 0.6
			}
		};

	var $wcsatt_schemes = $variations_product_options.closest( '.variation_subscription_schemes' );

	/* ------------------------------------*/
	/* Subscription Schemes
	/* ------------------------------------*/

	$.fn.wcsatt_scripts = function() {

		$( this ).find( '.help_tip, .tips' ).tipTip( {
			'attribute': 'data-tip',
			'fadeIn':    50,
			'fadeOut':   50,
			'delay':     200
		} );
	};

	$.fn.wcsatt_refresh_scheme_lengths = function() {

		var $lengthElement  = $( this ).find( '.wc_input_subscription_length' ),
			$periodSelector   = $( this ).find( '.wc_input_subscription_period' ),
			$intervalSelector = $( this ).find( '.wc_input_subscription_period_interval' ),
			selectedLength    = $lengthElement.val(),
			billingInterval   = parseInt( $intervalSelector.val() ),
			hasSelectedLength = false;

		$lengthElement.empty();

		$.each( wcsatt_admin_params.subscription_lengths[ $periodSelector.val() ], function( length, description ) {
			if ( parseInt( length ) == 0 || 0 == ( parseInt( length ) % billingInterval ) ) {
				$lengthElement.append( $( '<option></option>' ).attr( 'value',length ).text( description ) );
			}
		} );

		$lengthElement.children( 'option' ).each(function(){
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

		$variations_product_options.on( 'click', 'h3', function() {

			var p = $( this ).closest( '.wc-metabox' );
			var c = p.find( '.wc-metabox-content' );

			if ( p.hasClass( 'closed' ) ) {
				c.show();
			} else {
				c.hide();
			}

			p.toggleClass( 'closed' );

		} );

		$variations_product_options.find( '.wc-metabox' ).each( function() {

			var p = $( this );
			var c = p.find( '.wc-metabox-content' );

			if ( p.hasClass( 'closed' ) ) {
				c.hide();
			}
		} );

	}

	/**
	 * Check if variation is subscribable and show/hide elements
	 */
	 $( '#variable_product_options' ).on( 'change', 'input.variable_is_subscribable', function() {
		$( this ).closest( '.woocommerce_variation' ).find( '.show_if_variation_is_subscribable' ).hide();

		if ( $( this ).is( ':checked' ) ) {
			$( this ).closest( '.woocommerce_variation' ).find( '.show_if_variation_is_subscribable' ).show();
		}
	});

	// Price override method.
	$variations_product_options.on( 'change', 'select.subscription_pricing_method_input', function() {

		var override_method = $( this ).val();

		$( this ).closest( '.subscription_scheme_product_data' ).find( '.subscription_pricing_method' ).hide();
		$( this ).closest( '.subscription_scheme_product_data' ).find( '.subscription_pricing_method_' + override_method ).show();

	} );

	$variations_product_options.find( 'select.subscription_pricing_method_input' ).change();

	// Update subscription ranges when subscription period or interval is changed.
	$variations_product_options.on( 'change', '.wc_input_subscription_period', function() {
		var $t = $(this);

		$t.closest( '.variation_subscription_scheme' ).wcsatt_refresh_scheme_lengths();
	} );

	// Remove.
	$variations_product_options.on( 'click', '.remove_scheme', function(e) {
		e.preventDefault();

		var $parent = $( this ).closest( '.variation_subscription_scheme' );

		// Only remove option if confirmed.
		if ( window.confirm( wcsatt_admin_params.i18n_remove_subscription_scheme ) ) {

			$parent.find('*').off();
			$parent.remove();

			variable_subscription_schemes_row_indexes();

		} else {

			// Keeps the scheme in it's current open/closed state.
			return false;

		}

	} );

	// Adding subscription scheme for variation
	$variations_product_options.on( 'click', 'button.add_variation_subscription_scheme', function() {
		var $t = $(this);

		$variations_product_options.block( wcsatt_block_params );

		// Counts how many subscription options this variation has.
		var $sub_options = $t.parent().parent().find( '.variation_subscription_schemes' ).find( '.variation_subscription_scheme' ).size();

		// Returns the variation ID and order.
		var loop = $.parseJSON($t.attr('data-button'));

		var data = {
			action:   'wcsatt_add_variation_subscription_scheme',
			post_id:  loop.variation_id,
			loop:     loop.order,
			index:    $sub_options,
			security: wcsatt_admin_params.add_subscription_scheme_nonce
		};

		$.post( wcsatt_admin_params.wc_ajax_url, data, function( response ) {

			$t.parent().parent().find( '.variation_subscription_schemes' ).append( response.markup );

			var added = $variations_product_options.find( '.variation_subscription_scheme' ).last();

			added.wcsatt_scripts();

			added.find( 'select.subscription_pricing_method_input' ).change();

			variable_subscription_schemes_row_indexes();

			$variations_product_options.trigger( 'woocommerce_subscription_scheme_added', response );
			$variations_product_options.unblock();

		}, 'json' );

		return false;
	} );

	// Init metaboxes.
	//init_variation_subscription_schemes_metaboxes();

	/**
	 * Run actions when variations is loaded
	 *
	 * @param {Object} event
	 * @param {Int} needsUpdate
	 */
	$( '#woocommerce-product-data' ).on( 'woocommerce_variations_loaded', function( event, needsUpdate ) {
		needsUpdate = needsUpdate || false;

		var wrapper = $variations_product_options;

		if ( ! needsUpdate ) {
			// Show/hide subscription scheme options
			$( 'input.variable_is_subscribable', wrapper ).change();

			// Open sale schedule fields when have some sale price date
			$( '.woocommerce_variation', wrapper ).each( function( index, el ) {
				var $el = $( el );

				index = ( index + 1 );

				var $subscription_schemes_metabox_count = $el.find( '.wc-metabox' ).length;
				console.log( 'Number of Subscription Options found for Variation ' + index + ': ' + $subscription_schemes_metabox_count );

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
					items:                '.variation_subscription_scheme',
					cursor:               'move',
					axis:                 'y',
					handle:               '.sort-scheme',
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
						variable_subscription_schemes_row_indexes();
					}
				} );

			});

		}
	});

	// Re-index the subscription scheme options
	function variable_subscription_schemes_row_indexes() {
		$wcsatt_schemes.find( '.variation_subscription_scheme' ).each( function( index, el ) {
			$( '.position', el ).val( parseInt( $(el).index( '.variation_subscription_schemes .variation_subscription_scheme' ) ) );
			var ind = '#' + ( index + 1 ).toString();
			$( '.scheme-title', el ).html( ind );
		});
	}

	function init_variation_subscription_schemes_metaboxes() {

		// Initial order.
		var subscription_schemes = $wcsatt_schemes.find( '.variation_subscription_scheme' ).each( function( index, el ) {
			var $el = $( el );

			$el.get();

			subscription_schemes.sort( function( a, b ) {
				var compA = parseInt( $(a).attr( 'rel' ) );
				var compB = parseInt( $(b).attr( 'rel' ) );
				return ( compA < compB ) ? -1 : ( compA > compB ) ? 1 : 0;
			} );

			$( subscription_schemes ).each( function( idx, itm ) {
				$variations_product_options.append( itm );
			} );

			// Component ordering.
			$wcsatt_schemes.sortable( {
				items:                '.variation_subscription_scheme',
				cursor:               'move',
				axis:                 'y',
				handle:               'sort-scheme',
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
					variable_subscription_schemes_row_indexes();
				}
			} );
		} );
	}

} );
