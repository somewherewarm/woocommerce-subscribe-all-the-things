<?php
/**
 * Cart functionality for converting cart items to subscriptions.
 *
 * @class  WCS_ATT_Admin
 * @since  1.0.4
 */

class WCS_ATT_Admin {

	public static function init() {

		// Admin scripts and styles.
		add_action( 'admin_enqueue_scripts', __CLASS__ . '::admin_scripts' );

		// Ajax add subscription scheme.
		add_action( 'wp_ajax_wcsatt_add_subscription_scheme', __CLASS__ . '::ajax_add_subscription_scheme' );

		// Subscription scheme markup added on the 'wcsatt_subscription_scheme' action.
		add_action( 'wcsatt_subscription_scheme',  __CLASS__ . '::subscription_scheme', 10, 3 );

		// Subscription scheme options displayed on the 'wcsatt_subscription_scheme_content' action.
		add_action( 'wcsatt_subscription_scheme_content',  __CLASS__ . '::subscription_scheme_content', 10, 3 );

		// Subscription scheme options displayed on the 'wcsatt_subscription_scheme_content' action.
		add_action( 'wcsatt_subscription_scheme_product_content',  __CLASS__ . '::subscription_scheme_product_content', 10, 3 );

		/*
		 * WC Product Metaboxes.
		 */

		// Creates the admin panel tab.
		add_action( 'woocommerce_product_write_panel_tabs', __CLASS__ . '::product_write_panel_tab' );

		// Creates the panel for configuring subscription options.
		add_action( 'woocommerce_product_write_panels', __CLASS__ . '::product_write_panel' );

		// Processes and saves the necessary post meta.
		add_action( 'woocommerce_process_product_meta', __CLASS__ . '::process_product_meta', 15, 1 );

		/*
		 * "Subscribe to Cart" settings.
		 */

		// Append "Subscribe to Cart/Order" section in the Subscriptions settings tab.
		add_filter( 'woocommerce_subscription_settings', __CLASS__ . '::cart_level_admin_settings' );

		// Save posted cart subscription scheme settings.
		add_action( 'woocommerce_update_options_subscriptions', __CLASS__ . '::save_cart_level_settings' );

		// Display subscription scheme admin metaboxes in the "Subscribe to Cart/Order" section.
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

			// Default to "every month" scheme.
			apply_filters( 'wcsatt_default_subscription_scheme', array(
				'subscription_period_interval' => 1,
				'subscription_period'          => 'month',
				'subscription_length'          => 0,
				'id'                           => '1_month_0',
				'position'                     => 0,
			) )
		) );

		?><tr valign="top">
			<th scope="row" class="titledesc"><?php echo esc_html( $values[ 'title' ] ) ?></th>
			<td class="forminp forminp-subscription_schemes_metaboxes">
				<p class="description"><?php echo esc_html( $values[ 'desc' ] ) ?></p>
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

		// Insert before miscellaneous settings.
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

			// Save one time shipping option.
			update_post_meta( $post_id, '_subscription_one_time_shipping', stripslashes( isset( $_POST['_subscription_one_time_shipping'] ) ? 'yes' : 'no' ) );

			// Save subscription scheme options.
			if ( isset( $_POST[ 'wcsatt_schemes' ] ) ) {

				$posted_schemes = stripslashes_deep( $_POST[ 'wcsatt_schemes' ] );
				$unique_schemes = array();

				foreach ( $posted_schemes as $posted_scheme ) {

					// Copy variable type fields.
					if ( 'variable' === $product_type ) {
						if ( isset( $posted_scheme[ 'subscription_regular_price_variable' ] ) ) {
							$posted_scheme[ 'subscription_regular_price' ] = $posted_scheme[ 'subscription_regular_price_variable' ];
						}
						if ( isset( $posted_scheme[ 'subscription_sale_price_variable' ] ) ) {
							$posted_scheme[ 'subscription_sale_price' ] = $posted_scheme[ 'subscription_sale_price_variable' ];
						}
						if ( isset( $posted_scheme[ 'subscription_discount_variable' ] ) ) {
							$posted_scheme[ 'subscription_discount' ] = $posted_scheme[ 'subscription_discount_variable' ];
						}
						if ( isset( $posted_scheme[ 'subscription_pricing_method_variable' ] ) ) {
							$posted_scheme[ 'subscription_pricing_method' ] = $posted_scheme[ 'subscription_pricing_method_variable' ];
						}
					}

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

			// Save default status.

			if ( isset( $_POST[ '_wcsatt_default_status' ] ) ) {
				update_post_meta( $post_id, '_wcsatt_default_status', stripslashes( $_POST[ '_wcsatt_default_status' ] ) );
			}

			// Save one-time status.

			$force_subscription = isset( $_POST[ '_wcsatt_force_subscription' ] ) ? 'yes' : 'no';

			update_post_meta( $post_id, '_wcsatt_force_subscription', $force_subscription );

			// Set regular price as ZERO should the shop owner forget.
			// This helps make WooCommerce think it's still available for purchase.
			if ( 'yes' === $force_subscription && empty( $_POST[ '_regular_price' ] ) ) {
				update_post_meta( $post_id, '_regular_price', wc_format_decimal( 0 ) );
				update_post_meta( $post_id, '_price', wc_format_decimal( 0 ) );
			}

			// Save prompt.

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
	 * Subscription scheme markup adeed on the 'wcsatt_subscription_scheme' action.
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


		// Subscription Price, Interval and Period
		?><p class="form-field _satt_subscription_details">
			<label for="_satt_subscription_details"><?php esc_html_e( 'Interval', WCS_ATT::TEXT_DOMAIN ); ?></label>
			<span class="wrap">
				<label for="_satt_subscription_period_interval" class="wcs_hidden_label"><?php esc_html_e( 'Subscription interval', 'woocommerce-subscriptions' ); ?></label>
				<select id="_satt_subscription_period_interval" name="wcsatt_schemes[<?php echo $index; ?>][subscription_period_interval]" class="wc_input_subscription_period_interval">
				<?php foreach ( wcs_get_subscription_period_interval_strings() as $value => $label ) { ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $subscription_period_interval, true ) ?>><?php echo esc_html( $label ); ?></option>
				<?php } ?>
				</select>
				<label for="_satt_subscription_period" class="wcs_hidden_label"><?php esc_html_e( 'Subscription period', 'woocommerce-subscriptions' ); ?></label>
				<select id="_satt_subscription_period" name="wcsatt_schemes[<?php echo $index; ?>][subscription_period]" class="wc_input_subscription_period last" >
				<?php foreach ( wcs_get_subscription_period_strings() as $value => $label ) { ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $subscription_period, true ) ?>><?php echo esc_html( $label ); ?></option>
				<?php } ?>
				</select>
			</span>
			<?php echo WCS_ATT_Core_Compatibility::wc_help_tip( __( 'Choose the subscription billing interval and period.', WCS_ATT::TEXT_DOMAIN ) ); ?>
		</p><?php

		// Subscription Length
		woocommerce_wp_select( array(
			'id'          => '_satt_subscription_length',
			'class'       => 'wc_input_subscription_length',
			'label'       => __( 'Length', WCS_ATT::TEXT_DOMAIN ),
			'value'       => $subscription_length,
			'options'     => wcs_get_subscription_ranges( $subscription_period ),
			'name'        => 'wcsatt_schemes[' . $index . '][subscription_length]',
			'description' => __( 'Choose the subscription billing length.', WCS_ATT::TEXT_DOMAIN ),
			'desc_tip'    => true
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

		?><div class="hide_if_variable"><?php

			// Subscription Price Override Method.
			woocommerce_wp_select( array(
				'id'      => '_subscription_pricing_method_input',
				'class'   => 'subscription_pricing_method_input',
				'label'   => __( 'Price', WCS_ATT::TEXT_DOMAIN ),
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
					'description'   => __( 'Discount applied on the <strong>Regular Price</strong> of the product.', WCS_ATT::TEXT_DOMAIN ),
					'desc_tip'      => true,
					'data_type'     => 'decimal'
				) );

			?></div>
		</div>
		<div class="show_if_variable" style="display:none"><?php

			// Subscription Price Override Method.
			woocommerce_wp_select( array(
				'id'      => '_subscription_pricing_method_input_variable',
				'class'   => 'subscription_pricing_method_input',
				'label'   => __( 'Price', WCS_ATT::TEXT_DOMAIN ),
				'value'   => $subscription_pricing_method,
				'options' => array(
						'inherit'  => __( 'Inherit from chosen variation', WCS_ATT::TEXT_DOMAIN ),
						'override' => __( 'Override all variations', WCS_ATT::TEXT_DOMAIN ),
					),
				'name'    => 'wcsatt_schemes[' . $index . '][subscription_pricing_method_variable]'
				)
			);

			?><div class="subscription_pricing_method subscription_pricing_method_override"><?php

				// Price.
				woocommerce_wp_text_input( array(
					'id'            => '_override_subscription_regular_price_variable',
					'name'          => 'wcsatt_schemes[' . $index . '][subscription_regular_price_variable]',
					'value'         => $subscription_regular_price,
					'wrapper_class' => 'override_subscription_regular_price',
					'class'         => 'short',
					'label'         => __( 'Regular Price', 'woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')',
					'data_type'     => 'price'
				) );

				// Sale Price.
				woocommerce_wp_text_input( array(
					'id'            => '_override_subscription_sale_price_variable',
					'name'          => 'wcsatt_schemes[' . $index . '][subscription_sale_price_variable]',
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
					'id'            => '_subscription_price_discount_variable',
					'name'          => 'wcsatt_schemes[' . $index . '][subscription_discount_variable]',
					'value'         => $subscription_discount,
					'wrapper_class' => 'subscription_price_discount',
					'class'         => 'short',
					'label'         => __( 'Discount %', WCS_ATT::TEXT_DOMAIN ),
					'description'   => __( 'Discount applied on the <strong>Regular Price</strong> of the chosen variation.', WCS_ATT::TEXT_DOMAIN ),
					'desc_tip'      => true,
					'data_type'     => 'decimal'
				) );

			?></div>
		</div><?php
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
	 * Load scripts and styles.
	 *
	 * @return void
	 */
	public static function admin_scripts() {

		global $post;

		// Get admin screen id.
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
			wp_register_style( 'wcsatt_writepanel_css', WCS_ATT()->plugin_url() . '/assets/css/wcsatt-write-panels.css', array( 'woocommerce_admin_styles' ), WCS_ATT::VERSION );
			wp_enqueue_style( 'wcsatt_writepanel_css' );
		}

		// WooCommerce admin pages.
		if ( in_array( $screen_id, array( 'product', 'woocommerce_page_wc-settings' ) ) ) {

			wp_enqueue_script( 'wcsatt_writepanel' );

			$params = array(
				'add_subscription_scheme_nonce' => wp_create_nonce( 'wcsatt_add_subscription_scheme' ),
				'subscription_lengths'          => wcs_get_subscription_ranges(),
				'wc_ajax_url'                   => admin_url( 'admin-ajax.php' ),
				'post_id'                       => is_object( $post ) ? $post->ID : '',
				'wc_plugin_url'                 => WC()->plugin_url()
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

		$subscription_schemes = get_post_meta( $post->ID, '_wcsatt_schemes', true );

		?><div id="wcsatt_data" class="panel woocommerce_options_panel wc-metaboxes-wrapper">
			<div class="options_group"><?php

				// Subscription Status.
				woocommerce_wp_checkbox( array( 'id' => '_wcsatt_force_subscription', 'label' => __( 'Force subscription', WCS_ATT::TEXT_DOMAIN ), 'desc_tip' => true, 'description' => __( 'Check this option to prevent one-time purchases of this product. In effect when at least one Subscription Option has been added below.', WCS_ATT::TEXT_DOMAIN ) ) );

				// Default Status.
				woocommerce_wp_select( array( 'id' => '_wcsatt_default_status', 'wrapper_class'=> 'wcsatt_default_status', 'label' => __( 'Default to', WCS_ATT::TEXT_DOMAIN ), 'description' => '', 'options' => array(
					'one-time'     => __( 'One-time purchase', WCS_ATT::TEXT_DOMAIN ),
					'subscription' => __( 'Subscription', WCS_ATT::TEXT_DOMAIN ),
				) ) );

				// Subscription Prompt.
				woocommerce_wp_textarea_input( array( 'id' => '_wcsatt_subscription_prompt', 'label' => __( 'Subscription prompt', WCS_ATT::TEXT_DOMAIN ), 'description' => __( 'Custom html/text to display before the available Subscription Options. In effect when at least one Subscription Option has been added below.', WCS_ATT::TEXT_DOMAIN ), 'desc_tip' => true ) );

			?></div>

			<p class="form-field">
				<label>
					<?php
						echo __( 'Subscription Options', WCS_ATT::TEXT_DOMAIN );
						echo WCS_ATT_Core_Compatibility::wc_help_tip( __( 'Add one or more subscription options for this product.', WCS_ATT::TEXT_DOMAIN ) );
			?></label></p>
			<div class="subscription_schemes wc-metaboxes ui-sortable" data-count=""><?php

				if ( $subscription_schemes ) {

					$i = 0;

					foreach ( $subscription_schemes as $subscription_scheme ) {
						do_action( 'wcsatt_subscription_scheme', $i, $subscription_scheme, $post->ID );
						$i++;
					}
				}

			?></div>

			<p class="toolbar">
				<button type="button" class="button button-primary add_subscription_scheme"><?php _e( 'Add Option', WCS_ATT::TEXT_DOMAIN ); ?></button>
			</p>
		</div><?php
	}

}

WCS_ATT_Admin::init();
