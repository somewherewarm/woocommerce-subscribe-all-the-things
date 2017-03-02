<?php
/**
 * SATT Price API Tests
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Subscribe All The Things
 * @since    1.2.0
 */

/**
 * Test cases for WCS_ATT_Scheme_Prices class.
 *
 * @class  WCS_ATT_Scheme_Prices_Tests
 * @since  1.2.0
 */
class WCS_ATT_Scheme_Prices_Tests extends WCS_ATT_Test_Case {

	/**
	 * @covers WCS_ATT_Scheme_Prices::price_filter_exists
	 *
	 * @since 1.2.0
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

		$this->assertTrue( true, WCS_ATT_Scheme_Prices::price_filter_exists( WCS_ATT_Product::get_subscription_schemes( $product ) ) );
	}

	/**
	 * @covers WCS_ATT_Scheme_Prices::get_base_scheme
	 *
	 * @since 1.2.0
	 */
	public function test_get_base_scheme() {

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

		$base_scheme = WCS_ATT_Scheme_Prices::get_base_scheme( $product );

		$this->assertEquals( '1_month_6', $base_scheme->get_key() );
	}

	/**
	 * @covers WCS_ATT_Scheme_Prices::filter_get_price
	 * @covers WCS_ATT_Scheme_Prices::filter_get_sale_price
	 * @covers WCS_ATT_Scheme_Prices::filter_get_regular_price
	 * @covers WCS_ATT_Product::get_price
	 * @covers WCS_ATT_Product::get_sale_price
	 * @covers WCS_ATT_Product::get_regular_price
	 *
	 * @since 1.2.0
	 */
	public function test_get_price() {

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
			),

			2=> array(
				'subscription_period_interval' => 1,
				'subscription_period'          => 'month',
				'subscription_length'          => 9,
				'subscription_pricing_method'  => 'override',
				'subscription_regular_price'   => 10,
				'subscription_sale_price'      => 8
			)
		);

		$product = WCS_ATT_Test_Helpers_Product::create_simple_satt_product( $scheme_settings );

		// Product price with undefined subscription state.
		$this->assertEquals( 10, $product->get_price() );

		// Product price with a non-recurring scheme set on the object.
		WCS_ATT_Product::set_subscription_scheme( $product, false );
		$this->assertEquals( 10, $product->get_price() );

		// Product price with scheme '0' set on the object.
		WCS_ATT_Product::set_subscription_scheme( $product, '1_month_3' );
		$this->assertEquals( 10, $product->get_regular_price() );
		$this->assertEquals( '', $product->get_sale_price() );
		$this->assertEquals( 10, $product->get_price() );

		// Product price with scheme '1' set on the object.
		WCS_ATT_Product::set_subscription_scheme( $product, '1_month_6' );
		$this->assertEquals( 10, $product->get_regular_price() );
		$this->assertEquals( 9, $product->get_sale_price() );
		$this->assertEquals( 9, $product->get_price() );

		// Product price with scheme '2' set on the object.
		WCS_ATT_Product::set_subscription_scheme( $product, '1_month_9' );
		$this->assertEquals( 10, $product->get_regular_price() );
		$this->assertEquals( 8, $product->get_sale_price() );
		$this->assertEquals( 8, $product->get_price() );

		WCS_ATT_Test_Helpers_Product::delete_simple_satt_product( $product );
	}

	/**
	 * @covers WCS_ATT_Scheme_Prices::filter_get_price_html
	 * @covers WCS_ATT_Product::get_price_html
	 *
	 * @since 1.2.0
	 */
	public function test_get_price_html() {

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
			),

			2=> array(
				'subscription_period_interval' => 1,
				'subscription_period'          => 'month',
				'subscription_length'          => 9,
				'subscription_pricing_method'  => 'override',
				'subscription_regular_price'   => 10,
				'subscription_sale_price'      => 8
			)
		);

		$product = WCS_ATT_Test_Helpers_Product::create_simple_satt_product( $scheme_settings );

		// Product price html with defined subscription state.
		WCS_ATT_Product::set_subscription_scheme( $product, '1_month_3' );
		$this->assertEquals( '<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">&pound;</span>10.00</span> <span class="subscription-details"> / month for 3 months</span>', $product->get_price_html() );

		// Product price html with defined subscription state and discount.
		WCS_ATT_Product::set_subscription_scheme( $product, '1_month_6' );
		$this->assertEquals( '<del><span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">&pound;</span>10.00</span></del> <ins><span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">&pound;</span>9.00</span></ins> <span class="subscription-details"> / month for 6 months</span>', $product->get_price_html() );

		// Product price html with defined subscription state and price override.
		WCS_ATT_Product::set_subscription_scheme( $product, '1_month_9' );
		$this->assertEquals( '<del><span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">&pound;</span>10.00</span></del> <ins><span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">&pound;</span>8.00</span></ins> <span class="subscription-details"> / month for 9 months</span>', $product->get_price_html() );

		// Product price html with non-recurring (empty) scheme.
		WCS_ATT_Product::set_subscription_scheme( $product, false );
		$this->assertEquals( '<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">&pound;</span>10.00</span>', $product->get_price_html() );

		// Tough one: Product price html with undefined subscription state.
		WCS_ATT_Product::set_subscription_scheme( $product, null );
		$this->assertEquals( '<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">&pound;</span>10.00</span> <small class="wcsatt-sub-options">&ndash; subscription plans <span class="from">starting at </span><del><span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">&pound;</span>10.00</span></del> <ins><span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">&pound;</span>8.00</span></ins> <span class="subscription-details"> / month for 9 months</span></small>', $product->get_price_html() );

		WCS_ATT_Test_Helpers_Product::delete_simple_satt_product( $product );
	}
}
