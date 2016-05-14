<?php
/**
 * Templating and styling functions.
 *
 * @class 	WCS_ATT_Display
 * @version 1.0.3
 */

class WCS_ATT_Display {

	public static $bypass_price_html_filter = false;

	public static function init() {

		// Enqueue scripts and styles.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'frontend_scripts' ) );

		// Display a "Subscribe to Cart" section in the cart.
		add_action( 'woocommerce_before_cart_totals', array( __CLASS__, 'show_subscribe_to_cart_prompt' ) );

		// Use radio buttons to mark a cart item as a one-time sale or as a subscription.
		add_filter( 'woocommerce_cart_item_price', array( __CLASS__, 'convert_to_sub_cart_item_options' ), 1000, 3 );

		// Display subscription options in the single-product template.
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'convert_to_sub_product_options' ), 100 );

		// Add subscription price string info to simple products with attached subscription schemes.
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'filter_price_html' ), 1000, 2 );

		// Render simple product subscription options in the single-product template.
		add_action( 'wcsatt_single_product_options_simple', array( __CLASS__, 'convert_to_sub_simple_product_options' ) );
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
	 * Render simple product subscription options in the single-product template.
	 *
	 * @param  WC_Product_Simple  $product
	 * @return string
	 */
	public static function convert_to_sub_simple_product_options( $product ) {
		echo self::get_convert_to_sub_product_options_content( $product );
	}

	/**
	 * Options for purchasing a product once or creating a subscription from it.
	 *
	 * @param  WC_Product  $product
	 * @return void
	 */
	public static function get_convert_to_sub_product_options_content( $product ) {

		$content = '';

		$product_level_schemes       = WCS_ATT_Schemes::get_product_subscription_schemes( $product );
		$show_convert_to_sub_options = apply_filters( 'wcsatt_show_single_product_options', ! empty( $product_level_schemes ), $product );

		// Allow one-time purchase option?
		$allow_one_time_option = true;

		if ( ! empty( $product_level_schemes ) ) {

			$force_subscription = get_post_meta( $product->id, '_wcsatt_force_subscription', true );
			$default_status     = get_post_meta( $product->id, '_wcsatt_default_status', true );

			if ( $force_subscription === 'yes' ) {
				$allow_one_time_option = false;
			}

			$price_overrides_exist       = false;
			$scheme_prices               = array();

			$options                     = array();
			$default_subscription_scheme = current( $product_level_schemes );

			foreach ( $product_level_schemes as $subscription_scheme ) {
				$overridden_prices = WCS_ATT_Schemes::get_subscription_scheme_prices( $product, $subscription_scheme );
				if ( ! empty( $overridden_prices ) ) {
					$price_overrides_exist                         = true;
					$scheme_prices[ $subscription_scheme[ 'id' ] ] = $overridden_prices;
				}
			}

			if ( $allow_one_time_option && $default_status !== 'subscription' ) {
				$default_subscription_scheme_id = '0';
			} else {
				$default_subscription_scheme_id = $default_subscription_scheme[ 'id' ];
			}

			$default_subscription_scheme_id = apply_filters( 'wcsatt_get_default_subscription_scheme_id', $default_subscription_scheme_id, $product_level_schemes, $allow_one_time_option, $product );

			if ( $allow_one_time_option ) {
				$options[] = array(
					'id'          => '0',
					'description' => _x( 'None', 'product subscription selection - negative response', WCS_ATT::TEXT_DOMAIN ),
					'selected'    => $default_subscription_scheme_id === '0',
				);
			}

			foreach ( $product_level_schemes as $subscription_scheme ) {

				$subscription_scheme_id = $subscription_scheme[ 'id' ];

				$_cloned = clone $product;

				$_cloned->is_converted_to_sub          = 'yes';
				$_cloned->subscription_period          = $subscription_scheme[ 'subscription_period' ];
				$_cloned->subscription_period_interval = $subscription_scheme[ 'subscription_period_interval' ];
				$_cloned->subscription_length          = $subscription_scheme[ 'subscription_length' ];

				if ( $price_overrides_exist && isset( $scheme_prices[ $subscription_scheme_id ] ) ) {
					$_cloned->regular_price            = $scheme_prices[ $subscription_scheme_id ][ 'regular_price' ];
					$_cloned->price                    = $scheme_prices[ $subscription_scheme_id ][ 'price' ];
					$_cloned->sale_price               = $scheme_prices[ $subscription_scheme_id ][ 'sale_price' ];
					$_cloned->subscription_price       = $scheme_prices[ $subscription_scheme_id ][ 'price' ];
				}

				self::$bypass_price_html_filter = true;

				$sub_suffix = WC_Subscriptions_Product::get_price_string( $_cloned, array(
					'subscription_price' => $price_overrides_exist,
					'price'              => $_cloned->get_price_html(),
				) );

				self::$bypass_price_html_filter = false;

				$options[] = array(
					'id'          => $subscription_scheme_id,
					'description' => ucfirst( $allow_one_time_option ? sprintf( __( '%s', 'product subscription selection - positive response', WCS_ATT::TEXT_DOMAIN ), $sub_suffix ) : $sub_suffix ),
					'selected'    => $default_subscription_scheme_id === $subscription_scheme_id,
				);
			}

			// If there's just one option to display, it means that one-time purchases are not allowed and there's only one sub scheme on offer -- so don't show any options
			if ( count( $options ) === 1 ) {
				return false;
			}

			if ( $prompt = get_post_meta( $product->id, '_wcsatt_subscription_prompt', true ) ) {
				$prompt = wpautop( do_shortcode( wp_kses_post( $prompt ) ) );
			}

			ob_start();

			wc_get_template( 'product-options.php', array(
				'product'        => $product,
				'options'        => $options,
				'allow_one_time' => $allow_one_time_option,
				'prompt'         => $prompt,
			), false, WCS_ATT()->plugin_path() . '/templates/' );

			$content = ob_get_clean();
		}

		return $content;
	}

	/**
	 * Displays single-product options for purchasing a product once or creating a subscription from it.
	 *
	 * @return void
	 */
	public static function convert_to_sub_product_options() {

		global $product;

		?><div class="wcsatt-options-wrapper"><?php

			do_action( 'wcsatt_single_product_options_' . $product->product_type, $product );

		?></div><?php
	}

	/**
	 * Displays cart item options for purchasing a product once or creating a subscription from it.
	 *
	 * @param  string $price
	 * @param  array  $cart_item
	 * @param  string $cart_item_key
	 * @return string
	 */
	public static function convert_to_sub_cart_item_options( $price, $cart_item, $cart_item_key ) {

		$subscription_schemes        = WCS_ATT_Schemes::get_cart_item_subscription_schemes( $cart_item );
		$show_convert_to_sub_options = apply_filters( 'wcsatt_show_cart_item_options', ! empty( $subscription_schemes ), $cart_item, $cart_item_key );

		$is_mini_cart                = did_action( 'woocommerce_before_mini_cart' ) && ! did_action( 'woocommerce_after_mini_cart' );

		// currently show options only in cart
		if ( ! is_cart() || $is_mini_cart ) {
			return $price;
		}

		if ( ! $show_convert_to_sub_options ) {
			return $price;
		}

		// Allow one-time purchase option?
		$allow_one_time_option = true;
		$product_level_schemes = WCS_ATT_Schemes::get_subscription_schemes( $cart_item, 'cart-item' );

		if ( ! empty( $product_level_schemes ) ) {
			$force_subscription = get_post_meta( $cart_item[ 'product_id' ], '_wcsatt_force_subscription', true );
			if ( $force_subscription === 'yes' ) {
				$allow_one_time_option = false;
			}
		}

		$price_overrides_exist         = WCS_ATT_Schemes::subscription_price_overrides_exist( $subscription_schemes );
		$reset_product                 = wc_get_product( $cart_item[ 'product_id' ] );
		$options                       = array();
		$active_subscription_scheme_id = WCS_ATT_Schemes::get_active_subscription_scheme_id( $cart_item );

		if ( $allow_one_time_option ) {
			if ( $price_overrides_exist ) {
				if ( $active_subscription_scheme_id === '0' ) {
					$description = $price;
				} else {
					$description = WC()->cart->get_product_price( $reset_product );
				}
			} else {
				$description = __( 'only now', WCS_ATT::TEXT_DOMAIN );
			}

			$options[] = array(
				'id'          => '0',
				'description' => $description,
				'selected'    => $active_subscription_scheme_id === '0',
			);
		}

		foreach ( $subscription_schemes as $subscription_scheme ) {

			$subscription_scheme_id = $subscription_scheme[ 'id' ];

			if ( $price_overrides_exist ) {
				if ( $active_subscription_scheme_id === $subscription_scheme_id ) {
					$description = $price;
				} else {
					$converted_product         = clone $reset_product;
					$dummy_cart_item           = $cart_item;
					$dummy_cart_item[ 'data' ] = $converted_product;

					// Apply the subscription scheme id to the dummy cart item.
					$dummy_cart_item[ 'wccsub_data' ][ 'active_subscription_scheme_id' ] = $subscription_scheme_id;
					// Convert the dummy cart item using the applied id.
					$dummy_cart_item           = WCS_ATT_Cart::convert_to_sub( $dummy_cart_item );

					// Get the price of the dummy cart item.
					$description = WC()->cart->get_product_price( $dummy_cart_item[ 'data' ] );
				}
			} else {
				if ( $active_subscription_scheme_id === $subscription_scheme_id ) {

					$description = WC_Subscriptions_Product::get_price_string( $cart_item[ 'data' ], array(
						'subscription_price' => false
					) );

				} else {

					$converted_product         = clone $reset_product;
					$dummy_cart_item           = $cart_item;
					$dummy_cart_item[ 'data' ] = $converted_product;

					// Apply the subscription scheme id to the dummy cart item.
					$dummy_cart_item[ 'wccsub_data' ][ 'active_subscription_scheme_id' ] = $subscription_scheme_id;
					// Convert the dummy cart item using the applied id.
					$dummy_cart_item           = WCS_ATT_Cart::convert_to_sub( $dummy_cart_item );

					// Use the dummy cart item to obtain the description.
					$description = WC_Subscriptions_Product::get_price_string( $dummy_cart_item[ 'data' ], array(
						'subscription_price' => false
					) );
				}
			}

			$options[] = array(
				'id'          => $subscription_scheme_id,
				'description' => $description,
				'selected'    => $active_subscription_scheme_id === $subscription_scheme_id,
			);
		}

		// If there's just one option to display, it means that one-time purchases are not allowed and there's only one sub scheme on offer -- so don't show any options
		if ( count( $options ) === 1 ) {
			return $price;
		}

		// Grab price without Subs formatting
		remove_filter( 'woocommerce_cart_product_price', 'WC_Subscriptions_Cart' . '::cart_product_price', 10, 2 );
		remove_filter( 'woocommerce_cart_item_price', __CLASS__ . '::convert_to_sub_cart_item_options', 1000, 3 );
		$price = apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $cart_item[ 'data' ] ), $cart_item, $cart_item_key );
		add_filter( 'woocommerce_cart_item_price', __CLASS__ . '::convert_to_sub_cart_item_options', 1000, 3 );
		add_filter( 'woocommerce_cart_product_price', 'WC_Subscriptions_Cart' . '::cart_product_price', 10, 2 );

		ob_start();

		$classes = $price_overrides_exist ? array( 'overrides_exist' ) : array();

		wc_get_template( 'cart-item-options.php', array(
			'options'       => $options,
			'cart_item_key' => $cart_item_key,
			'classes'       => implode( ' ', $classes ),
		), false, WCS_ATT()->plugin_path() . '/templates/' );

		$convert_to_sub_options = ob_get_clean();

		if ( $price_overrides_exist ) {
			$price = $convert_to_sub_options;
		} else {
			$price = $price . $convert_to_sub_options;
		}

		return $price;
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
			<ul class="wcsatt-options-cart"><?php

				$options                       = array();
				$active_subscription_scheme_id = WCS_ATT_Schemes::get_active_cart_subscription_scheme_id();

				$options[] = array(
					'id'          => '0',
					'description' => _x( 'No thanks.', 'cart subscription selection - negative response', WCS_ATT::TEXT_DOMAIN ),
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
						'description' => sprintf( __( 'Yes, %s.', 'cart subscription selection - positive response', WCS_ATT::TEXT_DOMAIN ), $sub_suffix ),
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
	 * Adds subscription price string info to products with attached subscription schemes.
	 *
	 * @param  string     $price
	 * @param  WC_Product $product
	 * @return string
	 */
	public static function filter_price_html( $price, $product ) {

		if ( self::$bypass_price_html_filter ) {
			return $price;
		}

		if ( $product->get_price() === '' ) {
			return $price;
		}

		$product_level_schemes = WCS_ATT_Schemes::get_product_subscription_schemes( $product );

		if ( ! empty( $product_level_schemes ) ) {

			$force_subscription  = get_post_meta( $product->id, '_wcsatt_force_subscription', true );
			$subscription_scheme = current( $product_level_schemes );

			if ( 'variable' === $product->product_type ) {

				$prices = $product->get_variation_prices( true );

				if ( empty( $prices ) ) {
					return $price;
				}

				$min_variation_price    = current( $prices[ 'price' ] );
				$max_variation_price    = end( $prices[ 'price' ] );

				$variation_ids          = array_keys( $prices[ 'price' ] );
				$min_price_variation_id = current( $variation_ids );
				$min_price_variation    = wc_get_product( $min_price_variation_id );
			}

			$show_from_string = false;

			if ( count( $product_level_schemes ) > 1 ) {
				$show_from_string = true;
			} elseif ( 'variable' === $product->product_type && $min_variation_price !== $max_variation_price ) {
				$show_from_string = true;
				// If all variations prices are overridden, they will be equal, so don't show a "From" prefix.
				if ( isset( $subscription_scheme[ 'subscription_pricing_method' ] ) && $subscription_scheme[ 'subscription_pricing_method' ] === 'override' ) {
					$show_from_string = false;
				}
			}

			if ( $force_subscription === 'yes' ) {

				$suffix = '';

				if ( 'variable' === $product->product_type ) {
					$_product = $min_price_variation;
				} else {
					$_product = clone $product;
				}

				$_product->is_converted_to_sub          = 'yes';
				$_product->subscription_period          = $subscription_scheme[ 'subscription_period' ];
				$_product->subscription_period_interval = $subscription_scheme[ 'subscription_period_interval' ];
				$_product->subscription_length          = $subscription_scheme[ 'subscription_length' ];

				$overridden_prices = WCS_ATT_Schemes::get_subscription_scheme_prices( $_product, $subscription_scheme );

				if ( ! empty( $overridden_prices ) ) {
					$_product->regular_price            = $overridden_prices[ 'regular_price' ];
					$_product->price                    = $overridden_prices[ 'price' ];
					$_product->sale_price               = $overridden_prices[ 'sale_price' ];
					$_product->subscription_price       = $overridden_prices[ 'price' ];
				}

				self::$bypass_price_html_filter = true;
				$price = $_product->get_price_html();
				self::$bypass_price_html_filter = false;

				$price = WC_Subscriptions_Product::get_price_string( $_product, array( 'price' => $price ) );

				if ( $show_from_string && false === strpos( $price, $_product->get_price_html_from_text() ) ) {
					$price = sprintf( _x( '%1$s%2$s', 'Price range: from', WCS_ATT::TEXT_DOMAIN ), $_product->get_price_html_from_text(), $price );
				}

			} else {

				$price_overrides_exist = WCS_ATT_Schemes::subscription_price_overrides_exist( $product_level_schemes );

				if ( $price_overrides_exist ) {

					if ( 'variable' === $product->product_type ) {
						$_product = $min_price_variation;
					} else {
						$_product = clone $product;
					}

					$from_price               = '';
					$lowest_scheme_price_data = WCS_ATT_Schemes::get_lowest_price_subscription_scheme_data( $_product, $product_level_schemes );

					if ( $lowest_scheme_price_data ) {

						$lowest_scheme                          = $lowest_scheme_price_data[ 'scheme' ];

						$_product->is_converted_to_sub          = 'yes';
						$_product->subscription_period          = $lowest_scheme[ 'subscription_period' ];
						$_product->subscription_period_interval = $lowest_scheme[ 'subscription_period_interval' ];
						$_product->subscription_length          = $lowest_scheme[ 'subscription_length' ];

						$_product->price                        = $lowest_scheme_price_data[ 'price' ];
						$_product->sale_price                   = $lowest_scheme_price_data[ 'sale_price' ];
						$_product->regular_price                = $lowest_scheme_price_data[ 'regular_price' ];

						self::$bypass_price_html_filter         = true;
						$lowest_scheme_price_html               = $_product->get_price_html();
						$lowest_scheme_price_html               = WC_Subscriptions_Product::get_price_string( $_product, array( 'price' => $lowest_scheme_price_html ) );
						self::$bypass_price_html_filter         = false;

						if ( $show_from_string ) {
							$from_price = sprintf( _x( '%1$s%2$s', 'Price range: from', WCS_ATT::TEXT_DOMAIN ), _x( '<span class="from">from </span>', 'min-price: multiple plans available', WCS_ATT::TEXT_DOMAIN ), $lowest_scheme_price_html );
						} else {
							$from_price = sprintf( _x( '%1$s%2$s', 'Price range: from', WCS_ATT::TEXT_DOMAIN ), _x( '<span class="for">for </span>', 'min-price: 1 plan available', WCS_ATT::TEXT_DOMAIN ), $lowest_scheme_price_html );
						}
					}
				}

				if ( $price_overrides_exist ) {
					$suffix = ' <small class="wcsatt-sub-options">' . sprintf( _n( '&ndash; or subscribe %s', '&ndash; or subscribe %s', count( $product_level_schemes ), WCS_ATT::TEXT_DOMAIN ), $from_price ) . '</small>';
				} else {
					$suffix = ' <small class="wcsatt-sub-options">' . sprintf( _n( '&ndash; subscription available', '&ndash; subscription plans available', count( $product_level_schemes ), WCS_ATT::TEXT_DOMAIN ), $from_price ) . '</small>';
				}

				$price  = sprintf( _x( '%1$s%2$s', 'price html sub options suffix', WCS_ATT::TEXT_DOMAIN ), $price, $suffix );
			}
		}

		return $price;
	}
}

WCS_ATT_Display::init();
