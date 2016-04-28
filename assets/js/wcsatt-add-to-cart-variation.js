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

		console.log(variation.subscription_schemes); // Returns the subscription schemes data.
	} )

})( jQuery, window, document );
