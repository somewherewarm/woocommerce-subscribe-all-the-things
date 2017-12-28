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

			// Reword "Do not synchronise" to "Disabled".
			add_filter( 'woocommerce_subscription_billing_period_ranges', array( __CLASS__, 'ame_subscription_billing_period_range_data' ) );

			// Process and save the necessary meta.
			add_filter( 'wcsatt_processed_scheme_data', array( __CLASS__, 'process_scheme_sync_data' ), 10, 2 );
		}

		// Remember to set sync meta when setting a subscription scheme on a product object.
		add_action( 'wcsatt_set_product_subscription_scheme', array( __CLASS__, 'set_product_subscription_scheme_sync_date' ), 0, 3 );
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
						'options'     => WC_Subscriptions_Synchroniser::get_billing_period_ranges( $subscription_period ),
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
	public static function ame_subscription_billing_period_range_data( $range_data ) {

		foreach ( $range_data as $key => $data ) {
			$range_data[ $key ][ 0 ] = __( 'Disabled', 'woocommerce-subscribe-all-the-things' );
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
	 * Set subscription payment sync data on product objects.
	 *
	 * @param string      $scheme_key
	 * @param string      $active_scheme_key
	 * @param WC_Product  $product
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
