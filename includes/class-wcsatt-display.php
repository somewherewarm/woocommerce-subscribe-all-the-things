<?php
/**
 * Templating and styling functions.
 *
 * @class  WCS_ATT_Display
 * @since  1.0.0
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

		// Replace plain variation price html with subscription options template.
		add_filter( 'woocommerce_available_variation', array( __CLASS__, 'add_convert_to_sub_product_options_to_variation_data' ), 10, 3 );
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
			wp_enqueue_script( 'wcsatt-cart' );

			$params = array(
				'update_cart_option_nonce' => wp_create_nonce( 'wcsatt_update_cart_option' ),
				'wc_ajax_url'              => WCS_ATT_Core_Compatibility::is_wc_version_gte_2_4() ? WC_AJAX::get_endpoint( "%%endpoint%%" ) : WC()->ajax_url(),
			);

			wp_localize_script( 'wcsatt-cart', 'wcsatt_cart_params', $params );
		}

		if ( is_product() ) {
			wp_register_script( 'wcsatt-single-product', WCS_ATT()->plugin_url() . '/assets/js/wcsatt-single-add-to-cart.js', array( 'jquery' ), WCS_ATT::VERSION, true );
			wp_enqueue_script( 'wcsatt-single-product' );
		}
	}

	/**
	 * Replace plain variation price html with subscription options template.
	 * Subscription options are updated by the core variations script when a variation is selected.
	 *
	 * @param array                $variation_data
	 * @param WC_Product_Variable  $product
	 * @param WC_Product_Variation $variation
	 */
	public static function add_convert_to_sub_product_options_to_variation_data( $variation_data, $product, $variation ) {

		if ( $subscription_options_content = self::get_convert_to_sub_product_options_content( $variation ) ) {
			$variation_data[ 'price_html' ] = $subscription_options_content;
		}

		return $variation_data;
	}

	/**
	 * Displays single-product options for purchasing a product once or creating a subscription from it.
	 *
	 * @return void
	 */
	public static function convert_to_sub_product_options() {
		global $product;

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

		$product_level_schemes     = WCS_ATT_Schemes::get_product_subscription_schemes( $product );
		$show_subscription_options = apply_filters( 'wcsatt_show_single_product_options', ! empty( $product_level_schemes ), $product );

		// Subscription options for variable products are embedded inside the variation data 'price_html' field and updated by the core variations script.
		if ( $product->product_type === 'variable' ) {
			$show_subscription_options = false;
		}

		if ( $show_subscription_options ) {

			$force_subscription                   = get_post_meta( $product->id, '_wcsatt_force_subscription', true );
			$default_status                       = get_post_meta( $product->id, '_wcsatt_default_status', true );
			$allow_one_time_option                = $force_subscription === 'yes' ? false : true;
			$is_single_scheme_forced_subscription = $force_subscription === 'yes' && sizeof( $product_level_schemes ) === 1;

			$options                              = array();
			$default_subscription_scheme          = current( $product_level_schemes );

			// Option selected by default.
			if ( isset( $_REQUEST[ 'convert_to_sub_' . $product->id ] ) ) {
				$default_subscription_scheme_id = $_REQUEST[ 'convert_to_sub_' . $product->id ];
			} else {
				if ( $allow_one_time_option && $default_status !== 'subscription' ) {
					$default_subscription_scheme_id = '0';
				} else {
					$default_subscription_scheme_id = $default_subscription_scheme[ 'id' ];
				}
				$default_subscription_scheme_id = apply_filters( 'wcsatt_get_default_subscription_scheme_id', $default_subscription_scheme_id, $product_level_schemes, $allow_one_time_option, $product );
			}

			// One-time option.
			if ( $allow_one_time_option ) {
				$none_string                 = _x( 'None', 'product subscription selection - negative response', WCS_ATT::TEXT_DOMAIN );
				$one_time_option_description = $product->variation_id > 0 ? sprintf( __( '%1$s &ndash; %2$s', WCS_ATT::TEXT_DOMAIN ), $none_string, $product->get_price_html() ) : $none_string;

				$options[ '0' ] = array(
					'description' => apply_filters( 'wcsatt_single_product_one_time_option_description', $one_time_option_description, $product ),
					'selected'    => $default_subscription_scheme_id === '0',
					'data'        => apply_filters( 'wcsatt_single_product_one_time_option_data', array(), $product )
				);
			}

			// Sub scheme options.
			foreach ( $product_level_schemes as $subscription_scheme ) {

				$subscription_scheme_id = $subscription_scheme[ 'id' ];

				$_cloned = clone $product;

				$_cloned->is_converted_to_sub          = 'yes';
				$_cloned->subscription_period          = $subscription_scheme[ 'subscription_period' ];
				$_cloned->subscription_period_interval = $subscription_scheme[ 'subscription_period_interval' ];
				$_cloned->subscription_length          = $subscription_scheme[ 'subscription_length' ];

				$override_price = false === $is_single_scheme_forced_subscription && WCS_ATT_Scheme_Prices::has_subscription_price_override( $subscription_scheme );

				if ( $override_price ) {
					WCS_ATT_Scheme_Prices::add_price_filters( $_cloned, $subscription_scheme );
				}

				self::$bypass_price_html_filter = true;

				$sub_price_html = WC_Subscriptions_Product::get_price_string( $_cloned, array(
					'subscription_price' => $override_price || $is_single_scheme_forced_subscription,
					'price'              => $is_single_scheme_forced_subscription ? '' : '<span class="price subscription-price">' . $_cloned->get_price_html() . '</span>',
				) );

				self::$bypass_price_html_filter = false;

				$option_data = array(
					'subscription_scheme'   => $subscription_scheme,
					'overrides_price'       => $override_price,
					'discount_from_regular' => apply_filters( 'wcsatt_discount_from_regular', false )
				);

				$options[ $subscription_scheme_id ] = array(
					'description' => apply_filters( 'wcsatt_single_product_subscription_option_description', ucfirst( $allow_one_time_option ? sprintf( __( '%s', 'product subscription selection - positive response', WCS_ATT::TEXT_DOMAIN ), $sub_price_html ) : $sub_price_html ), $sub_price_html, $override_price, $allow_one_time_option, $_cloned, $subscription_scheme, $product ),
					'selected'    => $default_subscription_scheme_id === $subscription_scheme_id,
					'data'        => apply_filters( 'wcsatt_single_product_subscription_option_data', $option_data, $subscription_scheme, $product )
				);

				if ( $override_price ) {
					WCS_ATT_Scheme_Prices::remove_price_filters();
				}
			}

			if ( $prompt = get_post_meta( $product->id, '_wcsatt_subscription_prompt', true ) ) {
				$prompt = wpautop( do_shortcode( wp_kses_post( $prompt ) ) );
			}

			$options = apply_filters( 'wcsatt_single_product_options', $options, $product_level_schemes, $product );

			ob_start();

			wc_get_template( 'satt-product-options.php', array(
				'product'        => $product,
				'options'        => $options,
				'allow_one_time' => $allow_one_time_option,
				'prompt'         => $prompt,
			), false, WCS_ATT()->plugin_path() . '/templates/single-product/' );

			$content = ob_get_clean();
		}

		return $content;
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

		$is_mini_cart = did_action( 'woocommerce_before_mini_cart' ) && ! did_action( 'woocommerce_after_mini_cart' );

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

		$price_overrides_exist         = WCS_ATT_Scheme_Prices::subscription_price_overrides_exist( $subscription_schemes );
		$product_id                    = $cart_item[ 'variation_id' ] > 0 ? $cart_item[ 'variation_id' ] : $cart_item[ 'product_id' ];
		$reset_product                 = wc_get_product( $product_id );
		$options                       = array();
		$active_subscription_scheme_id = WCS_ATT_Schemes::get_active_subscription_scheme_id( $cart_item );

		// One-time option.
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

			$options[ '0' ] = array(
				'description' => $description,
				'selected'    => $active_subscription_scheme_id === '0',
			);
		}

		// Sub scheme options.
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
					$dummy_cart_item = WCS_ATT_Cart::convert_to_sub( $dummy_cart_item );

					// Get the price of the dummy cart item.
					$description = WC()->cart->get_product_price( $dummy_cart_item[ 'data' ] );
				}
			} else {
				if ( $active_subscription_scheme_id === $subscription_scheme_id ) {

					$description = WC_Subscriptions_Product::get_price_string( $cart_item[ 'data' ], array(
						'subscription_price' => false,
						'price'              => ''
					) );

				} else {

					$converted_product         = clone $reset_product;
					$dummy_cart_item           = $cart_item;
					$dummy_cart_item[ 'data' ] = $converted_product;

					// Apply the subscription scheme id to the dummy cart item.
					$dummy_cart_item[ 'wccsub_data' ][ 'active_subscription_scheme_id' ] = $subscription_scheme_id;
					// Convert the dummy cart item using the applied id.
					$dummy_cart_item  = WCS_ATT_Cart::convert_to_sub( $dummy_cart_item );

					// Use the dummy cart item to obtain the description.
					$description = WC_Subscriptions_Product::get_price_string( $dummy_cart_item[ 'data' ], array(
						'subscription_price' => false,
						'price'              => ''
					) );
				}
			}

			$options[ $subscription_scheme_id ] = array(
				'description' => $description,
				'selected'    => $active_subscription_scheme_id === $subscription_scheme_id,
			);
		}

		$options = apply_filters( 'wcsatt_cart_item_options', $options, $subscription_schemes, $cart_item, $cart_item_key );

		// If there's just one option to display, it means that one-time purchases are not allowed and there's only one sub scheme on offer -- so don't show any options.
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

		wc_get_template( 'satt-cart-item-options.php', array(
			'options'       => $options,
			'cart_item_key' => $cart_item_key,
			'classes'       => implode( ' ', $classes ),
		), false, WCS_ATT()->plugin_path() . '/templates/cart/' );

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

				$options[ '0' ] = array(
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

					$options[ $subscription_scheme[ 'id' ] ] = array(
						'description' => sprintf( __( 'Yes, %s.', 'cart subscription selection - positive response', WCS_ATT::TEXT_DOMAIN ), $sub_suffix ),
						'selected'    => $active_subscription_scheme_id === $subscription_scheme_id,
					);
				}

				foreach ( $options as $option_id => $option ) {
					?><li>
						<label>
							<input type="radio" name="convert_to_sub" value="<?php echo $option_id ?>" <?php checked( $option[ 'selected' ], true, true ); ?> />
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

			$has_variable_price  = false;
			$subscription_scheme = current( $product_level_schemes );

			if ( $price_overrides_exist = WCS_ATT_Scheme_Prices::subscription_price_overrides_exist( $product_level_schemes ) ) {
				$lowest_scheme_price_data = WCS_ATT_Scheme_Prices::get_lowest_price_subscription_scheme_data( $product, $product_level_schemes );
				$subscription_scheme      = $lowest_scheme_price_data[ 'scheme' ];
			}

			// Reinstantiate variable products to re-populate a filtered version of the 'prices_array' property. Otherwise, a clone should do... but re-instantiate just in case.
			$_product = wc_get_product( $product->id );

			// ...and let this be filterable.
			$_product = apply_filters( 'wcsatt_overridden_subscription_prices_product', $_product, $subscription_scheme, $product );

			$_product->is_converted_to_sub          = 'yes';
			$_product->subscription_period          = $subscription_scheme[ 'subscription_period' ];
			$_product->subscription_period_interval = $subscription_scheme[ 'subscription_period_interval' ];
			$_product->subscription_length          = $subscription_scheme[ 'subscription_length' ];

			// Add price method filters.
			WCS_ATT_Scheme_Prices::add_price_filters( $_product, $subscription_scheme );

			if ( count( $product_level_schemes ) > 1 ) {
				$has_variable_price = true;
			} else {
				if ( 'variable' === $product->product_type && $_product->get_variation_price( 'min' ) !== $_product->get_variation_price( 'max' ) ) {
					$has_variable_price = true;

					// If all variations prices are overridden, they will be equal.
					if ( isset( $subscription_scheme[ 'subscription_pricing_method' ] ) && $subscription_scheme[ 'subscription_pricing_method' ] === 'override' ) {
						$has_variable_price = false;
					}

				} elseif ( 'bundle' === $product->product_type && $product->get_bundle_price( 'min' ) !== $product->get_bundle_price( 'max' ) ) {
					$has_variable_price = true;

				} elseif ( 'composite' === $product->product_type && $product->get_composite_price( 'min' ) !== $product->get_composite_price( 'max' ) ) {
					$has_variable_price = true;
				}
			}

			$force_subscription = get_post_meta( $product->id, '_wcsatt_force_subscription', true );

			if ( $force_subscription === 'yes' ) {

				self::$bypass_price_html_filter = true;
				$price = $_product->get_price_html();
				self::$bypass_price_html_filter = false;

				$price = WC_Subscriptions_Product::get_price_string( $_product, array( 'price' => $price ) );

				if ( $has_variable_price && false === strpos( $price, $_product->get_price_html_from_text() ) ) {
					$price = sprintf( _x( '%1$s%2$s', 'Price range: from', WCS_ATT::TEXT_DOMAIN ), $_product->get_price_html_from_text(), $price );
				}

			} else {

				$suffix_price_html = '';

				// Discount format vs Price format. Experimental use only.
				if ( apply_filters( 'wcsatt_price_html_discount_format', false, $product ) && $subscription_scheme[ 'subscription_pricing_method' ] === 'inherit' ) {

					$discount          = $subscription_scheme[ 'subscription_discount' ];
					$discount_html     = '</small> <span class="wcsatt-sub-discount">' . sprintf( __( '%s&#37; off', WCS_ATT::TEXT_DOMAIN ), $discount ) . '</span><small>';
					$suffix_price_html = sprintf( __( 'at%1$s%2$s', WCS_ATT::TEXT_DOMAIN ), $has_variable_price ? __( ' up to', WCS_ATT::TEXT_DOMAIN ) : '', $discount_html );

				} else {

					self::$bypass_price_html_filter = true;
					$lowest_scheme_price_html = $_product->get_price_html();
					self::$bypass_price_html_filter = false;

					$lowest_scheme_price_html = WC_Subscriptions_Product::get_price_string( $_product, array( 'price' => $lowest_scheme_price_html ) );

					if ( $has_variable_price ) {
						$suffix_price_html = sprintf( _x( '%1$s%2$s', 'Price range: from', WCS_ATT::TEXT_DOMAIN ), _x( '<span class="from">from </span>', 'subscribe from price', WCS_ATT::TEXT_DOMAIN ), str_replace( $_product->get_price_html_from_text(), '', $lowest_scheme_price_html ) );
					} else {
						$suffix_price_html = sprintf( _x( '%1$s%2$s', 'Price range: from', WCS_ATT::TEXT_DOMAIN ), _x( '<span class="for">for </span>', 'subscribe for price', WCS_ATT::TEXT_DOMAIN ), $lowest_scheme_price_html );
					}
				}

				if ( $price_overrides_exist ) {
					$suffix = ' <small class="wcsatt-sub-options">' . sprintf( _n( '&ndash; or subscribe %s', '&ndash; subscription plans available %s', count( $product_level_schemes ), WCS_ATT::TEXT_DOMAIN ), $suffix_price_html ) . '</small>';
				} else {
					$suffix = ' <small class="wcsatt-sub-options">' . sprintf( _n( '&ndash; subscription available', '&ndash; subscription plans available', count( $product_level_schemes ), WCS_ATT::TEXT_DOMAIN ), $suffix_price_html ) . '</small>';
				}

				$price = sprintf( _x( '%1$s%2$s', 'price html sub options suffix', WCS_ATT::TEXT_DOMAIN ), $price, $suffix );
			}

			WCS_ATT_Scheme_Prices::remove_price_filters();
		}

		return $price;
	}
}

WCS_ATT_Display::init();
