<?php
/**
 * SATT Products API.
 *
 * @class 	WCS_ATT_Products
 * @version 1.0.3
 */

class WCS_ATT_Products {

	public static function init() {

		add_filter( 'woocommerce_product_class', array( __CLASS__, 'get_product_class' ), 1000, 4 );
	}

	public static function get_product_class( $classname, $product_type, $post_type, $product_id ) {
		return 'WCS_ATT_Product';
	}

	public static function get_wc_product( $product ) {
		remove_filter( 'woocommerce_product_class', array( __CLASS__, 'get_product_class' ), 1000, 4 );
		$product = wc_get_product( $product );
		add_filter( 'woocommerce_product_class', array( __CLASS__, 'get_product_class' ), 1000, 4 );
		return $product;
	}

	public static function convert_to_sub( $product, $subscription_scheme ) {

		$product->is_converted_to_sub          = 'yes';
		$product->subscription_period          = $subscription_scheme[ 'subscription_period' ];
		$product->subscription_period_interval = $subscription_scheme[ 'subscription_period_interval' ];
		$product->subscription_length          = $subscription_scheme[ 'subscription_length' ];

		return $product;
	}
}

WCS_ATT_Products::init();
