<?php
/**
 * Loading and filtering of subscription scheme settings.
 *
 * @class   WCS_ATT_Schemes
 * @version 1.0.4
 */

class WCS_ATT_Schemes {

	/**
	 * Returns the active cart-level subscription scheme id, or '0' if none is set.
	 *
	 * @return string
	 */
	public static function get_active_cart_subscription_scheme_id() {

		return WC()->session->get( 'wcsatt-active-scheme-id', '0' );
	}

	/**
	 * Returns the active subscription scheme of a cart item, or false if the cart item is a one-off purchase.
	 *
	 * @return string
	 */
	public static function get_active_subscription_scheme_id( $cart_item ) {

		$active_scheme_id = isset( $cart_item[ 'wccsub_data' ][ 'active_subscription_scheme_id' ] ) ? $cart_item[ 'wccsub_data' ][ 'active_subscription_scheme_id' ] : '0';

		return $active_scheme_id;
	}

	/**
	 * Returns the active subscription scheme of a cart item, or false if the cart item is a one-off purchase.
	 *
	 * @return array
	 */
	public static function get_active_subscription_scheme( $cart_item ) {

		$schemes          = self::get_subscription_schemes( $cart_item );
		$active_scheme_id = self::get_active_subscription_scheme_id( $cart_item );

		$active_scheme    = false;

		foreach ( $schemes as $scheme ) {
			if ( $scheme[ 'id' ] === $active_scheme_id ) {
				$active_scheme = $scheme;
				break;
			}
		}

		return $active_scheme;
	}

	/**
	 * Returns cart item pricing data based on the active subscription scheme settings of a cart item.
	 *
	 * @return string
	 */
	public static function get_active_subscription_scheme_prices( $cart_item, $active_subscription_scheme = array() ) {

		$prices = array();

		if ( empty( $active_subscription_scheme ) ) {
			$active_subscription_scheme = self::get_active_subscription_scheme( $cart_item );
		}

		if ( ! empty( $active_subscription_scheme ) ) {
			$prices = self::get_subscription_scheme_prices( $cart_item[ 'data' ], $active_subscription_scheme );
		}

		return $prices;
	}

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
	 * True if any of the subscription schemes overrides the basic price.
	 *
	 * @param  array  $subscription_schemes
	 * @return boolean
	 */
	public static function subscription_price_overrides_exist( $subscription_schemes ) {

		$has_price_overrides = false;

		foreach ( $subscription_schemes as $subscription_scheme ) {

			if ( ! isset( $subscription_scheme[ 'subscription_pricing_method' ] ) ) {
				continue;
			}

			if ( $subscription_scheme[ 'subscription_pricing_method' ] === 'override' ) {
				$has_price_overrides = true;
				break;
			} else if ( $subscription_scheme[ 'subscription_pricing_method' ] === 'inherit' && ! empty( $subscription_scheme[ 'subscription_discount' ] ) ) {
				$has_price_overrides = true;
				break;
			}
		}

		return $has_price_overrides;
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

		if ( apply_filters( 'wcsatt_discount_from_regular', true, $product ) ) {
			$regular_price = self::get_discounted_scheme_regular_price( $product );
		} else {
			$regular_price = $price;
		}

		$price = empty( $discount ) ? $price : ( empty( $regular_price ) ? $regular_price : round( ( double ) $regular_price * ( 100 - $discount ) / 100, wc_get_price_decimals() ) );

		return $price;
	}

	/**
	 * Returns all available subscription schemes (product-level and cart-level).
	 *
	 * @return array
	 */
	public static function get_subscription_schemes( $cart_item, $scope = 'all' ) {

		$schemes = array();

		if ( WCS_ATT_Cart::is_convertible_to_sub( $cart_item ) ) {

			// Get product-level subscription schemes stored in product meta

			if ( in_array( $scope, array( 'all', 'cart-item' ) ) ) {

				// This checks if the item in the cart is a variable product or any other product type.
				if ( $cart_item[ 'variation_id' ] > 0 ) {
					$product_id = $cart_item[ 'data' ]->variation_id; // ID of the selected variation in the cart

				} else {
					$product_id = $cart_item[ 'product_id' ];
				}

				$product_schemes = get_post_meta( $product_id, '_wcsatt_schemes', true );

				if ( $product_schemes ) {
					foreach ( $product_schemes as $scheme ) {
						$scheme[ 'scope' ] = 'cart-item';
						$schemes[]         = $scheme;
					}
				}
			}

			// Get cart-level subscription schemes stored in WC settings
			// Added only if there are no product-level schemes present

			if ( in_array( $scope, array( 'all', 'cart' ) ) ) {

				$cart_level_schemes = get_option( 'wcsatt_subscribe_to_cart_schemes', array() );

				if ( ! empty( $cart_level_schemes ) ) {
					foreach ( $cart_level_schemes as $scheme ) {
						$scheme[ 'scope' ] = 'cart';
						$schemes[]         = $scheme;
					}
				}
			}
		}

		return apply_filters( 'wcsatt_subscription_schemes', $schemes, $cart_item, $scope );
	}

	/**
	 * Returns all available subscription schemes for displaying single-product options (product-level).
	 *
	 * @param $post_id
	 * @return array
	 */
	public static function get_product_subscription_schemes( $post_id, $product_type ) {

		$schemes = array();

		$supported_types = WCS_ATT()->get_supported_product_types();

		if ( in_array( $product_type, $supported_types ) ) {

			// Get product-level subscription schemes stored in product meta

			$product_schemes = get_post_meta( $post_id, '_wcsatt_schemes', true );

			if ( $product_schemes ) {
				foreach ( $product_schemes as $scheme ) {
					$scheme[ 'scope' ] = 'cart-item';
					$schemes[]         = $scheme;
				}
			}
		}

		return apply_filters( 'wcsatt_product_subscription_schemes', $schemes, $post_id );
	}

	/**
	 * Returns the default subscription scheme id of a cart item, or '0' if the default option is a one-off purchase.
	 *
	 * @param  array $cart_item
	 * @param  array $cart_level_schemes
	 * @return string
	 */
	public static function set_subscription_scheme_id( $cart_item, $cart_level_schemes ) {

		if ( $cart_level_schemes ) {

			// default to last setting
			$default_scheme_id = WC()->session->get( 'wcsatt-active-scheme-id', false );

			if ( false === $default_scheme_id ) {

				// default to subscription

				if ( apply_filters( 'wcsatt_enable_cart_subscription_by_default', false ) ) {

					$default_scheme    = current( $cart_level_schemes );
					$default_scheme_id = $default_scheme[ 'id' ];

				// default to one-time

				} else {
					$default_scheme_id = '0';
				}

			}

		} else {

			// default to last setting
			$default_scheme_id = $cart_item[ 'wccsub_data' ][ 'active_subscription_scheme_id' ];

			if ( false === $default_scheme_id ) {

				if ( $subscription_schemes = self::get_subscription_schemes( $cart_item, 'cart-item' ) ) {

					$product_id         = $cart_item[ 'product_id' ];
					$force_subscription = get_post_meta( $product_id, '_wcsatt_force_subscription', true );
					$default_status     = get_post_meta( $product_id, '_wcsatt_default_status', true );
					$default_scheme_id  = '0';

					if ( $force_subscription === 'yes' || $default_status === 'subscription' ) {
						if ( ! empty( $subscription_schemes ) ) {
							$default_scheme    = current( $subscription_schemes );
							$default_scheme_id = $default_scheme[ 'id' ];
						}
					}
				}
			}
		}

		return apply_filters( 'wcsatt_set_subscription_scheme_id', $default_scheme_id, $cart_item, $cart_level_schemes );
	}

	/**
	 * Returns subscription schemes for cart-item level options.
	 * Will return either:
	 *
	 *  - product-level subscription schemes, when these are defined at product-level, or
	 *  - cart-level subscription schemes, when they exist and a grouped UI can't be displayed for all cart items ( @see get_cart_subscription_schemes ).
	 *
	 * @param  array $cart_item
	 * @return array
	 */
	public static function get_cart_item_subscription_schemes( $cart_item ) {

		$cart_item_schemes = array();

		// Cart-item options are displayed only if we don't have any grouped cart-level options to show
		if ( false === self::get_cart_subscription_schemes() ) {

			$cart_item_schemes = self::get_subscription_schemes( $cart_item, 'cart-item' );

			// Cart-level options are displayed at cart-item level when we can't show them grouped together
			if ( empty( $cart_item_schemes ) ) {
				$cart_item_schemes = self::get_subscription_schemes( $cart_item, 'cart' );
			}
		}

		return $cart_item_schemes;
	}

	/**
	 * Returns subscription schemes for cart-level options.
	 * Cart-level options will be displayed only if no cart-item is found with its own product-level subscription scheme.
	 * This means that subscription options defined at product-level and "legacy" subscription-type products will "block" the display of cart-level options.
	 *
	 * In this case, cart-level options will be displayed at cart-item level.
	 *
	 * Must be called after all cart session data has been loaded.
	 *
	 * @return array|boolean
	 */
	public static function get_cart_subscription_schemes() {

		$cart_level_schemes      = array();
		$cart_level_schemes_keys = array();
		$cart_level_schemes      = get_option( 'wcsatt_subscribe_to_cart_schemes', array() );

		if ( empty( $cart_level_schemes ) ) {
			return false;
		}

		foreach ( $cart_level_schemes as $cart_level_scheme ) {
			$cart_level_schemes_keys[] = $cart_level_scheme[ 'id' ];
		}

		foreach ( WC()->cart->cart_contents as $cart_item ) {

			if ( ! WCS_ATT_Cart::is_supported_product_type( $cart_item ) ) {
				return false;
			}

			if ( $cart_item_level_schemes = self::get_subscription_schemes( $cart_item, 'cart-item' ) ) {
				return false;
			}

			if ( WC_Subscriptions_Product::is_subscription( $cart_item[ 'product_id' ] ) ) {
				return false;
			}
		}

		return $cart_level_schemes;
	}
}
