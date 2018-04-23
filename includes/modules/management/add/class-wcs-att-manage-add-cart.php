<?php
/**
 * WCS_ATT_Manage_Add_Cart class
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
 * @class    WCS_ATT_Manage_Add_Cart
 * @version  2.1.0
 */
class WCS_ATT_Manage_Add_Cart extends WCS_ATT_Abstract_Module {

	/**
	 * Register display hooks.
	 *
	 * @return void
	 */
	protected function register_display_hooks() {

		// Template hooks.
		self::register_template_hooks();

		// Ajax handler.
		self::register_ajax_hooks();
	}

	/**
	 * Register form hooks.
	 */
	protected function register_form_hooks() {

		// Adds carts to subscriptions.
		add_action( 'wp_loaded', array( __CLASS__, 'form_handler' ), 100 );
	}

	/**
	 * Register template hooks.
	 */
	private static function register_template_hooks() {

		// Render the "Add-to-Subscription" options under the "Proceed to Checkout" button.
		add_action( 'woocommerce_after_cart_totals', array( __CLASS__, 'options_template' ), 100 );

		// Render subscriptions list.
		add_action( 'wcsatt_add_cart_to_subscription_html', array( __CLASS__, 'matching_subscriptions_template' ), 10, 2 );

		// Render subscriptions matching cart (server-side).
		add_action( 'wcsatt_display_subscriptions_matching_cart', array( __CLASS__, 'display_matching_subscriptions' ) );
	}

	/**
	 * Register ajax hooks.
	 */
	private static function register_ajax_hooks() {

		// Fetch subscriptions matching cart scheme via ajax.
		add_action( 'wc_ajax_wcsatt_load_subscriptions_matching_cart', array( __CLASS__, 'load_matching_subscriptions' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Templates
	|--------------------------------------------------------------------------
	*/

	/**
	 * 'Add cart to subscription' view -- template wrapper element.
	 */
	public static function options_template() {

		if ( 'off' === get_option( 'wcsatt_add_cart_to_subscription', 'off' ) ) {
			return;
		}

		$subscription_options_visible = false;
		$active_cart_scheme_key       = WCS_ATT_Cart::get_cart_subscription_scheme();
		$posted_data                  = WCS_ATT_Manage_Add::get_posted_data( 'update-cart' );

		wc_get_template( 'cart/cart-add-to-subscription.php', array(
			'is_visible' => false !== $active_cart_scheme_key,
			'is_checked' => $posted_data[ 'add_to_subscription_checked' ]
		), false, WCS_ATT()->plugin_path() . '/templates/' );
	}

	/**
	 * Displays list of subscriptions matching a cart.
	 */
	public static function display_matching_subscriptions() {

		$cart_schemes           = WCS_ATT_Cart::get_cart_subscription_schemes( 'cart-display' );
		$active_cart_scheme_key = WCS_ATT_Cart::get_cart_subscription_scheme();
		$active_cart_scheme     = isset( $cart_schemes[ $active_cart_scheme_key ] ) ? $cart_schemes[ $active_cart_scheme_key ] : false;

		if ( $active_cart_scheme ) {

			/**
			 * 'wcsatt_subscriptions_matching_cart' filter.
			 *
			 * Last chance to filter matched subscriptions.
			 *
			 * @param  array                $matching_subscriptions
			 * @param  WCS_ATT_Scheme|null  $scheme
			 */
			$matching_subscriptions = apply_filters( 'wcsatt_subscriptions_matching_cart', WCS_ATT_Manage_Add::get_matching_subscriptions( $active_cart_scheme ), $active_cart_scheme );

			/**
			 * 'wcsatt_add_cart_to_subscription_html' action.
			 *
			 * @param  array                $matching_subscriptions
			 * @param  WCS_ATT_Scheme|null  $scheme
			 *
			 */
			do_action( 'wcsatt_add_cart_to_subscription_html', $matching_subscriptions, $active_cart_scheme );
		}
	}

	/**
	 * 'Add to subscription' view -- matching list of subscriptions.
	 *
	 * @param  array                $subscriptions
	 * @param  WCS_ATT_Scheme|null  $scheme
	 * @return void
	 */
	public static function matching_subscriptions_template( $subscriptions, $scheme ) {

		add_action( 'woocommerce_my_subscriptions_actions', array( __CLASS__, 'button_template' ) );

		wp_nonce_field( 'wcsatt_add_cart_to_subscription', 'wcsatt_nonce' );

		wc_get_template( 'cart/cart-add-to-subscription-list.php', array(
			'subscriptions' => $subscriptions,
			'scheme'        => $scheme,
			'user_id'       => get_current_user_id()
		), false, WCS_ATT()->plugin_path() . '/templates/' );

		remove_action( 'woocommerce_my_subscriptions_actions', array( __CLASS__, 'button_template' ) );
	}

	/**
	 * 'Add to subscription' view -- 'Add' button template.
	 *
	 * @param  WC_Subscription  $subscription
	 */
	public static function button_template( $subscription ) {

		wc_get_template( 'cart/cart-add-to-subscription-button.php', array(
			'subscription_id' => $subscription->get_id()
		), false, WCS_ATT()->plugin_path() . '/templates/' );
	}

	/*
	|--------------------------------------------------------------------------
	| Ajax Handlers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Load all user subscriptions matching a cart + scheme key (known billing period and interval).
	 *
	 * @return void
	 */
	public static function load_matching_subscriptions() {

		$failure = array(
			'result' => 'failure',
			'html'   => ''
		);

		// User must be logged in.
		if ( ! is_user_logged_in() ) {
			wp_send_json( $failure );
		}

		ob_start();

		self::display_matching_subscriptions();

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

	/*
	|--------------------------------------------------------------------------
	| Form Handlers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Adds carts to subscriptions.
	 */
	public static function form_handler() {

		$posted_data = WCS_ATT_Manage_Add::get_posted_data( 'cart' );

		if ( empty( $posted_data[ 'subscription_id' ] ) ) {
			return;
		}

		if ( empty( $posted_data[ 'subscription_scheme' ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $posted_data[ 'nonce' ], 'wcsatt_add_cart_to_subscription' ) ) {
			return;
		}

		$subscription_id         = $posted_data[ 'subscription_id' ];
		$subscription            = wcs_get_subscription( $subscription_id );

		if ( ! $subscription ) {
			wc_add_notice( sprintf( __( 'Subscription #%d cannot be edited. Please get in touch with us for assistance.', 'woocommerce-subscribe-all-the-things' ), $subscription_id ), 'error' );
			return;
		}

		$cart_schemes            = WCS_ATT_Cart::get_cart_subscription_schemes( 'cart' );
		$subscription_scheme_key = $posted_data[ 'subscription_scheme' ];
		$subscription_scheme_obj = isset( $cart_schemes[ $subscription_scheme_key ] ) ? $cart_schemes[ $subscription_scheme_key ] : false;

		if ( ! $subscription_scheme_obj || ! WC_Subscriptions_Cart::cart_contains_subscription() || 1 !== sizeof( WC()->cart->recurring_carts ) || ! $subscription_scheme_obj->matches_subscription( $subscription ) ) {
			wc_add_notice( sprintf( __( 'Your cart cannot be added to subscription #%d. Please get in touch with us for assistance.', 'woocommerce-subscribe-all-the-things' ), $subscription_id ), 'error' );
			return;
		}

		try {

			/**
			 * 'wcsatt_add_cart_to_subscription' action.
			 *
			 * @param  WC_Subscription  $subscription
			 *
			 * @hooked WCS_ATT_Manage_Add::add_cart_to_subscription - 10
			 */
			do_action( 'wcsatt_add_cart_to_subscription', $subscription );

		} catch ( Exception $e ) {

			if ( $notice = $e->getMessage() ) {

				wc_add_notice( $notice, 'error' );
				return false;
			}
		}
	}
}
