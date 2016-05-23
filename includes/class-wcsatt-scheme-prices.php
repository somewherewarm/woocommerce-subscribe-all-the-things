<?php
/**
 * Filtering of product prices based on subscription scheme price override settings.
 *
 * @class  WCS_ATT_Scheme_Prices
 * @since  1.1.0
 */

class WCS_ATT_Scheme_Prices {

	/**
	 * Returns cart item pricing data based on a subscription scheme's settings.
	 *
	 * @return string
	 */
	public static function get_subscription_scheme_prices( $product, $subscription_scheme ) {

		$prices = array();

		if ( ! empty( $subscription_scheme ) ) {
			if ( isset( $subscription_scheme[ 'subscription_pricing_method' ] ) ) {
				if ( $subscription_scheme[ 'subscription_pricing_method' ] === 'override' ) {
					$prices[ 'regular_price' ] = $subscription_scheme[ 'subscription_regular_price' ];
					$prices[ 'sale_price' ]    = $subscription_scheme[ 'subscription_sale_price' ];
					$prices[ 'price' ]         = $subscription_scheme[ 'subscription_price' ];
				} else if ( $subscription_scheme[ 'subscription_pricing_method' ] === 'inherit' && ! empty( $subscription_scheme[ 'subscription_discount' ] ) && $product->price > 0 ) {
					$prices[ 'regular_price' ] = self::get_discounted_scheme_regular_price( $product );
					$prices[ 'price' ]         = self::get_discounted_scheme_price( $product, $subscription_scheme[ 'subscription_discount' ] );

					if ( $prices[ 'price' ] < $prices[ 'regular_price' ] ) {
						$prices[ 'sale_price' ] = $prices[ 'price' ] ;
					}
				}
			}
		}

		return $prices;
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

			$price_overrides_exist = self::subscription_price_overrides_exist( $subscription_schemes );

			if ( $price_overrides_exist ) {

				$lowest_scheme               = false;
				$lowest_scheme_price         = $product->price;
				$lowest_scheme_sale_price    = $product->sale_price;
				$lowest_scheme_regular_price = $product->regular_price;

				$data = array(
					'price'         => $lowest_scheme_price,
					'regular_price' => $lowest_scheme_regular_price,
					'sale_price'    => $lowest_scheme_sale_price,
					'scheme'        => current( $subscription_schemes )
				);

				foreach ( $subscription_schemes as $subscription_scheme ) {

					$overridden_prices = self::get_subscription_scheme_prices( $product, $subscription_scheme );

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

		return $data;
	}

	/**
	 * Returns cart item pricing data based on the active subscription scheme settings of a cart item.
	 *
	 * @return string
	 */
	public static function get_active_subscription_scheme_prices( $cart_item, $active_subscription_scheme = array() ) {

		$prices = array();

		if ( empty( $active_subscription_scheme ) ) {
			$active_subscription_scheme = WCS_ATT_Schemes::get_active_subscription_scheme( $cart_item );
		}

		if ( ! empty( $active_subscription_scheme ) ) {
			$prices = self::get_subscription_scheme_prices( $cart_item[ 'data' ], $active_subscription_scheme );
		}

		return $prices;
	}

	/**
	 * Get product regular price (before discount).
	 *
	 * @return mixed
	 */
	private static function get_discounted_scheme_regular_price( $product ) {

		$regular_price = $product->regular_price;

		$regular_price = empty( $regular_price ) ? $product->price : $regular_price;

		return $regular_price;
	}

	/**
	 * Get product price after discount.
	 *
	 * @return mixed
	 */
	private static function get_discounted_scheme_price( $product, $discount ) {

		$price = $product->price;

		if ( $price === '' ) {
			return $price;
		}

		if ( apply_filters( 'wcsatt_discount_from_regular', false, $product ) ) {
			$regular_price = self::get_discounted_scheme_regular_price( $product );
		} else {
			$regular_price = $price;
		}

		$price = empty( $discount ) ? $price : ( empty( $regular_price ) ? $regular_price : round( ( double ) $regular_price * ( 100 - $discount ) / 100, wc_get_price_decimals() ) );

		return $price;
	}
}
