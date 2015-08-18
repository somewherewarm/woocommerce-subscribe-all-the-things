jQuery( function($) {

	$wccsubs_data_tab  = $( '#cart_subscriptions_data' );
	$wccsubs_schemes   = $wccsubs_data_tab.find( '.subscription_schemes' );
	wccsubs_block_params = {
			message:    null,
			overlayCSS: {
				background: wccsubs_admin_params.post_id !== '' ? '#fff' : '#f1f1f1',
				opacity:    0.6
			}
		};

	/* ------------------------------------*/
	/* Subscription Schemes
	/* ------------------------------------*/

	$.fn.wccsubs_scripts = function() {

		$( this ).find( '.help_tip, .tips' ).tipTip( {
			'attribute': 'data-tip',
			'fadeIn':    50,
			'fadeOut':   50,
			'delay':     200
		} );
	};

	// Unused (for now)
	if ( wccsubs_admin_params.post_id === '' ) {

		$wccsubs_data_tab.on( 'click', 'h3', function() {

			var p = $( this ).closest( '.wc-metabox' );
			var c = p.find( '.wc-metabox-content' );

			if ( p.hasClass( 'closed' ) ) {
				c.show();
			} else {
				c.hide();
			}

			p.toggleClass( 'closed' );

		} );

		$wccsubs_data_tab.find( '.wc-metabox' ).each( function() {

			var p = $( this );
			var c = p.find( '.wc-metabox-content' );

			if ( p.hasClass( 'closed' ) ) {
				c.hide();
			}
		} );

	}

	// Hide "default to" option when "force subscription" is checked
	$wccsubs_data_tab.find( 'input#_wccsubs_force_subscription' ).on( 'change', function() {

		if ( $( this ).is( ':checked' ) ) {
			$wccsubs_data_tab.find( '.wccsubs_default_status' ).hide();
		} else {
			$wccsubs_data_tab.find( '.wccsubs_default_status' ).show();
		}

	} ).change();

	// Remove
	$wccsubs_data_tab.on( 'click', 'button.remove_row', function() {

		var $parent = $( this ).parent().parent();

		$parent.find('*').off();
		$parent.remove();
		subscription_schemes_row_indexes();
	} );

	// Expand
	$wccsubs_data_tab.on( 'click', '.expand_all', function() {
		$wccsubs_schemes.find( '.wc-metabox > .wc-metabox-content' ).show();
		return false;
	} );

	// Close
	$wccsubs_data_tab.on( 'click', '.close_all', function() {
		$wccsubs_schemes.find( '.wc-metabox > .wc-metabox-content' ).hide();
		return false;
	} );

	// Add
	var subscription_schemes_metabox_count = $wccsubs_data_tab.find( '.wc-metabox' ).length;

	$wccsubs_data_tab.on( 'click', 'button.add_subscription_scheme', function () {

		$wccsubs_data_tab.block( wccsubs_block_params );

		subscription_schemes_metabox_count++;

		var data = {
			action:  'wccsubs_add_subscription_scheme',
			post_id:  wccsubs_admin_params.post_id,
			index:    subscription_schemes_metabox_count,
			security: wccsubs_admin_params.add_subscription_scheme_nonce
		};

		$.post( wccsubs_admin_params.wc_ajax_url, data, function ( response ) {

			$wccsubs_schemes.append( response.markup );

			var added = $wccsubs_schemes.find( '.subscription_scheme' ).last();

			added.wccsubs_scripts();

			subscription_schemes_row_indexes();

			$wccsubs_data_tab.unblock();
			$wccsubs_data_tab.trigger( 'woocommerce_subscription_scheme_added', response );

		}, 'json' );

		return false;
	} );

	// Init metaboxes
	init_subscription_schemes_metaboxes();

	function subscription_schemes_row_indexes() {
		$wccsubs_schemes.find( '.subscription_scheme' ).each( function( index, el ) {
			$( '.position', el ).val( parseInt( $(el).index( '.subscription_schemes .subscription_scheme' ) ) );
		} );
	}


	function init_subscription_schemes_metaboxes() {

		// Initial order
		var subscription_schemes = $wccsubs_schemes.find( '.subscription_scheme' ).get();

		subscription_schemes.sort( function( a, b ) {
		   var compA = parseInt( $(a).attr( 'rel' ) );
		   var compB = parseInt( $(b).attr( 'rel' ) );
		   return ( compA < compB ) ? -1 : ( compA > compB ) ? 1 : 0;
		} );

		$( subscription_schemes ).each( function( idx, itm ) {
			$wccsubs_schemes.append( itm );
		} );

		// Component ordering
		$wccsubs_schemes.sortable( {
			items:                '.subscription_scheme',
			cursor:               'move',
			axis:                 'y',
			handle:               'h3',
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
