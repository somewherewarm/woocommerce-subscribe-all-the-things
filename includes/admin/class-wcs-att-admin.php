<?php
/**
 * WCS_ATT_Admin class
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Subscribe All The Things
 * @since    1.0.4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin includes and hooks.
 *
 * @class    WCS_ATT_Admin
 * @version  2.0.0
 */
class WCS_ATT_Admin {

	public static function init() {

		/*
		 * Single-Product settings.
		 */

		// Metabox includes.
		add_action( 'init', array( __CLASS__, 'admin_init' ) );

		// Admin scripts and styles.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_scripts' ) );

		// Ajax add subscription scheme.
		add_action( 'wp_ajax_wcsatt_add_subscription_scheme', array( __CLASS__, 'ajax_add_subscription_scheme' ) );

		/*
		 * Subscribe-to-Cart settings.
		 */

		// Append "Subscribe to Cart/Order" section in the Subscriptions settings tab.
		add_filter( 'woocommerce_subscription_settings', array( __CLASS__, 'cart_level_admin_settings' ) );

		// Save posted cart subscription scheme settings.
		add_action( 'woocommerce_update_options_subscriptions', array( __CLASS__, 'save_cart_level_settings' ) );

		// Display subscription scheme admin metaboxes in the "Subscribe to Cart/Order" section.
		add_action( 'woocommerce_admin_field_subscription_schemes', array( __CLASS__, 'subscription_schemes_content' ) );
	}

	/**
	 * Admin init.
	 */
	public static function admin_init() {
		self::includes();
	}

	/**
	 * Include classes.
	 */
	public static function includes() {
		require_once( 'meta-boxes/class-wcs-att-meta-box-product-data.php' );
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
						<button type="button" class="button add_subscription_scheme"><?php _e( 'Add Option', 'woocommerce-subscribe-all-the-things' ); ?></button>
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
				'name' => __( 'Subscribe to Cart', 'woocommerce-subscribe-all-the-things' ),
				'type' => 'title',
				'desc' => '',
				'id'   => 'wcsatt_subscribe_to_cart_options',
			),

			array(
				'name' => __( 'Cart Subscription Options', 'woocommerce-subscribe-all-the-things' ),
				'desc' => __( 'Options for purchasing cart contents on a recurring basis.', 'woocommerce-subscribe-all-the-things' ),
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
			wp_register_script( 'wcsatt_writepanel', WCS_ATT()->plugin_url() . '/assets/js/wcs-att-write-panels' . $suffix . '.js', $writepanel_dependencies, WCS_ATT::VERSION );
			wp_register_style( 'wcsatt_writepanel_css', WCS_ATT()->plugin_url() . '/assets/css/wcs-att-write-panels.css', array( 'woocommerce_admin_styles' ), WCS_ATT::VERSION );
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
}

WCS_ATT_Admin::init();
