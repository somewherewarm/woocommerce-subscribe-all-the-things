<?php
/**
 * Product helpers
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Subscribe All The Things
 * @since    1.2.0
 */

/**
 * WCS_ATT_Product_Helpers class.
 *
 * This helper class should ONLY be used for unit tests!
 */
class WCS_ATT_Test_Helpers_Product {

	/**
	 * Delete a composite and everything in it.
	 *
	 * @since  1.2.0
	 * @param  WC_Product  $product
	 */
	public static function delete_simple_satt_product( $product ) {
		WC_Helper_Product::delete_product( $product->get_id() );
	}

	/**
	 * Create product composite.
	 *
	 * @since 1.2.0
	 *
	 * @param  array  $config
	 * @return WC_Product
	 */
	public static function create_simple_satt_product( $config = array() ) {

		// Create a simple product.
		$simple_product = WC_Helper_Product::create_simple_product();

		// Add a couple subscription schemes to it.
		$product_id = $simple_product->get_id();

		// Default schemes configuration.
		$config = wp_parse_args( $config, array(

			'scheme_data' => array(

				0 => array(
					'subscription_period_interval' => 1,
					'subscription_period'          => 'month',
					'subscription_length'          => 5
				),

				1 => array(
					'subscription_period_interval' => 2,
					'subscription_period'          => 'month',
					'subscription_length'          => 10
				)
			),

			'force_subscription' => false
		) );

		$scheme_data = array();

		foreach ( $config[ 'scheme_data' ] as $scheme ) {
			$id                 = $scheme[ 'subscription_period_interval' ] . '_' . $scheme[ 'subscription_period' ] . '_' . $scheme[ 'subscription_length' ];
			$scheme_data[ $id ] = array_merge( $scheme, array( 'id' => $id ) );
		}

		// Store schemes.
		update_post_meta( $product_id, '_wcsatt_schemes', $scheme_data );

		// Store other options.
		update_post_meta( $product_id, '_wcsatt_force_subscription', $config[ 'force_subscription' ] ? 'yes' : 'no' );

		return $simple_product;
	}
}
