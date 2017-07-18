<?php
/**
 * WCS_ATT_Display_Cart class
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
 * Cart template modifications.
 *
 * @class    WCS_ATT_Display_Cart
 * @version  2.0.0
 */
class WCS_ATT_Display_Cart {

	/**
	 * Initialize.
	 */
	public static function init() {
		self::add_hooks();
	}

	/**
	 * Hook-in.
	 */
	private static function add_hooks() {

		// Display a "Subscribe to Cart" section in the cart.
		add_action( 'woocommerce_before_cart_totals', array( __CLASS__, 'show_subscribe_to_cart_prompt' ) );

		// Use radio buttons to mark a cart item as a one-time sale or as a subscription.
		add_filter( 'woocommerce_cart_item_price', array( __CLASS__, 'show_cart_item_subscription_options' ), 1000, 3 );
	}

	/*
	|--------------------------------------------------------------------------
	| Filters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Displays cart item options for purchasing a product once or creating a subscription from it.
	 *
	 * @param  string $price
	 * @param  array  $cart_item
	 * @param  string $cart_item_key
	 * @return string
	 */
	public static function show_cart_item_subscription_options( $price, $cart_item, $cart_item_key ) {

		$product                     = $cart_item[ 'data' ];
		$subscription_schemes        = WCS_ATT_Cart::get_subscription_schemes( $cart_item, 'product' );
		$show_convert_to_sub_options = apply_filters( 'wcsatt_show_cart_item_options', ! empty( $subscription_schemes ), $cart_item, $cart_item_key );

		$is_mini_cart = did_action( 'woocommerce_before_mini_cart' ) && ! did_action( 'woocommerce_after_mini_cart' );

		// currently show options only in cart
		if ( ! is_cart() || $is_mini_cart ) {
			return $price;
		}

		if ( ! $show_convert_to_sub_options ) {
			return $price;
		}

		$active_subscription_scheme_key = WCS_ATT_Product_Schemes::get_subscription_scheme( $product );
		$force_subscription             = WCS_ATT_Product_Schemes::has_forced_subscription_scheme( $product );
		$price_filter_exists            = WCS_ATT_Product_Schemes::price_filter_exists( $subscription_schemes );
		$options                        = array();

		// Non-recurring (one-time) option.
		if ( false === $force_subscription ) {

			if ( $price_filter_exists ) {

				if ( false === $active_subscription_scheme_key ) {
					$description = $price;
				} else {
					$description = WCS_ATT_Cart::get_product_price( $cart_item, false );
				}

			} else {
				$description = __( 'only now', 'woocommerce-subscribe-all-the-things' );
			}

			$options[] = array(
				'description' => $description,
				'value'       => '0',
				'selected'    => false === $active_subscription_scheme_key,
			);
		}

		// Subscription options.
		foreach ( $subscription_schemes as $subscription_scheme ) {

			$subscription_scheme_key = $subscription_scheme->get_key();

			if ( $price_filter_exists ) {

				if ( $active_subscription_scheme_key === $subscription_scheme_key ) {
					$description = $price;
				} else {
					$description = WCS_ATT_Product_Prices::get_price_string( $product, array(
						'scheme_key' => $subscription_scheme_key,
						'price'      => WCS_ATT_Cart::get_product_price( $cart_item, $subscription_scheme_key )
					) );
				}

			} else {

				$description = WCS_ATT_Product_Prices::get_price_string( $product, array(
					'scheme_key'         => $subscription_scheme_key,
					'subscription_price' => false,
					'price'              => ''
				) );
			}

			$options[] = array(
				'description' => $description,
				'value'       => $subscription_scheme_key,
				'selected'    => $active_subscription_scheme_key === $subscription_scheme_key,
			);
		}

		$options = apply_filters( 'wcsatt_cart_item_options', $options, $subscription_schemes, $cart_item, $cart_item_key );

		// If there's just one option to display, it means that one-time purchases are not allowed and there's only one sub scheme on offer -- so don't show any options.
		if ( count( $options ) === 1 ) {
			return $price;
		}

		ob_start();

		$classes = $price_filter_exists ? array( 'overrides_exist' ) : array();

		wc_get_template( 'cart/cart-item-subscription-options.php', array(
			'options'       => $options,
			'cart_item_key' => $cart_item_key,
			'classes'       => implode( ' ', $classes ),
		), false, WCS_ATT()->plugin_path() . '/templates/' );

		$convert_to_sub_options = ob_get_clean();

		if ( $price_filter_exists ) {

			$price = $convert_to_sub_options;

		} else {

			// Grab bare price without subscription details.
			remove_filter( 'woocommerce_cart_product_price', array( 'WC_Subscriptions_Cart', 'cart_product_price' ), 10, 2 );
			remove_filter( 'woocommerce_cart_item_price',  array( __CLASS__, 'show_cart_item_subscription_options' ), 1000, 3 );

			$price = apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $cart_item[ 'data' ] ), $cart_item, $cart_item_key );

			add_filter( 'woocommerce_cart_item_price', array( __CLASS__, 'show_cart_item_subscription_options' ), 1000, 3 );
			add_filter( 'woocommerce_cart_product_price', array( 'WC_Subscriptions_Cart', 'cart_product_price' ), 10, 2 );

			// Concatenate stuff.
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
		if ( $subscription_schemes = WCS_ATT_Cart::get_cart_subscription_schemes( 'display' ) ) {

			$active_scheme_key = WCS_ATT_Cart::get_cart_subscription_scheme();
			$options           = array();

			$options[ '0' ] = array(
				'description' => _x( 'No thanks.', 'cart subscription selection - negative response', 'woocommerce-subscribe-all-the-things' ),
				'selected'    => $active_scheme_key === false,
			);

			// Create a dummy product.
			$dummy_product = new WC_Product( 0 );

			// Set the cart-level schemes on it.
			WCS_ATT_Product_Schemes::set_subscription_schemes( $dummy_product, $subscription_schemes );

			// Generate option descriptions.
			foreach ( $subscription_schemes as $subscription_scheme ) {

				$subscription_scheme_key = $subscription_scheme->get_key();

				WCS_ATT_Product_Schemes::set_subscription_scheme( $dummy_product, $subscription_scheme_key );

				$sub_suffix = WCS_ATT_Product_Prices::get_price_string( $dummy_product, array( 'price' => '', 'subscription_price' => false ) );

				$options[ $subscription_scheme_key ] = array(
					'description' => sprintf( _x( 'Yes, %s.', 'cart subscription selection - positive response', 'woocommerce-subscribe-all-the-things' ), $sub_suffix ),
					'selected'    => $active_scheme_key === $subscription_scheme_key,
				);
			}

			wc_get_template( 'cart/cart-subscription-options.php', array(
				'options' => $options,
			), false, WCS_ATT()->plugin_path() . '/templates/' );
		}
	}
}

WCS_ATT_Display_Cart::init();
