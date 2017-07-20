<?php
/**
 * WCS_ATT_Schemes class
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Subscribe All The Things
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deprecated subscription schemes API.
 *
 * @class    WCS_ATT_Schemes
 * @version  2.0.0
 */
class WCS_ATT_Schemes {

	/**
	 * Initialize.
	 */
	public static function init() {
		require_once( 'class-wcs-att-scheme-prices.php' );
	}

	/*
	|--------------------------------------------------------------------------
	| Deprecated
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns a subscription scheme by id (key since v2.0). Deprecated.
	 *
	 * @deprecated 2.0.0
	 *
	 * @return array
	 */
	public static function get_subscription_scheme_by_id( $id, $schemes ) {

		_deprecated_function( __METHOD__ . '()', '2.0.0', 'WCS_ATT_Product_Schemes::get_subscription_scheme()' );

		$found_scheme = false;

		foreach ( $schemes as $scheme ) {
			if ( $scheme->get_key() === $id ) {
				$found_scheme = $scheme;
				break;
			}
		}

		return $found_scheme;
	}

	/**
	 * Returns cart-level subscription schemes, available only if there are no cart-items with product-level subscription schemes.
	 * Subscription options defined at product-level and "legacy" subscription-type products "block" the display of cart-level subscription options.
	 *
	 * Must be called after all cart session data has been loaded.
	 *
	 * @deprecated 2.0.0
	 *
	 * @return array|boolean
	 */
	public static function get_cart_subscription_schemes() {
		_deprecated_function( __METHOD__ . '()', '2.0.0', 'WCS_ATT_Cart::get_subscription_schemes()' );
		return WCS_ATT_Cart::get_subscription_schemes();
	}

	/**
	 * Returns all available subscription schemes (product-level and cart-level).
	 *
	 * @param  array   $cart_item
	 * @param  string  $context
	 * @return array
	 */
	public static function get_subscription_schemes( $cart_item, $context = 'any' ) {
		_deprecated_function( __METHOD__ . '()', '2.0.0', 'WCS_ATT_Cart::get_subscription_schemes()' );
		return WCS_ATT_Cart::get_subscription_schemes( $cart_item, $context === 'cart-item' ? 'product' : $context );
	}

	/**
	 * Returns subscription schemes used to render cart-item level options.
	 *
	 * @deprecated 2.0.0
	 *
	 * @param  array  $cart_item
	 * @return array
	 */
	public static function get_cart_item_subscription_schemes( $cart_item ) {
		_deprecated_function( __METHOD__ . '()', '2.0.0', 'WCS_ATT_Cart::get_subscription_schemes()' );
		return WCS_ATT_Cart::get_subscription_schemes( $cart_item, 'product' );
	}

	/**
	 * Returns the active cart-level subscription scheme id, or false if none is set.
	 *
	 * @deprecated 2.0.0
	 *
	 * @return string
	 */
	public static function get_active_cart_subscription_scheme_id() {
		_deprecated_function( __METHOD__ . '()', '2.0.0', 'WCS_ATT_Cart::get_cart_subscription_scheme()' );
		return WCS_ATT_Cart::get_cart_subscription_scheme();
	}

	/**
	 * Returns the active subscription scheme of a cart item, or false if the cart item is a one-off purchase.
	 *
	 * @deprecated 2.0.0
	 *
	 * @return string
	 */
	public static function get_active_subscription_scheme_id( $cart_item ) {
		_deprecated_function( __METHOD__ . '()', '2.0.0', 'WCS_ATT_Cart::get_subscription_scheme()' );
		return WCS_ATT_Cart::get_subscription_scheme( $cart_item );
	}

	/**
	 * Returns the active subscription scheme of a cart item, or false if the cart item is a one-off purchase.
	 *
	 * @deprecated 2.0.0
	 *
	 * @return array
	 */
	public static function get_active_subscription_scheme( $cart_item ) {
		_deprecated_function( __METHOD__ . '()', '2.0.0', 'WCS_ATT_Cart::get_subscription_scheme() and WCS_ATT_Product_Schemes::get_subscription_scheme()' );
		$active_scheme_key = WCS_ATT_Cart::get_subscription_scheme( $cart_item );
		return WCS_ATT_Product_Scheme::get_subscription_scheme( $cart_item[ 'product' ], $active_scheme_key, 'object' );
	}

	/**
	 * Returns the default subscription scheme to set on a cart item.
	 *
	 * @deprecated  2.0.0
	 * @access      private
	 *
	 * @param  array $cart_item
	 * @param  array $cart_level_schemes
	 * @return string
	 */
	public static function set_subscription_scheme_id( $cart_item, $cart_level_schemes ) {
		_deprecated_function( __METHOD__ . '()', '2.0.0', 'WCS_ATT_Cart::get_subscription_scheme_to_apply()' );
	}

	/**
	 * Returns all available subscription schemes for displaying single-product options (product-level).
	 *
	 * @deprecated  2.0.0
	 *
	 * @return array
	 */
	public static function get_product_subscription_schemes( $product ) {
		_deprecated_function( __METHOD__ . '()', '2.0.0', 'WCS_ATT_Product_Schemes::get_subscription_schemes()' );
		return WCS_ATT_Product_Schemes::get_subscription_schemes( $product );
	}
}

WCS_ATT_Schemes::init();
