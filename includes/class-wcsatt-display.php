<?php
/**
 * Templating and styling functions.
 *
 * @class 	WCS_ATT_Display
 * @version 1.0.0
 */

class WCS_ATT_Display {

	public static function init() {

		// Enqueue scripts and styles
		add_action( 'wp_enqueue_scripts', __CLASS__ . '::frontend_scripts' );

		// Display a "Subscribe to Cart" section in the cart
		add_action( 'woocommerce_before_cart_totals', __CLASS__ . '::show_subscribe_to_cart_prompt' );

		// Use radio buttons to mark a cart item as a one-time sale or as a subscription
		add_filter( 'woocommerce_cart_item_subtotal', __CLASS__ . '::convert_to_sub_options', 1000, 3 );

		// Display subscription options in the single-product template
		add_action( 'woocommerce_before_add_to_cart_button',  __CLASS__ . '::convert_to_sub_product_options', 100 );

		// Add subscription price string info to products with attached subscription schemes
		add_filter( 'woocommerce_get_price_html',  __CLASS__ . '::filter_price_html', 1000, 2 );
	}

	/**
	 * Front end styles and scripts.
	 *
	 * @return void
	 */
	public static function frontend_scripts() {

		wp_register_style( 'wcsatt-css', WCS_ATT()->plugin_url() . '/assets/css/wcsatt-frontend.css', false, WCS_ATT::VERSION, 'all' );
		wp_enqueue_style( 'wcsatt-css' );

		if ( is_cart() ) {
			wp_register_script( 'wcsatt-cart', WCS_ATT()->plugin_url() . '/assets/js/wcsatt-cart.js', array( 'jquery', 'wc-country-select', 'wc-address-i18n' ), WCS_ATT::VERSION, true );
		}

		wp_enqueue_script( 'wcsatt-cart' );

		$params = array(
			'update_cart_option_nonce' => wp_create_nonce( 'wcsatt_update_cart_option' ),
			'wc_ajax_url'              => WCS_ATT_Core_Compatibility::is_wc_version_gte_2_4() ? WC_AJAX::get_endpoint( "%%endpoint%%" ) : WC()->ajax_url(),
		);

		wp_localize_script( 'wcsatt-cart', 'wcsatt_cart_params', $params );
	}

	/**
	 * Displays signle-prouct options for purchasing a product once or creating a subscription from it.
	 *
	 * @return void
	 */
	public static function convert_to_sub_product_options() {

		global $product;

		$subscription_schemes          = WCS_ATT_Schemes::get_product_subscription_schemes( $product );
		$show_convert_to_sub_options   = apply_filters( 'wcsatt_show_single_product_options', ! empty( $subscription_schemes ), $product );

		// Allow one-time purchase option?
		$allow_one_time_option         = true;
		$has_product_level_schemes     = empty( $subscription_schemes ) ? false : true;

		if ( $has_product_level_schemes ) {

			$force_subscription = get_post_meta( $product->id, '_wcsatt_force_subscription', true );
			$default_status     = get_post_meta( $product->id, '_wcsatt_default_status', true );

			if ( $force_subscription === 'yes' ) {
				$allow_one_time_option = false;
			}

			$options                     = array();
			$default_subscription_scheme = current( $subscription_schemes );

			if ( $allow_one_time_option && $default_status !== 'subscription' ) {
				$default_subscription_scheme_id = '0';
			} else {
				$default_subscription_scheme_id = $default_subscription_scheme[ 'id' ];
			}

			$default_subscription_scheme_id = apply_filters( 'wcsatt_get_default_subscription_scheme_id', $default_subscription_scheme_id, $subscription_schemes, $allow_one_time_option, $product );

			if ( $allow_one_time_option ) {
				$options[] = array(
					'id'          => '0',
					'description' => __( 'No, thanks.', WCS_ATT::TEXT_DOMAIN ),
					'selected'    => $default_subscription_scheme_id === '0',
				);
			}

			foreach ( $subscription_schemes as $subscription_scheme ) {

				$subscription_scheme_id = $subscription_scheme[ 'id' ];

				$_cloned = clone $product;

				$_cloned->is_converted_to_sub          = 'yes';
				$_cloned->subscription_period          = $subscription_scheme[ 'subscription_period' ];
				$_cloned->subscription_period_interval = $subscription_scheme[ 'subscription_period_interval' ];
				$_cloned->subscription_length          = $subscription_scheme[ 'subscription_length' ];

				$sub_suffix = WC_Subscriptions_Product::get_price_string( $_cloned, array( 'subscription_price' => false ) );

				$options[] = array(
					'id'          => $subscription_scheme_id,
					'description' => $allow_one_time_option ? sprintf( __( 'Yes, bill me %s.', WCS_ATT::TEXT_DOMAIN ), $sub_suffix ) : $sub_suffix,
					'selected'    => $default_subscription_scheme_id === $subscription_scheme_id,
				);
			}

			// If there's just one option to display, it means that one-time purchases are not allowed and there's only one sub scheme on offer -- so don't show any options
			if ( count( $options ) === 1 ) {
				return false;
			}

			wc_get_template( 'product-options.php', array(
				'product'        => $product,
				'options'        => $options,
				'allow_one_time' => $allow_one_time_option,
			), false, WCS_ATT()->plugin_path() . '/templates/' );
		}
	}

	/**
	 * Displays cart item options for purchasing a product once or creating a subscription from it.
	 *
	 * @param  string $subtotal
	 * @param  array  $cart_item
	 * @param  string $cart_item_key
	 * @return string
	 */
	public static function convert_to_sub_options( $subtotal, $cart_item, $cart_item_key ) {

		$subscription_schemes        = WCS_ATT_Schemes::get_cart_item_subscription_schemes( $cart_item );
		$show_convert_to_sub_options = apply_filters( 'wcsatt_show_cart_item_options', ! empty( $subscription_schemes ), $cart_item, $cart_item_key );

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
		$has_product_level_schemes     = empty( WCS_ATT_Schemes::get_subscription_schemes( $cart_item, 'cart-item' ) ) ? false : true;

		if ( $has_product_level_schemes ) {
			$force_subscription = get_post_meta( $cart_item[ 'product_id' ], '_wcsatt_force_subscription', true );
			if ( $force_subscription === 'yes' ) {
				$allow_one_time_option = false;
			}
		}

		$options                       = array();
		$active_subscription_scheme_id = WCS_ATT_Schemes::get_active_subscription_scheme_id( $cart_item );

		if ( $allow_one_time_option ) {
			$options[] = array(
				'id'          => '0',
				'description' => __( 'only this time', WCS_ATT::TEXT_DOMAIN ),
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

		wc_get_template( 'cart-item-options.php', array(
			'options'       => $options,
			'cart_item_key' => $cart_item_key
		), false, WCS_ATT()->plugin_path() . '/templates/' );

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
		if ( $subscription_schemes = WCS_ATT_Schemes::get_cart_subscription_schemes() ) {

			?>
			<h2><?php _e( 'Cart Subscription', WCS_ATT::TEXT_DOMAIN ); ?></h2>
			<p><?php _e( 'Interested in subscribing to these items?', WCS_ATT::TEXT_DOMAIN ); ?></p>
			<ul class="wcsatt-convert-cart"><?php

				$options                       = array();
				$active_subscription_scheme_id = WCS_ATT_Schemes::get_active_cart_subscription_scheme_id();

				$options[] = array(
					'id'          => '0',
					'description' => __( 'No thanks.', WCS_ATT::TEXT_DOMAIN ),
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
						'description' => sprintf( __( 'Yes, bill me %s.', WCS_ATT::TEXT_DOMAIN ), $sub_suffix ),
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

	/**
	 * Add subscription price string info to products with attached subscription schemes.
	 *
	 * @param  string     $price
	 * @param  WC_Product $product
	 * @return string
	 */
	public static function filter_price_html( $price, $product ) {

		$subscription_schemes      = WCS_ATT_Schemes::get_product_subscription_schemes( $product );
		$has_product_level_schemes = empty( $subscription_schemes ) ? false : true;

		if ( $has_product_level_schemes ) {

			$force_subscription  = get_post_meta( $product->id, '_wcsatt_force_subscription', true );
			$subscription_scheme = current( $subscription_schemes );

			$_cloned = clone $product;

			$_cloned->is_converted_to_sub          = 'yes';
			$_cloned->subscription_period          = $subscription_scheme[ 'subscription_period' ];
			$_cloned->subscription_period_interval = $subscription_scheme[ 'subscription_period_interval' ];
			$_cloned->subscription_length          = $subscription_scheme[ 'subscription_length' ];

			if ( count( $subscription_schemes ) === 1 && $force_subscription === 'yes' ) {
				$price = WC_Subscriptions_Product::get_price_string( $_cloned, array( 'price' => $price ) );
			} elseif ( $force_subscription === 'yes' ) {
				$suffix = ' <small class="wcsatt-sub-options">' . __( '(sign-up required)', WCS_ATT::TEXT_DOMAIN ) . '</small>';
				$price  = sprintf( __( '%1$s%2$s', 'price html sub options suffix', WCS_ATT::TEXT_DOMAIN ), $price, $suffix );
			} else {
				$suffix = ' <small class="wcsatt-sub-options">' . __( '(includes sign-up options)', WCS_ATT::TEXT_DOMAIN ) . '</small>';
				$price  = sprintf( __( '%1$s%2$s', 'price html sub options suffix', WCS_ATT::TEXT_DOMAIN ), $price, $suffix );
			}
		}

		return $price;
	}
}

WCS_ATT_Display::init();
