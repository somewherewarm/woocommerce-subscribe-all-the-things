<?php
/**
 * Product Schemes API Tests
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Subscribe All The Things
 * @since    2.0.0
 */

/**
 * Tests for the WCS_ATT_Product_Schemes class.
 *
 * @class    WCS_ATT_Product_Schemes_Tests
 * @version  2.0.0
 */
class WCS_ATT_Product_Schemes_Tests extends WCS_ATT_Test_Case {

	/**
	 * @covers WCS_ATT_Product_Schemes::get_subscription_schemes
	 *
	 * @since 2.0.0
	 */
	public function test_get_subscription_schemes() {

		$product              = WCS_ATT_Test_Helpers_Product::create_simple_satt_product();
		$subscription_schemes = WCS_ATT_Product_Schemes::get_subscription_schemes( $product );

		$this->assertEquals( 2, sizeof( $subscription_schemes ) );

		WCS_ATT_Test_Helpers_Product::delete_simple_satt_product( $product );
	}

	/**
	 * @covers WCS_ATT_Product_Schemes::get_subscription_scheme
	 *
	 * @since 2.0.0
	 */
	public function test_get_subscription_scheme() {

		$product                        = WCS_ATT_Test_Helpers_Product::create_simple_satt_product();
		$active_subscription_scheme_key = WCS_ATT_Product_Schemes::get_subscription_scheme( $product );

		$this->assertEquals( null, $active_subscription_scheme_key );

		WCS_ATT_Test_Helpers_Product::delete_simple_satt_product( $product );
	}

	/**
	 * @covers WCS_ATT_Product_Schemes::get_base_subscription_scheme
	 *
	 * @since 2.0.0
	 */
	public function test_get_base_subscription_scheme() {

		$scheme_settings = array(

			0 => array(
				'subscription_period_interval' => 1,
				'subscription_period'          => 'month',
				'subscription_length'          => 3
			),

			1 => array(
				'subscription_period_interval' => 1,
				'subscription_period'          => 'month',
				'subscription_length'          => 6,
				'subscription_pricing_method'  => 'inherit',
				'subscription_discount'        => 10
			)
		);

		$product = WCS_ATT_Test_Helpers_Product::create_simple_satt_product( $scheme_settings );

		$base_scheme = WCS_ATT_Product_Schemes::get_base_subscription_scheme( $product );

		$this->assertEquals( '1_month_6', $base_scheme->get_key() );
	}

	/**
	 * @covers WCS_ATT_Product_Schemes::set_subscription_scheme
	 * @covers WCS_ATT_Product_Schemes::get_subscription_scheme
	 *
	 * @since 2.0.0
	 */
	public function test_set_subscription_scheme() {

		$product = WCS_ATT_Test_Helpers_Product::create_simple_satt_product();

		$result = WCS_ATT_Product_Schemes::set_subscription_scheme( $product, '1_month_5' );

		$this->assertTrue( $result );
		$this->assertEquals( '1_month_5', WCS_ATT_Product_Schemes::get_subscription_scheme( $product ) );

		// Setting a non-recurring scheme on the object should also work.
		$result = WCS_ATT_Product_Schemes::set_subscription_scheme( $product, false );

		$this->assertTrue( $result );
		$this->assertEquals( false, WCS_ATT_Product_Schemes::get_subscription_scheme( $product ) );

		// Finally, resetting the object to an undefined subscription state should also work.
		$result = WCS_ATT_Product_Schemes::set_subscription_scheme( $product, null );

		$this->assertTrue( $result );
		$this->assertEquals( null, WCS_ATT_Product_Schemes::get_subscription_scheme( $product ) );

		WCS_ATT_Test_Helpers_Product::delete_simple_satt_product( $product );
	}

	/**
	 * @covers WCS_ATT_Product_Schemes::price_filter_exists
	 *
	 * @since 2.0.0
	 */
	public function test_price_filter_exists() {

		$scheme_settings = array(

			0 => array(
				'subscription_period_interval' => 1,
				'subscription_period'          => 'month',
				'subscription_length'          => 3
			),

			1 => array(
				'subscription_period_interval' => 1,
				'subscription_period'          => 'month',
				'subscription_length'          => 6,
				'subscription_pricing_method'  => 'inherit',
				'subscription_discount'        => 10
			)
		);

		$product = WCS_ATT_Test_Helpers_Product::create_simple_satt_product( $scheme_settings );

		$this->assertTrue( true, WCS_ATT_Product_Schemes::price_filter_exists( WCS_ATT_Product_Schemes::get_subscription_schemes( $product ) ) );
	}
}
