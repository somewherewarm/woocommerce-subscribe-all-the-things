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
	 * Returns cart item level subscription schemes.
	 *
	 * @return array
	 */
	public static function get_subscription_schemes( $cart_item ) {

		// Get product-level subscription schemes stored in product meta

		$schemes = array();

		if ( WCCSubs_Cart::is_convertible_to_sub( $cart_item ) ) {

			$product_id           = $cart_item[ 'product_id' ];
			$subscription_schemes = get_post_meta( $product_id, '_wccsubs_schemes', true );

			foreach ( $subscription_schemes as $scheme ) {
				$schemes[ $scheme[ 'id' ] ] = $scheme;
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

			$subscription_schemes = self::get_subscription_schemes( $cart_item );

			if ( ! empty( $subscription_schemes ) ) {
				$default_scheme       = current( $subscription_schemes );
				$default_scheme_id    = $default_scheme[ 'id' ];

				return $default_scheme_id;
			}

		}

		return '0';
	}

	/**
	 * Returns cart level subscription schemes that are common to all cart items.
	 *
	 * @return array
	 */
	public static function get_cart_subscription_schemes() {

		// Find common
		foreach ( WC()->cart->get_cart() as $cart_item ) {


		}

		return false;
	}

}
