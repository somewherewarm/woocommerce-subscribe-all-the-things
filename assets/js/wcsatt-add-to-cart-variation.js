;(function ( $, window, document, undefined ) {

	var form                  = $(this),
			single_variation      = form.find( '.single_variation' ),
			product               = form.closest( '.product' ),
			product_id            = parseInt( form.data( 'product_id' ), 10 ),
			product_variations    = form.data( 'product_variations' ),
			reset_variations      = form.find( '.reset_variations' ),
			template              = wp.template( 'variation-template' ),
			unavailable_template  = wp.template( 'unavailable-variation-template' ),
			single_variation_wrap = form.find( '.single_variation_wrap' );

	// When the variation is revealed
	form.on( 'show_variation', function( event, variation ) {
		event.preventDefault();

		$.each ( variation.subscription_schemes, function( key, value ) {
			var scheme_id          = value.id;
			var position           = value.position;
			var discount           = value.subscription_discount;
			var period             = value.subscription_period;
			var period_interval    = value.subscription_period_interval;
			var sub_price          = value.subscription_price;
			var sub_pricing_method = value.subscription_pricing_method;
			var sub_regular_price  = value.subscription_regular_price;
			var sub_sale_price     = value.subscription_sale_price;

			console.log('ID: ' + scheme_id);
			console.log('Position: ' + position);
			console.log('Discount: ' + discount);
			console.log('Period Type: ' + period);
			console.log('Period Intevral: ' + period_interval);
			console.log('Subscription Price: ' + sub_price);
			console.log('Subscription Pricing Method: ' + sub_pricing_method);
			console.log('Subscription Regular Price: ' + sub_regular_price);
			console.log('Subscription Sale Price: ' + sub_sale_price);

		} );

	} )

})( jQuery, window, document );
