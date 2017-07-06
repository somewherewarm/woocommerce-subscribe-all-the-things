<?php
/**
 * WCS_ATT_Meta_Box_Product_Data class
 *
 * @package  WooCommerce Subscribe All the Things
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product meta-box data for SATT-enabled product types.
 *
 * @class    WCS_ATT_Meta_Box_Product_Data
 * @version  2.0.0
 */
class WCS_ATT_Meta_Box_Product_Data {

	/**
	 * Hook-in point.
	 */
	public static function init() {

		// Create the SATT Subscriptions tab.
		add_action( 'woocommerce_product_data_tabs', array( __CLASS__, 'satt_product_data_tab' ) );

		// Create the SATT Subscriptions tab panel.
		if ( WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ) {
			add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'product_data_panel' ) );
		} else {
			add_action( 'woocommerce_product_write_panels', array( __CLASS__, 'product_data_panel' ) );
		}

		// Subscription scheme markup added on the 'wcsatt_subscription_scheme' action.
		add_action( 'wcsatt_subscription_scheme', array( __CLASS__, 'subscription_scheme' ), 10, 3 );

		// Subscription scheme options displayed on the 'wcsatt_subscription_scheme_content' action.
		add_action( 'wcsatt_subscription_scheme_content', array( __CLASS__, 'subscription_scheme_content' ), 10, 3 );

		// Subscription scheme options displayed on the 'wcsatt_subscription_scheme_product_content' action.
		add_action( 'wcsatt_subscription_scheme_product_content', array( __CLASS__, 'subscription_scheme_product_content' ), 10, 3 );

		// Process and save the necessary meta.
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'process_product_meta' ), 15, 1 );
	}

	/**
	 * Add Subscriptions tab.
	 *
	 * @param  array  $tabs
	 * @return void
	 */
	public static function satt_product_data_tab( $tabs ) {

		$tabs[ 'satt' ] = array(
			'label'  => __( 'Subscriptions', 'woocommerce-subscribe-all-the-things' ),
			'target' => 'wcsatt_data',
			'class'  => array( 'cart_subscription_options', 'cart_subscriptions_tab', 'show_if_simple', 'show_if_variable', 'show_if_bundle', 'hide_if_subscription', 'hide_if_variable-subscription' )
		);

		return $tabs;
	}

	/**
	 * Product writepanel for Subscriptions.
	 *
	 * @return void
	 */
	public static function product_data_panel() {

		global $post, $product_object;

		if ( WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ) {
			$subscription_schemes = $product_object->get_meta( '_wcsatt_schemes', true );
		} else {
			$subscription_schemes = get_post_meta( $post->ID, '_wcsatt_schemes', true );
		}

		?><div id="wcsatt_data" class="panel woocommerce_options_panel wc-metaboxes-wrapper">
			<div class="options_group"><?php

				// Subscription Status.
				woocommerce_wp_checkbox( array( 'id' => '_wcsatt_force_subscription', 'label' => __( 'Force subscription', 'woocommerce-subscribe-all-the-things' ), 'desc_tip' => true, 'description' => __( 'Check this option to prevent one-time purchases of this product. In effect when at least one Subscription Option has been added below.', 'woocommerce-subscribe-all-the-things' ) ) );

				// Default Status.
				woocommerce_wp_select( array( 'id' => '_wcsatt_default_status', 'wrapper_class'=> 'wcsatt_default_status', 'label' => __( 'Default to', 'woocommerce-subscribe-all-the-things' ), 'description' => '', 'options' => array(
					'one-time'     => __( 'One-time purchase', 'woocommerce-subscribe-all-the-things' ),
					'subscription' => __( 'Subscription', 'woocommerce-subscribe-all-the-things' ),
				) ) );

				// Subscription Prompt.
				woocommerce_wp_textarea_input( array( 'id' => '_wcsatt_subscription_prompt', 'label' => __( 'Subscription prompt', 'woocommerce-subscribe-all-the-things' ), 'description' => __( 'Custom html/text to display before the available Subscription Options. In effect when at least one Subscription Option has been added below.', 'woocommerce-subscribe-all-the-things' ), 'desc_tip' => true ) );

			?></div>

			<p class="form-field">
				<label>
					<?php
						echo __( 'Subscription Options', 'woocommerce-subscribe-all-the-things' );
						echo WCS_ATT_Core_Compatibility::wc_help_tip( __( 'Add one or more subscription options for this product.', 'woocommerce-subscribe-all-the-things' ) );
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
				<button type="button" class="button button-primary add_subscription_scheme"><?php _e( 'Add Option', 'woocommerce-subscribe-all-the-things' ); ?></button>
			</p>
		</div><?php
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


		// Subscription Price, Interval and Period.
		?><p class="form-field _satt_subscription_details">
			<label for="_satt_subscription_details"><?php esc_html_e( 'Interval', 'woocommerce-subscribe-all-the-things' ); ?></label>
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
			<?php echo WCS_ATT_Core_Compatibility::wc_help_tip( __( 'Choose the subscription billing interval and period.', 'woocommerce-subscribe-all-the-things' ) ); ?>
		</p><?php

		// Subscription Length.
		woocommerce_wp_select( array(
			'id'          => '_satt_subscription_length',
			'class'       => 'wc_input_subscription_length',
			'label'       => __( 'Length', 'woocommerce-subscribe-all-the-things' ),
			'value'       => $subscription_length,
			'options'     => wcs_get_subscription_ranges( $subscription_period ),
			'name'        => 'wcsatt_schemes[' . $index . '][subscription_length]',
			'description' => __( 'Choose the subscription billing length.', 'woocommerce-subscribe-all-the-things' ),
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
				'label'   => __( 'Price', 'woocommerce-subscribe-all-the-things' ),
				'value'   => $subscription_pricing_method,
				'options' => array(
						'inherit'  => __( 'Inherit from product', 'woocommerce-subscribe-all-the-things' ),
						'override' => __( 'Override product', 'woocommerce-subscribe-all-the-things' ),
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
					'label'         => __( 'Discount %', 'woocommerce-subscribe-all-the-things' ),
					'description'   => __( 'Discount applied on the <strong>Regular Price</strong> of the product.', 'woocommerce-subscribe-all-the-things' ),
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
				'label'   => __( 'Price', 'woocommerce-subscribe-all-the-things' ),
				'value'   => $subscription_pricing_method,
				'options' => array(
						'inherit'  => __( 'Inherit from chosen variation', 'woocommerce-subscribe-all-the-things' ),
						'override' => __( 'Override all variations', 'woocommerce-subscribe-all-the-things' ),
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
					'label'         => __( 'Discount %', 'woocommerce-subscribe-all-the-things' ),
					'description'   => __( 'Discount applied on the <strong>Regular Price</strong> of the chosen variation.', 'woocommerce-subscribe-all-the-things' ),
					'desc_tip'      => true,
					'data_type'     => 'decimal'
				) );

			?></div>
		</div><?php
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

			$schemes = array();

			// Process scheme options.
			if ( isset( $_POST[ 'wcsatt_schemes' ] ) ) {

				$posted_schemes = stripslashes_deep( $_POST[ 'wcsatt_schemes' ] );

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

								WC_Admin_Meta_Boxes::add_error( __( 'Please enter positive subscription discount values, between 0-100.', 'woocommerce-subscribe-all-the-things' ) );
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

					$schemes[ $scheme_id ]         = $posted_scheme;
					$schemes[ $scheme_id ][ 'id' ] = $scheme_id;
				}
			}

			// Process one-time shipping option.
			$one_time_shipping = isset( $_POST[ '_subscription_one_time_shipping' ] ) ? 'yes' : 'no';

			// Process default status option.
			$default_status = isset( $_POST[ '_wcsatt_default_status' ] ) ? stripslashes( $_POST[ '_wcsatt_default_status' ] ) : 'one-time';

			// Process force-sub status.
			$force_subscription = isset( $_POST[ '_wcsatt_force_subscription' ] ) ? 'yes' : 'no';

			// Process prompt text.
			$prompt = ! empty( $_POST[ '_wcsatt_subscription_prompt' ] ) ? wp_kses_post( stripslashes( $_POST[ '_wcsatt_subscription_prompt' ] ) ) : false;

			if ( WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ) {

				$product = wc_get_product( $post_id );

				if ( $product ) {

					// Save scheme options.
					if ( ! empty( $schemes ) ) {
						$product->update_meta_data( '_wcsatt_schemes', $schemes );
					} else {
						$product->delete_meta_data( '_wcsatt_schemes' );
					}

					// Save one-time shipping option.
					$product->update_meta_data( '_subscription_one_time_shipping', $one_time_shipping );

					// Save default status.
					$product->update_meta_data( '_wcsatt_default_status', $default_status );

					// Save force-sub status.
					$product->update_meta_data( '_wcsatt_force_subscription', $force_subscription );

					// Set regular price as ZERO should the shop owner forget.
					// This helps make WooCommerce think it's still available for purchase.
					if ( 'yes' === $force_subscription && empty( $_POST[ '_regular_price' ] ) ) {
						$product->set_regular_price( 0 );
						$product->set_price( 0 );
					}

					// Save prompt.
					if ( false === $prompt ) {
						$product->update_meta_data( '_wcsatt_subscription_prompt', $prompt );
					} else {
						$product->delete_meta_data( '_wcsatt_subscription_prompt' );
					}

					$product->save();
				}

			} else {

				// Save scheme options.
				if ( ! empty( $schemes ) ) {
					update_post_meta( $post_id, '_wcsatt_schemes', $schemes );
				} else {
					delete_post_meta( $post_id, '_wcsatt_schemes' );
				}

				// Save one-time shipping option.
				update_post_meta( $post_id, '_subscription_one_time_shipping', $one_time_shipping );

				// Save default status.
				update_post_meta( $post_id, '_wcsatt_default_status', $default_status );

				// Save force-sub status.
				update_post_meta( $post_id, '_wcsatt_force_subscription', $force_subscription );

				// Set regular price as ZERO should the shop owner forget.
				// This helps make WooCommerce think it's still available for purchase.
				if ( 'yes' === $force_subscription && empty( $_POST[ '_regular_price' ] ) ) {
					update_post_meta( $post_id, '_regular_price', wc_format_decimal( 0 ) );
					update_post_meta( $post_id, '_price', wc_format_decimal( 0 ) );
				}

				// Save prompt.
				if ( false === $prompt ) {
					delete_post_meta( $post_id, '_wcsatt_subscription_prompt' );
				} else {
					update_post_meta( $post_id, '_wcsatt_subscription_prompt', $prompt );
				}
			}

		} else {

			if ( WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ) {

				$product = wc_get_product( $post_id );

				if ( $product ) {

					$product->delete_meta_data( '_wcsatt_schemes' );
					$product->delete_meta_data( '_wcsatt_force_subscription' );
					$product->delete_meta_data( '_wcsatt_default_status' );
					$product->delete_meta_data( '_wcsatt_subscription_prompt' );
				}

			} else {
				delete_post_meta( $post_id, '_wcsatt_schemes' );
				delete_post_meta( $post_id, '_wcsatt_force_subscription' );
				delete_post_meta( $post_id, '_wcsatt_default_status' );
				delete_post_meta( $post_id, '_wcsatt_subscription_prompt' );
			}
		}
	}
}

WCS_ATT_Meta_Box_Product_Data::init();
