<?php
/**
 * WCS_ATT_Scheme_Prices class
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Subscribe All the Things
 * @since    1.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tiny subscription scheme prices API and filters.
 *
 * @class    WCS_ATT_Scheme_Prices
 * @version  1.2.0
 */
class WCS_ATT_Scheme_Prices {

	/**
	 * Add price filters. Filtering early allows us to override "raw" prices as safely as possible.
	 * This allows 3p code to apply discounts or other transformations on overridden prices.
	 * The catch: Any price filters added by 3p code with a priority earlier than 0 will be rendered ineffective.
	 *
	 * @return  void
	 */
	public static function add_price_filters() {

		add_filter( 'woocommerce_get_price', array( __CLASS__, 'filter_get_price' ), 0, 2 );
		add_filter( 'woocommerce_get_sale_price', array( __CLASS__, 'filter_get_sale_price' ), 0, 2 );
		add_filter( 'woocommerce_get_regular_price', array( __CLASS__, 'filter_get_regular_price' ), 0, 2 );

		add_filter( 'woocommerce_variation_prices', array( __CLASS__, 'filter_get_variation_prices' ), 0, 2 );
		add_filter( 'woocommerce_variation_prices_hash', array( __CLASS__, 'filter_variation_prices_hash', 0, 2 ) );

		do_action( 'wcsatt_add_price_filters' );
	}

	/**
	 * Remove price filters.
	 *
	 * @return  void
	 */
	public static function remove_price_filters() {

		remove_filter( 'woocommerce_get_price', array( __CLASS__, 'filter_get_price' ), 0, 2 );
		remove_filter( 'woocommerce_get_sale_price', array( __CLASS__, 'filter_get_sale_price' ), 0, 2 );
		remove_filter( 'woocommerce_get_regular_price', array( __CLASS__, 'filter_get_regular_price' ), 0, 2 );

		remove_filter( 'woocommerce_variation_prices', array( __CLASS__, 'filter_get_variation_prices' ), 0, 2 );
		remove_filter( 'woocommerce_variation_prices_hash', array( __CLASS__, 'filter_variation_prices_hash', 0, 2 ) );

		do_action( 'wcsatt_remove_price_filters' );
	}

	/**
	 * Add price html filters.
	 *
	 * @return  void
	 */
	public static function add_price_html_filters() {

		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'filter_get_price_html' ), 9999, 2 );

		do_action( 'wcsatt_add_price_html_filters' );
	}

	/**
	 * Remove price html filters.
	 *
	 * @return  void
	 */
	public static function remove_price_html_filters() {

		remove_filter( 'woocommerce_get_price_html', array( __CLASS__, 'filter_get_price_html' ), 9999, 2 );

		do_action( 'wcsatt_remove_price_html_filters' );
	}

	/**
	 * Returns modified raw prices based on subscription scheme settings.
	 *
	 * @param  array  $raw_prices
	 * @param  array  $subscription_scheme
	 * @return string
	 */
	public static function get_scheme_prices( $raw_prices, $subscription_scheme ) {

		$prices = $raw_prices;

		if ( ! empty( $subscription_scheme ) ) {
			if ( 'override' === $subscription_scheme->get_pricing_mode() ) {

				$prices[ 'regular_price' ] = $subscription_scheme->get_regular_price();
				$prices[ 'sale_price' ]    = $subscription_scheme->get_sale_price();

				$prices[ 'price' ] = '' !== $prices[ 'sale_price' ] && $prices[ 'sale_price' ] < $prices[ 'regular_price' ] ? $prices[ 'sale_price' ] : $prices[ 'regular_price' ];

			} elseif ( 'inherit' === $subscription_scheme->get_pricing_mode() && $subscription_scheme->get_discount() > 0 && $raw_prices[ 'price' ] > 0 ) {

				$prices[ 'regular_price' ] = empty( $prices[ 'regular_price' ] ) ? $prices[ 'price' ] : $prices[ 'regular_price' ];
				$prices[ 'price' ]         = self::get_discounted_scheme_price( $raw_prices, $subscription_scheme->get_discount() );

				if ( $prices[ 'price' ] < $prices[ 'regular_price' ] ) {
					$prices[ 'sale_price' ] = $prices[ 'price' ] ;
				}
			}
		}

		return apply_filters( 'wcsatt_subscription_scheme_prices', $prices, $subscription_scheme );
	}

	/**
	 * Indicates whether the product price is modified by one or more subscription schemes.
	 *
	 * @param  array  $subscription_schemes
	 * @return boolean
	 */
	public static function price_filter_exists( $subscription_schemes ) {

		$price_filter_exists = false;

		foreach ( $subscription_schemes as $subscription_scheme ) {
			if ( $subscription_scheme->has_price_filter() ) {
				$price_filter_exists = true;
				break;
			}
		}

		return $price_filter_exists;
	}

	/**
	 * Returns the "base" subscription scheme by finding the one with the lowest recurring price.
	 * If prices are equal, no interval-based comparison is carried out:
	 * Reason: In some applications "$5 every week for 2 weeks" (=$10) might be seen as "cheaper" than "$5 every month for 3 months" (=$15), and in some the opposite.
	 * Instead of making guesswork and complex calculations, we can let scheme order be used to define the "base" scheme manually.
	 *
	 * @param  WC_Product  $product
	 * @return string
	 */
	public static function get_base_scheme( $product ) {

		$base_scheme = null;
		$schemes     = WCS_ATT_Product::get_subscription_schemes( $product );

		if ( ! empty( $schemes ) ) {

			$product_price       = WCS_ATT_Core_Compatibility::get_prop( $product, 'price' );
			$price_filter_exists = self::price_filter_exists( $schemes );
			$base_scheme         = current( $schemes );
			$base_scheme_price   = $product_price;

			if ( $price_filter_exists ) {

				foreach ( $schemes as $scheme ) {

					$scheme_price = WCS_ATT_Product::get_price( $product, $scheme->get_key() );

					if ( $scheme_price < $base_scheme_price ) {
						$base_scheme       = $scheme;
						$base_scheme_price = $scheme_price;
					}
				}
			}
		}

		return apply_filters( 'wcsatt_get_base_scheme', $base_scheme, $product );
	}

	/**
	 * Get price after discount.
	 *
	 * @param  array  $raw_prices
	 * @param  string $discount
	 * @return mixed
	 */
	private static function get_discounted_scheme_price( $raw_prices, $discount ) {

		$price = $raw_prices[ 'price' ];

		if ( $price === '' ) {
			return $price;
		}

		if ( apply_filters( 'wcsatt_discount_from_regular', false ) ) {
			$regular_price = empty( $raw_prices[ 'regular_price' ] ) ? $raw_prices[ 'price' ] : $raw_prices[ 'regular_price' ];
		} else {
			$regular_price = $price;
		}

		if ( ! empty( $discount ) ) {
			$price = empty( $regular_price ) ? $regular_price : round( ( double ) $regular_price * ( 100 - $discount ) / 100, wc_get_price_decimals() );
		}

		return $price;
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
	public static function filter_get_price_html( $price_html, $product ) {

		if ( WCS_ATT_Product::has_subscriptions( $product ) ) {
			$price_html = WCS_ATT_Product::get_price_html( $product, '', $price_html );
		}

		return $price_html;
	}

	/**
	 * Filter variation prices hash to load different prices depending on the scheme that's active on the object.
	 *
	 * @param  array                $hash
	 * @param  WC_Product_Variable  $product
	 * @return array
	 */
	public static function filter_variation_prices_hash( $hash, $product ) {

		$active_scheme_key = WCS_ATT_Product::get_subscription_scheme( $product );

		if ( ! empty( $active_scheme_key ) ) {
			$hash[] = $active_scheme_key;
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
	public static function filter_get_variation_prices( $raw_prices, $product ) {

		$subscription_scheme = WCS_ATT_Product::get_subscription_scheme( $product, 'object' );

		if ( ! empty( $subscription_scheme ) && $subscription_scheme->has_price_filter() ) {

			if ( apply_filters( 'wcsatt_price_filters_allowed', true, $product ) ) {

				$prices         = array();
				$regular_prices = array();
				$sale_prices    = array();

				$variation_ids  = array_keys( $raw_prices[ 'price' ] );

				foreach ( $variation_ids as $variation_id ) {

					$overridden_prices = self::get_scheme_prices( array(
						'price'         => $raw_prices[ 'price' ][ $variation_id  ],
						'sale_price'    => $raw_prices[ 'sale_price' ][ $variation_id ],
						'regular_price' => $raw_prices[ 'regular_price' ][ $variation_id ]
					), $subscription_scheme );

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
	public static function filter_get_price( $price, $product ) {

		if ( WCS_ATT_Product::is_subscription( $product ) ) {
			$price = WCS_ATT_Product::get_price( $product );
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
	public static function filter_get_regular_price( $regular_price, $product ) {

		if ( WCS_ATT_Product::is_subscription( $product ) ) {
			$regular_price = WCS_ATT_Product::get_regular_price( $product );
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
	public static function filter_get_sale_price( $sale_price, $product ) {

		if ( WCS_ATT_Product::is_subscription( $product ) ) {
			$sale_price = WCS_ATT_Product::get_sale_price( $product );
		}

		return $sale_price;
	}

	/*
	|--------------------------------------------------------------------------
	| Deprecated
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns modified raw prices based on subscription scheme settings.
	 *
	 * @deprecated  1.2.0
	 *
	 * @param  array  $raw_prices
	 * @param  array  $subscription_scheme
	 * @return string
	 */
	public static function get_subscription_scheme_prices( $raw_prices, $subscription_scheme ) {
		_deprecated_function( __METHOD__ . '()', '1.2.0', 'WCS_ATT_Scheme_Prices::get_scheme_prices()' );
		return self::get_scheme_prices( $raw_prices, $subscription_scheme );
	}

	/**
	 * True if any of the subscription schemes overrides the default price.
	 *
	 * @deprecated  1.2.0
	 *
	 * @param  array  $subscription_schemes
	 * @return boolean
	 */
	public static function subscription_price_overrides_exist( $subscription_schemes ) {
		_deprecated_function( __METHOD__ . '()', '1.2.0', 'WCS_ATT_Scheme_Prices::price_filter_exists()' );
		return self::price_filter_exists( $subscription_schemes );
	}

	/**
	 * True if a subscription scheme modifies the price of the product it's attached onto when active.
	 *
	 * @deprecated  1.2.0
	 *
	 * @param  WCS_ATT_Scheme  $subscription_scheme
	 * @return boolean
	 */
	public static function has_subscription_price_override( $subscription_scheme ) {
		_deprecated_function( __METHOD__ . '()', '1.2.0', 'WCS_ATT_Scheme::has_price_filter()' );
		return $subscription_scheme->has_price_filter();
	}

	/**
	 * Returns lowest price data for a product given the subscription schemes attached to it.
	 *
	 * @deprecated  1.2.0
	 *
	 * @param  WC_Product  $product
	 * @param  array       $subscription_schemes
	 * @return array
	 */
	public static function get_lowest_price_subscription_scheme_data( $product, $subscription_schemes ) {
		_deprecated_function( __METHOD__ . '()', '1.2.0', 'WCS_ATT_Scheme_Prices::get_lowest_price_scheme_data()' );
		$base_scheme = self::get_base_scheme( $product );
		$data = array_merge( array( 'scheme' => $base_scheme ), self::get_subscription_scheme_prices( $base_scheme ) );
		$data = apply_filters( 'wcsatt_get_lowest_price_sub_scheme_data', $data, $base_scheme );
		return $data;
	}

	/**
	 * Returns cart item pricing data based on the active subscription scheme settings of a cart item.
	 *
	 * @deprecated  1.1.2
	 *
	 * @return string
	 */
	public static function get_active_subscription_scheme_prices( $cart_item, $active_subscription_scheme = array() ) {
		_deprecated_function( __METHOD__ . '()', '1.1.2', 'WCS_ATT_Cart::get_active_subscription_scheme_prices()' );
		return WCS_ATT_Cart::get_active_subscription_scheme_prices( $cart_item, $active_subscription_scheme );
	}
}
