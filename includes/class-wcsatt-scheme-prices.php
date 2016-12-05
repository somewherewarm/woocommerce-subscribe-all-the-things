<?php
/**
 * Filtering of product prices based on subscription scheme price override settings.
 *
 * @class  WCS_ATT_Scheme_Prices
 * @since  1.1.0
 */

class WCS_ATT_Scheme_Prices {

	public static $price_overriding_scheme  = false;
	public static $price_overridden_product = false;

	/**
	 * Add price filters to modify child product prices depending on the per-product pricing option state, including any discounts defined at bundled item level.
	 *
	 * @param   array  $subscription_scheme
	 * @return  void
	 */
	public static function add_price_filters( $product, $subscription_scheme ) {

		if ( $subscription_scheme && self::has_subscription_price_override( $subscription_scheme ) ) {

			self::$price_overriding_scheme  = $subscription_scheme;
			self::$price_overridden_product = $product;

			add_filter( 'woocommerce_get_price', array( __CLASS__, 'filter_get_price' ), 0, 2 );
			add_filter( 'woocommerce_get_sale_price', array( __CLASS__, 'filter_get_sale_price' ), 0, 2 );
			add_filter( 'woocommerce_get_regular_price', array( __CLASS__, 'filter_get_regular_price' ), 0, 2 );
			add_filter( 'woocommerce_variation_prices', array( __CLASS__, 'filter_get_variation_prices' ), 0, 3 );

			do_action( 'wcsatt_add_price_filters', $product, $subscription_scheme );
		}
	}

	/**
	 * Remove price filters after modifying child product prices depending on the per-product pricing option state, including any discounts defined at bundled item level.
	 *
	 * @return  void
	 */
	public static function remove_price_filters() {

		if ( self::$price_overriding_scheme ) {

			remove_filter( 'woocommerce_get_price', array( __CLASS__, 'filter_get_price' ), 0, 2 );
			remove_filter( 'woocommerce_get_sale_price', array( __CLASS__, 'filter_get_sale_price' ), 0, 2 );
			remove_filter( 'woocommerce_get_regular_price', array( __CLASS__, 'filter_get_regular_price' ), 0, 2 );
			remove_filter( 'woocommerce_variation_prices', array( __CLASS__, 'filter_get_variation_prices' ), 0, 3 );

			do_action( 'wcsatt_remove_price_filters' );

			self::$price_overriding_scheme  = false;
			self::$price_overridden_product = false;
		}
	}

	/**
	 * Filter get_variation_prices() calls to take price overrides into account.
	 *
	 * @param  array                $prices_array
	 * @param  WC_Product_Variable  $product
	 * @param  boolean              $display
	 * @return array
	 */
	public static function filter_get_variation_prices( $prices_array, $product, $display ) {

		$subscription_scheme = self::$price_overriding_scheme;

		if ( $subscription_scheme && self::has_subscription_price_override( $subscription_scheme ) ) {

			if ( apply_filters( 'wcsatt_price_filters_allowed', true, self::$price_overridden_product, $subscription_scheme, $product ) ) {

				$prices         = array();
				$regular_prices = array();
				$sale_prices    = array();

				$variation_ids  = array_keys( $prices_array[ 'price' ] );

				foreach ( $variation_ids as $variation_id ) {

					$overridden_prices = self::get_subscription_scheme_prices( array(
						'price'         => $prices_array[ 'price' ][ $variation_id  ],
						'regular_price' => $prices_array[ 'regular_price' ][ $variation_id ],
						'sale_price'    => $prices_array[ 'sale_price' ][ $variation_id ]
					), $subscription_scheme );

					$prices[ $variation_id ]         = $overridden_prices[ 'price' ];
					$regular_prices[ $variation_id ] = $overridden_prices[ 'regular_price' ];
					$sale_prices[ $variation_id ]    = $overridden_prices[ 'sale_price' ];
				}

				asort( $prices );
				asort( $regular_prices );
				asort( $sale_prices );

				$prices_array = array(
					'price'         => $prices,
					'regular_price' => $regular_prices,
					'sale_price'    => $sale_prices
				);
			}
		}

		return $prices_array;
	}

	/**
	 * Filter get_price() calls to take price overrides into account.
	 *
	 * @param  double       $price      unmodified price
	 * @param  WC_Product   $product    the bundled product
	 * @return double                   modified price
	 */
	public static function filter_get_price( $price, $product ) {

		$subscription_scheme = self::$price_overriding_scheme;

		if ( $subscription_scheme ) {

			if ( apply_filters( 'wcsatt_price_filters_allowed', true, self::$price_overridden_product, $subscription_scheme, $product ) ) {

				$prices_array = array(
					'price'         => $price,
					'regular_price' => $product->regular_price,
					'sale_price'    => $product->sale_price
				);

				$overridden_prices = self::get_subscription_scheme_prices( $prices_array, $subscription_scheme );
				$price             = $overridden_prices[ 'price' ];
			}
		}

		return $price;
	}

	/**
	 * Filter get_regular_price() calls to take price overrides into account.
	 *
	 * @param  double       $price      unmodified reg price
	 * @param  WC_Product   $product    the bundled product
	 * @return double                   modified reg price
	 */
	public static function filter_get_regular_price( $regular_price, $product ) {

		$subscription_scheme = self::$price_overriding_scheme;

		if ( $subscription_scheme ) {

			if ( apply_filters( 'wcsatt_price_filters_allowed', true, self::$price_overridden_product, $subscription_scheme, $product ) ) {

				self::$price_overriding_scheme = false;

				$prices_array = array(
					'price'         => $product->price,
					'regular_price' => $regular_price,
					'sale_price'    => $product->sale_price
				);

				self::$price_overriding_scheme = $subscription_scheme;

				$overridden_prices = self::get_subscription_scheme_prices( $prices_array, $subscription_scheme );
				$regular_price     = $overridden_prices[ 'regular_price' ];
			}
		}

		return $regular_price;
	}

	/**
	 * Filter get_sale_price() calls to take price overrides into account.
	 *
	 * @param  double       $price      unmodified reg price
	 * @param  WC_Product   $product    the bundled product
	 * @return double                   modified reg price
	 */
	public static function filter_get_sale_price( $sale_price, $product ) {

		$subscription_scheme = self::$price_overriding_scheme;

		if ( $subscription_scheme ) {

			if ( apply_filters( 'wcsatt_price_filters_allowed', true, self::$price_overridden_product, $subscription_scheme, $product ) ) {

				self::$price_overriding_scheme = false;

				$prices_array = array(
					'price'         => $product->get_price(),
					'regular_price' => $product->get_regular_price(),
					'sale_price'    => $sale_price
				);

				self::$price_overriding_scheme = $subscription_scheme;

				$overridden_prices = self::get_subscription_scheme_prices( $prices_array, $subscription_scheme );
				$sale_price        = $overridden_prices[ 'sale_price' ];
			}
		}

		return $sale_price;
	}

	/**
	 * Returns cart item pricing data based on a subscription scheme's settings.
	 *
	 * @param  array  $prices_array
	 * @param  array  $subscription_scheme
	 * @return string
	 */
	public static function get_subscription_scheme_prices( $prices_array, $subscription_scheme ) {

		$prices = $prices_array;

		if ( ! empty( $subscription_scheme ) ) {
			if ( isset( $subscription_scheme[ 'subscription_pricing_method' ] ) ) {
				if ( $subscription_scheme[ 'subscription_pricing_method' ] === 'override' ) {
					$prices[ 'regular_price' ] = $subscription_scheme[ 'subscription_regular_price' ];
					$prices[ 'sale_price' ]    = $subscription_scheme[ 'subscription_sale_price' ];
					$prices[ 'price' ]         = $subscription_scheme[ 'subscription_price' ];
				} else if ( $subscription_scheme[ 'subscription_pricing_method' ] === 'inherit' && ! empty( $subscription_scheme[ 'subscription_discount' ] ) && $prices_array[ 'price' ] > 0 ) {
					$prices[ 'regular_price' ] = self::get_discounted_scheme_regular_price( $prices_array );
					$prices[ 'price' ]         = self::get_discounted_scheme_price( $prices_array, $subscription_scheme[ 'subscription_discount' ] );

					if ( $prices[ 'price' ] < $prices[ 'regular_price' ] ) {
						$prices[ 'sale_price' ] = $prices[ 'price' ] ;
					}
				}
			}
		}

		return apply_filters( 'wcsatt_subscription_scheme_prices', $prices, $subscription_scheme );
	}

	/**
	 * True if any of the subscription schemes overrides the default price.
	 *
	 * @param  array   $subscription_schemes
	 * @return boolean
	 */
	public static function subscription_price_overrides_exist( $subscription_schemes ) {

		$price_override_exists = false;

		foreach ( $subscription_schemes as $subscription_scheme ) {
			if ( self::has_subscription_price_override( $subscription_scheme ) ) {
				$price_override_exists = true;
				break;
			}
		}

		return $price_override_exists;
	}

	/**
	 * True if a subscription scheme has price overrides.
	 *
	 * @param  array   $subscription_scheme
	 * @return boolean
	 */
	public static function has_subscription_price_override( $subscription_scheme ) {

		$price_override_exists = false;

		if ( isset( $subscription_scheme[ 'subscription_pricing_method' ] ) ) {
			if ( $subscription_scheme[ 'subscription_pricing_method' ] === 'override' ) {
				$price_override_exists = true;
			} else if ( $subscription_scheme[ 'subscription_pricing_method' ] === 'inherit' && ! empty( $subscription_scheme[ 'subscription_discount' ] ) ) {
				$price_override_exists = true;
			}
		}

		return $price_override_exists;
	}

	/**
	 * Returns lowest price data for a product given the subscription schemes attached to it.
	 *
	 * @param  WC_Product  $product
	 * @param  array       $subscription_schemes
	 * @return string
	 */
	public static function get_lowest_price_subscription_scheme_data( $product, $subscription_schemes ) {

		$data = false;

		if ( ! empty( $subscription_schemes ) ) {

			$lowest_scheme               = current( $subscription_schemes );
			$lowest_scheme_price         = $product->price;
			$lowest_scheme_sale_price    = $product->sale_price;
			$lowest_scheme_regular_price = $product->regular_price;

			$data = array(
				'price'         => $lowest_scheme_price,
				'regular_price' => $lowest_scheme_regular_price,
				'sale_price'    => $lowest_scheme_sale_price,
				'scheme'        => $lowest_scheme
			);

			$price_overrides_exist = self::subscription_price_overrides_exist( $subscription_schemes );

			if ( $price_overrides_exist ) {

				foreach ( $subscription_schemes as $subscription_scheme ) {

					$overridden_prices = self::get_subscription_scheme_prices( array(
						'price'         => $product->price,
						'regular_price' => $product->regular_price,
						'sale_price'    => $product->sale_price
					), $subscription_scheme );

					if ( ! empty( $overridden_prices ) ) {
						if ( $overridden_prices[ 'price' ] < $lowest_scheme_price ) {
							$lowest_scheme               = $subscription_scheme;
							$lowest_scheme_price         = $overridden_prices[ 'price' ];
							$lowest_scheme_regular_price = $overridden_prices[ 'regular_price' ];
							$lowest_scheme_sale_price    = $overridden_prices[ 'sale_price' ];
						}
					}
				}

				if ( $lowest_scheme_price < $product->price ) {

					$data = array(
						'price'         => $lowest_scheme_price,
						'regular_price' => $lowest_scheme_regular_price,
						'sale_price'    => $lowest_scheme_sale_price,
						'scheme'        => $lowest_scheme
					);
				}
			}
		}

		return apply_filters( 'wcsatt_get_lowest_price_sub_scheme_data', $data, $lowest_scheme );
	}

	/**
	 * Get regular price (before discount).
	 *
	 * @param  array  $prices_array
	 * @return mixed
	 */
	private static function get_discounted_scheme_regular_price( $prices_array ) {

		$regular_price = $prices_array[ 'regular_price' ];

		$regular_price = empty( $regular_price ) ? $prices_array[ 'price' ] : $regular_price;

		return $regular_price;
	}

	/**
	 * Get price after discount.
	 *
	 * @param  array  $prices_array
	 * @param  string $discount
	 * @return mixed
	 */
	private static function get_discounted_scheme_price( $prices_array, $discount ) {

		$price = $prices_array[ 'price' ];

		if ( $price === '' ) {
			return $price;
		}

		if ( apply_filters( 'wcsatt_discount_from_regular', false ) ) {
			$regular_price = self::get_discounted_scheme_regular_price( $prices_array );
		} else {
			$regular_price = $price;
		}

		$price = empty( $discount ) ? $price : ( empty( $regular_price ) ? $regular_price : round( ( double ) $regular_price * ( 100 - $discount ) / 100, wc_get_price_decimals() ) );

		return $price;
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
