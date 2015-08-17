<?php
/**
 * Loading and filtering of subscription scheme settings.
 *
 * @class 	WCCSubs_Schemes
 * @version 1.0.0
 */

class WCCSubs_Schemes {

	/**
	 * Returns the active subscription scheme of a cart item, or false if the cart item is a one-off purchase.
	 *
	 * @return array
	 */
	public static function get_active_subscription_scheme( $cart_item ) {

		$schemes          = self::get_subscription_schemes( $cart_item );
		$active_scheme_id = isset( $cart_item[ 'wccsub_data' ][ 'active_subscription_scheme_id' ] ) ? $cart_item[ 'wccsub_data' ][ 'active_subscription_scheme_id' ] : '0';
		$active_scheme    = isset( $schemes[ $active_scheme_id ] ) ? $schemes[ $active_scheme_id ] : false;

		return $active_scheme;
	}

	/**
	 * Returns all available subscription schemes (product-level and cart-level).
	 *
	 * @return array
	 */
	public static function get_subscription_schemes( $cart_item ) {

		$schemes = array();

		if ( WCCSubs_Cart::is_convertible_to_sub( $cart_item ) ) {

			// Get product-level subscription schemes stored in product meta

			$product_id      = $cart_item[ 'product_id' ];
			$product_schemes = get_post_meta( $product_id, '_wccsubs_schemes', true );

			foreach ( $product_schemes as $scheme ) {
				$scheme[ 'scope' ]          = 'cart-item';
				$schemes[ $scheme[ 'id' ] ] = $scheme;
			}

			// Get cart-level subscription schemes stored in WC settings
			// Added only if there are no product-level schemes present

			if ( empty( $schemes ) ) {

				$wcs_prefix             = WC_Subscriptions_Admin::$option_prefix;
				$cart_level_subs_active = get_option( $wcs_prefix . '_enable_cart_subscriptions', 'no' );

				if ( $cart_level_subs_active === 'yes' ) {

					$cart_level_schemes = get_option( $wcs_prefix . '_subscribe_to_cart_schemes', array() );

					if ( ! empty( $cart_level_schemes ) ) {
						foreach ( $cart_level_schemes as $scheme ) {
							$scheme[ 'scope' ]          = 'cart';
							$schemes[ $scheme[ 'id' ] ] = $scheme;
						}
					}
				}
			}

		}

		return $schemes;
	}

	/**
	 * Returns the default subscription scheme id of a cart item, or '0' if the default option is a one-off purchase.
	 *
	 * @param  array $cart_item
	 * @return string
	 */
	public static function get_default_subscription_scheme_id( $cart_item ) {

		$product_id     = $cart_item[ 'product_id' ];
		$default_status = get_post_meta( $product_id, '_wccsubs_default_status', true );

		if ( $default_status === 'subscription' ) {

			$subscription_schemes = self::get_cart_item_subscription_schemes( $cart_item );

			if ( ! empty( $subscription_schemes ) ) {
				$default_scheme       = current( $subscription_schemes );
				$default_scheme_id    = $default_scheme[ 'id' ];

				return $default_scheme_id;
			}

		}

		return '0';
	}

	/**
	 * Returns subscription schemes for cart-item level options.
	 *
	 * @param  array $cart_item
	 * @return array
	 */
	public static function get_cart_item_subscription_schemes( $cart_item ) {

		$schemes           = self::get_subscription_schemes( $cart_item );
		$cart_item_schemes = array();

		foreach ( $schemes as $scheme ) {
			if ( $scheme[ 'scope' ] === 'cart-item' ) {
				$cart_item_schemes[ $scheme[ 'id' ] ] = $scheme;
			}
		}

		return $cart_item_schemes;
	}

	/**
	 * Returns subscription schemes for cart-level options.
	 * Cart-level schemes must be a superset of any schemes found at cart item level, including subscription-type products ( = single scheme ).
	 * Otherwise, the cart-level options will not be displayed.
	 *
	 * Must be called after all cart session data has been loaded.
	 *
	 * @return array|boolean
	 */
	public static function get_cart_subscription_schemes() {

		$wcs_prefix              = WC_Subscriptions_Admin::$option_prefix;
		$cart_level_subs_active  = get_option( $wcs_prefix . '_enable_cart_subscriptions', 'no' );
		$cart_level_schemes      = array();

		if ( $cart_level_subs_active === 'yes' ) {
			$schemes = get_option( $wcs_prefix . '_subscribe_to_cart_schemes', array() );
			foreach ( $schemes as $scheme ) {
				$cart_level_schemes[ $scheme[ 'id' ] ] = $scheme;
			}
		}

		if ( empty( $cart_level_schemes ) ) {
			return false;
		}

		$cart_level_schemes_keys = array_keys( $cart_level_schemes );

		foreach ( WC()->cart->get_cart() as $cart_item ) {

			if ( ! WCCSubs()->is_supported_product_type( $cart_item ) ) {
				return false;
			}

			if ( $cart_item_level_schemes = self::get_cart_item_subscription_schemes( $cart_item ) ) {

				foreach ( array_keys( $cart_item_level_schemes ) as $cart_item_level_scheme_id ) {
					if ( ! in_array( $cart_item_level_scheme_id, $cart_level_schemes_keys ) ) {
						return false;
					}
				}

			} elseif ( WC_Subscriptions_Product::is_subscription( $cart_item[ 'product_id' ] ) ) {
				// todo
				return false;
			}

		}

		return $cart_level_schemes;
	}

}
