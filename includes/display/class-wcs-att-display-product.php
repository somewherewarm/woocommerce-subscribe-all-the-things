<?php
/**
 * WCS_ATT_Display_Product class
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Subscribe All the Things
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single-product template modifications.
 *
 * @class    WCS_ATT_Display_Product
 * @version  2.0.0
 */
class WCS_ATT_Display_Product {

	/**
	 * Initialization.
	 */
	public static function init() {
		self::add_hooks();
	}

	/**
	 * Single-product display hooks.
	 */
	private static function add_hooks() {

		// Display subscription options in the single-product template.
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'show_subscription_options' ), 100 );

		// Changes the "Add to Cart" button text when a product with the force subscription is set.
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( __CLASS__, 'add_to_cart_text' ), 10, 1 );

		// Replace plain variation price html with subscription options template.
		add_filter( 'woocommerce_available_variation', array( __CLASS__, 'add_subscription_options_to_variation_data' ), 0, 3 );
	}

	/**
	 * Options for purchasing a product once or creating a subscription from it.
	 *
	 * @param  WC_Product  $product
	 * @return void
	 */
	public static function get_subscription_options_content( $product ) {

		$content = '';

		$subscription_schemes      = WCS_ATT_Product_Schemes::get_subscription_schemes( $product );
		$show_subscription_options = apply_filters( 'wcsatt_show_single_product_options', ! empty( $subscription_schemes ), $product );

		// Subscription options for variable products are embedded inside the variation data 'price_html' field and updated by the core variations script.
		if ( $product->is_type( 'variable' ) ) {
			$show_subscription_options = false;
		}

		if ( $show_subscription_options ) {

			$product_id                           = WCS_ATT_Core_Compatibility::get_product_id( $product );
			$force_subscription                   = WCS_ATT_Product_Schemes::has_forced_subscription_scheme( $product );
			$is_single_scheme_forced_subscription = $force_subscription && sizeof( $subscription_schemes ) === 1;
			$default_subscription_scheme_key      = apply_filters( 'wcsatt_get_default_subscription_scheme_id', WCS_ATT_Product_Schemes::get_default_subscription_scheme( $product, 'key' ), $subscription_schemes, false === $force_subscription, $product ); // Why 'false === $force_subscription'? The answer is back-compat.
			$options                              = array();

			// Option selected by default.
			if ( isset( $_REQUEST[ 'convert_to_sub_' . $product_id ] ) ) {
				$default_subscription_scheme_option_value = $_REQUEST[ 'convert_to_sub_' . $product_id ];
			} else {
				$default_subscription_scheme_option_value = false === $default_subscription_scheme_key ? '0' : $default_subscription_scheme_key;
			}

			// Non-recurring (one-time) option.
			if ( false === $force_subscription ) {
				$none_string                 = _x( 'None', 'product subscription selection - negative response', 'woocommerce-subscribe-all-the-things' );
				$one_time_option_description = $product->is_type( 'variation' ) ? sprintf( __( '%1$s &ndash; %2$s', 'woocommerce-subscribe-all-the-things' ), $none_string, '<span class="price">' . WCS_ATT_Product_Prices::get_price_html( $product, false ) . '</span>' ) : $none_string;

				$options[] = array(
					'description' => apply_filters( 'wcsatt_single_product_one_time_option_description', $one_time_option_description, $product ),
					'value'       => '0',
					'selected'    => '0' === $default_subscription_scheme_option_value,
					'data'        => apply_filters( 'wcsatt_single_product_one_time_option_data', array(), $product )
				);
			}

			// Subscription options.
			foreach ( $subscription_schemes as $subscription_scheme ) {

				$sub_price_html = '<span class="price subscription-price">' . WCS_ATT_Product_Prices::get_price_html( $product, $subscription_scheme->get_key() ) . '</span>';

				$option_data = array(
					'subscription_scheme'   => $subscription_scheme->get_data(),
					'overrides_price'       => $subscription_scheme->has_price_filter(),
					'discount_from_regular' => apply_filters( 'wcsatt_discount_from_regular', false )
				);

				$options[] = array(
					'description' => apply_filters( 'wcsatt_single_product_subscription_option_description', ucfirst( false === $force_subscription ? sprintf( _x( '%s', 'product subscription selection - positive response', 'woocommerce-subscribe-all-the-things' ), $sub_price_html ) : $sub_price_html ), $sub_price_html, $subscription_scheme->has_price_filter(), false === $force_subscription, $product, $subscription_scheme ),
					'value'       => $subscription_scheme->get_key(),
					'selected'    => $default_subscription_scheme_option_value === $subscription_scheme->get_key(),
					'data'        => apply_filters( 'wcsatt_single_product_subscription_option_data', $option_data, $subscription_scheme, $product )
				);
			}

			if ( $prompt = $product->get_meta( '_wcsatt_subscription_prompt', true ) ) {
				$prompt = wpautop( do_shortcode( wp_kses_post( $prompt ) ) );
			}

			$options = apply_filters( 'wcsatt_single_product_options', $options, $subscription_schemes, $product );

			ob_start();

			wc_get_template( 'single-product/product-subscription-options.php', array(
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

	/*
	|--------------------------------------------------------------------------
	| Filters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Replace plain variation price html with subscription options template.
	 * Subscription options are updated by the core variations script when a variation is selected.
	 *
	 * @param  array                 $variation_data
	 * @param  WC_Product_Variable   $variable_product
	 * @param  WC_Product_Variation  $variation_product
	 * @return array
	 */
	public static function add_subscription_options_to_variation_data( $variation_data, $variable_product, $variation_product ) {
		global $product;

		if ( is_a( $product, 'WC_Product' ) && $variable_product->get_id() === $product->get_id() && ! did_action( 'wc_ajax_woocommerce_show_composited_product' ) ) {
			if ( $subscription_options_content = self::get_subscription_options_content( $variation_product ) ) {
				$variation_data[ 'price_html' ] = $subscription_options_content;
			}
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
	 * Override the WooCommerce "Add to Cart" button text with "Sign Up Now".
	 *
	 * @since 1.1.1
	 */
	public static function add_to_cart_text( $button_text ) {

		global $product;

		$product_schemes    = $product->get_meta( '_wcsatt_schemes', true );
		$force_subscription = $product->get_meta( '_wcsatt_force_subscription', true );

		if ( in_array( $product->get_type(), WCS_ATT()->get_supported_product_types() ) && $product_schemes ) {
			if ( 'yes' === $force_subscription ) {
				$button_text = get_option( WC_Subscriptions_Admin::$option_prefix . '_add_to_cart_button_text', __( 'Sign Up Now', 'woocommerce-subscribe-all-the-things' ) );
			}
		}

		return apply_filters( 'wcsatt_add_to_cart_text', $button_text );
	}

}

WCS_ATT_Display_Product::init();
