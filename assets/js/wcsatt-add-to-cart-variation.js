;(function ( $, window, document, undefined ) {

	var v                     = $(this),
			currency_symbol       = wcsatt_add_to_cart_variation_params.currency_symbol,
			currency_pos          = wcsatt_add_to_cart_variation_params.currency_pos,
			none                  = wcsatt_add_to_cart_variation_params.none,
			all_time              = wcsatt_add_to_cart_variation_params.all_time,
			every                 = wcsatt_add_to_cart_variation_params.every,
			day                   = wcsatt_add_to_cart_variation_params.day,
			days                  = wcsatt_add_to_cart_variation_params.days,
			week                  = wcsatt_add_to_cart_variation_params.week,
			weeks                 = wcsatt_add_to_cart_variation_params.weeks,
			month                 = wcsatt_add_to_cart_variation_params.month,
			months                = wcsatt_add_to_cart_variation_params.months,
			year                  = wcsatt_add_to_cart_variation_params.year,
			years                 = wcsatt_add_to_cart_variation_params.years,
			nd_intervals          = wcsatt_add_to_cart_variation_params.nd_intervals,
			rd_intervals          = wcsatt_add_to_cart_variation_params.rd_intervals,
			th_intervals          = wcsatt_add_to_cart_variation_params.th_intervals,
			force_subscription    = $( 'input[name=force_subscription]' ).val(),
			default_status        = $( 'input[name=default_status]' ).val(),
			prompt                = $( 'input[name=prompt]' ).val(),
			product               = $( 'body.single-product' ).find( '.product' ),
			load_schemes          = false;

	v.on('load', function( event ) {
		event.preventDefault();

		// If the product type is a bundle product then don't load the subscription schemes.
		if ( ! product.hasClass('product-type-bundle') ) {
			load_schemes = true;
		}
	} );

	// When the variation is revealed
	v.on( 'show_variation', function( event, variation ) {
		event.preventDefault();

		var product_id     = variation.variation_id,
				current_price  = variation.display_price,
				regular_price  = variation.display_regular_price,
				checked        = false,
				radio_selected = '';

		// Only take action if the variation is subscribable and we are allowed to load the schemes.
		if ( variation.is_subscribable && load_schemes ) {

			// Checks that this selected variation has subscription schemes.
			if ( variation.subscription_schemes.length > 0 ) {

				var sub_html = '<h3>' + prompt + '</h3>'
				+ '<ul class="wcsatt-convert-product">';

				if ( force_subscription == 'yes' ) {
					var allow_one_time_option = false;
				}

				if ( allow_one_time_option && default_status != 'subscription' ) {
					var default_subscription_scheme_id = '0';
				} else {
					var default_subscription_scheme_id = '1';
				}

				if ( allow_one_time_option ) {
					if ( force_subscription != 'yes' ) {
						checked = true;
						if ( checked ) {
							radio_selected = 'checked="checked" ';
						} else {
							radio_selected = '';
						}
					}

					sub_html = sub_html + '<li>'
						+ '<label>'
						+ '<input type="radio" name="convert_to_sub_' + product_id + '" value="0" ' + radio_selected + ' /> '
						+ wcsatt_add_to_cart_variation_params.none
						+ '</label>'
					+ '</li>';

				}

				$.each ( variation.subscription_schemes, function( key, value ) {
					var scheme_id          = value.id,
							position           = value.position,
							discount           = value.subscription_discount,
							period             = value.subscription_period,
							period_interval    = value.subscription_period_interval,
							sub_length         = value.subscription_length,
							sub_price          = value.subscription_price,
							sub_pricing_method = value.subscription_pricing_method,
							sub_regular_price  = value.subscription_regular_price,
							sub_sale_price     = value.subscription_sale_price,
							radio_selected     = '',
							previous_price     = '';

					var data = {
						'current_price'      : current_price,
						'regular_price'      : regular_price,
						'discount'           : discount,
						'period'             : period,
						'period_interval'    : period_interval,
						'sub_length'         : sub_length,
						'sub_price'          : sub_price,
						'sub_pricing_method' : sub_pricing_method,
						'sub_regular_price'  : sub_regular_price,
						'sub_sale_price'     : sub_sale_price
					};

					// Get the price for this option
					var get_price = get_sub_option_price(data);

					// Get the price string for this option
					var get_price_string = get_sub_option_price_string(data);

					if ( force_subscription == 'yes' && key == 0 ) {
						checked = true;
						if ( checked ) {
							radio_selected = 'checked="checked" ';
						} else {
							radio_selected = '';
						}
					} else {
						if ( key == 0 ) {
							checked = true;
							if ( checked ) {
								radio_selected = 'checked="checked" ';
							}
						}
					}

					// Where does the currency symbol go? Before or After the price
					if ( currency_pos == 'left' ) {
						previous_price = currency_symbol + current_price;
					} else if ( currency_pos == 'right' ) {
						previous_price = current_price + currency_symbol;
					}

					// Create new option
					sub_html = sub_html + '<li>'
						+ '<label>'
						+ '<input type="radio" name="convert_to_sub_' + product_id + '" value="' + scheme_id + '" ' + radio_selected + ' /> '
						+ '<del><span class="amount">' + previous_price + '</span></del> ' // Original Display Price
						+ get_price
						+ get_price_string
						+ '</label>'
						+ '</li>';

				} ); // END each subscription schemes

				sub_html = sub_html + '</ul>'; // Close the list of options

				// Place all price options for this variation.
				$('.woocommerce-variation-price').html(sub_html);

			} // END if variation has subscrition schemes

		} // END if variation is subscriable

	} );

	/**
	 * Returns the price for the subscription option.
	 */
	function get_sub_option_price( values ) {
		var current_price       = values.current_price, // The current price of the product.
				regular_price       = values.regular_price, // The regular price of the product.
				subscription_price  = values.sub_price, // This is empty if the pricing method is set to inherit the base price.
				pricing_method      = values.sub_pricing_method, // Inherit or Override
				discount            = values.discount, // Percentage of the discount based on the base price.
				sub_regular_price   = values.sub_regular_price, // Subscription Regular Price.
				sub_sale_price      = values.sub_sale_price; // Subscription Sale Price.

		var price = '';

		// Identify the pricing method to determin the subscription option price.
		switch( pricing_method ) {
			case 'override':
				// If the sale price is available and does match the subscription price.
				if ( sub_sale_price && sub_sale_price == subscription_price ) {
					subscription_price = sub_sale_price;
				} else if ( sub_regular_price && sub_regular_price == subscription_price ) {
					// If the regular price is available and does match the subscription price.
					subscription_price = sub_regular_price;
				}

				break;

			case 'inherit':
			default:
				// The regular price times divided by 100 times the discount and rounded up to a new fixed value.
				var discount_price = round( regular_price / 100 * discount, wcsatt_add_to_cart_variation_params.price_decimals );
				subscription_price = round( regular_price - discount_price, wcsatt_add_to_cart_variation_params.price_decimals );

				break;
		}

		price = subscription_price; // Update the price.

		// Where does the currency symbol go? Before or After the price
		if ( currency_pos == 'left' ) {
			price = currency_symbol + price;
		} else if ( currency_pos == 'right' ) {
			price = price + currency_symbol;
		}

		return '<ins><span class="amount">' + price + '</span></ins> ';
	};

	/**
	 * This rounds up the number for a fixed return value.
	 *
	 * @source http://stackoverflow.com/questions/1726630/formatting-a-number-with-exactly-two-decimals-in-javascript
	 */
	function round(value) {
		value = +value;

		if(isNaN(value))
			return NaN;

		// Shift
		value = value.toString().split('e');
		value = Math.round(+(value[0] + 'e' + (value[1] ? (+value[1] + 2) : 2)));

		// Shift back
		value = value.toString().split('e');
		return (+(value[0] + 'e' + (value[1] ? (+value[1] - 2) : -2))).toFixed(2);
	}

	/**
	 * Returns a string representing the details of the subscription option terms.
	 *
	 * For example "Per Month for 3 Months".
	 */
	function get_sub_option_price_string( values ) {
		var billing_interval    = values.period_interval,
				billing_period      = values.period,
				subscription_length = values.sub_length;

		var the_periods = {
			2 : '2' + nd_intervals,
			3 : '3' + rd_intervals,
			4 : '4' + th_intervals,
			5 : '5' + th_intervals,
			6 : '6' + th_intervals
		};

		switch (billing_period) {
			case 'day':
				if ( billing_interval > 1 ) {
					var subscription_period = days;
				} else {
					var subscription_period = day;
				}

				break;

			case 'week':
				if ( billing_interval > 1 ) {
					var subscription_period = weeks;
				} else {
					var subscription_period = week;
				}

				break;

			case 'month':
				if ( billing_interval > 1 ) {
					var subscription_period = months;
				} else {
					var subscription_period = month;
				}

				break;

			case 'year':
				if ( billing_interval > 1 ) {
					var subscription_period = years;
				} else {
					var subscription_period = year;
				}

				break;
		}

		var subscription_string = '<span class="subscription-details">';

		if ( subscription_period  ) {
			if ( subscription_length != billing_interval ) {
				// Note: If customer is billed more than per interval: "Every 2 months"
				if ( billing_interval > 1 ) {
					subscription_string += every + ' ' + billing_interval + ' ' + subscription_period;
				} else {
					// Note: If customer is billed each period: "Per month"
					subscription_string += ' / ' + subscription_period;
				}
			} else {
				// Note: Billing period: e.g. "every week"
				subscription_string += every + ' ' + subscription_period;
			}
		}

		subscription_string += '</span>';

		return subscription_string;
	};

})( jQuery, window, document );
