<?php
/**
 * WCS_ATT_Ajax class
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
 * @class    WCS_ATT_Ajax
 * @version  2.1.0
 */
class WCS_ATT_Ajax {

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

		// Fetch matching subscriptions via ajax.
		add_action( 'wc_ajax_wcsatt_load_matching_subscriptions', __CLASS__ . '::load_subscriptions_matching_product' );
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

		$selected_scheme = false;

		if ( ! empty( $_POST[ 'selected_scheme' ] ) ) {
			$selected_scheme = wc_clean( $_POST[ 'selected_scheme' ] );
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! empty( $cart_item[ 'wcsatt_data' ] ) ) {
				// Save scheme key on cart item.
				$cart_item[ 'wcsatt_data' ][ 'active_subscription_scheme' ] = $selected_scheme;
				// Apply scheme.
				WCS_ATT_Cart::apply_subscription_scheme( $cart_item );
			}
		}

		// Save chosen scheme.
		WCS_ATT_Cart::set_cart_subscription_scheme( $selected_scheme );

		// Recalculate totals.
		WC()->cart->calculate_totals();

		// Update the cart table apart from the totals in order to show modified price html strings with sub details.
		wc_get_template( 'cart/cart.php' );

		die();
	}

	/**
	 * Load all user subscriptions matching a product + scheme key (known billing period and interval).
	 *
	 * @return void
	 */
	public static function load_subscriptions_matching_product() {

		$failure = array(
			'result' => 'failure',
			'html'   => ''
		);

		$product_id = ! empty( $_POST[ 'product_id' ] ) ? absint( $_POST[ 'product_id' ] ) : false;
		$scheme_key = ! empty( $_POST[ 'scheme_key' ] ) ? wc_clean( $_POST[ 'scheme_key' ] ) : false;

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			wp_send_json( $failure );
		}

		$scheme = WCS_ATT_Product_Schemes::get_subscription_scheme( $product, 'object', $scheme_key );

		if ( ! $scheme ) {
			wp_send_json( $failure );
		}

		// Get all subscriptions of the current user.
		$subscriptions = wcs_get_subscriptions( array(
			'subscription_status'    => array( 'active' ),
			'subscriptions_per_page' => -1,
			'customer_id'            => get_current_user_id()
		) );

		// Filter them by period + interval. PHP 5.2 be damned.
		$matching_subscriptions = array();

		if ( ! empty( $subscriptions ) ) {
			foreach ( $subscriptions as $subscription_id => $subscription ) {

				$period   = $subscription->get_billing_period();
				$interval = $subscription->get_billing_interval();

				// Code not readable on purpose. Leave this alone.
				if ( $period !== $scheme->get_period() || absint( $interval ) !== $scheme->get_interval() ) {
					continue;
				}

				$matching_subscriptions[ $subscription_id ] = $subscription;
			}
		}

		/**
		 * 'wcsatt_subscriptions_matching_product' filter.
		 *
		 * Last chance to filter matched subscriptions.
		 *
		 * @since  2.1.0
		 *
		 * @param  array                $matching_subscriptions
		 * @param  WC_Product           $product
		 * @param  WCS_ATT_Scheme|null  $scheme
		 */
		$matching_subscriptions = apply_filters( 'wcsatt_subscriptions_matching_product', $matching_subscriptions, $product, $scheme );

		ob_start();

		/**
		 * 'wcsatt_add_product_to_subscription_html' action.
		 *
		 * @since  2.1.0
		 *
		 * @param  array                $matching_subscriptions
		 * @param  WC_Product           $product
		 * @param  WCS_ATT_Scheme|null  $scheme
		 *
		 * @hooked WCS_ATT_Display_Product::matching_subscriptions_template - 10
		 */
		do_action( 'wcsatt_add_product_to_subscription_html', $matching_subscriptions, $product, $scheme );

		$html = ob_get_clean();

		if ( ! $html ) {
			$result = $failure;
		} else {
			$result = array(
				'result' => 'success',
				'html'   => $html
			);
		}

		wp_send_json( $result );
	}
}

WCS_ATT_Ajax::init();
