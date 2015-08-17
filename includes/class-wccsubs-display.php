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

		// Use radio buttons to mark a cart item as a one-time sale or as a subscription
		add_filter( 'woocommerce_cart_item_subtotal', __CLASS__ . '::convert_to_sub_options', 1000, 3 );
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
	 * Displays an option to purchase the item once or create a subscription from it.
	 *
	 * @param  string $subtotal
	 * @param  array  $cart_item
	 * @param  string $cart_item_key
	 * @return string
	 */
	public static function convert_to_sub_options( $subtotal, $cart_item, $cart_item_key ) {

		$subscription_schemes        = WCCSubs_Schemes::get_cart_item_subscription_schemes( $cart_item );
		$show_convert_to_sub_options = apply_filters( 'wccsub_show_cart_item_options', ! empty( $subscription_schemes ), $cart_item, $cart_item_key );

		// currently show options only in cart
		if ( ! is_cart() || ! $show_convert_to_sub_options ) {
			return $subtotal;
		}

		$options                       = array();
		$active_subscription_scheme    = WCCSubs_Schemes::get_active_subscription_scheme( $cart_item );
		$active_subscription_scheme_id = false !== $active_subscription_scheme ? $active_subscription_scheme[ 'id' ] : '0';

		$options[] = array(
			'id'          => '0',
			'description' => __( 'only this time', WCCSubs::TEXT_DOMAIN ),
			'selected'    => $active_subscription_scheme_id === '0',
		);

		foreach ( $subscription_schemes as $subscription_scheme_id => $subscription_scheme ) {

			if ( $cart_item[ 'data' ]->is_converted_to_sub === 'yes' && $subscription_scheme_id === $active_subscription_scheme_id ) {

				// if the cart item is converted to a sub, create the subscription price suffix using the already-populated subscription properties
				$cart_item[ 'data' ]->delete_subscription_price_suffix = 'no';
				$sub_suffix  = WC_Subscriptions_Product::get_price_string( $cart_item[ 'data' ], array( 'subscription_price' => false ) );
				$cart_item[ 'data' ]->delete_subscription_price_suffix = 'yes';

			} else {

				// if the cart item has not been converted to a sub, populate the product with the subscription properties, generate the suffix and reset
				$_cloned = clone $cart_item[ 'data' ];

				$_cloned->is_converted_to_sub              = 'yes';
				$_cloned->delete_subscription_price_suffix = 'no';
				$_cloned->subscription_period              = $subscription_scheme[ 'subscription_period' ];
				$_cloned->subscription_period_interval     = $subscription_scheme[ 'subscription_period_interval' ];
				$_cloned->subscription_length              = $subscription_scheme[ 'subscription_length' ];

				$sub_suffix = WC_Subscriptions_Product::get_price_string( $_cloned, array( 'subscription_price' => false ) );
			}

			$options[] = array(
				'id'          => $subscription_scheme_id,
				'description' => $sub_suffix,
				'selected'    => $active_subscription_scheme_id === $subscription_scheme_id,
			);
		}

		if ( empty( $options ) ) {
			return $subtotal;
		}

		ob_start();

		?><ul class="wccsubs-convert"><?php

			foreach ( $options as $option ) {
				?><li>
					<label>
						<input type="radio" name="cart[<?php echo $cart_item_key; ?>][convert_to_sub]" value="<?php echo $option[ 'id' ] ?>" <?php checked( $option[ 'selected' ], true, true ); ?> />
						<?php echo $option[ 'description' ]; ?>
					</label>
				</li><?php
			}

		?></ul><?php

		$convert_to_sub_options = ob_get_clean();

		$subtotal = $subtotal . $convert_to_sub_options;

		return $subtotal;
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
