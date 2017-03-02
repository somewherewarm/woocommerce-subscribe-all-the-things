<?php
/**
 * WCS_ATT_Display class
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Subscribe All the Things
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end support.
 *
 * @class    WCS_ATT_Display
 * @version  1.2.0
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

		// Changes the "Add to Cart" button text when a product with the force subscription is set.
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( __CLASS__, 'add_to_cart_text' ), 10, 1 );

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
				'wc_ajax_url'              => WC_AJAX::get_endpoint( "%%endpoint%%" ),
				'is_wc_version_gte_2_6'    => WCS_ATT_Core_Compatibility::is_wc_version_gte_2_6() ? 'yes' : 'no'
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
	 * @param  array                 $variation_data
	 * @param  WC_Product_Variable   $product
	 * @param  WC_Product_Variation  $variation
	 * @return array
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

		$subscription_schemes      = WCS_ATT_Product::get_subscription_schemes( $product );
		$show_subscription_options = apply_filters( 'wcsatt_show_single_product_options', ! empty( $subscription_schemes ), $product );

		// Subscription options for variable products are embedded inside the variation data 'price_html' field and updated by the core variations script.
		if ( $product->is_type( 'variable' ) ) {
			$show_subscription_options = false;
		}

		if ( $show_subscription_options ) {

			$product_id                           = WCS_ATT_Core_Compatibility::get_id( $product );
			$force_subscription                   = WCS_ATT_Product::has_forced_subscription( $product );
			$is_single_scheme_forced_subscription = $force_subscription && sizeof( $subscription_schemes ) === 1;
			$default_subscription_scheme_key      = WCS_ATT_Product::get_default_subscription_scheme( $product, 'key' );
			$options                              = array();

			// Option selected by default.
			if ( isset( $_REQUEST[ 'convert_to_sub_' . $product_id ] ) ) {
				$default_subscription_scheme_option_value = $_REQUEST[ 'convert_to_sub_' . $product_id ];
			} else {
				$default_subscription_scheme_option_value = apply_filters( 'wcsatt_get_default_subscription_scheme_id', false === $default_subscription_scheme_key ? '0' : $default_subscription_scheme_key, $subscription_schemes, false === $force_subscription, $product );
			}

			// Non-recurring (one-time) option.
			if ( false === $force_subscription ) {
				$none_string                 = _x( 'None', 'product subscription selection - negative response', WCS_ATT::TEXT_DOMAIN );
				$one_time_option_description = $product->is_type( 'variation' ) ? sprintf( __( '%1$s &ndash; %2$s', WCS_ATT::TEXT_DOMAIN ), $none_string, $product->get_price_html() ) : $none_string;

				$options[ '0' ] = array(
					'description' => apply_filters( 'wcsatt_single_product_one_time_option_description', $one_time_option_description, $product ),
					'selected'    => '0' === $default_subscription_scheme_option_value,
					'data'        => apply_filters( 'wcsatt_single_product_one_time_option_data', array(), $product )
				);
			}

			// Sub scheme options.
			foreach ( $subscription_schemes as $subscription_scheme ) {

				$sub_price_html = '<span class="price">' . WCS_ATT_Product::get_price_html( $product, $subscription_scheme->get_key() ) . '</span>';

				$option_data = array(
					'subscription_scheme'   => $subscription_scheme,
					'overrides_price'       => $subscription_scheme->has_price_filter(),
					'discount_from_regular' => apply_filters( 'wcsatt_discount_from_regular', false )
				);

				$options[ $subscription_scheme->get_key() ] = array(
					'description' => apply_filters( 'wcsatt_single_product_subscription_option_description', ucfirst( false === $force_subscription ? sprintf( __( '%s', 'product subscription selection - positive response', WCS_ATT::TEXT_DOMAIN ), $sub_price_html ) : $sub_price_html ), $sub_price_html, $subscription_scheme->has_price_filter(), false === $force_subscription, $product, $subscription_scheme ),
					'selected'    => $default_subscription_scheme_option_value === $subscription_scheme->get_key(),
					'data'        => apply_filters( 'wcsatt_single_product_subscription_option_data', $option_data, $subscription_scheme, $product )
				);
			}

			if ( $prompt = WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ? $product->get_meta( '_wcsatt_subscription_prompt', true ) : get_post_meta( $product_id, '_wcsatt_subscription_prompt', true ) ) {
				$prompt = wpautop( do_shortcode( wp_kses_post( $prompt ) ) );
			}

			$options = apply_filters( 'wcsatt_single_product_options', $options, $subscription_schemes, $product );

			ob_start();

			wc_get_template( 'single-product/satt-product-options.php', array(
				'product'        => $product,
				'product_id'     => $product_id,
				'options'        => $options,
				'allow_one_time' => false === $force_subscription,
				'prompt'         => $prompt,
			), false, WCS_ATT()->plugin_path() . '/templates/' );

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
		$subscription_schemes = WCS_ATT_Schemes::get_subscription_schemes( $cart_item, 'cart-item' );

		if ( ! empty( $subscription_schemes ) ) {
			$force_subscription = WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ? $cart_item[ 'data' ]->get_meta( '_wcsatt_force_subscription', true ) : get_post_meta( $cart_item[ 'product_id' ], '_wcsatt_force_subscription', true );
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

		wc_get_template( 'cart/satt-cart-item-options.php', array(
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

					$sub_suffix  = WC_Subscriptions_Product::get_price_string( $dummy_product, array( 'price' => '', 'subscription_price' => false ) );

					$options[ $subscription_scheme[ 'id' ] ] = array(
						'description' => sprintf( __( 'Yes, %s.', 'cart subscription selection - positive response', WCS_ATT::TEXT_DOMAIN ), $sub_suffix ),
						'selected'    => $active_subscription_scheme_id === $subscription_scheme_id,
					);
				}

				foreach ( $options as $option_id => $option ) {
					?><li>
						<label>
							<input type="radio" name="convert_to_sub" value="<?php echo $option_id ?>" <?php checked( $option[ 'selected' ], true, true ); ?> />
							<?php echo wp_kses_post( $option[ 'description' ] ); ?>
						</label>
					</li><?php
				}

			?></ul>
			<?php
		}
	}

	/**
	 * Override the WooCommerce "Add to Cart" button text with "Sign Up Now".
	 *
	 * @since 1.1.1
	 */
	public static function add_to_cart_text( $button_text ) {

		global $product;

		$product_schemes    = WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ? $product->get_meta( '_wcsatt_schemes', true ) : get_post_meta( $product->id, '_wcsatt_schemes', true );
		$force_subscription = WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ? $product->get_meta( '_wcsatt_force_subscription', true ) : get_post_meta( $product->id, '_wcsatt_force_subscription', true );

		if ( in_array( $product->get_type(), WCS_ATT()->get_supported_product_types() ) && $product_schemes ) {
			if ( 'yes' === $force_subscription ) {
				$button_text = get_option( WC_Subscriptions_Admin::$option_prefix . '_add_to_cart_button_text', __( 'Sign Up Now', WCS_ATT::TEXT_DOMAIN ) );
			}
		}

		return apply_filters( 'wcsatt_add_to_cart_text', $button_text );
	}

}

WCS_ATT_Display::init();
