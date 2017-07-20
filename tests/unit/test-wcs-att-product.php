<?php
/**
 * Product API Tests
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Subscribe All The Things
 * @since    2.0.0
 */

/**
 * Tests for the WCS_ATT_Product class.
 *
 * @class    WCS_ATT_Product_Tests
 * @version  2.0.0
 */
class WCS_ATT_Product_Tests extends WCS_ATT_Test_Case {

	/**
	 * @covers WCS_ATT_Product_Schemes::is_subscription
	 *
	 * @since 2.0.0
	 */
	public function test_is_subscription() {

		$product = WCS_ATT_Test_Helpers_Product::create_simple_satt_product();

		// The product should not be seen as a subscription just yet.
		$this->assertFalse( WCS_ATT_Product::is_subscription( $product ) );

		WCS_ATT_Product_Schemes::set_subscription_scheme( $product, '1_month_5' );

		// But now it should...
		$this->assertTrue( WCS_ATT_Product::is_subscription( $product ) );
		$this->assertTrue( WC_Subscriptions_Product::is_subscription( $product ) );

		WCS_ATT_Test_Helpers_Product::delete_simple_satt_product( $product );
	}

	/**
	 * @covers WCS_ATT_Product_Schemes::set_runtime_meta
	 * @covers WCS_ATT_Product_Schemes::get_runtime_meta
	 *
	 * @since 2.0.0
	 */
	public function test_get_set_runtime_meta() {

		$product = WCS_ATT_Test_Helpers_Product::create_simple_satt_product();

		// Ensure runtime meta is created on the object correctly.
		WCS_ATT_Product_Schemes::set_subscription_scheme( $product, '1_month_5' );
		$this->assertNotEmpty( $product->get_meta( '_satt_data', true ) );
		$this->assertEquals( 2, sizeof( WCS_ATT_Product::get_runtime_meta( $product, 'subscription_schemes' ) ) );

		$this->assertEquals( 'month', WCS_ATT_Product::get_runtime_meta( $product, 'subscription_period' ) );
		$this->assertEquals( '1', WCS_ATT_Product::get_runtime_meta( $product, 'subscription_period_interval' ) );
		$this->assertEquals( '5', WCS_ATT_Product::get_runtime_meta( $product, 'subscription_length' ) );

		// Keys persisted by WCS in the DB should be retrievable using the WC core meta getter as well.
		$this->assertEquals( 'month', $product->get_meta( '_subscription_period', true ) );
		$this->assertEquals( '1', $product->get_meta( '_subscription_period_interval', true ) );
		$this->assertEquals( '5', $product->get_meta( '_subscription_length', true ) );

		WCS_ATT_Test_Helpers_Product::delete_simple_satt_product( $product );
	}

	/**
	 * @covers WCS_ATT_Product_Schemes::delete_runtime_meta
	 *
	 * @since 2.0.0
	 */
	public function test_delete_runtime_meta() {

		$product = WCS_ATT_Test_Helpers_Product::create_simple_satt_product();

		// Set a scheme on the object.
		WCS_ATT_Product_Schemes::set_subscription_scheme( $product, '1_month_5' );

		// Save.
		$product->save();

		// Runtime meta shouldn't be there anymore.
		$this->assertEmpty( $product->get_meta( '_satt_data', true ) );
		$this->assertEmpty( $product->get_meta( '_subscription_period', true ) );
		$this->assertEmpty( $product->get_meta( '_subscription_period_interval', true ) );
		$this->assertEmpty( $product->get_meta( '_subscription_length', true ) );

		WCS_ATT_Test_Helpers_Product::delete_simple_satt_product( $product );
	}
}
