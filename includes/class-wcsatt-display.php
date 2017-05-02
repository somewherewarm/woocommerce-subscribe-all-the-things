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
	 * Initialization.
	 */
	public static function init() {

		// Enqueue scripts and styles.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'frontend_scripts' ) );

		// Display subscription options in the single-product template.
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'show_subscription_options' ), 100 );

		// Changes the "Add to Cart" button text when a product with the force subscription is set.
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( __CLASS__, 'add_to_cart_text' ), 10, 1 );

		// Replace plain variation price html with subscription options template.
		add_filter( 'woocommerce_available_variation', array( __CLASS__, 'add_subscription_options_to_variation_data' ), 10, 3 );
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

		wp_register_style( 'wcsatt-css', WCS_ATT()->plugin_url() . '/assets/css/wcsatt-frontend.css', false, WCS_ATT::VERSION, 'all' );
		wp_enqueue_style( 'wcsatt-css' );

		if ( is_cart() ) {

			wp_register_script( 'wcsatt-cart', WCS_ATT()->plugin_url() . '/assets/js/wcsatt-cart.js', array( 'jquery', 'wc-country-select', 'wc-address-i18n' ), WCS_ATT::VERSION, true );
			wp_enqueue_script( 'wcsatt-cart' );

			$params = array(
				'update_cart_option_nonce' => wp_create_nonce( 'wcsatt_update_cart_option' ),
				'wc_ajax_url'              => WC_AJAX::get_endpoint( "%%endpoint%%" ),
				'is_wc_version_gte_2_6'    => WCS_ATT_Core_Compatibility::is_wc_version_gte_2_6() ? 'yes' : 'no'
			);

			wp_localize_script( 'wcsatt-cart', 'wcsatt_cart_params', $params );
		}

		if ( is_product() ) {
			wp_register_script( 'wcsatt-single-product', WCS_ATT()->plugin_url() . '/assets/js/wcsatt-single-add-to-cart.js', array( 'jquery' ), WCS_ATT::VERSION, true );
			wp_enqueue_script( 'wcsatt-single-product' );
		}
	}

	/**
	 * Replace plain variation price html with subscription options template.
	 * Subscription options are updated by the core variations script when a variation is selected.
	 *
	 * @param  array                 $variation_data
	 * @param  WC_Product_Variable   $product
	 * @param  WC_Product_Variation  $variation
	 * @return array
	 */
	public static function add_subscription_options_to_variation_data( $variation_data, $product, $variation ) {

		if ( $subscription_options_content = self::get_subscription_options_content( $variation ) ) {
			$variation_data[ 'price_html' ] = $subscription_options_content;
		}

		return $variation_data;
	}

	/**
	 * Displays single-product options for purchasing a product once or creating a subscription from it.
	 *
	 * @return void
	 */
	public static function show_subscription_options() {
		global $product;
		echo self::get_subscription_options_content( $product );
	}

	/**
	 * Options for purchasing a product once or creating a subscription from it.
	 *
	 * @param  WC_Product  $product
	 * @return void
	 */
	public static function get_subscription_options_content( $product ) {

		$content = '';

		$subscription_schemes      = WCS_ATT_Product::get_subscription_schemes( $product );
		$show_subscription_options = apply_filters( 'wcsatt_show_single_product_options', ! empty( $subscription_schemes ), $product );

		// Subscription options for variable products are embedded inside the variation data 'price_html' field and updated by the core variations script.
		if ( $product->is_type( 'variable' ) ) {
			$show_subscription_options = false;
		}

		if ( $show_subscription_options ) {

			$product_id                           = WCS_ATT_Core_Compatibility::get_id( $product );
			$force_subscription                   = WCS_ATT_Product::has_forced_subscription( $product );
			$is_single_scheme_forced_subscription = $force_subscription && sizeof( $subscription_schemes ) === 1;
			$default_subscription_scheme_key      = apply_filters( 'wcsatt_get_default_subscription_scheme_id', WCS_ATT_Product::get_default_subscription_scheme( $product, 'key' ), $subscription_schemes, false === $force_subscription, $product ); // Why 'false === $force_subscription'? The answer is back-compat.
			$options                              = array();

			// Option selected by default.
			if ( isset( $_REQUEST[ 'convert_to_sub_' . $product_id ] ) ) {
				$default_subscription_scheme_option_value = $_REQUEST[ 'convert_to_sub_' . $product_id ];
			} else {
				$default_subscription_scheme_option_value = false === $default_subscription_scheme_key ? '0' : $default_subscription_scheme_key;
			}

			// Non-recurring (one-time) option.
			if ( false === $force_subscription ) {
				$none_string                 = _x( 'None', 'product subscription selection - negative response', WCS_ATT::TEXT_DOMAIN );
				$one_time_option_description = $product->is_type( 'variation' ) ? sprintf( __( '%1$s &ndash; %2$s', WCS_ATT::TEXT_DOMAIN ), $none_string, '<span class="price">' . WCS_ATT_Product::get_price_html( $product, false ) . '</span>' ) : $none_string;

				$options[] = array(
					'description' => apply_filters( 'wcsatt_single_product_one_time_option_description', $one_time_option_description, $product ),
					'value'       => '0',
					'selected'    => '0' === $default_subscription_scheme_option_value,
					'data'        => apply_filters( 'wcsatt_single_product_one_time_option_data', array(), $product )
				);
			}

			// Subscription options.
			foreach ( $subscription_schemes as $subscription_scheme ) {

				$sub_price_html = '<span class="price subscription-price">' . WCS_ATT_Product::get_price_html( $product, $subscription_scheme->get_key() ) . '</span>';

				$option_data = array(
					'subscription_scheme'   => $subscription_scheme->get_data(),
					'overrides_price'       => $subscription_scheme->has_price_filter(),
					'discount_from_regular' => apply_filters( 'wcsatt_discount_from_regular', false )
				);

				$options[] = array(
					'description' => apply_filters( 'wcsatt_single_product_subscription_option_description', ucfirst( false === $force_subscription ? sprintf( __( '%s', 'product subscription selection - positive response', WCS_ATT::TEXT_DOMAIN ), $sub_price_html ) : $sub_price_html ), $sub_price_html, $subscription_scheme->has_price_filter(), false === $force_subscription, $product, $subscription_scheme ),
					'value'       => $subscription_scheme->get_key(),
					'selected'    => $default_subscription_scheme_option_value === $subscription_scheme->get_key(),
					'data'        => apply_filters( 'wcsatt_single_product_subscription_option_data', $option_data, $subscription_scheme, $product )
				);
			}

			if ( $prompt = WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ? $product->get_meta( '_wcsatt_subscription_prompt', true ) : get_post_meta( $product_id, '_wcsatt_subscription_prompt', true ) ) {
				$prompt = wpautop( do_shortcode( wp_kses_post( $prompt ) ) );
			}

			$options = apply_filters( 'wcsatt_single_product_options', $options, $subscription_schemes, $product );

			ob_start();

			wc_get_template( 'single-product/satt-product-options.php', array(
				'product'        => $product,
				'product_id'     => $product_id,
				'options'        => $options,
				'allow_one_time' => false === $force_subscription,
				'prompt'         => $prompt,
			), false, WCS_ATT()->plugin_path() . '/templates/' );

			$content = ob_get_clean();
		}

		return $content;
	}

	/**
	 * Override the WooCommerce "Add to Cart" button text with "Sign Up Now".
	 *
	 * @since 1.1.1
	 */
	public static function add_to_cart_text( $button_text ) {

		global $product;

		$product_schemes    = WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ? $product->get_meta( '_wcsatt_schemes', true ) : get_post_meta( $product->id, '_wcsatt_schemes', true );
		$force_subscription = WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ? $product->get_meta( '_wcsatt_force_subscription', true ) : get_post_meta( $product->id, '_wcsatt_force_subscription', true );

		if ( in_array( $product->get_type(), WCS_ATT()->get_supported_product_types() ) && $product_schemes ) {
			if ( 'yes' === $force_subscription ) {
				$button_text = get_option( WC_Subscriptions_Admin::$option_prefix . '_add_to_cart_button_text', __( 'Sign Up Now', WCS_ATT::TEXT_DOMAIN ) );
			}
		}

		return apply_filters( 'wcsatt_add_to_cart_text', $button_text );
	}

}

WCS_ATT_Display::init();
