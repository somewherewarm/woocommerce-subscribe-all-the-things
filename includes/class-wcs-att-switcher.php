<?php
/**
 * WCS_ATT_Switcher class
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Subscribe All the Things
 * @since    2.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles scheme switching for SATT items.
 *
 * @class    WCS_ATT_Switcher
 * @version  2.1.0
 */
class WCS_ATT_Switcher {

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

		// Allow scheme switching for SATT products with more than 1 scheme.
		add_filter( 'wcs_is_product_switchable', array( __CLASS__, 'is_product_switchable' ), 10, 2 );

		// Disable one-time purchases when switching.
		add_filter( 'wcsatt_force_subscription', array( __CLASS__, 'force_subscription' ), 10, 2 );

		// Allow WCS to recognize any supported product as a subscription when validating a switch: Add filter.
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'add_is_subscription_filter' ), 9 );

		// Allow WCS to recognize any supported product as a subscription when validating a switch: Remove filter.
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'remove_is_subscription_filter' ), 11 );

		// Make WCS see products with a switched scheme as non-identical ones.
		add_filter( 'woocommerce_subscriptions_switch_is_identical_product', array( __CLASS__, 'is_identical_product' ), 10, 6 );

		// Modify cart item being switched.
		add_action( 'wcsatt_applied_cart_item_subscription_scheme', array( __CLASS__, 'edit_switched_cart_item' ), 10, 2 );
	}

	/**
	 * True if switching is in progress.
	 *
	 * @return boolean
	 */
	public static function switching() {
		return isset( $_GET[ 'switch-subscription' ] ) && isset( $_GET[ 'item' ] );
	}

	/**
	 * True if a subscribed product scheme/configuration is being switched.
	 *
	 * @param  WC_Product  $product_switched
	 * @return boolean
	 */
	public static function switching_product( $product_switched ) {

		$switching = false;

		if ( self::switching() ) {

			if ( is_product() ) {

				global $product;

				if ( $product->get_id() === $product_switched->get_id() ) {
					$switching = true;
				}

			} elseif ( ! empty( $_REQUEST[ 'add-to-cart' ] ) && is_numeric( $_REQUEST[ 'add-to-cart' ] ) ) {

				$product_id = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $_REQUEST[ 'add-to-cart' ] ) );

				if ( isset( $_REQUEST[ 'convert_to_sub_' . $product_id ] ) ) {
					$posted    = wc_clean( $_REQUEST[ 'convert_to_sub_' . $product_id ] );
					$switching = ! empty( $posted );
				}
			}
		}

		return $switching;
	}

	/*
	|--------------------------------------------------------------------------
	| Hooks
	|--------------------------------------------------------------------------
	*/

	/**
	 * Allow scheme switching for SATT products with more than 1 subscription scheme.
	 *
	 * @param  boolean     $is_switchable
	 * @param  WC_Product  $product
	 * @return boolean
	 */
	public static function is_product_switchable( $is_switchable, $product ) {

		if ( ! $is_switchable ) {
			$is_switchable = WCS_ATT_Product::supports_feature( $product, 'subscription_scheme_switching' );
		}

		return $is_switchable;
	}

	/**
	 * Disable one-time purchases when switching.
	 *
	 * @param  boolean     $is_forced
	 * @param  WC_Product  $product
	 * @return boolean
	 */
	public static function force_subscription( $is_forced, $product ) {

		if ( ! $is_forced && self::switching() ) {
			$is_forced = self::switching_product( $product );
		}

		return $is_forced;
	}

	/**
	 * Allow WCS to recognize any supported product as a subscription when validating a switch: Add filter.
	 *
	 * @param  boolean  $is_valid
	 * @return boolean
	 */
	public static function add_is_subscription_filter( $is_valid ) {

		if ( self::switching() ) {
			add_filter( 'woocommerce_is_subscription', array( __CLASS__, 'filter_is_subscription' ), 11, 3 );
		}

		return $is_valid;
	}

	/**
	 * Allow WCS to recognize any supported product as a subscription when validating a switch: Remove filter.
	 *
	 * @param  boolean  $is_valid
	 * @return boolean
	 */
	public static function remove_is_subscription_filter( $is_valid ) {

		if ( self::switching() ) {
			remove_filter( 'woocommerce_is_subscription', array( __CLASS__, 'filter_is_subscription' ), 11, 3 );
		}

		return $is_valid;
	}

	/**
	 * Hooks onto 'woocommerce_is_subscription' to trick WCS into thinking it is dealing with a subscription-type product when switching.
	 *
	 * @param  boolean     $is
	 * @param  int         $product_id
	 * @param  WC_Product  $product
	 * @return boolean
	 */
	public static function filter_is_subscription( $is, $product_id, $product ) {

		if ( self::switching() ) {

			if ( ! is_a( $product, 'WC_Product' ) ) {
				$product = wc_get_product( $product_id );
			}

			if ( ! $product ) {
				return $is;
			}

			if ( self::switching_product( $product ) && WCS_ATT_Product_Schemes::has_subscription_schemes( $product ) ) {
				$is = true;
			}
		}

		return $is;
	}

	/**
	 * Make WCS see products with a switched scheme as non-identical ones.
	 *
	 * @param  boolean        $is_identical
	 * @param  int            $product_id
	 * @param  int            $quantity
	 * @param  int            $variation_id
	 * @param  WC_Order       $subscription
	 * @param  WC_Order_Item  $item
	 * @return boolean
	 */
	public static function is_identical_product( $is_identical, $product_id, $quantity, $variation_id, $subscription, $item ) {

		if ( $is_identical ) {

			if ( isset( $_REQUEST[ 'convert_to_sub_' . $product_id ] ) ) {

				$new_subscription_scheme_key = wc_clean( $_REQUEST[ 'convert_to_sub_' . $product_id ] );
				$old_subscription_scheme_key = WCS_ATT_Order::get_subscription_scheme( $item );

				if ( $new_subscription_scheme_key && $new_subscription_scheme_key !== $old_subscription_scheme_key ) {
					$is_identical = false;
				}
			}
		}

		return $is_identical;
	}

	/**
	 * Modify cart item being switched.
	 *
	 * @param  array   $cart_item
	 * @param  string  $cart_item_key
	 * @return void
	 */
	public static function edit_switched_cart_item( $cart_item, $cart_item_key ) {

		/*
		 * Keep only the applied scheme when switching.
		 * If we don't do this, then multiple scheme options will show up next to the cart item.
		 */
		if ( isset( $cart_item[ 'subscription_switch' ] ) ) {

			$applied_scheme = WCS_ATT_Product_Schemes::get_subscription_scheme( $cart_item[ 'data' ] );
			$schemes        = array();

			foreach ( WCS_ATT_Cart::get_subscription_schemes( $cart_item ) as $scheme_key => $scheme ) {

				if ( $scheme_key === $applied_scheme ) {
					$schemes[ $scheme_key ] = $scheme;
				}
			}

			WCS_ATT_Product_Schemes::set_subscription_schemes( WC()->cart->cart_contents[ $cart_item_key ][ 'data' ], $schemes );
			WCS_ATT_Product_Schemes::set_forced_subscription_scheme( WC()->cart->cart_contents[ $cart_item_key ][ 'data' ], true );
		}
	}
}

WCS_ATT_Switcher::init();
