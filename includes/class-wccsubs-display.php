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

		if ( is_cart() ) {
			wp_register_script( 'wccsubs-cart', WCCSubs()->plugin_url() . '/assets/js/wccsubs-cart.js', array( 'jquery', 'wc-country-select', 'wc-address-i18n' ), WCCSubs::VERSION, true );
		}

		wp_enqueue_script( 'wccsubs-cart' );

		$params = array(
			'update_cart_option_nonce' => wp_create_nonce( 'wccsubs_update_cart_option' ),
			'wc_ajax_url'              => WCCSubs_Core_Compatibility::is_wc_version_gte_2_4() ? WC_AJAX::get_endpoint( "%%endpoint%%" ) : WC()->ajax_url(),
		);

		wp_localize_script( 'wccsubs-cart', 'wccsubs_cart_params', $params );
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
		$show_convert_to_sub_options = apply_filters( 'wccsubs_show_cart_item_options', ! empty( $subscription_schemes ), $cart_item, $cart_item_key );

		$is_mini_cart                = did_action( 'woocommerce_before_mini_cart' ) && ! did_action( 'woocommerce_after_mini_cart' );

		// currently show options only in cart
		if ( ! is_cart() || $is_mini_cart ) {
			return $subtotal;
		}

		if ( ! $show_convert_to_sub_options ) {
			return $subtotal;
		}

		// Allow one-time purchase option?
		$allow_one_time_option         = true;
		$has_product_level_schemes     = empty( WCCSubs_Schemes::get_subscription_schemes( $cart_item, 'cart-item' ) ) ? false : true;

		if ( $has_product_level_schemes ) {
			$force_subscription = get_post_meta( $cart_item[ 'product_id' ], '_wccsubs_force_subscription', true );
			if ( $force_subscription === 'yes' ) {
				$allow_one_time_option = false;
			}
		}

		$options                       = array();
		$active_subscription_scheme_id = WCCSubs_Schemes::get_active_subscription_scheme_id( $cart_item );

		if ( $allow_one_time_option ) {
			$options[] = array(
				'id'          => '0',
				'description' => __( 'only this time', WCCSubs::TEXT_DOMAIN ),
				'selected'    => $active_subscription_scheme_id === '0',
			);
		}

		foreach ( $subscription_schemes as $subscription_scheme ) {

			$subscription_scheme_id = $subscription_scheme[ 'id' ];

			if ( $cart_item[ 'data' ]->is_converted_to_sub === 'yes' && $subscription_scheme_id === $active_subscription_scheme_id ) {

				// if the cart item is converted to a sub, create the subscription price suffix using the already-populated subscription properties
				$sub_suffix  = WC_Subscriptions_Product::get_price_string( $cart_item[ 'data' ], array( 'subscription_price' => false ) );

			} else {

				// if the cart item has not been converted to a sub, populate the product with the subscription properties, generate the suffix and reset
				$_cloned = clone $cart_item[ 'data' ];

				$_cloned->is_converted_to_sub              = 'yes';
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

		// If there's just one option to display, it means that one-time purchases are not allowed and there's only one sub scheme on offer -- so don't show any options
		if ( count( $options ) === 1 ) {
			return $subtotal;
		}

		// Grab subtotal without Subs formatting
		remove_filter( 'woocommerce_cart_product_subtotal', 'WC_Subscriptions_Cart' . '::get_formatted_product_subtotal', 11, 4 );
		remove_filter( 'woocommerce_cart_item_subtotal', __CLASS__ . '::convert_to_sub_options', 1000, 3 );
		$subtotal = apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $cart_item[ 'data' ], $cart_item[ 'quantity' ] ), $cart_item, $cart_item_key );
		add_filter( 'woocommerce_cart_item_subtotal', __CLASS__ . '::convert_to_sub_options', 1000, 3 );
		add_filter( 'woocommerce_cart_product_subtotal', 'WC_Subscriptions_Cart' . '::get_formatted_product_subtotal', 11, 4 );


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
		if ( $subscription_schemes = WCCSubs_Schemes::get_cart_subscription_schemes() ) {

			?>
			<h2><?php _e( 'Cart Subscription', WCCSubs::TEXT_DOMAIN ); ?></h2>
			<p><?php _e( 'Interested in subscribing to these items?', WCCSubs::TEXT_DOMAIN ); ?></h2>
			<ul class="wccsubs-convert-cart"><?php

				$options                       = array();
				$active_subscription_scheme_id = WCCSubs_Schemes::get_active_cart_subscription_scheme_id();

				$options[] = array(
					'id'          => '0',
					'description' => __( 'No &mdash; I will purchase again, if needed.', WCCSubs::TEXT_DOMAIN ),
					'selected'    => $active_subscription_scheme_id === '0',
				);

				foreach ( $subscription_schemes as $subscription_scheme ) {

					$subscription_scheme_id = $subscription_scheme[ 'id' ];

					$dummy_product                               = new WC_Product( '1' );
					$dummy_product->is_converted_to_sub          = 'yes';
					$dummy_product->subscription_period          = $subscription_scheme[ 'subscription_period' ];
					$dummy_product->subscription_period_interval = $subscription_scheme[ 'subscription_period_interval' ];
					$dummy_product->subscription_length          = $subscription_scheme[ 'subscription_length' ];

					$sub_suffix  = WC_Subscriptions_Product::get_price_string( $dummy_product, array( 'subscription_price' => false ) );

					$options[] = array(
						'id'          => $subscription_scheme[ 'id' ],
						'description' => sprintf( __( 'Yes &mdash; Bill me %s.', WCCSubs::TEXT_DOMAIN ), $sub_suffix ),
						'selected'    => $active_subscription_scheme_id === $subscription_scheme_id,
					);
				}

				foreach ( $options as $option ) {
					?><li>
						<label>
							<input type="radio" name="convert_to_sub" value="<?php echo $option[ 'id' ] ?>" <?php checked( $option[ 'selected' ], true, true ); ?> />
							<?php echo $option[ 'description' ]; ?>
						</label>
					</li><?php
				}

			?></ul>
			<?php
		}
	}

}

WCCSubs_Display::init();
