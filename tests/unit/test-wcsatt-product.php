<?php
/**
 * Product API Tests
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Subscribe All The Things
 * @since    1.2.0
 */

/**
 * Test cases for WCS_ATT_Product class.
 *
 * @class    WCS_ATT_Product_Tests
 * @version  1.2.0
 */
class WCS_ATT_Product_Tests extends WCS_ATT_Test_Case {

	/**
	 * @covers WCS_ATT_Product::get_subscription_schemes
	 *
	 * @since 1.2.0
	 */
	public function test_get_subscription_schemes() {

		$product              = WCS_ATT_Test_Helpers_Product::create_simple_satt_product();
		$subscription_schemes = WCS_ATT_Product::get_subscription_schemes( $product );

		$this->assertEquals( 2, sizeof( $subscription_schemes ) );

		WCS_ATT_Test_Helpers_Product::delete_simple_satt_product( $product );
	}

	/**
	 * @covers WCS_ATT_Product::get_subscription_scheme
	 *
	 * @since 1.2.0
	 */
	public function test_get_subscription_scheme() {

		$product                        = WCS_ATT_Test_Helpers_Product::create_simple_satt_product();
		$active_subscription_scheme_key = WCS_ATT_Product::get_subscription_scheme( $product );

		$this->assertEquals( null, $active_subscription_scheme_key );

		WCS_ATT_Test_Helpers_Product::delete_simple_satt_product( $product );
	}

	/**
	 * @covers WCS_ATT_Product::set_subscription_scheme
	 * @covers WCS_ATT_Product::get_period
	 * @covers WCS_ATT_Product::get_interval
	 * @covers WCS_ATT_Product::get_length
	 *
	 * @since 1.2.0
	 */
	public function test_set_subscription_scheme() {

		$product = WCS_ATT_Test_Helpers_Product::create_simple_satt_product();

		// The product should not be seen as a subscription just yet.
		$this->assertFalse( WCS_ATT_Product::is_subscription( $product ) );

		$result = WCS_ATT_Product::set_subscription_scheme( $product, '1_month_5' );

		// But now it should...
		$this->assertTrue( $result );
		$this->assertEquals( '1_month_5', WCS_ATT_Product::get_subscription_scheme( $product ) );

		$this->assertTrue( WCS_ATT_Product::is_subscription( $product ) );
		$this->assertTrue( WC_Subscriptions_Product::is_subscription( $product ) );

		// ...and we should be able to get all subscription parameters from the object.
		$this->assertEquals( 'month', WCS_ATT_Product::get_period( $product ) );
		$this->assertEquals( 1, WCS_ATT_Product::get_interval( $product ) );
		$this->assertEquals( 5, WCS_ATT_Product::get_length( $product ) );

		// Setting a non-recurring scheme on the object should also work.
		$result = WCS_ATT_Product::set_subscription_scheme( $product, false );

		$this->assertTrue( $result );
		$this->assertEquals( false, WCS_ATT_Product::get_subscription_scheme( $product ) );

		// Finally, resetting the object to an undefined subscription state should also work.
		$result = WCS_ATT_Product::set_subscription_scheme( $product, null );

		$this->assertTrue( $result );
		$this->assertEquals( null, WCS_ATT_Product::get_subscription_scheme( $product ) );

		WCS_ATT_Test_Helpers_Product::delete_simple_satt_product( $product );
	}
}
