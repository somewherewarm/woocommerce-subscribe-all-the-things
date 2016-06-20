<?php
/**
 * Loading and filtering of subscription scheme settings.
 *
 * @class  WCS_ATT_Schemes
 * @since  1.0.0
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
	 * Returns a subscription scheme by id.
	 *
	 * @return array
	 */
	public static function get_subscription_scheme_by_id( $id, $schemes ) {

		$found_scheme = false;

		foreach ( $schemes as $scheme ) {
			if ( $scheme[ 'id' ] === $id ) {
				$found_scheme = $scheme;
				break;
			}
		}

		return $found_scheme;
	}

	/**
	 * Returns all available subscription schemes (product-level and cart-level).
	 *
	 * @return array
	 */
	public static function get_subscription_schemes( $cart_item, $scope = 'all' ) {

		$schemes = array();

		if ( WCS_ATT_Cart::is_convertible_to_sub( $cart_item ) ) {

			// Get product-level subscription schemes stored in product meta.

			if ( in_array( $scope, array( 'all', 'cart-item' ) ) ) {

				$product_id      = $cart_item[ 'product_id' ];
				$product_schemes = get_post_meta( $product_id, '_wcsatt_schemes', true );

				if ( $product_schemes ) {
					foreach ( $product_schemes as $scheme ) {
						$scheme[ 'scope' ] = 'cart-item';
						$schemes[]         = $scheme;
					}
				}
			}

			// Get cart-level subscription schemes stored in WC settings.
			// Added only if there are no product-level schemes present.

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
	 * @return array
	 */
	public static function get_product_subscription_schemes( $product ) {

		$schemes = array();

		if ( ! is_object( $product ) ) {
			return $schemes;
		}

		if ( $product->variation_id > 0 ) {
			return self::get_variation_subscription_schemes( $product );
		}

		$supported_types = WCS_ATT()->get_supported_product_types();

		if ( in_array( $product->product_type, $supported_types ) ) {

			// Get product-level subscription schemes stored in product meta.

			$product_schemes = get_post_meta( $product->id, '_wcsatt_schemes', true );

			if ( $product_schemes ) {
				foreach ( $product_schemes as $scheme ) {
					$scheme[ 'scope' ] = 'cart-item';
					$schemes[]         = $scheme;
				}
			}
		}

		return apply_filters( 'wcsatt_product_subscription_schemes', $schemes, $product );
	}

	/**
	 * Returns all available subscription schemes attached to a variation.
	 *
	 * @param  WC_Product_Variation  $variation
	 * @return array
	 */
	private static function get_variation_subscription_schemes( $variation ) {

		$schemes = array();

		// Get product-level subscription schemes stored in variation meta.

		$variation_schemes = get_post_meta( $variation->variation_id, '_wcsatt_schemes', true );

		if ( $variation_schemes ) {
			foreach ( $variation_schemes as $scheme ) {
				$scheme[ 'scope' ] = 'cart-item';
				$schemes[]         = $scheme;
			}
		} else {

			// Get product-level subscription schemes stored in product meta.

			$product_schemes = get_post_meta( $variation->id, '_wcsatt_schemes', true );

			if ( $product_schemes ) {
				foreach ( $product_schemes as $scheme ) {
					$scheme[ 'scope' ] = 'cart-item';
					$schemes[]         = $scheme;
				}
			}
		}

		return apply_filters( 'wcsatt_variation_subscription_schemes', $schemes, $variation );
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

			// Default to last setting.
			$default_scheme_id = WC()->session->get( 'wcsatt-active-scheme-id', false );

			if ( false === $default_scheme_id ) {

				// Default to subscription.

				if ( apply_filters( 'wcsatt_enable_cart_subscription_by_default', false ) ) {

					$default_scheme    = current( $cart_level_schemes );
					$default_scheme_id = $default_scheme[ 'id' ];

				// Default to one-time.

				} else {
					$default_scheme_id = '0';
				}
			}

		} else {

			// Default to last setting.
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

		// Cart-item options are displayed only if we don't have any grouped cart-level options to show.
		if ( false === self::get_cart_subscription_schemes() ) {

			$cart_item_schemes = self::get_subscription_schemes( $cart_item, 'cart-item' );

			// Cart-level options are displayed at cart-item level when we can't show them grouped together.
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
