<?php
/**
 * WCS_ATT_Add class
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
 * Add stuff to existing subscriptions.
 *
 * @class    WCS_ATT_Add
 * @version  2.1.0
 */
class WCS_ATT_Add extends WCS_ATT_Abstract_Module {

	/**
	 * Register hooks.
	 *
	 * @param  string  $type
	 * @return void
	 */
	public static function register_hooks( $type ) {

		if ( 'display' === $type ) {

			self::register_template_hooks();
			self::register_ajax_hooks();
		}
	}

	/**
	 * Register template hooks.
	 */
	private static function register_template_hooks() {

		// Render wrapper element.
		add_filter( 'woocommerce_after_add_to_cart_button', array( __CLASS__, 'add_to_subscription' ), 1000 );

		// Render subscriptions list.
		add_action( 'wcsatt_add_product_to_subscription_html', array( __CLASS__, 'matching_subscriptions_template' ), 10, 3 );
	}

	/**
	 * Register ajax hooks.
	 */
	private static function register_ajax_hooks() {

		// Fetch matching subscriptions via ajax.
		add_action( 'wc_ajax_wcsatt_load_matching_subscriptions', __CLASS__ . '::load_subscriptions_matching_product' );
	}

	/*
	|--------------------------------------------------------------------------
	| Template Functions
	|--------------------------------------------------------------------------
	*/

	/**
	 * 'Add to subscription' view -- 'Add' button template.
	 *
	 * @param  WC_Subscription  $subscription
	 */
	public static function add_to_subscription_button_template( $subscription ) {

		wc_get_template( 'single-product/product-add-to-subscription-button.php', array(
			'subscription' => $subscription
		), false, WCS_ATT()->plugin_path() . '/templates/' );

	}

	/**
	 * 'Add to subscription' view -- matching list of subscriptions.
	 *
	 * @param  array       $subscriptions
	 * @param  WC_Product  $product
	 * @param  WCS_ATT_Scheme|null  $scheme
	 * @return void
	 */
	public static function matching_subscriptions_template( $subscriptions, $product, $scheme ) {

		add_action( 'woocommerce_my_subscriptions_actions', array( __CLASS__, 'add_to_subscription_button_template' ) );

		wc_get_template( 'single-product/product-add-to-subscription-list.php', array(
			'subscriptions' => $subscriptions,
			'product'       => $product,
			'scheme'        => $scheme,
			'user_id'       => get_current_user_id()
		), false, WCS_ATT()->plugin_path() . '/templates/' );

		remove_action( 'woocommerce_my_subscriptions_actions', array( __CLASS__, 'add_to_subscription_button_template' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Template Hooks
	|--------------------------------------------------------------------------
	*/

	/**
	 * 'Add to subscription' view -- wrapper element.
	 *
	 * @since  2.1.0
	 */
	public static function add_to_subscription() {

		global $product;

		if ( ! WCS_ATT_Product::supports_feature( $product, 'subscription_management_add_to_subscription' ) ) {
			return;
		}

		// Bypass when switching.
		if ( WCS_ATT_Switch::switching_product( $product ) ) {
			return;
		}

		// User must be logged in.
		if ( ! is_user_logged_in() ) {
			return;
		}

		$subscription_options_visible = false;

		/*
		 * Subscription options for variable products are embedded inside the variation data 'price_html' field and updated by the core variations script.
		 * The add-to-subscription template is displayed when a variation is found.
		 */
		if ( ! $product->is_type( 'variable' ) ) {

			$product_id                           = WCS_ATT_Core_Compatibility::get_product_id( $product );
			$subscription_schemes                 = WCS_ATT_Product_Schemes::get_subscription_schemes( $product );
			$force_subscription                   = WCS_ATT_Product_Schemes::has_forced_subscription_scheme( $product );
			$is_single_scheme_forced_subscription = $force_subscription && sizeof( $subscription_schemes ) === 1;
			$default_subscription_scheme_key      = apply_filters( 'wcsatt_get_default_subscription_scheme_id', WCS_ATT_Product_Schemes::get_default_subscription_scheme( $product, 'key' ), $subscription_schemes, false === $force_subscription, $product ); // Why 'false === $force_subscription'? The answer is back-compat.

			$subscription_options_visible = $is_single_scheme_forced_subscription || $default_subscription_scheme_key;
		}

		wc_get_template( 'single-product/product-add-to-subscription.php', array(
			'product_id' => $product->get_id(),
			'is_visible' => $subscription_options_visible
		), false, WCS_ATT()->plugin_path() . '/templates/' );
	}

	/*
	|--------------------------------------------------------------------------
	| Ajax Handlers
	|--------------------------------------------------------------------------
	*/

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

		// User must be logged in.
		if ( ! is_user_logged_in() ) {
			wp_send_json( $failure );
		}

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
		 * @hooked WCS_ATT_Add::matching_subscriptions_template - 10
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
