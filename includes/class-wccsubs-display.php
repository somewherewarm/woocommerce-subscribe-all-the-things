<?php
/**
 * Templating and styling functions.
 *
 * @class 	WCCSubs_Display
 * @version 1.0.0
 */

class WCCSubs_Display {

	public static function init() {

		// Enqueue scripts and styles
		add_action( 'wp_enqueue_scripts', __CLASS__ . '::frontend_scripts' );

		// Display a "Subscribe to Cart" section in the cart
		add_action( 'woocommerce_before_cart_totals', __CLASS__ . '::show_subscribe_to_cart_prompt' );
	}

	/**
	 * Front end styles and scripts.
	 *
	 * @return void
	 */
	public static function frontend_scripts() {

		wp_register_style( 'wccsubs-css', WCCSubs()->plugin_url() . '/assets/css/wccsubs-frontend.css', false, WCCSubs::VERSION, 'all' );
		wp_enqueue_style( 'wccsubs-css' );
	}

	/**
	 * Show a "Subscribe to Cart" section in the cart.
	 * Visible only when all cart items have a common 'cart/order' subscription scheme.
	 *
	 * @return void
	 */
	public static function show_subscribe_to_cart_prompt() {

		// Show cart/order level options only if all cart items share a common cart/order level subscription scheme.
		if ( WCCSubs_Schemes::get_cart_subscription_schemes() ) {

			?>
			<h2><?php _e( 'Subscribe to Cart', WCCSubs::TEXT_DOMAIN ); ?></h2>
			<?php
		}
	}

}

WCCSubs_Display::init();
