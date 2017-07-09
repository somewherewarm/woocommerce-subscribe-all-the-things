<?php
/**
 * WCS_ATT_Product API
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
 * API for working with subscription-enabled product objects.
 *
 * @class    WCS_ATT_Product
 * @version  2.0.0
 */
class WCS_ATT_Product {

	/**
	 * Flag to ensure hooks can be added only once.
	 * @var bool
	 */
	private static $added_hooks = false;

	/**
	 * Include Product API price and scheme components and add hooks.
	 */
	public static function init() {

		require_once( 'product/class-wcs-att-product-schemes.php' );
		require_once( 'product/class-wcs-att-product-prices.php' );

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

		// Allow WCS to recognize any product as a subscription.
		add_filter( 'woocommerce_is_subscription', array( __CLASS__, 'filter_is_subscription' ), 10, 3 );

		// Delete object meta in use by the application layer.
		add_action( 'woocommerce_before_product_object_save', array( __CLASS__, 'delete_reserved_meta' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Conditionals
	|--------------------------------------------------------------------------
	*/

	/**
	 * Determines if a subscription scheme is set on the product object.
	 *
	 * @param  WC_Product  $product  Product object to check.
	 * @return boolean               Result of check.
	 */
	public static function is_subscription( $product ) {
		return WCS_ATT_Product_Schemes::has_active_subscription_scheme( $product );
	}

	/**
	 * Checks a product object to determine if it is a WCS subscription-type product.
	 *
	 * @param  WC_Product  $product  Product object to check.
	 * @return boolean               Result of check.
	 */
	public static function is_legacy_subscription( $product ) {
		return $product->is_type( array( 'subscription', 'subscription_variation', 'variable-subscription' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Filters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Hooks onto 'woocommerce_is_subscription' to trick WCS into thinking it is dealing with a subscription-type product.
	 *
	 * @param  boolean     $is
	 * @param  int         $product_id
	 * @param  WC_Product  $product
	 * @return boolean
	 */
	public static function filter_is_subscription( $is, $product_id, $product ) {

		if ( ! $product ) {
			return $is;
		}

		if ( self::is_subscription( $product ) ) {
			$is = true;
		}

		return $is;
	}

	/**
	 * Delete object meta in use by the application layer.
	 *
	 * @param  WC_Product  $product
	 */
	public static function delete_reserved_meta( $product ) {
		$reserved_meta_keys = array( 'has_forced_subscription', 'subscription_schemes', 'active_subscription_scheme_key', 'default_subscription_scheme_key' );
		foreach ( $reserved_meta_keys as $reserved_meta_key ) {
			$product->delete_meta_data( '_' . $reserved_meta_key );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Property getter (compatibility wrapper).
	 *
	 * @param  WC_Product  $product   Product object.
	 * @param  string      $property  Property name.
	 * @return mixed
	 */
	public static function get_product_property( $product, $property ) {

		if ( WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ) {
			$value = $product->get_meta( '_' . $property, true );
		} else {
			$value = isset( $product->$property ) ? $product->$property : '';
		}

		return $value;
	}

	/**
	 * Property setter (compatibility wrapper).
	 *
	 * @param  WC_Product  $product  Product object.
	 * @param  string      $name     Property name.
	 * @param  string      $value    Property value.
	 * @return mixed
	 */
	public static function set_product_property( $product, $name, $value ) {

		if ( WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ) {
			$product->add_meta_data( '_' . $name, $value, true );
		} else {
			$product->$name = $value;
		}
	}
}

WCS_ATT_Product::init();
