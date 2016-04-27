<?php
/**
 * Templating and styling functions.
 *
 * @class 	WCS_ATT_Display
 * @version 1.0.4
 */

class WCS_ATT_Display {

	public static $bypass_price_html_filter = false;

	public static function init() {

		// Enqueue scripts and styles
		add_action( 'wp_enqueue_scripts', __CLASS__ . '::frontend_scripts' );

		// Display a "Subscribe to Cart" section in the cart
		add_action( 'woocommerce_before_cart_totals', __CLASS__ . '::show_subscribe_to_cart_prompt' );

		// Use radio buttons to mark a cart item as a one-time sale or as a subscription
		add_filter( 'woocommerce_cart_item_price', __CLASS__ . '::convert_to_sub_cart_item_options', 1000, 3 );

		// Display subscription options in the single-product template
		add_action( 'woocommerce_before_add_to_cart_button',  __CLASS__ . '::convert_to_sub_product_options', 100 );

		// Add subscription price string info to products with attached subscription schemes
		add_filter( 'woocommerce_get_price_html',  __CLASS__ . '::filter_price_html', 1000, 2 );

		// Adds the subscription scheme details to the variation form json
		add_filter( 'woocommerce_available_variation', __CLASS__ . '::add_variation_data', 10, 1 );
	}

	/**
	 * Adds the subscription data to the data product variations form json.
	 *
	 * @since  1.0.4
	 * @param  $variations
	 * @return array
	 */
	public static function add_variation_data( $variations ) {
		$variations[ 'is_subscibable' ] = self::is_subscribable( $variations[ 'variation_id'] );
		$variations[ 'subscription_schemes' ] = WCS_ATT_Schemes::get_product_subscription_schemes( $variations[ 'variation_id'], 'variation' );

		return $variations;
	}

	/**
	 * Checks if the variation is subscribable.
	 *
	 * @since  1.0.4
	 * @access public
	 * @param  int $post_id
	 * @return bool
	 */
	public static function is_subscribable( $post_id ) {
		$is_subscribable = get_post_meta( $post_id, '_subscribable', true );

		if ( isset( $is_subscribable ) && $is_subscribable == 'yes' ) {
			return true;
		}

		return false;
	}

	/**
	 * Front end styles and scripts.
	 *
	 * @return void
	 */
	public static function frontend_scripts() {

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_style( 'wcsatt-css', WCS_ATT()->plugin_url() . '/assets/css/wcsatt-frontend.css', false, WCS_ATT::VERSION, 'all' );
		wp_enqueue_style( 'wcsatt-css' );

		// Product Page
		if ( is_product() ) {
			wp_register_script( 'wcsatt-add-to-cart-variation', WCS_ATT()->plugin_url() . '/assets/js/wcsatt-add-to-cart-variation.js', array( 'jquery', 'wc-util' ), WCS_ATT::VERSION, true );
			wp_enqueue_script( 'wcsatt-add-to-cart-variation' );
		}

		// Cart Page
		if ( is_cart() ) {
			wp_register_script( 'wcsatt-cart', WCS_ATT()->plugin_url() . '/assets/js/wcsatt-cart' . $suffix . '.js', array( 'jquery', 'wc-country-select', 'wc-address-i18n' ), WCS_ATT::VERSION, true );
			wp_enqueue_script( 'wcsatt-cart' );

			$params = array(
				'update_cart_option_nonce' => wp_create_nonce( 'wcsatt_update_cart_option' ),
				'wc_ajax_url'              => WCS_ATT_Core_Compatibility::is_wc_version_gte_2_4() ? WC_AJAX::get_endpoint( "%%endpoint%%" ) : WC()->ajax_url(),
			);

			wp_localize_script( 'wcsatt-cart', 'wcsatt_cart_params', $params );
		}

	}

	/**
	 * Displays options for purchasing a single product once or creating a subscription from it.
	 *
	 * @return void
	 */
	public static function convert_to_sub_product_options() {

		global $product;

		// Check what product type this product is
		if ( $terms = wp_get_object_terms( $product->id, 'product_type' ) ) {
			$product_type = sanitize_title( current( $terms )->name );
		} else {
			$product_type = 'simple';
		}

		// If the product is a variable product then dont display the subscription options
		if ( $product_type == 'variable' ) return false;

		$post_id = $product->id; // Product ID

		$subscription_schemes        = WCS_ATT_Schemes::get_product_subscription_schemes( $post_id, $product_type );
		$show_convert_to_sub_options = apply_filters( 'wcsatt_show_single_product_options', ! empty( $subscription_schemes ), $product );

		// Allow one-time purchase option?
		$allow_one_time_option       = true;
		$has_product_level_schemes   = ! empty( $subscription_schemes );

		if ( $has_product_level_schemes ) {

			$force_subscription = get_post_meta( $product->id, '_wcsatt_force_subscription', true );
			$default_status     = get_post_meta( $product->id, '_wcsatt_default_status', true );

			if ( $force_subscription === 'yes' ) {
				$allow_one_time_option = false;
			}

			$price_overrides_exist       = false;
			$scheme_prices               = array();

			$options                     = array();
			$default_subscription_scheme = current( $subscription_schemes );

			foreach ( $subscription_schemes as $subscription_scheme ) {
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

			$default_subscription_scheme_id = apply_filters( 'wcsatt_get_default_subscription_scheme_id', $default_subscription_scheme_id, $subscription_schemes, $allow_one_time_option, $product );

			if ( $allow_one_time_option ) {
				$options[] = array(
					'id'          => '0',
					'description' => _x( 'None', 'product subscription selection - negative response', WCS_ATT::TEXT_DOMAIN ),
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

			wc_get_template( 'product-options.php', array(
				'product'        => $product,
				'options'        => $options,
				'allow_one_time' => $allow_one_time_option,
				'prompt'         => $prompt,
			), false, WCS_ATT()->plugin_path() . '/templates/' );
		}
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
		$allow_one_time_option     = true;
		$product_level_schemes     = WCS_ATT_Schemes::get_subscription_schemes( $cart_item, 'cart-item' );
		$has_product_level_schemes = empty( $product_level_schemes ) ? false : true;

		if ( $has_product_level_schemes ) {
			$force_subscription = get_post_meta( $cart_item[ 'product_id' ], '_wcsatt_force_subscription', true );
			if ( $force_subscription === 'yes' ) {
				$allow_one_time_option = false;
			}
		}

		$price_overrides_exist         = WCS_ATT_Schemes::subscription_price_overrides_exist( $subscription_schemes );
		$reset_product                 = wc_get_product( $cart_item[ 'product_id' ] ); // Product ID
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
			<ul class="wcsatt-convert-cart"><?php

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
	 * Add subscription price string info to products with attached subscription schemes.
	 *
	 * @param  string     $price
	 * @param  WC_Product $product
	 * @return string
	 */
	public static function filter_price_html( $price, $product ) {

		// Check what product type this product is
		if ( $terms = wp_get_object_terms( $product->id, 'product_type' ) ) {
			$product_type = sanitize_title( current( $terms )->name );
		} else {
			$product_type = 'simple';
		}

		if ( self::$bypass_price_html_filter ) {
			return $price;
		}

		if ( $product_type != 'variable' ) {
			$post_id = $product->id;
		} else {
			$post_id = $product->children['visible']['0']; // Gets the first enabled variation
		}

		$subscription_schemes      = WCS_ATT_Schemes::get_product_subscription_schemes( $post_id, $product_type );
		$has_product_level_schemes = empty( $subscription_schemes ) ? false : true;

		if ( $has_product_level_schemes ) {

			$force_subscription = get_post_meta( $product->id, '_wcsatt_force_subscription', true );

			if ( $force_subscription === 'yes' ) {

				$subscription_scheme = current( $subscription_schemes );
				$overridden_prices   = WCS_ATT_Schemes::get_subscription_scheme_prices( $product, $subscription_scheme );
				$suffix              = '';

				$_cloned = clone $product;

				$_cloned->is_converted_to_sub          = 'yes';
				$_cloned->subscription_period          = $subscription_scheme[ 'subscription_period' ];
				$_cloned->subscription_period_interval = $subscription_scheme[ 'subscription_period_interval' ];
				$_cloned->subscription_length          = $subscription_scheme[ 'subscription_length' ];

				if ( ! empty( $overridden_prices ) ) {
					$_cloned->regular_price            = $overridden_prices[ 'regular_price' ];
					$_cloned->price                    = $overridden_prices[ 'price' ];
					$_cloned->sale_price               = $overridden_prices[ 'sale_price' ];
					$_cloned->subscription_price       = $overridden_prices[ 'price' ];
				}

				self::$bypass_price_html_filter = true;
				$price = $_cloned->get_price_html();
				self::$bypass_price_html_filter = false;
				$price = WC_Subscriptions_Product::get_price_string( $_cloned, array( 'price' => $price ) );

				if ( count( $subscription_schemes ) > 1 && false === strpos( $price, $_cloned->get_price_html_from_text() ) ) {
					$price = sprintf( _x( '%1$s%2$s', 'Price range: from', WCS_ATT::TEXT_DOMAIN ), $_cloned->get_price_html_from_text(), $price );
				}

			} else {

				$price_overrides_exist       = WCS_ATT_Schemes::subscription_price_overrides_exist( $subscription_schemes );
				$lowest_scheme_price         = $product->price;
				$lowest_scheme_sale_price    = $product->sale_price;
				$lowest_scheme_regular_price = $product->regular_price;

				$lowest_scheme_price_html    = '';
				$from_price                  = '';

				if ( $price_overrides_exist ) {
					foreach ( $subscription_schemes as $subscription_scheme ) {
						$overridden_prices = WCS_ATT_Schemes::get_subscription_scheme_prices( $product, $subscription_scheme );
						if ( ! empty( $overridden_prices ) ) {
							if ( $overridden_prices[ 'price' ] < $lowest_scheme_price ) {
								$lowest_scheme_price         = $overridden_prices[ 'price' ];
								$lowest_scheme_sale_price    = $overridden_prices[ 'sale_price' ];
								$lowest_scheme_regular_price = $overridden_prices[ 'regular_price' ];
							}
						}
					}

					if ( $lowest_scheme_price < $product->price ) {

						$_cloned                               = clone $product;

						$_cloned->is_converted_to_sub          = 'yes';
						$_cloned->subscription_period          = $subscription_scheme[ 'subscription_period' ];
						$_cloned->subscription_period_interval = $subscription_scheme[ 'subscription_period_interval' ];
						$_cloned->subscription_length          = $subscription_scheme[ 'subscription_length' ];

						$_cloned->price                        = $lowest_scheme_price;
						$_cloned->sale_price                   = $lowest_scheme_price;
						$_cloned->regular_price                = $lowest_scheme_regular_price;

						self::$bypass_price_html_filter        = true;
						$lowest_scheme_price_html              = $_cloned->get_price_html();
						$lowest_scheme_price_html              = WC_Subscriptions_Product::get_price_string( $_cloned, array( 'price' => $lowest_scheme_price_html ) );
						self::$bypass_price_html_filter        = false;

						if ( count( $subscription_schemes ) > 1 ) {
							$from_price = sprintf( _x( '%1$s%2$s', 'Price range: from', WCS_ATT::TEXT_DOMAIN ), _x( '<span class="from">from </span>', 'min-price: 1 plan available', WCS_ATT::TEXT_DOMAIN ), $lowest_scheme_price_html );
						} else {
							$from_price = sprintf( _x( '%1$s%2$s', 'Price range: from', WCS_ATT::TEXT_DOMAIN ), _x( '<span class="for">for </span>', 'min-price: multiple plans available', WCS_ATT::TEXT_DOMAIN ), $lowest_scheme_price_html );
						}
					}
				}

				if ( $price_overrides_exist ) {
					$suffix = ' <small class="wcsatt-sub-options">' . sprintf( _n( '&ndash; or subscribe %s', '&ndash; or subscribe %s', count( $subscription_schemes ), WCS_ATT::TEXT_DOMAIN ), $from_price ) . '</small>';
				} else {
					$suffix = ' <small class="wcsatt-sub-options">' . sprintf( _n( '&ndash; subscription plan available', '&ndash; subscription plans available', count( $subscription_schemes ), WCS_ATT::TEXT_DOMAIN ), $from_price ) . '</small>';
				}

				$price  = sprintf( _x( '%1$s%2$s', 'price html sub options suffix', WCS_ATT::TEXT_DOMAIN ), $price, $suffix );
			}
		}

		return $price;
	}
}

WCS_ATT_Display::init();
