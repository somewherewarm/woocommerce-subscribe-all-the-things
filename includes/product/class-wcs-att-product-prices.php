<?php
/**
 * WCS_ATT_Product_Prices class
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
 * API for working with the prices of subscription-enabled product objects.
 *
 * @class    WCS_ATT_Product_Prices
 * @version  2.0.0
 */
class WCS_ATT_Product_Prices {

	/**
	 * Initialize.
	 */
	public static function init() {

		require_once( 'class-wcs-att-product-price-filters.php' );

		self::add_hooks();
	}

	/**
	 * Add price filters.
	 *
	 * @return void
	 */
	private static function add_hooks() {

		WCS_ATT_Product_Price_Filters::add();
	}

	/*
	|--------------------------------------------------------------------------
	| Getters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns a string representing the details of the active subscription scheme.
	 *
	 * @param  WC_Product  $product  Product object.
	 * @param  array       $include  An associative array of flags to indicate how to calculate the price and what to include - @see 'WC_Subscriptions_Product::get_price_string'.
	 * @param  array       $args     Optional args to pass into 'WC_Subscriptions_Product::get_price_string'. Use 'scheme_key' to optionally define a scheme key to use.
	 * @return string
	 */
	public static function get_price_string( $product, $args = array() ) {

		$scheme_key = isset( $args[ 'scheme_key' ] ) ? $args[ 'scheme_key' ] : '';

		$active_scheme_key = WCS_ATT_Product_Schemes::get_subscription_scheme( $product );
		$scheme_key        = '' === $scheme_key ? $active_scheme_key : $scheme_key;

		// Attempt to switch scheme when requesting the price html of a scheme other than the active one.
		$scheme_switch_required = $scheme_key !== $active_scheme_key;
		$switched_scheme        = $scheme_switch_required ? WCS_ATT_Product_Schemes::set_subscription_scheme( $product, $scheme_key ) : false;

		$price_string = WC_Subscriptions_Product::get_price_string( $product, $args );

		// Switch back to the initially active scheme, if switched.
		if ( $switched_scheme ) {
			WCS_ATT_Product_Schemes::set_subscription_scheme( $product, $active_scheme_key );
		}

		return $price_string;
	}

	/**
	 * Returns the price html associated with the active subscription scheme.
	 * You may optionally pass a scheme key to get the price html string associated with it.
	 *
	 * @param  WC_Product  $product     Product object.
	 * @param  integer     $scheme_key  Scheme key or the currently active one, if undefined. Optional.
	 * @param  array       $args        Optional args to pass into 'WC_Subscriptions_Product::get_price_string'. Use 'price_html' to optionally define the bare price html (without subscription details) to use.
	 * @return string
	 */
	public static function get_price_html( $product, $scheme_key = '', $args = array() ) {

		$price_html = isset( $args[ 'price' ] ) ? $args[ 'price' ] : null;

		if ( null === $price_html ) {
			// No infinite loops, thank you.
			$price_html = self::get_price_html_unfiltered( $product );

			if ( empty( $price_html ) ) {
				return $price_html;
			}
		}

		$active_scheme_key = WCS_ATT_Product_Schemes::get_subscription_scheme( $product );
		$scheme_key        = '' === $scheme_key ? $active_scheme_key : $scheme_key;

		// Attempt to switch scheme when requesting the price html of a scheme other than the active one.
		$scheme_switch_required = $scheme_key !== $active_scheme_key;
		$switched_scheme        = $scheme_switch_required ? WCS_ATT_Product_Schemes::set_subscription_scheme( $product, $scheme_key ) : false;

		// Scheme switch required but unsuccessful? Problem. Set price html to an empty string.
		if ( $scheme_switch_required && false === $switched_scheme ) {

			$price_string = '';

		// Add subscription details to the bare product html price.
		} else {

			// Scheme is set on the object? Just add the subscription details.
			if ( WCS_ATT_Product::is_subscription( $product ) ) {

				if ( $switched_scheme ) {
					// No infinite loops, thank you.
					$price_html = self::get_price_html_unfiltered( $product );
				}

				$args[ 'price' ] = $price_html;

				$price_html = WC_Subscriptions_Product::get_price_string( $product, $args );

			// Subscription state is undefined? Construct a special price string.
			} elseif ( is_null( $scheme_key ) ) {

				$schemes         = WCS_ATT_Product_Schemes::get_subscription_schemes( $product );
				$base_scheme     = WCS_ATT_Product_Schemes::get_base_subscription_scheme( $product );
				$base_scheme_key = $base_scheme->get_key();

				// Temporarily apply base scheme on product object.
				$switched_scheme = WCS_ATT_Product_Schemes::set_subscription_scheme( $product, $base_scheme_key );

				if ( $product->is_type( 'variable' ) && $product->get_variation_price( 'min' ) !== $product->get_variation_price( 'max' ) ) {
					$has_variable_price = true;
				} elseif (  $product->is_type( 'bundle' ) && $product->get_bundle_price( 'min' ) !== $product->get_bundle_price( 'max' ) ) {
					$has_variable_price = true;
				} elseif ( $product->is_type( 'composite' ) && $product->get_composite_price( 'min' ) !== $product->get_composite_price( 'max' ) ) {
					$has_variable_price = true;
				} else {
					$has_variable_price = false;
				}

				$html_from_text = wc_get_price_html_from_text();

				if ( WCS_ATT_Product_Schemes::has_forced_subscription_scheme( $product ) ) {

					// No infinite loops, thank you.
					$price_html = self::get_price_html_unfiltered( $product );

					$args[ 'price' ] = $price_html;

					$price_html = WC_Subscriptions_Product::get_price_string( $product, $args );

					if ( ( $has_variable_price || sizeof( $schemes ) > 1 ) && false === strpos( $price_html, $html_from_text ) ) {
						$price_html = sprintf( _x( '%1$s%2$s', 'Price range: from', 'woocommerce-subscribe-all-the-things' ), $html_from_text, $price_html );
					}

				} else {

					$suffix_price_html        = '';
					$use_discount_html_format = true;
					$has_variable_discount    = false;
					$discount                 = '';

					// Show discount format if all schemes are of the 'inherit' pricing mode type.
					$price_filter_exists = WCS_ATT_Product_Schemes::price_filter_exists( $schemes );

					if ( $price_filter_exists ) {
						foreach ( $schemes as $scheme ) {
							if ( $scheme->has_price_filter() ) {
								if ( 'inherit' !== $scheme->get_pricing_mode() ) {
									$use_discount_html_format = false;
									break;
								} elseif ( $discount !== $scheme->get_discount() ) {
									if ( '' === $discount ) {
										$discount = $scheme->get_discount();
									} else {
										$has_variable_discount = true;
									}
								}
							}
						}
					}

					// Relative discount format vs Absolute price format.
					if ( $price_filter_exists && apply_filters( 'wcsatt_price_html_discount_format', $use_discount_html_format, $product ) ) {

						$discount          = $base_scheme->get_discount();
						$discount_html     = '</small> <span class="wcsatt-sub-discount">' . sprintf( __( '%s&#37; off', 'woocommerce-subscribe-all-the-things' ), $discount ) . '</span><small>';
						$suffix_price_html = sprintf( __( 'subscribe and get %1$s%2$s', 'woocommerce-subscribe-all-the-things' ), $has_variable_discount ? __( ' up to', 'woocommerce-subscribe-all-the-things' ) : '', $discount_html );
						$suffix            = ' <small class="wcsatt-sub-options">' . sprintf( __( '&ndash; or %s', 'woocommerce-subscribe-all-the-things' ), $suffix_price_html ) . '</small>';

					} else {

						// No infinite loops, thank you.
						$base_scheme_price_html = self::get_price_html_unfiltered( $product );

						$args[ 'price' ] = $base_scheme_price_html;

						$price_string_args = apply_filters( 'wcsatt_get_single_product_lowest_price_string',
							$args,
							array(
								'price'         => self::get_price( $product ),
								'sale_price'    => self::get_sale_price( $product ),
								'regular_price' => self::get_regular_price( $product ),
								'scheme'        => $base_scheme
							),
							$product
						);

						$base_scheme_price_html = WC_Subscriptions_Product::get_price_string( $product, $price_string_args );

						if ( sizeof( $schemes ) > 1 ) {
							$suffix_price_html = sprintf( _x( '%1$s%2$s', 'Price range: starting at', 'woocommerce-subscribe-all-the-things' ), _x( '<span class="from">starting at </span>', 'subscriptions "starting at" price string', 'woocommerce-subscribe-all-the-things' ), str_replace( $html_from_text, '', $base_scheme_price_html ) );
						} elseif ( $has_variable_price ) {
							$suffix_price_html = sprintf( _x( '%1$s%2$s', 'Price range: from', 'woocommerce-subscribe-all-the-things' ), _x( '<span class="from">from </span>', 'subscription "from" price string', 'woocommerce-subscribe-all-the-things' ), str_replace( $html_from_text, '', $base_scheme_price_html ) );
						} else {
							$suffix_price_html = $base_scheme_price_html;
						}

						if ( WCS_ATT_Product_Schemes::price_filter_exists( $schemes ) ) {
							$suffix = ' <small class="wcsatt-sub-options">' . sprintf( _n( '&ndash; or %s', '&ndash; subscription plans %s', sizeof( $schemes ), 'woocommerce-subscribe-all-the-things' ), $suffix_price_html ) . '</small>';
						} else {
							$suffix = ' <small class="wcsatt-sub-options">' . sprintf( _n( '&ndash; subscription plan available', '&ndash; subscription plans available', sizeof( $schemes ), 'woocommerce-subscribe-all-the-things' ), $suffix_price_html ) . '</small>';
						}
					}

					$price_html = sprintf( _x( '%1$s%2$s', 'price html sub options suffix', 'woocommerce-subscribe-all-the-things' ), $price_html, $suffix );
				}
			}
		}

		// Switch back to the initially active scheme, if switched.
		if ( $switched_scheme ) {
			WCS_ATT_Product_Schemes::set_subscription_scheme( $product, $active_scheme_key );
		}

		return $price_html;
	}

	/**
	 * Unfiltered alias of 'WC_Product::get_price_html'.
	 *
	 * @param  WC_Product  $product  Product object.
	 * @return string
	 */
	public static function get_price_html_unfiltered( $product ) {

		WCS_ATT_Product_Price_Filters::remove( 'price_html' );
		$price_html = $product->get_price_html();
		WCS_ATT_Product_Price_Filters::add( 'price_html' );

		return $price_html;
	}

	/**
	 * Returns the recurring vanilla/regular/sale price.
	 *
	 * @param  WC_Product  $product     Product object.
	 * @param  string      $scheme_key  Optional key to get the price of a specific scheme.
	 * @param  string      $context     Function call context.
	 * @param  string      $price_type  Price to get. Values: '', 'regular', or 'sale'.
	 * @return mixed                    The price charged charged per subscription period.
	 */
	protected static function get_product_price( $product, $scheme_key = '', $context = 'view', $price_type = '' ) {

		$price_type = $price_type && in_array( $price_type, array( 'regular', 'sale' ) ) ? $price_type : '';
		$price_prop = $price_type ? $price_type . '_price' : 'price';
		$price_fn   = 'get_' . $price_prop;

		// In 'view' context, switch the active scheme if needed - and call 'WC_Product::get_{price_prop}'.
		if ( 'view' === $context ) {

			$active_scheme_key = WCS_ATT_Product_Schemes::get_subscription_scheme( $product );
			$scheme_key        = '' === $scheme_key ? $active_scheme_key : $scheme_key;

			// Attempt to switch scheme when requesting the price html of a scheme other than the active one.
			$scheme_switch_required = $scheme_key !== $active_scheme_key;
			$switched_scheme        = $scheme_switch_required ? WCS_ATT_Product_Schemes::set_subscription_scheme( $product, $scheme_key ) : false;

			$price = $product->$price_fn();

			// Switch back to the initially active scheme, if switched.
			if ( $switched_scheme ) {
				WCS_ATT_Product_Schemes::set_subscription_scheme( $product, $active_scheme_key );
			}

		// In 'edit' context, just grab the raw price from the scheme data + product props.
		} else {

			$subscription_scheme = WCS_ATT_Product_Schemes::get_subscription_scheme( $product, 'object', $scheme_key );
			$price               = $product->$price_fn( 'edit' );

			if ( ! empty( $subscription_scheme ) ) {

				if ( $subscription_scheme->has_price_filter() && apply_filters( 'wcsatt_price_filters_allowed', true, $product ) ) {

					$prices_array = array(
						'price'         => 'price'         === $price_prop ? $price : $product->get_price( 'edit' ),
						'sale_price'    => 'sale_price'    === $price_prop ? $price : $product->get_sale_price( 'edit' ),
						'regular_price' => 'regular_price' === $price_prop ? $price : $product->get_regular_price( 'edit' )
					);

					$overridden_prices = $subscription_scheme->get_prices( $prices_array);
					$price             = $overridden_prices[ $price_prop ];
				}
			}
		}

		return $price;
	}

	/**
	 * Returns the recurring price.
	 *
	 * @param  WC_Product  $product     Product object.
	 * @param  string      $scheme_key  Optional key to get the price of a specific scheme.
	 * @param  string      $context     Function call context.
	 * @return mixed                    The price charged per subscription period.
	 */
	public static function get_price( $product, $scheme_key = '', $context = 'view' ) {
		return self::get_product_price( $product, $scheme_key, $context );
	}

	/**
	 * Returns the recurring regular price.
	 *
	 * @param  WC_Product  $product     Product object.
	 * @param  string      $scheme_key  Optional key to get the regular price of a specific scheme.
	 * @param  string      $context     Function call context.
	 * @return mixed                    The regular price charged per subscription period.
	 */
	public static function get_regular_price( $product, $scheme_key = '', $context = 'view' ) {
		return self::get_product_price( $product, $scheme_key, $context, 'regular' );
	}

	/**
	 * Returns the recurring sale price.
	 *
	 * @param  WC_Product  $product     Product object.
	 * @param  string      $scheme_key  Optional key to get the price of a specific scheme.
	 * @param  string      $context     Function call context.
	 * @return mixed                    The sale price charged per subscription period.
	 */
	public static function get_sale_price( $product, $scheme_key = '', $context = 'view' ) {
		return self::get_product_price( $product, $scheme_key, $context, 'sale' );
	}
}

WCS_ATT_Product_Prices::init();
