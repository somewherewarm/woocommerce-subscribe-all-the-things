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

		$result = WCS_ATT_Product_Schemes::set_subscription_scheme( $product, '1_month_5' );

		// But now it should...
		$this->assertTrue( WCS_ATT_Product::is_subscription( $product ) );
		$this->assertTrue( WC_Subscriptions_Product::is_subscription( $product ) );

		WCS_ATT_Test_Helpers_Product::delete_simple_satt_product( $product );
	}
}
