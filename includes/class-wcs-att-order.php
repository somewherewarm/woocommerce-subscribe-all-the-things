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
 * @version  2.0.0
 */
class WCS_ATT_Order {

	/**
	 * Flag to ensure hooks can be added only once.
	 * @var bool
	 */
	private static $added_hooks = false;

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

		if ( self::$added_hooks ) {
			return;
		}

		self::$added_hooks = true;

		// Restore subscription data when creating a cart item using an order item as reference.
		add_filter( 'woocommerce_order_again_cart_item_data', array( __CLASS__, 'restore_cart_item_from_order_item' ), 10, 3 );

		// Restore the subscription state of a product instantiated using an order item as reference.
		if ( WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ) {
			add_filter( 'woocommerce_order_item_product', array( __CLASS__, 'restore_product_from_order_item' ), 10, 2 );
		} else {
			// Using this under WC 3.0 may result in serious performance issues.
			add_filter( 'woocommerce_get_product_from_item', array( __CLASS__, 'restore_product_from_order_item' ), 10, 2 );
		}

		// Save subscription scheme in subscription item meta so it can be re-applied later.
		if ( WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ) {
			add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'save_subscription_scheme_meta' ), 10, 3 );
		} else {
			add_action( 'woocommerce_add_order_item_meta', array( __CLASS__, 'save_subscription_scheme_meta_legacy' ), 10, 2 );
		}
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
	 * @return string|false|null
	 */
	public static function get_subscription_scheme( $order_item ) {
		$scheme_key = null;
		// Because who trusts WC 3.0 for back-compat?
		if ( WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ) {
			if ( $order_item->meta_exists( '_wcsatt_scheme' ) ) {
				$scheme_key = $order_item->get_meta( '_wcsatt_scheme', true );
				$scheme_key = 0 === absint( $scheme_key ) ? false : strval( $scheme_key );
			// Backwards compatibility with v1.
			} elseif ( $order_item->meta_exists( '_wcsatt_scheme_id' ) ) {
				$scheme_key = $order_item->get_meta( '_wcsatt_scheme_id', true );
				$scheme_key = 0 === absint( $scheme_key ) ? false : strval( $scheme_key );
			}
		} else {
			if ( isset( $order_item[ 'wcsatt_scheme' ] ) ) {
				$scheme_key = 0 === absint( $order_item[ 'wcsatt_scheme' ] ) ? false : strval( $order_item[ 'wcsatt_scheme' ] );
			// Backwards compatibility with v1.
			} elseif ( isset( $order_item[ 'wcsatt_scheme_id' ] ) ) {
				$scheme_key = 0 === absint( $order_item[ 'wcsatt_scheme_id' ] ) ? false : strval( $order_item[ 'wcsatt_scheme_id' ] );
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
		if ( null !== ( $scheme_key = self::get_subscription_scheme( $order_item ) ) ) {
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
		if ( null !== ( $scheme_key = self::get_subscription_scheme( $order_item ) ) ) {
			WCS_ATT_Product_Schemes::set_subscription_scheme( $product, $scheme_key );
		}
		return $product;
	}

	/**
	 * Stores the scheme key against the order item.
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
}

WCS_ATT_Order::init();
