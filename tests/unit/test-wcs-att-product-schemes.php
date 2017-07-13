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
	 * @covers WCS_ATT_Product_Schemes::has_forced_subscription_scheme
	 *
	 * @since 2.0.0
	 */
	public function test_has_forced_subscription_scheme() {

		$product_one_time = WCS_ATT_Test_Helpers_Product::create_simple_satt_product();

		$this->assertEquals( false, WCS_ATT_Product_Schemes::has_forced_subscription_scheme( $product_one_time ) );

		$product_sub = WCS_ATT_Test_Helpers_Product::create_simple_satt_product( array( 'force_subscription' => true ) );

		$this->assertEquals( true, WCS_ATT_Product_Schemes::has_forced_subscription_scheme( $product_sub ) );

		WCS_ATT_Test_Helpers_Product::delete_simple_satt_product( $product_one_time );
		WCS_ATT_Test_Helpers_Product::delete_simple_satt_product( $product_sub );
	}

	/**
	 * @covers WCS_ATT_Product_Schemes::get_subscription_scheme
	 *
	 * @since 2.0.0
	 */
	public function test_get_subscription_scheme() {

		$product = WCS_ATT_Test_Helpers_Product::create_simple_satt_product();

		// Nothing set.
		$this->assertSame( null, WCS_ATT_Product_Schemes::get_subscription_scheme( $product ) );

		// Test one-time option.
		WCS_ATT_Product::set_runtime_meta( $product, 'active_subscription_scheme_key', false );
		$this->assertSame( false, WCS_ATT_Product_Schemes::get_subscription_scheme( $product ) );

		// Test a valid option.
		WCS_ATT_Product::set_runtime_meta( $product, 'active_subscription_scheme_key', '1_month_5' );
		$this->assertEquals( '1_month_5', WCS_ATT_Product_Schemes::get_subscription_scheme( $product ) );

		WCS_ATT_Test_Helpers_Product::delete_simple_satt_product( $product );
	}

	/**
	 * @covers WCS_ATT_Product_Schemes::get_subscription_scheme
	 *
	 * @since 2.0.0
	 */
	public function test_get_subscription_scheme_invalid() {

		$product = WCS_ATT_Test_Helpers_Product::create_simple_satt_product( array( 'force_subscription' => true ) );

		// Test an invalid option.
		WCS_ATT_Product::set_runtime_meta( $product, 'active_subscription_scheme_key', 'xxx' );
		$this->assertSame( null, WCS_ATT_Product_Schemes::get_subscription_scheme( $product ) );

		// Test another invalid option.
		WCS_ATT_Product::set_runtime_meta( $product, 'active_subscription_scheme_key', false );
		$this->assertSame( null, WCS_ATT_Product_Schemes::get_subscription_scheme( $product ) );

		WCS_ATT_Test_Helpers_Product::delete_simple_satt_product( $product );
	}

	/**
	 * @covers WCS_ATT_Product_Schemes::get_base_subscription_scheme
	 *
	 * @since 2.0.0
	 */
	public function test_get_base_subscription_scheme() {

		$scheme_data = array(

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

		$product = WCS_ATT_Test_Helpers_Product::create_simple_satt_product( array( 'scheme_data' => $scheme_data ) );

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
		$this->assertSame( false, WCS_ATT_Product_Schemes::get_subscription_scheme( $product ) );

		// Finally, resetting the object to an undefined subscription state should also work.
		$result = WCS_ATT_Product_Schemes::set_subscription_scheme( $product, null );

		$this->assertTrue( $result );
		$this->assertSame( null, WCS_ATT_Product_Schemes::get_subscription_scheme( $product ) );

		WCS_ATT_Test_Helpers_Product::delete_simple_satt_product( $product );
	}

	/**
	 * @covers WCS_ATT_Product_Schemes::get_default_subscription_scheme
	 *
	 * @since 2.0.0
	 */
	public function test_get_default_subscription_scheme() {

		$product = WCS_ATT_Test_Helpers_Product::create_simple_satt_product();

		$this->assertEquals( false, WCS_ATT_Product_Schemes::get_default_subscription_scheme( $product, 'key' ) );

		// Now prevent one-time purchases.
		WCS_ATT_Product_Schemes::set_forced_subscription_scheme( $product, true );

		$this->assertEquals( '1_month_5', WCS_ATT_Product_Schemes::get_default_subscription_scheme( $product, 'key' ) );

		// Delete the first scheme and update the object.
		$schemes = WCS_ATT_Product_Schemes::get_subscription_schemes( $product );

		array_shift( $schemes );

		WCS_ATT_Product_Schemes::set_subscription_schemes( $product, $schemes );

		$this->assertEquals( '2_month_10', WCS_ATT_Product_Schemes::get_default_subscription_scheme( $product, 'key' ) );

		WCS_ATT_Test_Helpers_Product::delete_simple_satt_product( $product );
	}

	/**
	 * @covers WCS_ATT_Product_Schemes::price_filter_exists
	 *
	 * @since 2.0.0
	 */
	public function test_price_filter_exists() {

		$scheme_data = array(

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

		$product = WCS_ATT_Test_Helpers_Product::create_simple_satt_product( array( 'scheme_data' => $scheme_data ) );

		$this->assertTrue( true, WCS_ATT_Product_Schemes::price_filter_exists( WCS_ATT_Product_Schemes::get_subscription_schemes( $product ) ) );
	}
}
