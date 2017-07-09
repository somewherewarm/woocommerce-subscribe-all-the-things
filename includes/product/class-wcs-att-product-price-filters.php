<?php
/**
 * WCS_ATT_Product_Price_Filters class
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
 * Handles modifications to the prices of subscription-enabled product objects.
 *
 * @class    WCS_ATT_Product_Price_Filters
 * @version  2.0.0
 */
class WCS_ATT_Product_Price_Filters {

	/*
	|--------------------------------------------------------------------------
	| Public Price Filters API
	|--------------------------------------------------------------------------
	*/

	/**
	 * Add price filters. Filtering early allows us to override "raw" prices as safely as possible.
	 * This allows 3p code to apply discounts or other transformations on overridden prices.
	 * The catch: Any price filters added by 3p code with a priority earlier than 0 will be rendered ineffective.
	 *
	 * @param  string  $context  Filtering context. Values: 'price', 'price_html', ''.
	 * @return void
	 */
	public static function add( $context = '' ) {

		if ( in_array( $context, array( 'price', '' ) ) ) {

			if ( WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ) {

				add_filter( 'woocommerce_product_get_price', array( __CLASS__, 'filter_price' ), 0, 2 );
				add_filter( 'woocommerce_product_get_sale_price', array( __CLASS__, 'filter_sale_price' ), 0, 2 );
				add_filter( 'woocommerce_product_get_regular_price', array( __CLASS__, 'filter_regular_price' ), 0, 2 );
				add_filter( 'woocommerce_product_variation_get_price', array( __CLASS__, 'filter_price' ), 0, 2 );
				add_filter( 'woocommerce_product_variation_get_sale_price', array( __CLASS__, 'filter_sale_price' ), 0, 2 );
				add_filter( 'woocommerce_product_variation_get_regular_price', array( __CLASS__, 'filter_regular_price' ), 0, 2 );

			} else {

				add_filter( 'woocommerce_get_price', array( __CLASS__, 'filter_price' ), 0, 2 );
				add_filter( 'woocommerce_get_sale_price', array( __CLASS__, 'filter_sale_price' ), 0, 2 );
				add_filter( 'woocommerce_get_regular_price', array( __CLASS__, 'filter_regular_price' ), 0, 2 );
			}

			add_filter( 'woocommerce_get_variation_prices_hash', array( __CLASS__, 'filter_variation_prices_hash' ), 0, 2 );
			add_filter( 'woocommerce_variation_prices', array( __CLASS__, 'filter_variation_prices' ), 0, 2 );

			add_filter( 'woocommerce_available_variation', array( __CLASS__, 'filter_variation_data' ), 0, 3 );

			/**
			 * Action 'wcsatt_add_price_filters'.
			 */
			do_action( 'wcsatt_add_price_filters' );

		 }

		if ( in_array( $context, array( 'price_html', '' ) ) ) {

			add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'filter_price_html' ), 9999, 2 );

			/**
			* Action 'wcsatt_add_price_html_filters'.
			*/
			do_action( 'wcsatt_add_price_html_filters' );
		 }
	}

	/**
	 * Remove price filters.
	 *
	 * @param  string  $context  Filtering context. Values: 'price', 'price_html', ''.
	 * @return void
	 */
	public static function remove( $context = '' ) {

		if ( in_array( $context, array( 'price', '' ) ) ) {

			if ( WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ) {

				remove_filter( 'woocommerce_product_get_price', array( __CLASS__, 'filter_price' ), 0, 2 );
				remove_filter( 'woocommerce_product_get_sale_price', array( __CLASS__, 'filter_sale_price' ), 0, 2 );
				remove_filter( 'woocommerce_product_get_regular_price', array( __CLASS__, 'filter_regular_price' ), 0, 2 );
				remove_filter( 'woocommerce_product_variation_get_price', array( __CLASS__, 'filter_price' ), 0, 2 );
				remove_filter( 'woocommerce_product_variation_get_sale_price', array( __CLASS__, 'filter_sale_price' ), 0, 2 );
				remove_filter( 'woocommerce_product_variation_get_regular_price', array( __CLASS__, 'filter_regular_price' ), 0, 2 );

			} else {

				remove_filter( 'woocommerce_get_price', array( __CLASS__, 'filter_price' ), 0, 2 );
				remove_filter( 'woocommerce_get_sale_price', array( __CLASS__, 'filter_sale_price' ), 0, 2 );
				remove_filter( 'woocommerce_get_regular_price', array( __CLASS__, 'filter_regular_price' ), 0, 2 );
			}

			remove_filter( 'woocommerce_get_variation_prices_hash', array( __CLASS__, 'filter_variation_prices_hash' ), 0, 2 );
			remove_filter( 'woocommerce_variation_prices', array( __CLASS__, 'filter_variation_prices' ), 0, 2 );

			remove_filter( 'woocommerce_available_variation', array( __CLASS__, 'filter_variation_data' ), 0, 3 );

			/**
			 * Action 'wcsatt_remove_price_filters'.
			 */
			do_action( 'wcsatt_remove_price_filters' );
		}

		if ( in_array( $context, array( 'price_html', '' ) ) ) {

			remove_filter( 'woocommerce_get_price_html', array( __CLASS__, 'filter_price_html' ), 9999, 2 );

			/**
			 * Action 'wcsatt_remove_price_html_filters'.
			 */
			do_action( 'wcsatt_remove_price_html_filters' );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Filters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Filter html price based on the subscription scheme that is activated on the object.
	 *
	 * @param  string      $price_html
	 * @param  WC_Product  $product
	 * @return string
	 */
	public static function filter_price_html( $price_html, $product ) {

		if ( WCS_ATT_Product_Schemes::has_subscription_schemes( $product ) ) {
			$price_html = WCS_ATT_Product_Prices::get_price_html( $product, '', array( 'price' => $price_html ) );
		}

		return $price_html;
	}

	/**
	 * Filter variation data based on the subscription scheme that is activated on the parent.
	 *
	 * @param  array                 $variation_data
	 * @param  WC_Product_Variable   $product
	 * @param  WC_Product_Variation  $variation
	 * @return array
	 */
	public static function filter_variation_data( $variation_data, $product, $variation ) {

		$product_schemes   = WCS_ATT_Product_Schemes::get_subscription_schemes( $product );
		$variation_schemes = WCS_ATT_Product_Schemes::get_subscription_schemes( $variation );

		$variation_data_update_needed = false;

		// Copy product schemes to child.
		if ( ! empty( $product_schemes ) && array_keys( $product_schemes ) !== array_keys( $variation_schemes ) ) {
			WCS_ATT_Product_Schemes::set_subscription_schemes( $variation, $product_schemes );
			$variation_data_update_needed = true;
		}

		$product_scheme   = WCS_ATT_Product_Schemes::get_subscription_scheme( $product );
		$variation_scheme = WCS_ATT_Product_Schemes::get_subscription_scheme( $variation );

		// Set active product scheme on child.
		if ( $product_scheme !== $variation_scheme ) {
			WCS_ATT_Product_Schemes::set_subscription_scheme( $variation, $product_scheme );
			$variation_data_update_needed = true;
		}

		// Copy "Force Subscription" state.
		$product_has_forced_sub   = WCS_ATT_Product_Schemes::has_forced_subscription_scheme( $product );
		$variation_has_forced_sub = WCS_ATT_Product_Schemes::has_forced_subscription_scheme( $variation );

		if ( $product_has_forced_sub !== $variation_has_forced_sub ) {
			WCS_ATT_Product_Schemes::set_forced_subscription_scheme( $variation, WCS_ATT_Product_Schemes::has_forced_subscription_scheme( $product ) );
			$variation_data_update_needed = true;
		}

		if ( $variation_data_update_needed ) {
			$variation_data[ 'display_price' ]         = WCS_ATT_Core_Compatibility::wc_get_price_to_display( $variation );
			$variation_data[ 'display_regular_price' ] = WCS_ATT_Core_Compatibility::wc_get_price_to_display( $variation, array( 'price' => $variation->get_regular_price() ) );
			$variation_data[ 'price_html' ]            = $variation_data[ 'price_html' ] ? '<span class="price">' . $variation->get_price_html() . '</span>' : '';
		}

		return $variation_data;
	}

	/**
	 * Filter variation prices hash to load different prices depending on the scheme that's active on the object.
	 *
	 * @param  array                $hash
	 * @param  WC_Product_Variable  $product
	 * @return array
	 */
	public static function filter_variation_prices_hash( $hash, $product ) {

		$active_scheme = WCS_ATT_Product_Schemes::get_subscription_scheme( $product );


		if ( ! empty( $active_scheme ) ) {
			$hash[] = $active_scheme ? $active_scheme : '0';
		}

		return $hash;
	}

	/**
	 * Filter get_variation_prices() calls to take price filters into account.
	 * We could as well have used 'woocommerce_variation_prices_{regular_/sale_}price' filters.
	 * This is a bit slower but makes code simpler when there are no variation-level schemes.
	 *
	 * @param  array                $raw_prices
	 * @param  WC_Product_Variable  $product
	 * @return array
	 */
	public static function filter_variation_prices( $raw_prices, $product ) {

		$subscription_scheme = WCS_ATT_Product_Schemes::get_subscription_scheme( $product, 'object' );

		if ( ! empty( $subscription_scheme ) && $subscription_scheme->has_price_filter() ) {

			if ( apply_filters( 'wcsatt_price_filters_allowed', true, $product ) ) {

				$prices         = array();
				$regular_prices = array();
				$sale_prices    = array();

				$variation_ids  = array_keys( $raw_prices[ 'price' ] );

				foreach ( $variation_ids as $variation_id ) {

					$overridden_prices = $subscription_scheme->get_prices( array(
						'price'         => $raw_prices[ 'price' ][ $variation_id  ],
						'sale_price'    => $raw_prices[ 'sale_price' ][ $variation_id ],
						'regular_price' => $raw_prices[ 'regular_price' ][ $variation_id ]
					) );

					$prices[ $variation_id ]         = $overridden_prices[ 'price' ];
					$sale_prices[ $variation_id ]    = $overridden_prices[ 'sale_price' ];
					$regular_prices[ $variation_id ] = $overridden_prices[ 'regular_price' ];
				}

				asort( $prices );
				asort( $sale_prices );
				asort( $regular_prices );

				$raw_prices = array(
					'price'         => $prices,
					'sale_price'    => $sale_prices,
					'regular_price' => $regular_prices
				);
			}
		}

		return $raw_prices;
	}

	/**
	 * Filter get_price() calls to take scheme price overrides into account.
	 *
	 * @param  double      $price
	 * @param  WC_Product  $product
	 * @return double
	 */
	public static function filter_price( $price, $product ) {

		if ( WCS_ATT_Product::is_subscription( $product ) ) {
			$price = WCS_ATT_Product_Prices::get_price( $product, '', 'edit' );
		}

		return $price;
	}

	/**
	 * Filter get_regular_price() calls to take scheme price overrides into account.
	 *
	 * @param  double      $price
	 * @param  WC_Product  $product
	 * @return double
	 */
	public static function filter_regular_price( $regular_price, $product ) {

		if ( WCS_ATT_Product::is_subscription( $product ) ) {
			$regular_price = WCS_ATT_Product_Prices::get_regular_price( $product, '', 'edit' );
		}

		return $regular_price;
	}

	/**
	 * Filter get_sale_price() calls to take scheme price overrides into account.
	 *
	 * @param  double      $price
	 * @param  WC_Product  $product
	 * @return double
	 */
	public static function filter_sale_price( $sale_price, $product ) {

		if ( WCS_ATT_Product::is_subscription( $product ) ) {
			$sale_price = WCS_ATT_Product_Prices::get_sale_price( $product, '', 'edit' );
		}

		return $sale_price;
	}
}
