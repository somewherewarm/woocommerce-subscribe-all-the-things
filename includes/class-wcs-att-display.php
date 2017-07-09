<?php
/**
 * WCS_ATT_Display class
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Subscribe All the Things
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end support and single-product template modifications.
 *
 * @class    WCS_ATT_Display
 * @version  2.0.0
 */
class WCS_ATT_Display {

	/**
	 * Flag to ensure hooks can be added only once.
	 * @var bool
	 */
	private static $added_hooks = false;

	/**
	 * Initialization.
	 */
	public static function init() {

		// Cart display hooks.
		require_once( 'display/class-wcs-att-display-cart.php' );
		// Single-product display hooks.
		require_once( 'display/class-wcs-att-display-product.php' );

		self::add_hooks();
	}

	/**
	 * Hook-in.
	 */
	private static function add_hooks() {

		if ( self::$added_hooks ) {
			return;
		}

		self::$added_hooks = true;

		// Enqueue scripts and styles.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'frontend_scripts' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Filters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Front end styles and scripts.
	 *
	 * @return void
	 */
	public static function frontend_scripts() {

		wp_register_style( 'wcsatt-css', WCS_ATT()->plugin_url() . '/assets/css/wcs-att-frontend.css', false, WCS_ATT::VERSION, 'all' );
		wp_enqueue_style( 'wcsatt-css' );

		if ( is_cart() ) {

			wp_register_script( 'wcsatt-cart', WCS_ATT()->plugin_url() . '/assets/js/wcs-att-cart.js', array( 'jquery', 'wc-country-select', 'wc-address-i18n' ), WCS_ATT::VERSION, true );
			wp_enqueue_script( 'wcsatt-cart' );

			$params = array(
				'update_cart_option_nonce' => wp_create_nonce( 'wcsatt_update_cart_option' ),
				'wc_ajax_url'              => WC_AJAX::get_endpoint( "%%endpoint%%" ),
				'is_wc_version_gte_2_6'    => WCS_ATT_Core_Compatibility::is_wc_version_gte_2_6() ? 'yes' : 'no'
			);

			wp_localize_script( 'wcsatt-cart', 'wcsatt_cart_params', $params );
		}

		if ( is_product() ) {
			wp_register_script( 'wcsatt-single-product', WCS_ATT()->plugin_url() . '/assets/js/wcs-att-single-add-to-cart.js', array( 'jquery' ), WCS_ATT::VERSION, true );
			wp_enqueue_script( 'wcsatt-single-product' );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Deprecated
	|--------------------------------------------------------------------------
	*/

	/**
	 * Options for purchasing a product once or creating a subscription from it.
	 *
	 * @param  WC_Product  $product
	 * @return void
	 */
	public static function get_subscription_options_content( $product ) {
		_deprecated_function( __METHOD__ . '()', '2.0.0', 'WCS_ATT_Display_Product::get_subscription_options_content()' );
		return WCS_ATT_Display_Product::get_subscription_options_content( $product );
	}
}

WCS_ATT_Display::init();
