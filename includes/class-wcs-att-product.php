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
	 * DB meta expected by WCS that needs to be added by SATT at runtime.
	 * @var array
	 */
	private static $subscription_product_type_meta_keys = array(
		'subscription_period',
		'subscription_period_interval',
		'subscription_length',
		'subscription_trial_period',
		'subscription_trial_length',
		'subscription_sign_up_fee'
	);

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

		// Allow WCS to recognize any product as a subscription.
		add_filter( 'woocommerce_is_subscription', array( __CLASS__, 'filter_is_subscription' ), 10, 3 );

		// Delete object meta in use by the application layer.
		add_action( 'woocommerce_before_product_object_save', array( __CLASS__, 'delete_runtime_meta' ) );
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
	public static function is_subscription_product_type( $product ) {
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
	 * Note that the subscription state of a product object:
	 *
	 * 1. Cannot be persisted in the DB.
	 * 2. Is lost when the object is saved.
	 *
	 * This is intended behavior.
	 *
	 * @param  WC_Product  $product
	 */
	public static function delete_runtime_meta( $product ) {

		$product->delete_meta_data( '_satt_data' );

		// Don't delete any subscription product-type meta :)
		if ( ! self::is_subscription_product_type( $product ) ) {
			foreach ( self::$subscription_product_type_meta_keys as $runtime_meta_key ) {
				$product->delete_meta_data( '_' . $runtime_meta_key );
			}
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
	 * @param  WC_Product  $product  Product object.
	 * @param  string      $key      Runtime meta key name.
	 * @return mixed
	 */
	public static function get_runtime_meta( $product, $key ) {

		if ( in_array( $key, self::$subscription_product_type_meta_keys ) ) {

			$value = $product->get_meta( '_' . $key, true );

		} else {

			$data = $product->get_meta( '_satt_data', true );

			if ( is_array( $data ) && isset( $data[ $key ] ) ) {
				$value = $data[ $key ];
			} else {
				$value = '';
			}
		}

		return $value;
	}

	/**
	 * Property setter (compatibility wrapper).
	 *
	 * @param  WC_Product  $product  Product object.
	 * @param  string      $key      Runtime meta key name.
	 * @param  string      $value    Property value.
	 * @return mixed
	 */
	public static function set_runtime_meta( $product, $key, $value ) {

		if ( in_array( $key, self::$subscription_product_type_meta_keys ) ) {

			$product->add_meta_data( '_' . $key, $value, true );

		} else {

			$data = $product->get_meta( '_satt_data', true );

			if ( empty( $data ) ) {
				$data = array();
			}

			$data[ $key ] = $value;

			$product->add_meta_data( '_satt_data', $data, true );
		}
	}
}

WCS_ATT_Product::init();
