<?php
/**
 * WCS_ATT_Display_Ajax class
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Subscribe All the Things
 * @since    2.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AJAX front-end requests.
 *
 * @class    WCS_ATT_Display_Ajax
 * @version  2.1.0
 */
class WCS_ATT_Display_Ajax {

	/**
	 * Initialization.
	 */
	public static function init() {
		self::add_hooks();
	}

	/**
	 * Hook-in.
	 */
	private static function add_hooks() {

		// Ajax handler for saving the subscription scheme chosen at cart-level.
		add_action( 'wc_ajax_wcsatt_update_cart_option', array( __CLASS__, 'update_cart_scheme' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Hooks
	|--------------------------------------------------------------------------
	*/

	/**
	 * Ajax handler for saving the subscription scheme chosen at cart-level.
	 *
	 * @return void
	 */
	public static function update_cart_scheme() {

		check_ajax_referer( 'wcsatt_update_cart_option', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$posted_subscription_scheme_key = WCS_ATT_Form_Handler::get_posted_subscription_scheme( 'cart' );

		if ( empty( $posted_subscription_scheme_key ) ) {
			$posted_subscription_scheme_key = false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! empty( $cart_item[ 'wcsatt_data' ] ) ) {
				// Save scheme key on cart item.
				$cart_item[ 'wcsatt_data' ][ 'active_subscription_scheme' ] = $posted_subscription_scheme_key;
				// Apply scheme.
				WCS_ATT_Cart::apply_subscription_scheme( $cart_item );
			}
		}

		// Save chosen scheme.
		WCS_ATT_Cart::set_cart_subscription_scheme( $posted_subscription_scheme_key );

		// Recalculate totals.
		WC()->cart->calculate_totals();

		// Update the cart table apart from the totals in order to show modified price html strings with sub details.
		wc_get_template( 'cart/cart.php' );

		die();
	}
}

WCS_ATT_Display_Ajax::init();
