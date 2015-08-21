<?php
/**
 * Cart functionality for converting cart items to subscriptions.
 *
 * @class 	WCCSubs_Admin
 * @version 1.0.0
 */

class WCCSubs_Admin {

	public static function init() {

		// Admin scripts and styles
		add_action( 'admin_enqueue_scripts', __CLASS__ . '::admin_scripts' );

		// Ajax add subscription scheme
		add_action( 'wp_ajax_wccsubs_add_subscription_scheme', __CLASS__ . '::ajax_add_subscription_scheme' );

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
		 * WC Admin Settings
		 */

		// Subscription scheme options displayed on the 'wccsubs_subscription_scheme_content' action
		add_action( 'wccsubs_subscription_scheme_content',  __CLASS__ . '::subscription_scheme_content', 10, 4 );

		// Append "Subscribe to Cart/Order" section in the Subscriptions settings tab
		add_filter( 'woocommerce_subscription_settings', __CLASS__ . '::cart_level_admin_settings' );

		// Display subscription scheme admin metaboxes in the "Subscribe to Cart/Order" section
		add_action( 'woocommerce_admin_field_subscription_schemes_metaboxes', __CLASS__ . '::subscription_schemes_metaboxes_content' );

		// Process posted subscription scheme admin metaboxes
		add_action( 'woocommerce_update_options_' . WC_Subscriptions_Admin::$tab_name, __CLASS__ . '::process_subscription_schemes_metaboxes_content', 9 );

	}

	/**
	 * Preprocess posted admin subscription scheme settings.
	 *
	 * @return void
	 */
	public static function process_subscription_schemes_metaboxes_content() {

		if ( empty( $_POST[ '_wcsnonce' ] ) || ! wp_verify_nonce( $_POST[ '_wcsnonce' ], 'wcs_subscription_settings' ) ) {
			return;
		}

		// Save subscription scheme options

		// Posted variable name must be the same as the field id so that WC can pick it up and save it
		$field_name = WC_Subscriptions_Admin::$option_prefix . '_subscribe_to_cart_schemes';

		if ( isset( $_POST[ 'wccsubs_schemes' ] ) ) {

			$posted_schemes = stripslashes_deep( $_POST[ 'wccsubs_schemes' ] );
			$scheme_ids     = array();
			$clean_schemes  = array();

			foreach ( $posted_schemes as $posted_scheme ) {

				$scheme_id = $posted_scheme[ 'subscription_period_interval' ] . '_' . $posted_scheme[ 'subscription_period' ] . '_' . $posted_scheme[ 'subscription_length' ];

				if ( in_array( $scheme_id, $scheme_ids ) ) {
					continue;
				}

				$posted_scheme[ 'id' ] = $scheme_id;
				$scheme_ids[]          = $scheme_id;
				$clean_schemes[]       = $posted_scheme;
			}

			$_POST[ $field_name ] = $clean_schemes;

		} else {

			$_POST[ $field_name ] = array();
		}
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
				<div id="cart_subscriptions_data" class="wc-metaboxes-wrapper">
					<div class="subscription_schemes wc-metaboxes ui-sortable" data-count=""><?php

						if ( $subscription_schemes ) {

							$i = 0;

							foreach ( $subscription_schemes as $subscription_scheme ) {

								$subscription_scheme_id = $subscription_scheme[ 'id' ];

								?><div class="subscription_scheme wc-metabox closed" rel="<?php echo $subscription_scheme[ 'position' ]; ?>">
									<h3>
										<button type="button" class="remove_row button"><?php echo __( 'Remove', 'woocommerce' ); ?></button>
										<div class="subscription_scheme_data">
											<?php do_action( 'wccsubs_subscription_scheme_content', $i, $subscription_scheme, '', false ); ?>
										</div>
										<input type="hidden" name="wccsubs_schemes[<?php echo $i; ?>][id]" class="scheme_id" value="<?php echo $subscription_scheme_id; ?>" />
										<input type="hidden" name="wccsubs_schemes[<?php echo $i; ?>][position]" class="position" value="<?php echo $i; ?>"/>
									</h3>
								</div><?php

								$i++;
							}
						}

					?></div>
					<p class="toolbar">
						<button type="button" class="button add_subscription_scheme"><?php _e( 'Add Option', WCCSubs::TEXT_DOMAIN ); ?></button>
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
					'name'     => __( 'Subscribe to Cart/Order', WCCSubs::TEXT_DOMAIN ),
					'type'     => 'title',
					'desc'     => '',
					'id'       => WC_Subscriptions_Admin::$option_prefix . '_subscribe_to_cart_options',
				);

				$new_settings[] = array(
					'name'            => __( 'Cart/Order Subscriptions', WCCSubs::TEXT_DOMAIN ),
					'desc'            => __( 'Enable Cart/Order Subscriptions', WCCSubs::TEXT_DOMAIN ),
					'id'              => WC_Subscriptions_Admin::$option_prefix . '_enable_cart_subscriptions',
					'default'         => 'no',
					'type'            => 'checkbox',
					'desc_tip'        => __( 'Enabling this option will allow customers to purchase their entire cart/order content as a Subscription.', WCCSubs::TEXT_DOMAIN ),
				);

				$new_settings[] = array(
					'name'            => __( 'Subscription Options', WCCSubs::TEXT_DOMAIN ),
					'desc'            => __( 'Configure the subscription options available for signing up to cart/order contents.', WCCSubs::TEXT_DOMAIN ),
					'id'              => WC_Subscriptions_Admin::$option_prefix . '_subscribe_to_cart_schemes',
					'type'            => 'subscription_schemes_metaboxes',
					'desc_tip'        => __( 'Test.', WCCSubs::TEXT_DOMAIN ),
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
		$supported_types = WCCSubs()->get_supported_product_types();

		if ( in_array( $product_type, $supported_types ) ) {

			// Save subscription scheme options

			if ( isset( $_POST[ 'wccsubs_schemes' ] ) ) {

				$posted_schemes = stripslashes_deep( $_POST[ 'wccsubs_schemes' ] );
				$scheme_ids     = array();
				$clean_schemes  = array();

				foreach ( $posted_schemes as $posted_scheme ) {

					$scheme_id = $posted_scheme[ 'subscription_period_interval' ] . '_' . $posted_scheme[ 'subscription_period' ] . '_' . $posted_scheme[ 'subscription_length' ];

					if ( in_array( $scheme_id, $scheme_ids ) ) {
						continue;
					}

					$posted_scheme[ 'id' ] = $scheme_id;
					$scheme_ids[]          = $scheme_id;
					$clean_schemes[]       = $posted_scheme;
				}

				update_post_meta( $post_id, '_wccsubs_schemes', $clean_schemes );

			} else {
				delete_post_meta( $post_id, '_wccsubs_schemes' );
			}

			// Save default status

			if ( isset( $_POST[ '_wccsubs_default_status' ] ) ) {
				update_post_meta( $post_id, '_wccsubs_default_status', stripslashes( $_POST[ '_wccsubs_default_status' ] ) );
			}

			// Save one-time status

			$force_subscription = isset( $_POST[ '_wccsubs_force_subscription' ] ) ? 'yes' : 'no';

			update_post_meta( $post_id, '_wccsubs_force_subscription', $force_subscription );

		} else {

			delete_post_meta( $post_id, '_wccsubs_schemes' );
			delete_post_meta( $post_id, '_wccsubs_force_subscription' );
			delete_post_meta( $post_id, '_wccsubs_default_status' );
		}

	}

	/**
	 * Subscription scheme options displayed on the 'wccsubs_subscription_scheme_content' action.
	 *
	 * @param  int     $index
	 * @param  array   $scheme_data
	 * @param  int     $post_id
	 * @param  boolean $doing_ajax
	 * @return void
	 */
	public static function subscription_scheme_content( $index, $scheme_data, $post_id, $doing_ajax ) {

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
			'name'    => 'wccsubs_schemes[' . $index . '][subscription_period_interval]'
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
			'name'        => 'wccsubs_schemes[' . $index . '][subscription_period]'
			)
		);

		// Subscription Length
		woocommerce_wp_select( array(
			'id'      => '_subscription_length',
			'class'   => 'wc_input_subscription_length',
			'label'   => __( 'Subscription Length', 'woocommerce-subscriptions' ),
			'value'   => $subscription_length,
			'options' => wcs_get_subscription_ranges( $subscription_period ),
			'name'    => 'wccsubs_schemes[' . $index . '][subscription_length]'
			)
		);
	}

	/**
	 * Add subscription schemes via ajax.
	 *
	 * @return void
	 */
	public static function ajax_add_subscription_scheme() {

		check_ajax_referer( 'wccsubs_add_subscription_scheme', 'security' );

		$index   = intval( $_POST[ 'index' ] );
		$post_id = intval( $_POST[ 'post_id' ] );
		$ajax    = true;

		ob_start();

		if ( $index >= 0 ) {

			$result = 'success';

			if ( empty( $post_id ) ) {
				$post_id = '';
			}

			include( 'views/subscription-scheme.php' );

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

		$suffix                  = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$register                = false;
		$register_handle_name    = '';
		$writepanel_dependencies = array();
		$wc_screen_id            = sanitize_title( __( 'WooCommerce', 'woocommerce' ) );

		// Get admin screen id
		$screen = get_current_screen();

		// Product metaboxes
		if ( in_array( $screen->id, array( 'edit-product', 'product' ) ) ) {
			$register = true;
			$writepanel_dependencies = array( 'jquery', 'jquery-ui-datepicker', 'wc-admin-meta-boxes' );
			$register_handle_name = 'wccsubs_writepanel';
		} elseif ( $screen->id == $wc_screen_id . '_page_wc-settings' && isset( $_GET[ 'tab' ] ) && $_GET[ 'tab' ] === 'subscriptions' ) {
			$register = true;
			$writepanel_dependencies = array( 'jquery', 'jquery-ui-datepicker' );
			$register_handle_name = 'wccsubs_writepanel_global';
		}

		if ( $register ) {
			wp_register_script( $register_handle_name, WCCSubs()->plugin_url() . '/assets/js/wccsubs-write-panels' . $suffix . '.js', $writepanel_dependencies, WCCSubs::VERSION );
			wp_register_style( 'wccsubs_writepanel_css', WCCSubs()->plugin_url() . '/assets/css/wccsubs-write-panels.css', array( 'woocommerce_admin_styles' ), WCCSubs::VERSION );
		}

		// WooCommerce admin pages
		if ( in_array( $screen->id, array( 'product' ) ) ) {

			wp_enqueue_script( 'wccsubs_writepanel' );

			$params = array(
				'add_subscription_scheme_nonce' => wp_create_nonce( 'wccsubs_add_subscription_scheme' ),
				'wc_ajax_url'                   => admin_url( 'admin-ajax.php' ),
				'post_id'                       => isset( $post->ID ) ? $post->ID : '',
				'wc_plugin_url'                 => WC()->plugin_url(),
			);

			wp_localize_script( 'wccsubs_writepanel', 'wccsubs_admin_params', $params );
		}

		if ( in_array( $screen->id, array( 'edit-product', 'product' ) ) ) {
			wp_enqueue_style( 'wccsubs_writepanel_css' );
		}

		if ( $screen->id == $wc_screen_id . '_page_wc-settings' && isset( $_GET[ 'tab' ] ) && $_GET[ 'tab' ] === 'subscriptions' ) {

			wp_enqueue_script( 'wccsubs_writepanel_global' );

			$params = array(
				'add_subscription_scheme_nonce' => wp_create_nonce( 'wccsubs_add_subscription_scheme' ),
				'wc_ajax_url'                   => admin_url( 'admin-ajax.php' ),
				'post_id'                       => '',
				'wc_plugin_url'                 => WC()->plugin_url(),
			);

			wp_localize_script( 'wccsubs_writepanel_global', 'wccsubs_admin_params', $params );
			wp_enqueue_style( 'wccsubs_writepanel_css' );
		}
	}

	/**
	 * Cart Subs writepanel tab.
	 *
	 * @return void
	 */
	public static function product_write_panel_tab() {

		?><li class="cart_subscription_options cart_subscriptions_tab show_if_simple show_if_variable show_if_bundle hide_if_variable hide_if_subscription hide_if_variable-subscription">
			<a href="#cart_subscriptions_data"><?php _e( 'Subscriptions', WCCSubs::VERSION ); ?></a>
		</li><?php
	}

	/**
	 * Product writepanel for Subscriptions.
	 *
	 * @return void
	 */
	public static function product_write_panel() {

		global $post;

		$subscription_schemes = get_post_meta( $post->ID, '_wccsubs_schemes', true );

		?><div id="cart_subscriptions_data" class="panel woocommerce_options_panel wc-metaboxes-wrapper">
			<div class="options_group"><?php

				// Subscription Status
				woocommerce_wp_checkbox( array( 'id' => '_wccsubs_force_subscription', 'label' => __( 'Force subscription', WCCSubs::TEXT_DOMAIN ), 'description' => __( 'Check this option to prevent one-time purchases', WCCSubs::TEXT_DOMAIN ) ) );

				// Default Status
				woocommerce_wp_select( array( 'id' => '_wccsubs_default_status', 'wrapper_class'=> 'wccsubs_default_status', 'label' => __( 'Default to', WCCSubs::TEXT_DOMAIN ), 'description' => '', 'options' => array(
					'one-time'     => __( 'One-time purchase', WCCSubs::TEXT_DOMAIN ),
					'subscription' => __( 'Subscription', WCCSubs::TEXT_DOMAIN ),
				) ) );

			?></div>

			<p class="form-field"><label><?php _e( 'Subscription Options', WCCSubs::TEXT_DOMAIN ); ?></label>
				<img class="help_tip" data-tip="<?php _e( 'Add one or more subscription options for this product.', WCCSubs::TEXT_DOMAIN ); ?>" src="<?php echo WC()->plugin_url(); ?>/assets/images/help.png" />
			</p>
			<div class="subscription_schemes wc-metaboxes ui-sortable" data-count=""><?php

				if ( $subscription_schemes ) {

					$i = 0;

					foreach ( $subscription_schemes as $subscription_scheme ) {

						$subscription_scheme_id = $subscription_scheme[ 'id' ];

						?><div class="subscription_scheme wc-metabox closed" rel="<?php echo $subscription_scheme[ 'position' ]; ?>">
							<h3>
								<button type="button" class="remove_row button"><?php echo __( 'Remove', 'woocommerce' ); ?></button>
								<div class="subscription_scheme_data">
									<?php do_action( 'wccsubs_subscription_scheme_content', $i, $subscription_scheme, $post->ID, false ); ?>
								</div>
								<input type="hidden" name="wccsubs_schemes[<?php echo $i; ?>][id]" class="scheme_id" value="<?php echo $subscription_scheme_id; ?>" />
								<input type="hidden" name="wccsubs_schemes[<?php echo $i; ?>][position]" class="position" value="<?php echo $i; ?>"/>
							</h3>
						</div><?php

						$i++;
					}
				}

			?></div>

			<p class="toolbar">
				<button type="button" class="button button-primary add_subscription_scheme"><?php _e( 'Add Option', WCCSubs::TEXT_DOMAIN ); ?></button>
			</p>
		</div><?php
	}
}

WCCSubs_Admin::init();
