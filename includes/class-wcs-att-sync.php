<?php
/**
 * WCS_ATT_Sync class
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
 * Handles synchronization.
 *
 * @class    WCS_ATT_Sync
 * @version  2.1.0
 */
class WCS_ATT_Sync {

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

		if ( is_admin() ) {

			// Subscription scheme synchronization options.
			add_action( 'wcsatt_subscription_scheme_content', array( __CLASS__, 'subscription_scheme_sync_content' ), 20, 3 );

			// Process and save the necessary meta.
			add_filter( 'wcsatt_processed_scheme_data', array( __CLASS__, 'process_scheme_sync_data' ), 10, 2 );

			// Add the translated fields to the Subscriptions admin script when viewing schemes on the 'WooCommerce > Settings' page.
			add_filter( 'woocommerce_subscriptions_admin_script_parameters', array( __CLASS__, 'admin_script_parameters' ), 10 );

		}

		// Remember to set sync meta when setting a subscription scheme on a product object.
		add_action( 'wcsatt_set_product_subscription_scheme', array( __CLASS__, 'set_product_subscription_scheme_sync_date' ), 0, 3 );
	}

	/**
	 * Determines if the first payment of a product is prorated, assuming a scheme is set on it.
	 *
	 * @since  2.1.0
	 *
	 * @param  WC_Product             $product  Product object to check.
	 * @param  string|WCS_ATT_Scheme  $scheme   Optional scheme key when checking against one of the schemes already tied to the object, or an arbitrary 'WCS_ATT_Scheme' object to check against.
	 * @return boolean                          Result.
	 */
	public static function is_first_payment_prorated( $product, $scheme = '' ) {

		$is_first_payment_prorated = false;

		$schemes           = WCS_ATT_Product_Schemes::get_subscription_schemes( $product );
		$active_scheme_key = WCS_ATT_Product_Schemes::get_subscription_scheme( $product );

		if ( is_a( $scheme, 'WCS_ATT_Scheme' ) ) {

			$scheme_key_to_set = $scheme->get_key();

			// Apply scheme.
			WCS_ATT_Product_Schemes::set_subscription_schemes( $product, array( $scheme_key_to_set => $scheme ) );
			WCS_ATT_Product_Schemes::set_subscription_scheme( $product, $scheme_key_to_set );

			// Check if prorated.
			$is_first_payment_prorated = WC_Subscriptions_Synchroniser::is_product_prorated( $product );

			// Restore state.
			WCS_ATT_Product_Schemes::set_subscription_schemes( $product, $schemes );
			WCS_ATT_Product_Schemes::set_subscription_scheme( $product, $active_scheme_key );

		} else {

			$scheme_key_to_check = '' === $scheme ? $active_scheme_key : $scheme;

			// Attempt to switch scheme.
			$scheme_switch_required = $scheme_key_to_check !== $active_scheme_key;
			$switched_scheme        = $scheme_switch_required ? WCS_ATT_Product_Schemes::set_subscription_scheme( $product, $scheme_key_to_check ) : false;

			// Check if prorated.
			$is_first_payment_prorated = WC_Subscriptions_Synchroniser::is_product_prorated( $product );

			// Restore state.
			if ( $switched_scheme ) {
				WCS_ATT_Product_Schemes::set_subscription_schemes( $product, $schemes );
				WCS_ATT_Product_Schemes::set_subscription_scheme( $product, $active_scheme_key );
			}
		}

		return $is_first_payment_prorated;
	}

	/*
	|--------------------------------------------------------------------------
	| Hooks
	|--------------------------------------------------------------------------
	*/

	/**
	 * Renders subscription scheme synchronization options.
	 *
	 * @param  int     $index
	 * @param  array   $scheme_data
	 * @param  int     $post_id
	 * @return void
	 */
	public static function subscription_scheme_sync_content( $index, $scheme_data, $post_id ) {

		global $thepostid, $wp_locale;

		if ( empty( $thepostid ) ) {
			$thepostid = '-1';
		}

		if ( ! empty( $scheme_data ) ) {
			$subscription_period            = $scheme_data[ 'subscription_period' ];
			$subscription_payment_sync_date = isset( $scheme_data[ 'subscription_payment_sync_date' ] ) ? $scheme_data[ 'subscription_payment_sync_date' ] : 0;
		} else {
			$subscription_period            = 'month';
			$subscription_payment_sync_date = 0;
		}

		// Synchronization.
		if ( class_exists( 'WC_Subscriptions_Synchroniser' ) && WC_Subscriptions_Synchroniser::is_syncing_enabled() ) {

			$billing_period_ranges     = self::rename_subscription_billing_period_range_data( WC_Subscriptions_Synchroniser::get_billing_period_ranges( $subscription_period ) );
			$display_week_month_select = ! in_array( $subscription_period, array( 'month', 'week' ) ) ? 'display: none;' : '';
			$display_annual_select     = 'year' !== $subscription_period ? 'display: none;' : '';

			if ( is_array( $subscription_payment_sync_date ) ) {
				$payment_month = $subscription_payment_sync_date[ 'month' ];
				$payment_day   = $subscription_payment_sync_date[ 'day' ];
			} else {
				$payment_month = gmdate( 'm' );
				$payment_day   = $subscription_payment_sync_date;
			}

			?><div class="subscription_sync">
				<div class="subscription_sync_week_month" style="<?php echo esc_attr( $display_week_month_select ); ?>"><?php

					woocommerce_wp_select( array(
						'id'          => '_satt_subscription_payment_sync_date_' . $index,
						'class'       => 'wc_input_subscription_payment_sync select short',
						'label'       => __( 'Synchronization', 'woocommerce-subscribe-all-the-things' ),
						'options'     => $billing_period_ranges,
						'name'        => 'wcsatt_schemes[' . $index . '][subscription_payment_sync_date]',
						'description' => WC_Subscriptions_Synchroniser::$sync_description,
						'desc_tip'    => true,
						'value'       => $payment_day
						)
					);

				?></div>
				<div class="subscription_sync_annual" style="<?php echo esc_attr( $display_annual_select ); ?>">

					<p class="form-field _satt_subscription_payment_sync_date_day_field">
						<label for="_satt_subscription_payment_sync_date_day_<?php echo $index; ?>"><?php echo esc_html( __( 'Synchronization', 'woocommerce-subscribe-all-the-things' ) ); ?></label>
						<span class="wrap">
							<input type="number" id="_satt_subscription_payment_sync_date_day_<?php echo $index; ?>" name="wcsatt_schemes[<?php echo $index; ?>][subscription_payment_sync_date_day]" class="wc_input_subscription_payment_sync satt_subscription_payment_sync_date_day" value="<?php echo esc_attr( $payment_day ); ?>" placeholder="<?php echo esc_attr_x( 'Day', 'input field placeholder for day field for annual subscriptions', 'woocommerce-subscriptions' ); ?>"  />
							<select id="_satt_subscription_payment_sync_date_month_<?php echo $index; ?>" name="wcsatt_schemes[<?php echo $index; ?>][subscription_payment_sync_date_month]" class="wc_input_subscription_payment_sync last" >
								<?php foreach ( $wp_locale->month as $value => $label ) { ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $payment_month, true ) ?>><?php echo esc_html( $label ); ?></option>
								<?php } ?>
							</select>

							</select>
						</span>
						<?php echo wcs_help_tip( WC_Subscriptions_Synchroniser::$sync_description_year ); ?>
					</p>
				</div>
			</div><?php
		}
	}

	/**
	 * Keep it short. Rename "Do not synchronise" to "Disabled". Pointless but blame OCD.
	 *
	 * @param  array  $range_data
	 * @return array
	 */
	private static function rename_subscription_billing_period_range_data( $range_data ) {

		if ( isset( $range_data[ 0 ] ) ) {
			$range_data[ 0 ] = __( 'Disabled', 'woocommerce-subscribe-all-the-things' );
		} elseif ( is_array( $range_data ) ) {
			foreach ( $range_data as $key => $data ) {
				$range_data[ $key ] = self::rename_subscription_billing_period_range_data( $data );
			}
		}

		return $range_data;
	}

	/**
	 * Save subscription sync options.
	 *
	 * @param  array       $scheme
	 * @param  WC_Product  $product
	 * @return void
	 */
	public static function process_scheme_sync_data( $scheme_data ) {

		$subscription_period = isset( $scheme_data[ 'subscription_period' ] ) ? $scheme_data[ 'subscription_period' ] : '';

		if ( 'year' == $subscription_period ) {

			$scheme_data[ 'subscription_payment_sync_date' ] = array(
				'day'    => isset( $scheme_data[ 'subscription_payment_sync_date_day' ] ) ? $scheme_data[ 'subscription_payment_sync_date_day' ] : 0,
				'month'  => isset( $scheme_data[ 'subscription_payment_sync_date_month' ] ) ? $scheme_data[ 'subscription_payment_sync_date_month' ] : '01',
			);

		} else {

			if ( ! isset( $scheme_data[ 'subscription_payment_sync_date' ] ) ) {
				$scheme_data[ 'subscription_payment_sync_date' ] = 0;
			}
		}

		return $scheme_data;
	}

	/**
	 * Add translated syncing options for our client side script.
	 *
	 * @param  array  $script_parameters
	 */
	public static function admin_script_parameters( $script_parameters ) {

		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';

		if ( $screen_id === 'woocommerce_page_wc-settings' && isset( $_GET[ 'tab' ] ) && $_GET[ 'tab' ] === 'subscriptions' ) {

			$billing_period_strings = self::rename_subscription_billing_period_range_data( WC_Subscriptions_Synchroniser::get_billing_period_ranges() );

			$script_parameters[ 'syncOptions' ] = array(
				'week'  => $billing_period_strings[ 'week' ],
				'month' => $billing_period_strings[ 'month' ],
			);

		}

		return $script_parameters;
	}

	/**
	 * Set subscription payment sync data on product objects.
	 *
	 * @param  string      $scheme_key
	 * @param  string      $active_scheme_key
	 * @param  WC_Product  $product
	 */
	public static function set_product_subscription_scheme_sync_date( $scheme_key, $active_scheme_key, $product ) {

		$schemes = WCS_ATT_Product_Schemes::get_subscription_schemes( $product );

		if ( ! empty( $scheme_key ) && is_array( $schemes ) && isset( $schemes[ $scheme_key ] ) && $scheme_key !== $active_scheme_key ) {

			$scheme_to_set = $schemes[ $scheme_key ];

			WCS_ATT_Product::set_runtime_meta( $product, 'subscription_payment_sync_date', $scheme_to_set->get_sync_date() );

		} elseif ( empty( $scheme_key ) ) {

			WCS_ATT_Product::set_runtime_meta( $product, 'subscription_payment_sync_date', 0 );
		}
	}
}

WCS_ATT_Sync::init();
