<?php
/**
 * WCS_ATT_Form_Handler class
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
 * Handles front-end form submissions.
 *
 * @class    WCS_ATT_Form_Handler
 * @version  2.1.0
 */
class WCS_ATT_Form_Handler {

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
		WCS_ATT()->register_module_hooks( 'form' );
	}

	/**
	 * Get the posted subscription scheme key by context.
	 *
	 * @param  string  $context
	 * @param  array   $args
	 * @return string
	 */
	public static function get_posted_subscription_scheme( $context, $args = array() ) {

		if ( 'product' === $context ) {

			$posted_subscription_scheme_key = null;

			$key = isset( $args[ 'product_id' ] ) ? 'convert_to_sub_' . absint( $args[ 'product_id' ] ) : 'convert_to_sub';

			if ( isset( $_REQUEST[ $key ] ) ) {
				$posted_subscription_scheme_option = wc_clean( $_REQUEST[ $key ] );
				$posted_subscription_scheme_key    = ! empty( $posted_subscription_scheme_option ) ? $posted_subscription_scheme_option : false;
			}

		} elseif ( 'cart-item' === $context ) {

			$posted_subscription_scheme_key = null;

			if ( isset( $args[ 'cart_item_key' ] ) ) {

				$key = 'convert_to_sub';

				$cart_item_key = $args[ 'cart_item_key' ];

				$posted_subscription_scheme_option = isset( $_POST[ 'cart' ][ $cart_item_key ][ $key ] ) ? wc_clean( $_POST[ 'cart' ][ $cart_item_key ][ $key ] ) : null;
				$posted_subscription_scheme_key    = '0' !== $posted_subscription_scheme_option ? $posted_subscription_scheme_option : false;
			}

		} elseif ( 'cart' === $context ) {

			$posted_subscription_scheme_key = null;

			$key = 'selected_scheme';

			if ( isset( $_POST[ $key ] ) ) {
				$posted_subscription_scheme_key = wc_clean( $_POST[ $key ] );
			}
		}

		return $posted_subscription_scheme_key;
	}
}

WCS_ATT_Form_Handler::init();
