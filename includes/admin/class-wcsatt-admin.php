<?php
/**
 * Cart functionality for converting cart items to subscriptions.
 *
 * @class 	WCS_ATT_Admin
 * @version 1.0.0
 */

class WCS_ATT_Admin {

	public static function init() {

		// Admin scripts and styles
		add_action( 'admin_enqueue_scripts', __CLASS__ . '::admin_scripts' );

		// Ajax add subscription scheme
		add_action( 'wp_ajax_wcsatt_add_subscription_scheme', __CLASS__ . '::ajax_add_subscription_scheme' );

		// Subscription scheme markup added on the 'wcsatt_subscription_scheme' action
		add_action( 'wcsatt_subscription_scheme',  __CLASS__ . '::subscription_scheme', 10, 3 );

		// Subscription scheme options displayed on the 'wcsatt_subscription_scheme_content' action
		add_action( 'wcsatt_subscription_scheme_content',  __CLASS__ . '::subscription_scheme_content', 10, 3 );

		// Subscription scheme options displayed on the 'wcsatt_subscription_scheme_content' action
		add_action( 'wcsatt_subscription_scheme_product_content',  __CLASS__ . '::subscription_scheme_product_content', 10, 3 );

		/**
		 * WC Product Metaboxes
		 */

		// Creates the admin panel tab
		add_action( 'woocommerce_product_write_panel_tabs', __CLASS__ . '::product_write_panel_tab' );

		// Creates the panel for configuring subscription options
		add_action( 'woocommerce_product_write_panels', __CLASS__ . '::product_write_panel' );

		// Processes and saves the necessary post meta
		add_action( 'woocommerce_process_product_meta', __CLASS__ . '::process_product_meta' );

		/**
		 * "Subscribe to Cart" settings
		 */

		// Append "Subscribe to Cart/Order" section in the Subscriptions settings tab
		// add_filter( 'woocommerce_subscription_settings', __CLASS__ . '::cart_level_admin_settings' );

		// Display subscription scheme admin metaboxes in the "Subscribe to Cart/Order" section
		// add_action( 'woocommerce_admin_field_subscription_schemes_metaboxes', __CLASS__ . '::subscription_schemes_metaboxes_content' );

		// Process posted subscription scheme admin metaboxes - TODO
	}

	/**
	 * Subscriptions schemes admin metaboxes.
	 *
	 * @param  array $values
	 * @return void
	 */
	public static function subscription_schemes_metaboxes_content( $values ) {

		$field_name           = WC_Subscriptions_Admin::$option_prefix . '_subscribe_to_cart_schemes';
		$subscription_schemes = get_option( $field_name, false );

		if ( ! $subscription_schemes ) {
			$subscription_schemes = array(
				array(
					'subscription_period_interval' => 1,
					'subscription_period'          => 'month',
					'subscription_length'          => 0,
					'id'                           => '1_month_0',
					'position'                     => 0,
				)
			);
		}

		?><tr valign="top">
			<th scope="row" class="titledesc"><?php echo esc_html( $values['title'] ) ?></th>
			<td class="forminp forminp-subscription_schemes_metaboxes">
				<p class="description"><?php echo esc_html( $values['desc'] ) ?></p>
				<div id="wcsatt_data" class="wc-metaboxes-wrapper">
					<div class="subscription_schemes wc-metaboxes ui-sortable" data-count=""><?php

						if ( $subscription_schemes ) {

							$i = 0;

							foreach ( $subscription_schemes as $subscription_scheme ) {
								do_action( 'wcsatt_subscription_scheme', $i, $subscription_scheme, '' );
								$i++;
							}
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

		$new_settings = array();

		foreach ( $settings as $setting ) {

			$new_settings[] = $setting;

			if ( $setting[ 'type' ] === 'sectionend' && $setting[ 'id' ] === WC_Subscriptions_Admin::$option_prefix . '_miscellaneous' ) {

				$new_settings[] = array(
					'name'     => __( 'Subscribe to Cart/Order', WCS_ATT::TEXT_DOMAIN ),
					'type'     => 'title',
					'desc'     => '',
					'id'       => WC_Subscriptions_Admin::$option_prefix . '_subscribe_to_cart_options',
				);

				$new_settings[] = array(
					'name'            => __( 'Cart/Order Subscriptions', WCS_ATT::TEXT_DOMAIN ),
					'desc'            => __( 'Enable Cart/Order Subscriptions', WCS_ATT::TEXT_DOMAIN ),
					'id'              => WC_Subscriptions_Admin::$option_prefix . '_enable_cart_subscriptions',
					'default'         => 'no',
					'type'            => 'checkbox',
					'desc_tip'        => __( 'Enabling this option will allow customers to purchase their entire cart/order content as a Subscription.', WCS_ATT::TEXT_DOMAIN ),
				);

				$new_settings[] = array(
					'name'            => __( 'Subscription Options', WCS_ATT::TEXT_DOMAIN ),
					'desc'            => __( 'Configure the subscription options available for signing up to cart/order contents.', WCS_ATT::TEXT_DOMAIN ),
					'id'              => WC_Subscriptions_Admin::$option_prefix . '_subscribe_to_cart_schemes',
					'type'            => 'subscription_schemes_metaboxes',
					'desc_tip'        => __( 'Test.', WCS_ATT::TEXT_DOMAIN ),
				);

				$new_settings[] = array( 'type' => 'sectionend', 'id' => WC_Subscriptions_Admin::$option_prefix . '_subscribe_to_cart_options' );

			}
		}

		return $new_settings;
	}

	/**
	 * Save subscription options.
	 *
	 * @param  int  $post_id
	 * @return void
	 */
	public static function process_product_meta( $post_id ) {

		// Get type
		$product_type    = empty( $_POST[ 'product-type' ] ) ? 'simple' : sanitize_title( stripslashes( $_POST[ 'product-type' ] ) );
		$supported_types = WCS_ATT()->get_supported_product_types();

		if ( in_array( $product_type, $supported_types ) ) {

			// Save subscription scheme options

			if ( isset( $_POST[ 'wcsatt_schemes' ] ) ) {

				$posted_schemes = stripslashes_deep( $_POST[ 'wcsatt_schemes' ] );
				$scheme_ids     = array();
				$clean_schemes  = array();

				foreach ( $posted_schemes as $posted_scheme ) {

					$scheme_id = $posted_scheme[ 'subscription_period_interval' ] . '_' . $posted_scheme[ 'subscription_period' ] . '_' . $posted_scheme[ 'subscription_length' ] . '_' . $posted_scheme[ 'subscription_price' ];

					if ( in_array( $scheme_id, $scheme_ids ) ) {
						continue;
					}

					$posted_scheme[ 'id' ] = $scheme_id;
					$scheme_ids[]          = $scheme_id;
					$clean_schemes[]       = $posted_scheme;
				}

				update_post_meta( $post_id, '_wcsatt_schemes', $clean_schemes );

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
	 * Subscription scheme options displayed on the 'wcsatt_subscription_scheme_content' action.
	 *
	 * @param  int     $index
	 * @param  array   $scheme_data
	 * @param  int     $post_id
	 * @return void
	 */
	public static function subscription_scheme_product_content( $index, $scheme_data, $post_id ) {

		if ( ! empty( $scheme_data ) ) {
			$subscription_price_override_method = ! empty( $scheme_data[ 'subscription_price_override_method' ] ) ? $scheme_data[ 'subscription_price_override_method' ] : '';
			$subscription_price                 = isset( $scheme_data[ 'subscription_price_override_method' ] ) && $scheme_data[ 'subscription_price_override_method' ] === 'price' ? $scheme_data[ 'subscription_price' ] : '';
			$subscription_discount              = isset( $scheme_data[ 'subscription_price_override_method' ] ) && $scheme_data[ 'subscription_price_override_method' ] === 'discount' ? $scheme_data[ 'subscription_discount' ] : '';
		} else {
			$subscription_price_override_method = '';
			$subscription_price                 = '';
			$subscription_discount              = '';
		}

		// Subscription Price Override Method
		woocommerce_wp_select( array(
			'id'      => '_subscription_price_override_method_input',
			'class'   => 'subscription_price_override_method_input',
			'label'   => __( 'Override Subscription Price', WCS_ATT::TEXT_DOMAIN ),
			'value'   => $subscription_price_override_method,
			'options' => array(
					''         => __( 'Choose method&hellip;', WCS_ATT::TEXT_DOMAIN ),
					'price'    => __( 'Regular &amp; Sale Prices', WCS_ATT::TEXT_DOMAIN ) . ' (' . get_woocommerce_currency_symbol() . ')',
					'discount' => __( 'Discount', WCS_ATT::TEXT_DOMAIN ) . ' (%)',
				),
			'name'    => 'wcsatt_schemes[' . $index . '][subscription_price_override_method]'
			)
		);

		?><div class="subscription_price_override_method subscription_price_override_method_price"><?php
			// Price.
			woocommerce_wp_text_input( array( 'id' => '_override_subscription_regular_price', 'wrapper_class' => 'override_subscription_regular_price', 'class' => 'short', 'label' => __( 'Regular Price', 'woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')', 'data_type' => 'price' ) );
			// Sale Price.
			woocommerce_wp_text_input( array( 'id' => '_override_subscription_sale_price', 'wrapper_class' => 'override_subscription_sale_price', 'class' => 'short', 'label' => __( 'Sale Price', 'woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')', 'data_type' => 'price' ) );
		?></div>
		<div class="subscription_price_override_method subscription_price_override_method_discount"><?php
			// Discount.
			woocommerce_wp_text_input( array( 'id' => '_subscription_price_discount', 'wrapper_class' => 'subscription_price_discount', 'class' => 'short', 'label' => __( 'Discount %', WCS_ATT::TEXT_DOMAIN ), 'data_type' => 'decimal' ) );
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
	 * Load scripts and styles.
	 *
	 * @return void
	 */
	public static function admin_scripts() {

		global $post;

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// Get admin screen id
		$screen = get_current_screen();

		// Product metaboxes
		if ( in_array( $screen->id, array( 'edit-product', 'product' ) ) ) {
			wp_register_script( 'wcsatt_writepanel', WCS_ATT()->plugin_url() . '/assets/js/wcsatt-write-panels' . $suffix . '.js', array( 'jquery', 'jquery-ui-datepicker', 'wc-admin-meta-boxes' ), WCS_ATT::VERSION );
			wp_register_style( 'wcsatt_writepanel_css', WCS_ATT()->plugin_url() . '/assets/css/wcsatt-write-panels.css', array( 'woocommerce_admin_styles' ), WCS_ATT::VERSION );
		}

		// WooCommerce admin pages
		if ( in_array( $screen->id, array( 'product' ) ) ) {

			wp_enqueue_script( 'wcsatt_writepanel' );

			$params = array(
				'add_subscription_scheme_nonce' => wp_create_nonce( 'wcsatt_add_subscription_scheme' ),
				'wc_ajax_url'                   => admin_url( 'admin-ajax.php' ),
				'post_id'                       => $post->ID,
				'wc_plugin_url'                 => WC()->plugin_url(),
			);

			wp_localize_script( 'wcsatt_writepanel', 'wcsatt_admin_params', $params );
		}

		if ( in_array( $screen->id, array( 'edit-product', 'product' ) ) ) {
			wp_enqueue_style( 'wcsatt_writepanel_css' );
		}
	}

	/**
	 * Cart Subs writepanel tab.
	 *
	 * @return void
	 */
	public static function product_write_panel_tab() {

		?><li class="cart_subscription_options cart_subscriptions_tab show_if_simple show_if_variable show_if_bundle hide_if_variable hide_if_subscription hide_if_variable-subscription">
			<a href="#wcsatt_data"><?php _e( 'Subscription', WCS_ATT::VERSION ); ?></a>
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

			<p class="form-field"><label><?php _e( 'Subscription Options', WCS_ATT::TEXT_DOMAIN ); ?></label>
				<img class="help_tip" data-tip="<?php _e( 'Add one or more subscription options for this product.', WCS_ATT::TEXT_DOMAIN ); ?>" src="<?php echo WC()->plugin_url(); ?>/assets/images/help.png" />
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

			<p class="toolbar">
				<button type="button" class="button button-primary add_subscription_scheme"><?php _e( 'Add Option', WCS_ATT::TEXT_DOMAIN ); ?></button>
			</p>
		</div><?php
	}
}

WCS_ATT_Admin::init();
