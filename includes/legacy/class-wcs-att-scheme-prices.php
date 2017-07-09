<?php
/**
 * WCS_ATT_Scheme_Prices class
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Subscribe All the Things
 * @since    1.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deprecated subscription scheme prices API.
 *
 * @class    WCS_ATT_Scheme_Prices
 * @version  2.0.0
 */
class WCS_ATT_Scheme_Prices {

	/**
	 * @deprecated
	 * @var array
	 */
	public static $price_overriding_scheme = false;
	/**
	 * @deprecated
	 * @var WC_Product
	 */
	public static $price_overridden_product = false;

	/*
	|--------------------------------------------------------------------------
	| Deprecated
	|--------------------------------------------------------------------------
	*/

	/**
	 * Add price filters to modify child product prices.
	 *
	 * @deprecated  2.0.0
	 *
	 * @param   array  $subscription_scheme
	 * @return  void
	 */
	public static function add_price_filters( $product, $subscription_scheme ) {
		_deprecated_function( __METHOD__ . '()', '2.0.0', 'WCS_ATT_Product_Price_Filters::add()' );
		WCS_ATT_Product_Price_Filters::add( 'price' );
	}

	/**
	 * Remove price filters after modifying product prices.
	 *
	 * @deprecated  2.0.0
	 *
	 * @return  void
	 */
	public static function remove_price_filters() {
		_deprecated_function( __METHOD__ . '()', '2.0.0', 'WCS_ATT_Product_Price_Filters::remove()' );
		WCS_ATT_Product_Price_Filters::remove( 'price' );
	}

	/**
	 * Returns modified raw prices based on subscription scheme settings.
	 *
	 * @deprecated  2.0.0
	 *
	 * @param  array  $raw_prices
	 * @param  array  $subscription_scheme
	 * @return string
	 */
	public static function get_subscription_scheme_prices( $raw_prices, $subscription_scheme ) {
		_deprecated_function( __METHOD__ . '()', '2.0.0', 'WCS_ATT_Scheme::get_prices()' );
		return $subscription_scheme->get_prices( $raw_prices );
	}

	/**
	 * True if any of the subscription schemes overrides the default price.
	 *
	 * @deprecated  2.0.0
	 *
	 * @param  array  $subscription_schemes
	 * @return boolean
	 */
	public static function subscription_price_overrides_exist( $subscription_schemes ) {
		_deprecated_function( __METHOD__ . '()', '2.0.0', 'WCS_ATT_Product_Schemes::price_filter_exists()' );
		return WCS_ATT_Product_Schemes::price_filter_exists( $subscription_schemes );
	}

	/**
	 * True if a subscription scheme modifies the price of the product it's attached onto when active.
	 *
	 * @deprecated  2.0.0
	 *
	 * @param  WCS_ATT_Scheme  $subscription_scheme
	 * @return boolean
	 */
	public static function has_subscription_price_override( $subscription_scheme ) {
		_deprecated_function( __METHOD__ . '()', '2.0.0', 'WCS_ATT_Scheme::has_price_filter()' );
		return $subscription_scheme->has_price_filter();
	}

	/**
	 * Returns lowest price data for a product given the subscription schemes attached to it.
	 *
	 * @deprecated  2.0.0
	 *
	 * @param  WC_Product  $product
	 * @param  array       $subscription_schemes
	 * @return array
	 */
	public static function get_lowest_price_subscription_scheme_data( $product, $subscription_schemes ) {
		_deprecated_function( __METHOD__ . '()', '2.0.0', 'WCS_ATT_Product_Schemes::get_base_susbcription_scheme()' );
		$base_scheme = WCS_ATT_Product_Schemes::get_base_susbcription_scheme( $product );
		$data        = apply_filters( 'wcsatt_get_lowest_price_sub_scheme_data', array(
			'scheme'        => $base_scheme,
			'price'         => WCS_ATT_Product_Prices::get_price( $product, $base_scheme->get_key() ),
			'sale_price'    => WCS_ATT_Product_Prices::get_sale_price( $product, $base_scheme->get_key() ),
			'regular_price' => WCS_ATT_Product_Prices::get_regular_price( $product, $base_scheme->get_key() ),
		), $base_scheme);
		return $data;
	}
}
