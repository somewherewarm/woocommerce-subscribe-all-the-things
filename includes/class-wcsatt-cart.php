<?php
/**
 * Cart functionality for converting cart items to subscriptions.
 *
 * @class    WCS_ATT_Cart
 * @version  1.0.3
 */

class WCS_ATT_Cart {

	public static function init() {

		// Allow subs to recognize a cart item of any product type as a subscription.
		add_filter( 'woocommerce_is_subscription', __CLASS__ . '::is_converted_to_sub', 10, 3 );

		// Add convert-to-sub configuration data to cart items that can be converted.
		add_filter( 'woocommerce_add_cart_item_data', __CLASS__ . '::add_cart_item_convert_to_sub_data', 10, 3 );

		// Load convert-to-sub cart item session data.
		add_filter( 'woocommerce_get_cart_item_from_session', __CLASS__ . '::load_convert_to_sub_session_data', 5, 2 );

		// Process convert-to-sub product-level/cart-level session data.
		add_action( 'woocommerce_cart_loaded_from_session', __CLASS__ . '::apply_convert_to_sub_session_data', 5 );

		// Process convert-to-sub product-level/cart-level configuration data.
		add_action( 'woocommerce_add_to_cart', __CLASS__ . '::apply_convert_to_sub_data', 1000, 6 );

		// Save the convert to sub radio button setting when clicking the 'update cart' button.
		add_filter( 'woocommerce_update_cart_action_cart_updated', __CLASS__ . '::update_convert_to_sub_options', 10 );

		// Save the convert to sub cart-level setting via ajax.
		if ( WCS_ATT_Core_Compatibility::is_wc_version_gte_2_4() ) {
			add_action( 'wc_ajax_wcsatt_update_cart_option', __CLASS__ . '::update_convert_to_sub_cart_options' );
		} else {
			add_action( 'wp_ajax_wcsatt_update_cart_option', __CLASS__ . '::update_convert_to_sub_cart_options' );
			add_action( 'wp_ajax_nopriv_wcsatt_update_cart_option', __CLASS__ . '::update_convert_to_sub_cart_options' );
		}

		// Add scheme ID to cart item meta so resubscribe can later fetch it by ID.
		// Needed because length data is not stored on the subscription.
		add_action( 'woocommerce_add_subscription_item_meta', __CLASS__ . '::store_cart_item_wcsatt_id', 10, 2 );
	}

	/**
	 * Ajax handler for saving cart-level "subscribe to cart" preferences.
	 *
	 * @return void
	 */
	public static function update_convert_to_sub_cart_options() {

		check_ajax_referer( 'wcsatt_update_cart_option', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$selected_scheme = '0';

		if ( isset( $_POST[ 'selected_scheme' ] ) ) {
			$selected_scheme = wc_clean( $_POST[ 'selected_scheme' ] );
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! empty( $cart_item[ 'wccsub_data' ] ) ) {
				$cart_item[ 'wccsub_data' ][ 'active_subscription_scheme_id' ] = $selected_scheme;
				WC()->cart->cart_contents[ $cart_item_key ] = self::convert_to_sub( $cart_item );
			}
		}

		WC()->session->set( 'wcsatt-active-scheme-id', $selected_scheme );

		WC()->cart->calculate_totals();

		// Update the cart table apart from the totals in order to show modified price html strings with sub details.
		wc_get_template( 'cart/cart.php' );

		die();
	}

	/**
	 * Updates the convert-to-sub status of a cart item based on the cart item option.
	 *
	 * @param  boolean  $updated
	 * @return boolean
	 */
	public static function update_convert_to_sub_options( $updated ) {

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! empty( $cart_item[ 'wccsub_data' ] ) ) {

				$selected_scheme = isset( $_POST[ 'cart' ][ $cart_item_key ][ 'convert_to_sub' ] ) ? $_POST[ 'cart' ][ $cart_item_key ][ 'convert_to_sub' ] : false;
				$selected_scheme = apply_filters( 'wcsatt_updated_cart_item_scheme_id', $selected_scheme, $cart_item, $cart_item_key );

				if ( false !== $selected_scheme ) {
					WC()->cart->cart_contents[ $cart_item_key ][ 'wccsub_data' ][ 'active_subscription_scheme_id' ] = $selected_scheme;
				}
			}
		}

		return true;
	}

	/**
	 * Add convert-to-sub subscription data to cart items that can be converted.
	 *
	 * @param  array  $cart_item
	 * @param  int    $product_id
	 * @param  int    $variation_id
	 * @return array
	 */
	public static function add_cart_item_convert_to_sub_data( $cart_item, $product_id, $variation_id ) {

		if ( self::is_convertible_to_sub( $product_id ) ) {

			$posted_subscription_scheme_id = false;

			if ( ! empty( $_POST[ 'convert_to_sub_' . $product_id ] ) ) {
				$posted_subscription_scheme_id = wc_clean( $_POST[ 'convert_to_sub_' . $product_id ] );
			} elseif ( isset( $cart_item[ 'subscription_resubscribe' ] ) ) {
				// let's see if we can grab the scheme id from the order item meta
				$scheme_id = wc_get_order_item_meta( $cart_item[ 'subscription_resubscribe' ][ 'subscription_line_item_id' ], '_wcsatt_scheme_id', true );

				if ( '' !== $scheme_id ) {
					$posted_subscription_scheme_id = $scheme_id;
				}
			}

			$cart_item[ 'wccsub_data' ] = array(
				'active_subscription_scheme_id' => $posted_subscription_scheme_id,
			);
		}

		return $cart_item;
	}

	/**
	 * Load stored convert-to-sub session data.
	 * Cart items are converted to subscriptions here, then Subs code does all the magic.
	 *
	 * @param  array  $cart_item
	 * @param  array  $item_session_values
	 * @return array
	 */
	public static function load_convert_to_sub_session_data( $cart_item, $item_session_values ) {

		if ( isset( $item_session_values[ 'wccsub_data' ] ) ) {
			$cart_item[ 'wccsub_data' ] = $item_session_values[ 'wccsub_data' ];
		}

		return $cart_item;
	}

	/**
	 * Converts a cart item to a subscription.
	 *
	 * @param  array  $cart_item
	 * @return array
	 */
	public static function convert_to_sub( $cart_item ) {

		if ( $active_subscription_scheme = WCS_ATT_Schemes::get_active_subscription_scheme( $cart_item ) ) {

			$cart_item[ 'data' ]->is_converted_to_sub = 'yes';

			$subscription_prices = self::get_active_subscription_scheme_prices( $cart_item, $active_subscription_scheme );

			if ( ! empty( $subscription_prices ) ) {
				$cart_item[ 'data' ]->price                    = $subscription_prices[ 'price' ];
				$cart_item[ 'data' ]->regular_price            = $subscription_prices[ 'regular_price' ];
				$cart_item[ 'data' ]->sale_price               = $subscription_prices[ 'sale_price' ];
				$cart_item[ 'data' ]->subscription_price       = $subscription_prices[ 'price' ];
			}

			$cart_item[ 'data' ]->subscription_period          = $active_subscription_scheme[ 'subscription_period' ];
			$cart_item[ 'data' ]->subscription_period_interval = $active_subscription_scheme[ 'subscription_period_interval' ];
			$cart_item[ 'data' ]->subscription_length          = $active_subscription_scheme[ 'subscription_length' ];

		} else {

			$cart_item[ 'data' ]->is_converted_to_sub = 'no';
		}

		return apply_filters( 'wcsatt_cart_item', $cart_item );
	}

	/**
	 * Returns cart item pricing data based on the active subscription scheme settings of a cart item.
	 *
	 * @return string
	 */
	public static function get_active_subscription_scheme_prices( $cart_item, $active_subscription_scheme = array() ) {

		$prices = array();

		if ( empty( $active_subscription_scheme ) ) {
			$active_subscription_scheme = WCS_ATT_Schemes::get_active_subscription_scheme( $cart_item );
		}

		if ( ! empty( $active_subscription_scheme ) ) {
			$prices = WCS_ATT_Scheme_Prices::get_subscription_scheme_prices( array(
				'price'         => $cart_item[ 'data' ]->price,
				'regular_price' => $cart_item[ 'data' ]->regular_price,
				'sale_price'    => $cart_item[ 'data' ]->sale_price
			), $active_subscription_scheme );
		}

		return apply_filters( 'wcsatt_cart_item_prices', $prices, $cart_item, $active_subscription_scheme );
	}

	/**
	 * Convert cart items to subscriptions on adding to cart and re-calculate totals.
	 *
	 * @return void
	 */
	public static function apply_convert_to_sub_data( $item_key, $product_id, $quantity, $variation_id, $variation, $item_data ) {

		self::apply_convert_to_sub_session_data( WC()->cart );

		WC()->cart->calculate_totals();
	}

	/**
	 * Cart items are converted to subscriptions here, then Subs code does all the magic.
	 *
	 * @param  WC_Cart  $cart
	 * @return void
	 */
	public static function apply_convert_to_sub_session_data( $cart ) {

		$cart_level_schemes = WCS_ATT_Schemes::get_cart_subscription_schemes();

		foreach ( WC()->cart->cart_contents as $cart_item_key => $cart_item ) {

			if ( isset( $cart_item[ 'wccsub_data' ] ) ) {

				// Initialize subscription scheme data.
				$cart_item[ 'wccsub_data' ][ 'active_subscription_scheme_id' ] = WCS_ATT_Schemes::set_subscription_scheme_id( $cart_item, $cart_level_schemes );

				// Convert the cart item to a subscription, if needed.
				WC()->cart->cart_contents[ $cart_item_key ] = self::convert_to_sub( $cart_item );
			}
		}
	}

	/**
	 * Hooks onto 'woocommerce_is_subscription' to trick Subs into thinking it is dealing with a subscription.
	 * The necessary subscription properties are added to the product in 'load_convert_to_sub_session_data()'.
	 *
	 * @param  boolean     $is
	 * @param  int         $product_id
	 * @param  WC_Product  $product
	 * @return boolean
	 */
	public static function is_converted_to_sub( $is, $product_id, $product ) {

		if ( ! $product ) {
			return $is;
		}

		if ( isset( $product->is_converted_to_sub ) && $product->is_converted_to_sub === 'yes' ) {
			$is = true;
		}

		return $is;
	}

	/**
	 * True if a cart item can be converted from a one-shot purchase to a subscription and vice-versa.
	 * Subscription product types can't be converted to non-sub items.
	 *
	 * @param  int|array  $arg
	 * @return boolean
	 */
	public static function is_convertible_to_sub( $arg ) {

		if ( is_array( $arg ) && isset( $arg[ 'product_id' ] ) ) {
			$product_id = $arg[ 'product_id' ];
		} else {
			$product_id = absint( $arg );
		}

		return WC_Subscriptions_Product::is_subscription( $product_id ) ? false : true;
	}

	/**
	 * True if the product corresponding to a cart item is one of the types supported by the plugin.
	 *
	 * @param  array  $cart_item
	 * @return boolean
	 */
	public static function is_supported_product_type( $cart_item ) {

		$product         = $cart_item[ 'data' ];
		$product_type    = $cart_item[ 'data' ]->product_type;
		$supported_types = WCS_ATT()->get_supported_product_types();

		if ( in_array( $product_type, $supported_types ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Hooked into woocommerce_add_subscription_item_meta, this will store the wcsatt scheme id against the order item.
	 * Reason is that the resubscribe cart item data lacks a LOT of detail, so we need to reconstruct some of it.
	 * Normally it could be inferred from the product's available schemes and the old subscription details, but because
	 * length is not stored, it becomes ambiguous. Hence the need to store the ID.
	 *
	 * @param  integer  $item_id    ID of the order itemmeta
	 * @param  array    $cart_item  data about the order item
	 */
	public static function store_cart_item_wcsatt_id( $item_id, $cart_item ) {
		if ( isset( $cart_item[ 'wccsub_data' ][ 'active_subscription_scheme_id' ] ) ) {
			wc_add_order_item_meta( $item_id, '_wcsatt_scheme_id', $cart_item[ 'wccsub_data' ][ 'active_subscription_scheme_id' ] );
		}
	}
}

WCS_ATT_Cart::init();
