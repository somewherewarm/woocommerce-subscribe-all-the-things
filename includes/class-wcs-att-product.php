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
 * @version  2.1.0
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
		'subscription_sign_up_fee',
		'subscription_payment_sync_date'
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

	/**
	 * Query for support of SATT features.
	 *
	 * @param  WC_Product  $product  Product object to check.
	 * @param  string      $feature  Feature.
	 * @param  array       $args     Additional arguments.
	 * @return boolean               Result.
	 */
	public static function supports_feature( $product, $feature, $args = array() ) {

		$is_feature_supported = false;

		switch ( $feature ) {

			case 'subscription_schemes':

				$supported_product_types = WCS_ATT()->get_supported_product_types();
				$is_feature_supported    = in_array( $product->get_type(), $supported_product_types );

			break;
			case 'subscription_scheme_options_product_single':

				$subscription_schemes = WCS_ATT_Product_Schemes::get_subscription_schemes( $product );
				$is_feature_supported = apply_filters( 'wcsatt_show_single_product_options', ! empty( $subscription_schemes ), $product );

			break;
			case 'subscription_scheme_options_product_cart':

				if ( isset( $args[ 'cart_item' ] ) && isset( $args[ 'cart_item_key' ] ) ) {
					$subscription_schemes = WCS_ATT_Cart::get_subscription_schemes( $args[ 'cart_item' ], 'product' );
					$is_feature_supported = apply_filters( 'wcsatt_show_cart_item_options', ! empty( $subscription_schemes ), $args[ 'cart_item' ], $args[ 'cart_item_key' ] );
				}

			break;
			case 'subscription_scheme_switching':

				// Scheme switching allowed for all products with more than 1 subscription scheme, or variable ones.
				$subscription_schemes = WCS_ATT_Product_Schemes::get_subscription_schemes( $product );
				$is_feature_supported = sizeof( $subscription_schemes ) > 1 || ( sizeof( $subscription_schemes ) && $product->is_type( array( 'variable', 'variation' ) ) );

			break;
			case 'subscription_management_add_to_subscription':

				$add_to_subscription_option = get_option( 'wcsatt_add_product_to_subscription', 'off' );
				$is_feature_supported       = false;

				if ( 'off' !== $add_to_subscription_option ) {

					$is_feature_supported = self::supports_feature( $product, 'subscription_schemes' ) && false === $product->is_type( 'mix-and-match' ) && $product->is_purchasable();

					/**
					 * Important: Products with subscription schemes are matched to existing subscriptions with the same billing schedule as the chosen one.
					 * This behavior can be customized using the 'wcsatt_subscriptions_matching_product' filter - see 'WCS_ATT_Manage_Add_Product::load_matching_subscriptions'.
					 */

					if ( 'matching_schemes' === $add_to_subscription_option ) {
						$is_feature_supported = $is_feature_supported && self::supports_feature( $product, 'subscription_scheme_options_product_single' );
					}
				}

			break;
		}

		/**
		 * 'wcsatt_product_supports_feature' filter.
		 *
		 * @since  2.1.0
		 *
		 * @param  bool        $is_feature_supported
		 * @param  WC_Product  $product
		 * @param  string      $feature
		 * @param  array       $args
		 */
		return apply_filters( 'wcsatt_product_supports_feature', $is_feature_supported, $product, $feature, $args );
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
