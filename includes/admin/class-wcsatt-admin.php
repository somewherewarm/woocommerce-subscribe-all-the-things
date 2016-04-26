<?php
/**
 * Cart functionality for converting cart items to subscriptions.
 *
 * @class 	WCS_ATT_Admin
 * @version 1.0.4
 */

class WCS_ATT_Admin {

	public static function init() {

		// Admin scripts and styles
		add_action( 'admin_enqueue_scripts', __CLASS__ . '::admin_scripts' );

		// Ajax add subscription scheme
		add_action( 'wp_ajax_wcsatt_add_subscription_scheme', __CLASS__ . '::ajax_add_subscription_scheme' );

		// Ajax add subscription scheme for a variation
		add_action( 'wp_ajax_wcsatt_add_variation_subscription_scheme', __CLASS__ . '::ajax_add_variation_subscription_scheme' );

		// Subscription scheme markup added on the 'wcsatt_subscription_scheme' action
		add_action( 'wcsatt_subscription_scheme',  __CLASS__ . '::subscription_scheme', 10, 3 );

		// Subscription scheme markup added on the 'wcsatt_variable_subscription_scheme' action
		add_action( 'wcsatt_variable_subscription_scheme',  __CLASS__ . '::variable_subscription_scheme', 10, 4 );

		// Subscription scheme options displayed on the 'wcsatt_subscription_scheme_content' action
		add_action( 'wcsatt_subscription_scheme_content',  __CLASS__ . '::subscription_scheme_content', 10, 3 );

		// Subscription scheme options displayed on the 'wcsatt_variable_subscription_scheme_content' action
		add_action( 'wcsatt_variable_subscription_scheme_content',  __CLASS__ . '::variable_subscription_scheme_content', 10, 4 );

		// Subscription scheme options displayed on the 'wcsatt_subscription_scheme_content' action
		add_action( 'wcsatt_subscription_scheme_product_content',  __CLASS__ . '::subscription_scheme_product_content', 10, 3 );

		// Subscription scheme options displayed on the 'wcsatt_variable_subscription_scheme_content' action
		add_action( 'wcsatt_variable_subscription_scheme_product_content',  __CLASS__ . '::variable_subscription_scheme_product_content', 10, 4 );

		// Add bulk edit actions for variable products
		add_action( 'woocommerce_variable_product_bulk_edit_actions', __CLASS__ . '::variable_product_bulk_edit_actions', 10 );

		// Save subscription meta when a subscribable product is changed via bulk edit
		add_action( 'woocommerce_product_bulk_edit_save', __CLASS__ . '::bulk_edit_save_variable_meta', 10, 1 );

		// Adds a bulk action to enable all variations to have a subscription scheme
		add_action( 'woocommerce_bulk_edit_variations', __CLASS__ . '::bulk_edit_variations_toggle_subscriptions', 10, 4 );

		// Add a checkbox option to enable subscription options for variable products
		add_action( 'woocommerce_variation_options', __CLASS__ . '::variable_is_subscribable', 10, 3 );

		// Subscription scheme options displayed on the 'woocommerce_product_after_variable_attributes' action
		add_action( 'woocommerce_product_after_variable_attributes', __CLASS__ . '::subscription_variable_options', 10, 3 );

		/**
		 * WC Product Metaboxes
		 */

		// Creates the admin panel tab
		add_action( 'woocommerce_product_write_panel_tabs', __CLASS__ . '::product_write_panel_tab' );

		// Creates the panel for configuring subscription options
		add_action( 'woocommerce_product_write_panels', __CLASS__ . '::product_write_panel' );

		// Processes and saves the necessary post meta
		add_action( 'woocommerce_process_product_meta', __CLASS__ . '::process_product_meta' );

		// Processes and saves the necessary post meta for a variable product
		//add_action( 'woocommerce_save_product_variation', __CLASS__ . '::process_variable_meta' );
		add_action( 'woocommerce_ajax_save_product_variations', __CLASS__ . '::process_variable_meta', 10, 1 );

		/**
		 * "Subscribe to Cart" settings
		 */

		// Append "Subscribe to Cart/Order" section in the Subscriptions settings tab
		add_filter( 'woocommerce_subscription_settings', __CLASS__ . '::cart_level_admin_settings' );

		// Save posted cart subscription scheme settings
		add_action( 'woocommerce_update_options_subscriptions', __CLASS__ . '::save_cart_level_settings' );

		// Display subscription scheme admin metaboxes in the "Subscribe to Cart/Order" section
		add_action( 'woocommerce_admin_field_subscription_schemes', __CLASS__ . '::subscription_schemes_content' );
	}

	/**
	 * Subscriptions schemes admin metaboxes.
	 *
	 * @param  array $values
	 * @return void
	 */
	public static function subscription_schemes_content( $values ) {

		$subscription_schemes = get_option( 'wcsatt_subscribe_to_cart_schemes', array(
			// Default to "every month" scheme
			array(
				'subscription_period_interval' => 1,
				'subscription_period'          => 'month',
				'subscription_length'          => 0,
				'id'                           => '1_month_0',
				'position'                     => 0,
			)
		) );

		?><tr valign="top">
			<th scope="row" class="titledesc"><?php echo esc_html( $values['title'] ) ?></th>
			<td class="forminp forminp-subscription_schemes_metaboxes">
				<p class="description"><?php echo esc_html( $values['desc'] ) ?></p>
				<div id="wcsatt_data" class="wc-metaboxes-wrapper">
					<div class="subscription_schemes wc-metaboxes ui-sortable" data-count=""><?php

						$i = 0;

						foreach ( $subscription_schemes as $subscription_scheme ) {
							do_action( 'wcsatt_subscription_scheme', $i, $subscription_scheme, '' );
							$i++;
						}

					?></div>
					<p class="toolbar">
						<button type="button" class="button add_subscription_scheme"><?php _e( 'Add Option', WCS_ATT::TEXT_DOMAIN ); ?></button>
					</p>
				</div>
			</td>
		</tr><?php
	}

	/**
	 * Append "Subscribe to Cart/Order" section in the Subscriptions settings tab.
	 *
	 * @param  array $settings
	 * @return array
	 */
	public static function cart_level_admin_settings( $settings ) {

		// Insert before miscellaneous settings
		$misc_section_start = wp_list_filter( $settings, array( 'id' => 'woocommerce_subscriptions_miscellaneous', 'type' => 'title' ) );

		$spliced_array = array_splice( $settings, key( $misc_section_start ), 0, array(
			array(
				'name' => __( 'Subscribe to Cart', WCS_ATT::TEXT_DOMAIN ),
				'type' => 'title',
				'desc' => '',
				'id'   => 'wcsatt_subscribe_to_cart_options',
			),

			array(
				'name' => __( 'Subscribe to Cart Options', WCS_ATT::TEXT_DOMAIN ),
				'desc' => __( 'Offer customers the following options for subscribing to the contents of their cart.', WCS_ATT::TEXT_DOMAIN ),
				'id'   => 'wcsatt_subscribe_to_cart_schemes',
				'type' => 'subscription_schemes',
			),

			array(
				'type' => 'sectionend',
				'id'   => 'wcsatt_subscribe_to_cart_options',
			),
		) );

		return $settings;
	}

	/**
	 * Save subscription options when the edit product page is submitted for a variable
	 * product type (or the bulk edit product is saved).
	 *
	 * @since  1.0.4
	 * @param  int  $post_id
	 * @return void
	 */
	public static function process_variable_meta( $post_id ) {
		if ( empty( $_POST['_wcsnonce_save_variations'] ) || ! wp_verify_nonce( $_POST['_wcsnonce_save_variations'], 'wcs_subscription_variations' ) ) {
			return;
		}

		if ( ! isset( $_REQUEST['variable_post_id'] ) ) {
			return;
		}

		$variable_post_ids = $_POST['variable_post_id'];

		$max_loop = max( array_keys( $variable_post_ids ) );

		// Save each variations details
		for ( $i = 0; $i <= $max_loop; $i ++ ) {

			if ( ! isset( $variable_post_ids[ $i ] ) ) {
				continue;
			}

			$variation_id = absint( $variable_post_ids[ $i ] );

			$variable_is_subscribable = isset( $_POST['variable_is_subscribable'][ $i ] ) ? $_POST['variable_is_subscribable'][ $i ] : array();
			$is_subscribable = isset( $variable_is_subscribable ) ? 'yes' : 'no';

			update_post_meta( $variation_id, '_subscribable', wc_clean( $is_subscribable ) );

			// If this variation is subscribable then save this variations subscription options.
			if ( $is_subscribable == 'yes' ) {

				// Save subscription scheme options.
				if ( isset( $_POST[ 'wcsatt_schemes' ][ $i ] ) ) {

					$posted_schemes = stripslashes_deep( $_POST[ 'wcsatt_schemes' ][ $i ] );
					$unique_schemes = array();

					foreach ( $posted_schemes as $posted_scheme ) {

						// Format subscription prices.
						if ( isset( $posted_scheme[ 'subscription_regular_price' ] ) ) {
							$posted_scheme[ 'subscription_regular_price' ] = ( $posted_scheme[ 'subscription_regular_price'] === '' ) ? '' : wc_format_decimal( $posted_scheme[ 'subscription_regular_price' ] );
						}

						if ( isset( $posted_scheme[ 'subscription_sale_price' ] ) ) {
							$posted_scheme[ 'subscription_sale_price' ] = ( $posted_scheme[ 'subscription_sale_price'] === '' ) ? '' : wc_format_decimal( $posted_scheme[ 'subscription_sale_price' ] );
						}

						if ( '' !== $posted_scheme[ 'subscription_sale_price' ] ) {
							$posted_scheme[ 'subscription_price' ] = $posted_scheme[ 'subscription_sale_price' ];
						} else {
							$posted_scheme[ 'subscription_price' ] = ( $posted_scheme[ 'subscription_regular_price' ] === '' ) ? '' : $posted_scheme[ 'subscription_regular_price' ];
						}

						// Format subscription discount.
						if ( isset( $posted_scheme[ 'subscription_discount' ] ) ) {

							if ( is_numeric( $posted_scheme[ 'subscription_discount' ] ) ) {

								$discount = (float) wc_format_decimal( $posted_scheme[ 'subscription_discount' ] );

								if ( $discount < 0 || $discount > 100 ) {

									WC_Admin_Meta_Boxes::add_error( __( 'Please enter positive subscription discount values, between 0-100.', WCS_ATT::TEXT_DOMAIN ) );
									$posted_scheme[ 'subscription_discount' ] = '';

								} else {
									$posted_scheme[ 'subscription_discount' ] = $discount;
								}
							} else {
								$posted_scheme[ 'subscription_discount' ] = '';
							}
						} else {
							$posted_scheme[ 'subscription_discount' ] = '';
						}

						// Validate price override method.
						if ( isset( $posted_scheme[ 'subscription_pricing_method' ] ) && $posted_scheme[ 'subscription_pricing_method' ] === 'override' ) {
							if ( $posted_scheme[ 'subscription_price' ] === '' && $posted_scheme[ 'subscription_regular_price' ] === '' ) {
								$posted_scheme[ 'subscription_pricing_method' ] = 'inherit';
							}
						} else {
							$posted_scheme[ 'subscription_pricing_method' ] = 'inherit';
						}

						// Construct scheme id.
						$scheme_id = $posted_scheme[ 'subscription_period_interval' ] . '_' . $posted_scheme[ 'subscription_period' ] . '_' . $posted_scheme[ 'subscription_length' ];

						$unique_schemes[ $scheme_id ]         = $posted_scheme;
						$unique_schemes[ $scheme_id ][ 'id' ] = $scheme_id;
					}

					update_post_meta( $variation_id, '_wcsatt_schemes', $unique_schemes );

				} else {
					delete_post_meta( $variation_id, '_wcsatt_schemes' );
				}

			} // END if subscribable

		} // END for each variation

	}

	/**
	 * Save subscription options.
	 *
	 * @param  int  $post_id
	 * @return void
	 */
	public static function process_product_meta( $post_id ) {

		// Get type.
		$product_type    = empty( $_POST[ 'product-type' ] ) ? 'simple' : sanitize_title( stripslashes( $_POST[ 'product-type' ] ) );
		$supported_types = WCS_ATT()->get_supported_product_types();

		if ( in_array( $product_type, $supported_types ) ) {

			// Save subscription scheme options.
			if ( isset( $_POST[ 'wcsatt_schemes' ] ) ) {

				$posted_schemes = stripslashes_deep( $_POST[ 'wcsatt_schemes' ] );
				$unique_schemes = array();

				foreach ( $posted_schemes as $posted_scheme ) {

					// Format subscription prices.
					if ( isset( $posted_scheme[ 'subscription_regular_price' ] ) ) {
						$posted_scheme[ 'subscription_regular_price' ] = ( $posted_scheme[ 'subscription_regular_price'] === '' ) ? '' : wc_format_decimal( $posted_scheme[ 'subscription_regular_price' ] );
					}

					if ( isset( $posted_scheme[ 'subscription_sale_price' ] ) ) {
						$posted_scheme[ 'subscription_sale_price' ] = ( $posted_scheme[ 'subscription_sale_price'] === '' ) ? '' : wc_format_decimal( $posted_scheme[ 'subscription_sale_price' ] );
					}

					if ( '' !== $posted_scheme[ 'subscription_sale_price' ] ) {
						$posted_scheme[ 'subscription_price' ] = $posted_scheme[ 'subscription_sale_price' ];
					} else {
						$posted_scheme[ 'subscription_price' ] = ( $posted_scheme[ 'subscription_regular_price' ] === '' ) ? '' : $posted_scheme[ 'subscription_regular_price' ];
					}

					// Format subscription discount.
					if ( isset( $posted_scheme[ 'subscription_discount' ] ) ) {

						if ( is_numeric( $posted_scheme[ 'subscription_discount' ] ) ) {

							$discount = (float) wc_format_decimal( $posted_scheme[ 'subscription_discount' ] );

							if ( $discount < 0 || $discount > 100 ) {

								WC_Admin_Meta_Boxes::add_error( __( 'Please enter positive subscription discount values, between 0-100.', WCS_ATT::TEXT_DOMAIN ) );
								$posted_scheme[ 'subscription_discount' ] = '';

							} else {
								$posted_scheme[ 'subscription_discount' ] = $discount;
							}
						} else {
							$posted_scheme[ 'subscription_discount' ] = '';
						}
					} else {
						$posted_scheme[ 'subscription_discount' ] = '';
					}

					// Validate price override method.
					if ( isset( $posted_scheme[ 'subscription_pricing_method' ] ) && $posted_scheme[ 'subscription_pricing_method' ] === 'override' ) {
						if ( $posted_scheme[ 'subscription_price' ] === '' && $posted_scheme[ 'subscription_regular_price' ] === '' ) {
							$posted_scheme[ 'subscription_pricing_method' ] = 'inherit';
						}
					} else {
						$posted_scheme[ 'subscription_pricing_method' ] = 'inherit';
					}

					// Construct scheme id.
					$scheme_id = $posted_scheme[ 'subscription_period_interval' ] . '_' . $posted_scheme[ 'subscription_period' ] . '_' . $posted_scheme[ 'subscription_length' ];

					$unique_schemes[ $scheme_id ]         = $posted_scheme;
					$unique_schemes[ $scheme_id ][ 'id' ] = $scheme_id;
				}

				update_post_meta( $post_id, '_wcsatt_schemes', $unique_schemes );

			} else {
				delete_post_meta( $post_id, '_wcsatt_schemes' );
			}

			// Save default status

			if ( isset( $_POST[ '_wcsatt_default_status' ] ) ) {
				update_post_meta( $post_id, '_wcsatt_default_status', stripslashes( $_POST[ '_wcsatt_default_status' ] ) );
			}

			// Save one-time status

			$force_subscription = isset( $_POST[ '_wcsatt_force_subscription' ] ) ? 'yes' : 'no';

			update_post_meta( $post_id, '_wcsatt_force_subscription', $force_subscription );

			// Save prompt

			if ( ! empty( $_POST[ '_wcsatt_subscription_prompt' ] ) ) {
				$prompt = wp_kses_post( stripslashes( $_POST[ '_wcsatt_subscription_prompt' ] ) );
				update_post_meta( $post_id, '_wcsatt_subscription_prompt', $prompt );
			} else {
				delete_post_meta( $post_id, '_wcsatt_subscription_prompt' );
			}

		} else {

			delete_post_meta( $post_id, '_wcsatt_schemes' );
			delete_post_meta( $post_id, '_wcsatt_force_subscription' );
			delete_post_meta( $post_id, '_wcsatt_default_status' );
			delete_post_meta( $post_id, '_wcsatt_subscription_prompt' );
		}
	}

	/**
	 * Save subscription scheme option from the WooCommerce > Settings > Subscriptions administration screen.
	 *
	 * @param  int  $post_id
	 * @return void
	 */
	public static function save_cart_level_settings() {

		if ( isset( $_POST[ 'wcsatt_schemes' ] ) ) {
			$posted_schemes = $_POST[ 'wcsatt_schemes' ];
		} else {
			$posted_schemes = array();
		}

		$posted_schemes = stripslashes_deep( $posted_schemes );
		$unique_schemes = array();

		foreach ( $posted_schemes as $posted_scheme ) {

			// Construct scheme id.
			$scheme_id = $posted_scheme[ 'subscription_period_interval' ] . '_' . $posted_scheme[ 'subscription_period' ] . '_' . $posted_scheme[ 'subscription_length' ];

			$unique_schemes[ $scheme_id ]         = $posted_scheme;
			$unique_schemes[ $scheme_id ][ 'id' ] = $scheme_id;
		}

		update_option( 'wcsatt_subscribe_to_cart_schemes', $unique_schemes );
	}

	/**
	 * Subscription scheme markup added on the 'wcsatt_subscription_scheme' action.
	 *
	 * @param  int     $index
	 * @param  array   $scheme_data
	 * @param  int     $post_id
	 * @return void
	 */
	public static function subscription_scheme( $index, $scheme_data, $post_id ) {
		include( 'views/subscription-scheme.php' );
	}

	/**
	 * Subscription scheme markup added on the 'wcsatt_subscription_scheme' action.
	 *
	 * @param  int     $loop
	 * @param  int     $index
	 * @param  array   $scheme_data
	 * @param  int     $variation_id
	 * @return void
	 */
	public static function variable_subscription_scheme( $loop, $index, $scheme_data, $variation_id ) {
		include( 'views/variable-subscription-scheme.php' );
	}

	/**
	 * Subscription scheme options displayed on the 'wcsatt_subscription_scheme_content' action.
	 *
	 * @param  int     $index
	 * @param  array   $scheme_data
	 * @param  int     $post_id
	 * @return void
	 */
	public static function subscription_scheme_content( $index, $scheme_data, $post_id ) {

		global $thepostid;

		if ( empty( $thepostid ) ) {
			$thepostid = '-1';
		}

		if ( ! empty( $scheme_data ) ) {
			$subscription_period          = $scheme_data[ 'subscription_period' ];
			$subscription_period_interval = $scheme_data[ 'subscription_period_interval' ];
			$subscription_length          = $scheme_data[ 'subscription_length' ];
		} else {
			$subscription_period          = 'month';
			$subscription_period_interval = '';
			$subscription_length          = '';
		}

		// Subscription Period Interval
		woocommerce_wp_select( array(
			'id'      => '_subscription_period_interval',
			'class'   => 'wc_input_subscription_period_interval',
			'label'   => __( 'Subscription Periods', 'woocommerce-subscriptions' ),
			'value'   => $subscription_period_interval,
			'options' => wcs_get_subscription_period_interval_strings(),
			'name'    => 'wcsatt_schemes[' . $index . '][subscription_period_interval]'
			)
		);

		// Billing Period
		woocommerce_wp_select( array(
			'id'          => '_subscription_period',
			'class'       => 'wc_input_subscription_period',
			'label'       => __( 'Billing Period', 'woocommerce-subscriptions' ),
			'value'       => $subscription_period,
			'description' => _x( 'for', 'for in "Every month _for_ 12 months"', 'woocommerce-subscriptions' ),
			'options'     => wcs_get_subscription_period_strings(),
			'name'        => 'wcsatt_schemes[' . $index . '][subscription_period]'
			)
		);

		// Subscription Length
		woocommerce_wp_select( array(
			'id'      => '_subscription_length',
			'class'   => 'wc_input_subscription_length',
			'label'   => __( 'Subscription Length', 'woocommerce-subscriptions' ),
			'value'   => $subscription_length,
			'options' => wcs_get_subscription_ranges( $subscription_period ),
			'name'    => 'wcsatt_schemes[' . $index . '][subscription_length]'
			)
		);
	}

	/**
	 * Subscription scheme options displayed on the 'wcsatt_variable_subscription_scheme_content' action.
	 *
	 * @param  int     $loop
	 * @param  int     $index
	 * @param  array   $scheme_data
	 * @param  int     $variable_id
	 * @return void
	 */
	public static function variable_subscription_scheme_content( $loop, $index, $scheme_data, $variable_id ) {

		if ( ! empty( $scheme_data ) ) {
			$subscription_period          = $scheme_data[ 'subscription_period' ];
			$subscription_period_interval = $scheme_data[ 'subscription_period_interval' ];
			$subscription_length          = $scheme_data[ 'subscription_length' ];
		} else {
			$subscription_period          = 'month';
			$subscription_period_interval = '';
			$subscription_length          = '';
		}

		// Subscription Period Interval
		woocommerce_wp_select( array(
			'id'      => '_subscription_period_interval',
			'class'   => 'wc_input_subscription_period_interval',
			'label'   => __( 'Subscription Periods', 'woocommerce-subscriptions' ),
			'value'   => $subscription_period_interval,
			'options' => wcs_get_subscription_period_interval_strings(),
			'name'    => 'wcsatt_schemes[' . $loop . '][' . $index . '][subscription_period_interval]'
			)
		);

		// Billing Period
		woocommerce_wp_select( array(
			'id'          => '_subscription_period',
			'class'       => 'wc_input_subscription_period',
			'label'       => __( 'Billing Period', 'woocommerce-subscriptions' ),
			'value'       => $subscription_period,
			'description' => _x( 'for', 'for in "Every month _for_ 12 months"', 'woocommerce-subscriptions' ),
			'options'     => wcs_get_subscription_period_strings(),
			'name'        => 'wcsatt_schemes[' . $loop . '][' . $index . '][subscription_period]'
			)
		);

		// Subscription Length
		woocommerce_wp_select( array(
			'id'      => '_subscription_length',
			'class'   => 'wc_input_subscription_length',
			'label'   => __( 'Subscription Length', 'woocommerce-subscriptions' ),
			'value'   => $subscription_length,
			'options' => wcs_get_subscription_ranges( $subscription_period ),
			'name'    => 'wcsatt_schemes[' . $loop . '][' . $index . '][subscription_length]'
			)
		);
	}

	/**
	 * Subscription scheme options displayed on the 'wcsatt_subscription_scheme_content' action.
	 *
	 * @param  int     $index
	 * @param  array   $scheme_data
	 * @param  int     $post_id
	 * @return void
	 */
	public static function subscription_scheme_product_content( $index, $scheme_data, $post_id ) {

		if ( ! empty( $scheme_data ) ) {
			$subscription_pricing_method = ! empty( $scheme_data[ 'subscription_pricing_method' ] ) ? $scheme_data[ 'subscription_pricing_method' ] : 'inherit';
			$subscription_regular_price  = isset( $scheme_data[ 'subscription_regular_price' ] ) ? $scheme_data[ 'subscription_regular_price' ] : '';
			$subscription_sale_price     = isset( $scheme_data[ 'subscription_sale_price' ] ) ? $scheme_data[ 'subscription_sale_price' ] : '';
			$subscription_discount       = isset( $scheme_data[ 'subscription_discount' ] ) ? $scheme_data[ 'subscription_discount' ] : '';
		} else {
			$subscription_pricing_method = '';
			$subscription_regular_price  = '';
			$subscription_sale_price     = '';
			$subscription_discount       = '';
		}

		// Subscription Price Override Method
		woocommerce_wp_select( array(
			'id'      => '_subscription_pricing_method_input',
			'class'   => 'subscription_pricing_method_input',
			'label'   => __( 'Subscription Price', WCS_ATT::TEXT_DOMAIN ),
			'value'   => $subscription_pricing_method,
			'options' => array(
					'inherit'  => __( 'Inherit from product', WCS_ATT::TEXT_DOMAIN ),
					'override' => __( 'Override product', WCS_ATT::TEXT_DOMAIN ),
				),
			'name'    => 'wcsatt_schemes[' . $index . '][subscription_pricing_method]'
			)
		);

		?><div class="subscription_pricing_method subscription_pricing_method_override"><?php
			// Price.
			woocommerce_wp_text_input( array(
				'id'            => '_override_subscription_regular_price',
				'name'          => 'wcsatt_schemes[' . $index . '][subscription_regular_price]',
				'value'         => $subscription_regular_price,
				'wrapper_class' => 'override_subscription_regular_price',
				'class'         => 'short',
				'label'         => __( 'Regular Price', 'woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')',
				'data_type'     => 'price'
			) );
			// Sale Price.
			woocommerce_wp_text_input( array(
				'id'            => '_override_subscription_sale_price',
				'name'          => 'wcsatt_schemes[' . $index . '][subscription_sale_price]',
				'value'         => $subscription_sale_price,
				'wrapper_class' => 'override_subscription_sale_price',
				'class'         => 'short',
				'label'         => __( 'Sale Price', 'woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')',
				'data_type'     => 'price'
			) );
		?></div>
		<div class="subscription_pricing_method subscription_pricing_method_inherit"><?php
			// Discount.
			woocommerce_wp_text_input( array(
				'id'            => '_subscription_price_discount',
				'name'          => 'wcsatt_schemes[' . $index . '][subscription_discount]',
				'value'         => $subscription_discount,
				'wrapper_class' => 'subscription_price_discount',
				'class'         => 'short',
				'label'         => __( 'Discount %', WCS_ATT::TEXT_DOMAIN ),
				'data_type'     => 'decimal'
			) );
		?></div><?php
	}

	/**
	 * Subscription scheme options displayed on the 'wcsatt_variable_subscription_scheme_content' action.
	 *
	 * @param  int     $loop
	 * @param  int     $index
	 * @param  array   $scheme_data
	 * @param  int     $variable_id
	 * @return void
	 */
	public static function variable_subscription_scheme_product_content( $loop, $index, $scheme_data, $variable_id ) {

		if ( ! empty( $scheme_data ) ) {
			$subscription_pricing_method = ! empty( $scheme_data[ 'subscription_pricing_method' ] ) ? $scheme_data[ 'subscription_pricing_method' ] : 'inherit';
			$subscription_regular_price  = isset( $scheme_data[ 'subscription_regular_price' ] ) ? $scheme_data[ 'subscription_regular_price' ] : '';
			$subscription_sale_price     = isset( $scheme_data[ 'subscription_sale_price' ] ) ? $scheme_data[ 'subscription_sale_price' ] : '';
			$subscription_discount       = isset( $scheme_data[ 'subscription_discount' ] ) ? $scheme_data[ 'subscription_discount' ] : '';
		} else {
			$subscription_pricing_method = '';
			$subscription_regular_price  = '';
			$subscription_sale_price     = '';
			$subscription_discount       = '';
		}

		// Subscription Price Override Method
		woocommerce_wp_select( array(
			'id'      => '_subscription_pricing_method_input',
			'class'   => 'subscription_pricing_method_input',
			'label'   => __( 'Subscription Price', WCS_ATT::TEXT_DOMAIN ),
			'value'   => $subscription_pricing_method,
			'options' => array(
					'inherit'  => __( 'Inherit from product', WCS_ATT::TEXT_DOMAIN ),
					'override' => __( 'Override product', WCS_ATT::TEXT_DOMAIN ),
				),
			'name'    => 'wcsatt_schemes[' . $loop . '][' . $index . '][subscription_pricing_method]'
			)
		);

		?><div class="subscription_pricing_method subscription_pricing_method_override"><?php
			// Price.
			woocommerce_wp_text_input( array(
				'id'            => '_override_subscription_regular_price',
				'name'          => 'wcsatt_schemes[' . $loop . '][' . $index . '][subscription_regular_price]',
				'value'         => $subscription_regular_price,
				'wrapper_class' => 'override_subscription_regular_price',
				'class'         => 'short',
				'label'         => __( 'Regular Price', 'woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')',
				'data_type'     => 'price'
			) );
			// Sale Price.
			woocommerce_wp_text_input( array(
				'id'            => '_override_subscription_sale_price',
				'name'          => 'wcsatt_schemes[' . $loop . '][' . $index . '][subscription_sale_price]',
				'value'         => $subscription_sale_price,
				'wrapper_class' => 'override_subscription_sale_price',
				'class'         => 'short',
				'label'         => __( 'Sale Price', 'woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')',
				'data_type'     => 'price'
			) );
		?></div>
		<div class="subscription_pricing_method subscription_pricing_method_inherit"><?php
			// Discount.
			woocommerce_wp_text_input( array(
				'id'            => '_subscription_price_discount',
				'name'          => 'wcsatt_schemes[' . $loop . '][' . $index . '][subscription_discount]',
				'value'         => $subscription_discount,
				'wrapper_class' => 'subscription_price_discount',
				'class'         => 'short',
				'label'         => __( 'Discount %', WCS_ATT::TEXT_DOMAIN ),
				'data_type'     => 'decimal'
			) );
		?></div><?php
	}

	/**
	 * Add subscription schemes via ajax.
	 *
	 * @return void
	 */
	public static function ajax_add_subscription_scheme() {

		check_ajax_referer( 'wcsatt_add_subscription_scheme', 'security' );

		$index   = intval( $_POST[ 'index' ] );
		$post_id = intval( $_POST[ 'post_id' ] );

		ob_start();

		if ( $index >= 0 ) {

			$result = 'success';

			if ( empty( $post_id ) ) {
				$post_id = '';
			}

			do_action( 'wcsatt_subscription_scheme', $index, array(), $post_id );

		} else {
			$result = 'failure';
		}

		$output = ob_get_clean();

		header( 'Content-Type: application/json; charset=utf-8' );

		echo json_encode( array(
			'result' => $result,
			'markup' => $output
		) );

		die();

	}

	/**
	 * Add subscription schemes via ajax for a variation.
	 *
	 * @return void
	 */
	public static function ajax_add_variation_subscription_scheme() {

		check_ajax_referer( 'wcsatt_add_subscription_scheme', 'security' );

		$loop    = intval( $_POST[ 'loop'] );
		$index   = intval( $_POST[ 'index' ] );
		$post_id = intval( $_POST[ 'post_id' ] );

		ob_start();

		if ( $index >= 0 ) {

			$result = 'success';

			if ( empty( $post_id ) ) {
				$post_id = '';
			}

			do_action( 'wcsatt_variable_subscription_scheme', $loop, $index, array(), $post_id );

		} else {
			$result = 'failure';
		}

		$output = ob_get_clean();

		header( 'Content-Type: application/json; charset=utf-8' );

		echo json_encode( array(
			'result' => $result,
			'markup' => $output
		) );

		die();

	}

	/**
	 * Load scripts and styles.
	 *
	 * @return void
	 */
	public static function admin_scripts() {

		global $post;

		// Get admin screen id
		$screen      = get_current_screen();
		$screen_id   = $screen ? $screen->id : '';

		$add_scripts = false;
		$suffix      = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		if ( in_array( $screen_id, array( 'edit-product', 'product' ) ) ) {
			$add_scripts = true;
			$writepanel_dependencies = array( 'jquery', 'jquery-ui-datepicker', 'wc-admin-meta-boxes', 'wc-admin-product-meta-boxes' );
		} elseif ( $screen_id === 'woocommerce_page_wc-settings' && isset( $_GET[ 'tab' ] ) && $_GET[ 'tab' ] === 'subscriptions' ) {
			$add_scripts = true;
			$writepanel_dependencies = array( 'jquery', 'jquery-ui-datepicker' );
		}

		if ( $add_scripts ) {
			wp_register_script( 'wcsatt_writepanel', WCS_ATT()->plugin_url() . '/assets/js/wcsatt-write-panels' . $suffix . '.js', $writepanel_dependencies, WCS_ATT::VERSION );
			wp_register_script( 'wcsatt_writepanel_variable', WCS_ATT()->plugin_url() . '/assets/js/wcsatt-write-panels-variable.js', $writepanel_dependencies, WCS_ATT::VERSION );
			wp_register_style( 'wcsatt_writepanel_css', WCS_ATT()->plugin_url() . '/assets/css/wcsatt-write-panels.css', array( 'woocommerce_admin_styles' ), WCS_ATT::VERSION );
			wp_enqueue_style( 'wcsatt_writepanel_css' );
		}

		// WooCommerce admin pages
		if ( in_array( $screen_id, array( 'product', 'woocommerce_page_wc-settings' ) ) ) {

			wp_enqueue_script( 'wcsatt_writepanel' );
			wp_enqueue_script( 'wcsatt_writepanel_variable' );

			$params = array(
				'add_subscription_scheme_nonce'   => wp_create_nonce( 'wcsatt_add_subscription_scheme' ),
				'subscription_lengths'            => wcs_get_subscription_ranges(),
				'wc_ajax_url'                     => admin_url( 'admin-ajax.php' ),
				'post_id'                         => is_object( $post ) ? $post->ID : '',
				'wc_plugin_url'                   => WC()->plugin_url(),
				'i18n_remove_subscription_scheme' => __( 'Are you sure you want to remove this subscription option?', WCS_ATT::TEXT_DOMAIN )
			);

			wp_localize_script( 'wcsatt_writepanel', 'wcsatt_admin_params', $params );
		}
	}

	/**
	 * Cart Subs writepanel tab.
	 *
	 * @return void
	 */
	public static function product_write_panel_tab() {

		?><li class="cart_subscription_options cart_subscriptions_tab show_if_simple show_if_variable show_if_bundle hide_if_subscription hide_if_variable-subscription">
			<a href="#wcsatt_data"><?php _e( 'Subscriptions', WCS_ATT::VERSION ); ?></a>
		</li><?php
	}

	/**
	 * Product writepanel for Subscriptions.
	 *
	 * @return void
	 */
	public static function product_write_panel() {

		global $post;

		if ( $terms = wp_get_object_terms( $post->ID, 'product_type' ) ) {
			$product_type = sanitize_title( current( $terms )->name );
		} else {
			$product_type = 'simple';
		}

		$subscription_schemes = '';

		if ( $product_type == 'simple' ) $subscription_schemes = get_post_meta( $post->ID, '_wcsatt_schemes', true );

		?><div id="wcsatt_data" class="panel woocommerce_options_panel wc-metaboxes-wrapper">
			<div class="options_group"><?php

				// Subscription Status
				woocommerce_wp_checkbox( array( 'id' => '_wcsatt_force_subscription', 'label' => __( 'Force subscription', WCS_ATT::TEXT_DOMAIN ), 'description' => __( 'Check this option to prevent one-time purchases', WCS_ATT::TEXT_DOMAIN ) ) );

				// Default Status
				woocommerce_wp_select( array( 'id' => '_wcsatt_default_status', 'wrapper_class'=> 'wcsatt_default_status', 'label' => __( 'Default to', WCS_ATT::TEXT_DOMAIN ), 'description' => '', 'options' => array(
					'one-time'     => __( 'One-time purchase', WCS_ATT::TEXT_DOMAIN ),
					'subscription' => __( 'Subscription', WCS_ATT::TEXT_DOMAIN ),
				) ) );

				// Subscription Prompt
				woocommerce_wp_textarea_input( array( 'id' => '_wcsatt_subscription_prompt', 'label' => __( 'Subscription prompt', WCS_ATT::TEXT_DOMAIN ), 'description' => __( 'Custom html/text to display before subscription options.', WCS_ATT::TEXT_DOMAIN ), 'desc_tip' => true ) );

			?></div>

			<div class="options_group hide_if_variable">
				<p class="form-field"><label><?php _e( 'Subscription Options', WCS_ATT::TEXT_DOMAIN ); ?> <?php echo wc_help_tip( __( 'Add one or more subscription options for this product.', WCS_ATT::TEXT_DOMAIN ) ); ?></label>
					<button type="button" class="button button-primary add_subscription_scheme"><?php _e( 'Add Option', WCS_ATT::TEXT_DOMAIN ); ?></button>
				</p>
				<div class="subscription_schemes wc-metaboxes ui-sortable" data-count=""><?php

					if ( $subscription_schemes ) {

						$i = 0;

						foreach ( $subscription_schemes as $subscription_scheme ) {
							do_action( 'wcsatt_subscription_scheme', $i, $subscription_scheme, $post->ID );
							$i++;
						}
					}

				?></div>

			</div>
		</div><?php
	}

	/**
	 * Bulk edit option - Adds an option to the bulk edit menu.
	 *
	 * @since  1.0.4
	 * @access public
	 */
	public static function variable_product_bulk_edit_actions() {
		?>
		<optgroup label="<?php esc_attr_e( 'Subscribe to all the Things', WCS_ATT::TEXT_DOMAIN ); ?>">
			<option value="toggle_subscribable"><?php _e( 'Toggle &quot;Subscribable&quot;', WCS_ATT::TEXT_DOMAIN ); ?></option>
		</optgroup>
		<?php
	}

	/**
	 * Save a variation to be Subscribable when edited via the bulk edit.
	 *
	 * @param  object $product An instance of a WC_Product_* object.
	 * @return null
	 * @since  1.0.4
	 */
	public static function bulk_edit_save_variable_meta( $product ) {
		$variable_is_subscribable = isset( $_REQUEST['variable_is_subscribable'] ) ? $_REQUEST['variable_is_subscribable'] : array();
		$is_subscribable = isset( $variable_is_subscribable[ $product->id ] ) ? 'yes' : 'no';

		update_post_meta( $product->id, '_subscribable', wc_clean( $is_subscribable ) );
	}

	/**
	 * Bulk action - Toggle Subscribable Checkbox.
	 *
	 * @since  1.0.4
	 * @access private
	 * @param  array $variations
	 * @param  array $data
	 */
	private static function bulk_edit_variations_toggle_subscriptions( $bulk_action, $data, $product_id, $variations ) {
		echo 'Bulk Action: '. $bulk_action . '<br>';
		echo 'Data: '. $data . '<br>';
		echo 'Product ID: ' . $product_id . '<br>';
		echo 'Variations: <code>'; print_r($variations); echo '</code><br>';

		foreach ( $variations as $variation_id ) {
			echo 'Variation ID: ' . $variation_id . '<br>';

			$_subscribable   = get_post_meta( $variation_id, '_subscribable', true );
			$is_subscribable = 'no' === $_subscribable ? 'yes' : 'no';
			update_post_meta( $variation_id, '_subscribable', wc_clean( $is_subscribable ) );
		}

	}

	/**
	 * Subscription options added on the 'woocommerce_variation_options' action.
	 *
	 * @since  1.0.4
	 * @param  int     $loop
	 * @param  array   $variation_data
	 * @param  WP_Post $variation
	 * @return void
	 */
	public static function variable_is_subscribable( $loop, $variation_data, $variation ) {
		?>
		<label>
			<input type="checkbox" class="checkbox variable_is_subscribable" name="variable_is_subscribable[<?php echo $loop; ?>]" <?php checked( self::is_subscribable( $variation->ID ), 1 ); ?> /> <?php _e( 'Subscribable', WCS_ATT::TEXT_DOMAIN ); ?> <?php echo wc_help_tip( __( 'Enable this option if this variable is a subscribable variation', WCS_ATT::TEXT_DOMAIN ) ); ?>
		</label>
		<?php
	}

	/**
	 * Checks if a product is subscribable.
	 *
	 * @since  1.0.4
	 * @access public
	 * @param  int $post_id
	 * @return bool
	 */
	public static function is_subscribable( $post_id ) {
		$is_subscribable = get_post_meta( $post_id, '_subscribable', true );

		if ( isset( $is_subscribable ) && $is_subscribable == 'yes' ) {
			return true;
		}

		return false;
	}

	/**
	 * Subscription options added on the 'woocommerce_product_after_variable_attributes' action.
	 *
	 * @since  1.0.4
	 * @param  int     $loop
	 * @param  array   $variation_data
	 * @param  WP_Post $variation
	 * @return void
	 */
	public static function subscription_variable_options( $loop, $variation_data, $variation ) {
		include( 'views/subscription-options.php' );
	}
}

WCS_ATT_Admin::init();
