<?php
/**
 * WCS_ATT_Order class
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Subscribe All the Things
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order hooks for saving/restoring the subscription state of a product to/from order item data.
 *
 * @class    WCS_ATT_Order
 * @version  2.1.2
 */
class WCS_ATT_Order {

	/**
	 * Initialization.
	 */
	public static function init() {
		self::add_hooks();
	}

	/**
	 * Hook-in.
	 */
	private static function add_hooks() {

		// Restore subscription data when creating a cart item using an order item as reference.
		add_filter( 'woocommerce_order_again_cart_item_data', array( __CLASS__, 'restore_cart_item_from_order_item' ), 10, 3 );

		// Restore the subscription state of a product instantiated using an order item as reference.
		add_filter( 'woocommerce_order_item_product', array( __CLASS__, 'restore_product_from_order_item' ), 10, 2 );

		// Save subscription scheme in subscription item meta when checking out so it can be re-applied later.
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'save_subscription_scheme_meta' ), 10, 3 );

		// Hide subscription scheme metadata in order line items.
		add_filter( 'woocommerce_hidden_order_itemmeta', array( __CLASS__, 'hidden_order_item_meta' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| API
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns the key of the subscription scheme applied on the product when it was purchased.
	 *
	 * @param  array  $order_item
	 * @param  array  $args
	 * @return string|false|null
	 */
	public static function get_subscription_scheme( $order_item, $args = array() ) {

		$scheme_key = null;

		if ( $order_item->meta_exists( '_wcsatt_scheme' ) ) {

			$scheme_key = $order_item->get_meta( '_wcsatt_scheme', true );
			$scheme_key = 0 === absint( $scheme_key ) ? false : strval( $scheme_key );

		// Backwards compatibility with v1.
		} elseif ( $order_item->meta_exists( '_wcsatt_scheme_id' ) ) {

			$scheme_key = $order_item->get_meta( '_wcsatt_scheme_id', true );
			$scheme_key = 0 === absint( $scheme_key ) ? false : strval( $scheme_key );

		} else {

			$default_args = array(
				'subscription'       => false,
				'match_subscription' => false,
				'match_args'         => array(
					'next_payment'      => false,
					'upcoming_renewals' => false,
					'payment_date'      => false
				)
			);

			/**
			 * 'wcsatt_restore_subscription_scheme_from_subscription' filter.
			 *
			 * Controls whether SATT will attempt to restore a missing scheme key by matching a scheme against the subscription object.
			 *
			 * @since  2.1.2
			 *
			 * @param  array          $args
			 * @param  WC_Order_Item  $order_item
			 */
			$args = apply_filters( 'wcsatt_restore_subscription_scheme_from_subscription_args', wp_parse_args( $args, $default_args ), $order_item );

			if ( $args[ 'match_subscription' ] && wcs_is_subscription( $args[ 'subscription' ] ) ) {

				$product = $order_item->get_product();

				if ( $product && ( $subscription_schemes = WCS_ATT_Product_Schemes::get_subscription_schemes( $product ) ) ) {

					foreach ( $subscription_schemes as $subscription_scheme ) {
						if ( $subscription_scheme->matches_subscription( $args[ 'subscription' ], $args[ 'match_args' ] ) ) {
							$scheme_key = $subscription_scheme->get_key();
						}
					}
				}
			}
		}

		return $scheme_key;
	}

	/*
	|--------------------------------------------------------------------------
	| Hooks
	|--------------------------------------------------------------------------
	*/

	/**
	 * Attempts to restore subscription data when creating a cart item using an order item as reference.
	 *
	 * @param  array     $cart_item
	 * @param  array     $order_item
	 * @param  WC_Order  $order
	 * @return array
	 */
	public static function restore_cart_item_from_order_item( $cart_item, $order_item, $order ) {

		$scheme_key = self::get_subscription_scheme( $order_item, array(
			'subscription'       => $order,
			'match_subscription' => true
		) );

		if ( null !== $scheme_key ) {
			$cart_item[ 'wcsatt_data' ] = array(
				'active_subscription_scheme' => $scheme_key
			);
		}
		return $cart_item;
	}

	/**
	 * Attempts to restore the subscription state of a product instantiated using an order item as reference.
	 *
	 * @param  WC_Product  $product
	 * @param  array       $order_item
	 * @return WC_Product
	 */
	public static function restore_product_from_order_item( $product, $order_item ) {
		if ( $product && null !== ( $scheme_key = self::get_subscription_scheme( $order_item ) ) ) {
			WCS_ATT_Product_Schemes::set_subscription_scheme( $product, $scheme_key );
		}
		return $product;
	}

	/**
	 * Stores the scheme key against the order item when checking out.
	 * Used for reconstructing the scheme when reordering, resubscribing, etc - @see 'WCS_ATT_Cart::add_cart_item_data'.
	 *
	 * @param  WC_Order_Item  $order_item
	 * @param  string         $cart_item_key
	 * @param  array          $cart_item
	 * @return void
	 */
	public static function save_subscription_scheme_meta( $order_item, $cart_item_key, $cart_item ) {

		$scheme_key = WCS_ATT_Cart::get_subscription_scheme( $cart_item );

		if ( null !== $scheme_key ) {
			$scheme_key = false === $scheme_key ? '0' : $scheme_key;
			$order_item->add_meta_data( '_wcsatt_scheme', $scheme_key, true );
		}

		// Log mismatch - @see 'WCS_ATT_Cart::check_applied_subscription_schemes' and 'WCS_ATT_Cart::apply_subscription_scheme'.
		$applied_scheme_key = WCS_ATT_Product_Schemes::get_subscription_scheme( $cart_item[ 'data' ] );

		if ( $scheme_key !== $applied_scheme_key ) {
			$log_message = sprintf( 'Incorrect subscription scheme applied to cart item %s (%s). Scheme to apply: "%s". Applied scheme: "%s".', $cart_item_key, $cart_item[ 'data' ]->get_name(), var_export( $scheme_key, true ), var_export( $applied_scheme_key, true ) );
			WCS_ATT()->log( $log_message, 'notice' );

		}
	}

	/**
	 * Stores the scheme key against the order item (WC < 3.0).
	 * @see 'WCS_ATT_Order::save_subscription_scheme_meta'.
	 *
	 * @param  integer  $item_id
	 * @param  array    $cart_item
	 */
	public static function save_subscription_scheme_meta_legacy( $item_id, $cart_item ) {

		$scheme_key = WCS_ATT_Cart::get_subscription_scheme( $cart_item );

		if ( null !== $scheme_key ) {
			$scheme_key = false === $scheme_key ? '0' : $scheme_key;
			wc_add_order_item_meta( $item_id, '_wcsatt_scheme', $scheme_key );
		}
	}

	/**
	 * Hides subscription scheme metadata.
	 *
	 * @since  2.1.0
	 *
	 * @param  array  $hidden
	 * @return array
	 */
	public static function hidden_order_item_meta( $hidden ) {

		$current_meta = array( '_wcsatt_scheme' );
		$legacy_meta  = array( '_wcsatt_scheme_id' );

		return array_merge( $hidden, $current_meta, $legacy_meta );
	}
}

WCS_ATT_Order::init();
