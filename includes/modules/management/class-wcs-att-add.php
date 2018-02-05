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
	 * Using this to pass data from 'WC_Form_Handler::add_to_cart_action' into our own logic.
	 * @var array
	 */
	private static $add_product_to_subscription = array();

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
		} elseif ( 'form' === $type ) {
			self::register_form_hooks();
		}
	}

	/**
	 * Register template hooks.
	 */
	private static function register_template_hooks() {

		// Render wrapper element.
		add_filter( 'woocommerce_after_add_to_cart_button', array( __CLASS__, 'add_product_to_subscription_template' ), 1000 );

		// Render subscriptions list.
		add_action( 'wcsatt_add_product_to_subscription_html', array( __CLASS__, 'subscriptions_matching_product_template' ), 10, 3 );
	}

	/**
	 * Register ajax hooks.
	 */
	private static function register_ajax_hooks() {

		// Fetch matching subscriptions via ajax.
		add_action( 'wc_ajax_wcsatt_load_matching_subscriptions', array( __CLASS__, 'load_subscriptions_matching_product' ) );
	}

	/**
	 * Register form hooks.
	 */
	private static function register_form_hooks() {

		// Adds products to subscriptions after validating.
		add_action( 'wp_loaded', array( __CLASS__, 'add_product_to_subscription_form_handler' ) );
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
	public static function subscriptions_matching_product_template( $subscriptions, $product, $scheme ) {

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
	public static function add_product_to_subscription_template() {

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

			$subscription_schemes                 = WCS_ATT_Product_Schemes::get_subscription_schemes( $product );
			$force_subscription                   = WCS_ATT_Product_Schemes::has_forced_subscription_scheme( $product );
			$is_single_scheme_forced_subscription = $force_subscription && sizeof( $subscription_schemes ) === 1;
			$default_subscription_scheme_key      = apply_filters( 'wcsatt_get_default_subscription_scheme_id', WCS_ATT_Product_Schemes::get_default_subscription_scheme( $product, 'key' ), $subscription_schemes, false === $force_subscription, $product ); // Why 'false === $force_subscription'? The answer is back-compat.
			$default_subscription_scheme          = WCS_ATT_Product_Schemes::get_subscription_scheme( $product, 'object', $default_subscription_scheme_key );

			$subscription_options_visible = $is_single_scheme_forced_subscription || ( is_object( $default_subscription_scheme ) && ! $default_subscription_scheme->is_prorated() );
		}

		wp_nonce_field( 'add_product_to_subscription', 'wcsatt_nonce_' . $product->get_id() );

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

				// Period and interval must match.
				if ( $period !== $scheme->get_period() || absint( $interval ) !== $scheme->get_interval() ) {
					continue;
				}

				// The subscription must have an upcoming renewal.
				if ( ! $subscription->get_time( 'next_payment' ) ) {
					continue;
				}

				// The scheme length must match the remaining subscription renewals.
				if ( $scheme->get_length() ) {

					$subscription_next_payment = $subscription->get_time( 'next_payment' );
					$subscription_end          = $subscription->get_time( 'end' );

					// If the scheme has a length but the subscription is endless, dump it.
					if ( ! $subscription_end ) {
						continue;
					}

					$subscription_periods_left = wcs_estimate_periods_between( $subscription_next_payment, $subscription_end, $scheme->get_period() );

					if ( $subscription_periods_left !== $scheme->get_length() ) {
						continue;
					}
				}

				// If the scheme is synced, its payment day must match the next subscription renewal payment day.
				if ( $scheme->is_synced() ) {

					$scheme_sync_day           = $scheme->get_sync_date();
					$subscription_next_payment = $subscription->get_time( 'next_payment' );

					if ( 'week' === $period && $scheme_sync_day !== intval( date( 'N', $subscription_next_payment ) ) ) {
						continue;
					}

					if ( 'month' === $period && $scheme_sync_day !== intval( date( 'j', $subscription_next_payment ) ) ) {
						continue;
					}

					if ( 'year' === $period && ( $scheme_sync_day[ 'day' ] !== date( 'd', $subscription_next_payment ) || $scheme_sync_day[ 'month' ] !== date( 'm', $subscription_next_payment ) ) ) {
						continue;
					}
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

	/*
	|--------------------------------------------------------------------------
	| Form Handlers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get posted data.
	 *
	 * @param  string  $context
	 * @return array
	 */
	public static function get_posted_add_to_subscription_data( $context ) {

		$posted_data = array();

		if ( 'product' === $context ) {

			$posted_data = array(
				'product_id'          => false,
				'subscription_id'     => false,
				'subscription_scheme' => false
			);

			if ( ! empty( $_REQUEST[ 'add-to-subscription' ] ) && is_numeric( $_REQUEST[ 'add-to-subscription' ] ) ) {

				$posted_data[ 'product_id' ] = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $_REQUEST[ 'add-to-subscription' ] ) );

				if ( ! empty( $_REQUEST[ 'add_to_sub_' . $posted_data[ 'product_id' ] ] ) && is_numeric( $_REQUEST[ 'add_to_sub_' . $posted_data[ 'product_id' ] ] ) ) {

					$posted_data[ 'nonce' ]               = ! empty( $_REQUEST[ 'wcsatt_nonce_' . $posted_data[ 'product_id' ] ] ) ? $_REQUEST[ 'wcsatt_nonce_' . $posted_data[ 'product_id' ] ] : '';
					$posted_data[ 'subscription_id' ]     = absint( $_REQUEST[ 'add_to_sub_' . $posted_data[ 'product_id' ] ] );
					$posted_data[ 'subscription_scheme' ] = WCS_ATT_Form_Handler::get_posted_subscription_scheme( 'product', array( 'product_id' => $posted_data[ 'product_id' ] ) );
				}
			}
		}

		return $posted_data;
	}

	/**
	 * Adds products to subscriptions after validating.
	 */
	public static function add_product_to_subscription_form_handler() {

		$posted_data = self::get_posted_add_to_subscription_data( 'product' );

		if ( empty( $posted_data[ 'product_id' ] ) ) {
			return;
		}

		if ( empty( $posted_data[ 'subscription_id' ] ) ) {
			return;
		}

		if ( empty( $posted_data[ 'subscription_scheme' ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $posted_data[ 'nonce' ], 'add_product_to_subscription' ) ) {
			return;
		}

		$product_id      = $posted_data[ 'product_id' ];
		$subscription_id = $posted_data[ 'subscription_id' ];
		$subscription    = wcs_get_subscription( $subscription_id );

		if ( ! $subscription ) {
			wc_add_notice( sprintf( __( 'Subscription #%d cannot be edited. Please get in touch with us for assistance.', 'woocommerce-subscribe-all-the-things' ), $subscription_id ), 'error' );
			return;
		}

		/*
		 * Relay form validation to 'WC_Form_Handler::add_to_cart_action'.
		 * Use 'woocommerce_add_to_cart_validation' filter to:
		 *
		 * - Let WC validate the form.
		 * - If invalid, stop.
		 * - If valid, add the validated product to the selected subscription.
		 */

		self::$add_product_to_subscription = false;

		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_product_to_subscription' ), 9999, 5 );

		/**
		 * 'wcsatt_pre_add_product_to_subscription_validation' action.
		 *
		 * @param  int  $product_id
		 * @param  int  $subscription_id
		 */
		do_action( 'wcsatt_pre_add_product_to_subscription_validation', $product_id, $subscription_id );

		$_REQUEST[ 'add-to-cart' ] = $product_id;

		// No worries, nothing gets added to the cart at this point.
		WC_Form_Handler::add_to_cart_action();

		// Disarm 'WC_Form_Handler::add_to_cart_action'.
		$_REQUEST[ 'add-to-cart' ] = false;

		/**
		 * 'wcsatt_pre_add_product_to_subscription_validation' action.
		 *
		 * @param  int  $product_id
		 * @param  int  $subscription_id
		 */
		do_action( 'wcsatt_post_add_product_to_subscription_validation', $product_id, $subscription_id );


		// Remove filter.
		remove_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_product_to_subscription' ), 9999 );

		// Validation passed?
		if ( ! self::$add_product_to_subscription ) {
			return;
		}

		$subscription_scheme = $posted_data[ 'subscription_scheme' ];
		$quantity            = self::$add_product_to_subscription[ 'quantity' ];
		$variation_id        = self::$add_product_to_subscription[ 'variation_id' ];
		$variations          = self::$add_product_to_subscription[ 'variations' ];

		// At this point we've got the green light to proceed.
		self::add_product_to_subscription( $subscription, $subscription_scheme, $product_id, $quantity, $variation_id, $variations );
	}

	/**
	 * Adds a product to a subscription.
	 *
	 * @param WC_Subscription   $subscription
	 * @param string|null|bool  $subscription_scheme
	 * @param int               $product_id
	 * @param int               $quantity
	 * @param int               $variation_id
	 * @param array             $variations
	 */
	public static function add_product_to_subscription( $subscription, $subscription_scheme, $product_id, $quantity, $variation_id = 0, $variations = array() ) {

		$product = wc_get_product( $variation_id ? $variation_id : $product_id );

		// Set scheme on product (remember doing so may end up changing its price).
		WCS_ATT_Product_Schemes::set_subscription_scheme( $product, $subscription_scheme );

		$add_product_to_subscription_callback = apply_filters( 'wscatt_add_product_to_subscription_callback', false, $product );

		if ( is_callable( $add_product_to_subscription_callback ) ) {

			$added_item_id = call_user_func_array( $add_product_to_subscription_callback, array( $subscription, $product, $quantity ) );

		} else {

			$added_item_id = $subscription->add_product( $product, $quantity, array(
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'variations'   => $variations
			) );
		}

		$subscription_url  = $subscription->get_view_order_url();
		$subscription_link = sprintf( _x( '<a href="%1$s">#%2$s</a>', 'link to subscription', 'woocommerce-subscribe-all-the-things' ), esc_url( $subscription_url ), $subscription->get_id() );

		if ( ! $added_item_id || is_wp_error( $added_item_id ) ) {

			wc_add_notice( sprintf( __( 'There was a problem adding "%1$s" to subscription %2$s.', 'woocommerce-subscribe-all-the-things' ), $product->get_name(), $subscription_link ), 'error' );

		} else {

			$subscription->calculate_totals();

			$added_item = wcs_get_order_item( $added_item_id, $subscription );

			// Save the scheme key!
			$added_item->add_meta_data( '_wcsatt_scheme', false === $subscription_scheme ? '0' : $subscription_scheme, true );

			$subscription->add_order_note( sprintf( _x( 'Customer added "%1$s" (Product ID: #%2$d) from the product page.', 'used in order note', 'woocommerce-subscribe-all-the-things' ), $added_item->get_name(), $product_id ) );

			$subscription->save();

			wc_add_notice( sprintf( __( 'You have successfully added "%1$s" to subscription %2$s.', 'woocommerce-subscribe-all-the-things' ), $added_item->get_name(), $subscription_link ) );
		}

		/**
		 * Filter redirect url.
		 *
		 * @param  string  $url
		 * @param  int     $product_id
		 * @param  string  $subscription_id
		 */
		$redirect_url = apply_filters( 'wcsatt_add_product_to_subscription_redirect_url', $subscription_url, $added_item_id, $product, $subscription );

		wp_safe_redirect( $subscription_url );
		exit;
	}

	/*
	|--------------------------------------------------------------------------
	| Form Handling Hooks
	|--------------------------------------------------------------------------
	*/

	/**
	 * Signals 'add_product_to_subscription_form_handler' that validation failed.
	 * Data is exchanged via the 'add_product_to_subscription' static prop.
	 * Always returns false to ensure nothing gets added to the cart.
	 *
	 * @param  boolean  $result
	 * @param  int      $product_id
	 * @param  mixed    $quantity
	 * @param  int      $variation_id
	 * @param  array    $variations
	 * @return bool
	 */
	public static function validate_add_product_to_subscription( $result, $product_id, $quantity, $variation_id = 0, $variations = array() ) {

		if ( $result ) {

			$product = wc_get_product( $variation_id ? $variation_id : $product_id );

			/*
			 * Validate stock.
			 */

			if ( ! $product->is_in_stock() ) {
				wc_add_notice( sprintf( __( '&quot;%s&quot; is out of stock.', 'woocommerce-subscribe-all-the-things' ), $product->get_name() ), 'error' );
				return false;
			}

			if ( ! $product->has_enough_stock( $quantity ) ) {
				/* translators: 1: product name 2: quantity in stock */
				wc_add_notice( sprintf( __( '&quot;%1$s&quot; does not have enough stock (%2$s remaining).', 'woocommerce-subscribe-all-the-things' ), $product->get_name(), wc_format_stock_quantity_for_display( $product->get_stock_quantity(), $product ) ), 'error' );
				return false;
			}

			/*
			 * Flash the green light.
			 */

			self::$add_product_to_subscription = array(
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'quantity'     => $quantity,
				'variations'   => $variations
			);
		}

		return false;
	}
}
