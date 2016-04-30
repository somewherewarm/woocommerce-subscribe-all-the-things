;(function ( $, window, document, undefined ) {

	var v                     = $(this),
			single_variation      = v.find( '.single_variation' ),
			product               = v.closest( '.product' ),
			product_id            = parseInt( v.data( 'product_id' ), 10 ),
			single_variation_wrap = v.find( '.single_variation_wrap' ),
			form                  = v.find( '.variations_form' ),
			force_subscription    = $( 'input[name=force_subscription]' ).val(),
			default_status        = $( 'input[name=default_status]' ).val(),
			prompt                = $( 'input[name=prompt]' ).val();

	// When the variation is revealed
	v.on( 'show_variation', function( event, variation ) {
		event.preventDefault();

		// Only take action if the variation is subscribable.
		if ( variation.is_subscribable ) {

			// Checks that this selected variation has subscription schemes.
			if ( variation.subscription_schemes.length > 0 ) {

				var sub_html = '<h3>' + prompt + '</h3>'
				+ '<ul class="wcsatt-convert-cart">';

				if ( force_subscription == 'yes' ) {
					var allow_one_time_option = false;
				}

				if ( allow_one_time_option && default_status != 'subscription' ) {
					var default_subscription_scheme_id = '0';
				} else {
					var default_subscription_scheme_id = '1';
				}

				if ( !allow_one_time_option ) {

					sub_html = sub_html + '<li>'
						+ '<label>'
						+ '<input type="radio" name="convert_to_sub_' + $(product_id) + '" value="0" /> '
						+	wcsatt_add_to_cart_variation_params.none
						+ '</label>'
					+ '</li>';

				}

				$.each ( variation.subscription_schemes, function( key, value ) {
					var scheme_id          = value.id,
							position           = value.position,
							discount           = value.subscription_discount,
							period             = value.subscription_period,
							period_interval    = value.subscription_period_interval,
							sub_price          = value.subscription_price,
							sub_pricing_method = value.subscription_pricing_method,
							sub_regular_price  = value.subscription_regular_price,
							sub_sale_price     = value.subscription_sale_price;

					var price_string = {
						'discount'           : discount,
						'period'             : period,
						'period_interval'    : period_interval,
						'sub_price'          : sub_price,
						'sub_pricing_method' : sub_pricing_method,
						'sub_regular_price'  : sub_regular_price,
						'sub_sale_price'     : sub_sale_price
					};

					// Get the price for this option
					var get_price = get_price_string( price_string );

					// Create new option
					sub_html = sub_html + '<li>'
						+ '<label>'
						+ '<input type="radio" name="convert_to_sub_' + product_id + '" value="' + scheme_id + '" />'
						+	'<del><span class="amount">Regular Price</span></del>'
						+ '<ins><span class="amount">' + sub_price + '</span></ins>'
						+ '<span class="subscription-details"> / ' + get_price + '</span>'
						+ '</label>'
					+ '</li>';

				} ); // END each subscription schemes

				sub_html = sub_html + '</ul>'; // Close the list of options

				// Place all price options for this variation.
				$('.woocommerce-variation-price').html(sub_html);

			} // END if variation has subscrition schemes

		} // END if variation is subscriable

	} )

	/**
	 * Returns a string representing the details of the subscription option.
	 *
	 * For example "$20 per Month for 3 Months".
	 */
	function get_price_string( values ) {
		console.log(values);

		// Need to finish coding this by converting the get_price_string() function from WooCommerce Subscriptions.

		return values.period;
	}

})( jQuery, window, document );
