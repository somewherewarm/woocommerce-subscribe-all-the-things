<?php
/**
 * WCS_ATT_Meta_Box_Product_Data class
 *
 * @package  WooCommerce Subscribe All the Things
 * @since    1.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product meta-box data for SATT-enabled product types.
 *
 * @class    WCS_ATT_Meta_Box_Product_Data
 * @version  1.2.0
 */
class WCS_ATT_Meta_Box_Product_Data {

	/**
	 * Hook-in point.
	 */
	public static function init() {

		// Creates the admin panel tab.
		add_action( 'woocommerce_product_data_tabs', array( __CLASS__, 'satt_product_data_tab' ) );

		// Creates the panel for configuring subscription options.
		if ( WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ) {
			add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'product_data_panel' ) );
		} else {
			add_action( 'woocommerce_product_write_panels', array( __CLASS__, 'product_data_panel' ) );
		}

		// Processes and saves the necessary post meta.
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'process_product_meta' ), 15, 1 );
	}

	/**
	 * Add SATT product options tab.
	 *
	 * @param  array  $tabs
	 * @return void
	 */
	public static function satt_product_data_tab( $tabs ) {

		$tabs[ 'satt_options' ] = array(
			'label'  => __( 'Subscriptions', WCS_ATT::TEXT_DOMAIN ),
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
						$product->update_meta_data( '_wcsatt_subscription_prompt' );
					} else {
						$product->delete_meta_data( '_wcsatt_subscription_prompt', $prompt );
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
