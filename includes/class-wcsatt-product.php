<?php
/**
 * WCS_ATT_Product API
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
 * API for working with subscription-enabled product objects.
 *
 * @class    WCS_ATT_Product
 * @version  2.0.0
 */
class WCS_ATT_Product {

	public static function init() {

		// Allow WCS to recognize any product as a subscription.
		add_filter( 'woocommerce_is_subscription', array( __CLASS__, 'filter_is_subscription' ), 10, 3 );

		// Delete object meta in use by the application layer.
		add_action( 'woocommerce_before_product_object_save', array( __CLASS__, 'delete_reserved_meta' ) );

		// Make product prices scheme-dependent.
		WCS_ATT_Scheme_Prices::add_price_filters();
		WCS_ATT_Scheme_Prices::add_price_html_filters();
	}

	/*
	|--------------------------------------------------------------------------
	| Conditionals
	|--------------------------------------------------------------------------
	*/

	/**
	 * Determines if a subscription scheme is set on the product object.
	 *
	 * @param  WC_Product  $product  Product object to check.
	 * @return boolean               Result of check.
	 */
	public static function is_subscription( $product ) {
		$active_scheme_key = self::get_subscription_scheme( $product );
		return ! empty( $active_scheme_key );
	}

	/**
	 * Determines if the product can be purchased on a recurring basis.
	 *
	 * @param  WC_Product  $product  Product object to check.
	 * @return boolean               Result of check.
	 */
	public static function has_subscriptions( $product ) {
		return sizeof( self::get_subscription_schemes( $product ) ) > 0;
	}

	/**
	 * Determines if the product is only purchasable on a recurring basis.
	 *
	 * @param  WC_Product  $product  Product object to check.
	 * @return boolean               Result of check.
	 */
	public static function has_forced_subscription( $product ) {

		if ( '' === ( $forced = self::get_product_property( $product, 'has_forced_subscription' ) ) && self::has_subscriptions( $product ) ) {

			$forced = WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ? $product->get_meta( '_wcsatt_force_subscription', true ) : get_post_meta( WCS_ATT_Core_Compatibility::get_id( $product ), '_wcsatt_force_subscription', true );

			// Attempt to get opion from parent if undefined on variation.
			if ( '' === $forced && $product->is_type( 'variation' ) ) {

				if ( WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ) {
					$parent = wc_get_product( $product->get_parent_id() );
					$forced = $parent ? $parent->get_meta( '_wcsatt_force_subscription', true ) : '';
				} else {
					$forced = get_post_meta( WCS_ATT_Core_Compatibility::get_parent_id( $product ), '_wcsatt_force_subscription', true );
				}
			}

			self::set_product_property( $product, 'has_forced_subscription', $forced );
		}

		return 'yes' === $forced;
	}

	/**
	 * Checks a product object to determine if it is a WCS subscription-type product.
	 *
	 * @param  WC_Product  $product  Product object to check.
	 * @return boolean               Result of check.
	 */
	public static function is_legacy_subscription( $product ) {
		return $product->is_type( array( 'subscription', 'subscription_variation', 'variable-subscription' ) );
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

		$active_scheme_key = self::get_subscription_scheme( $product );
		$scheme_key        = '' === $scheme_key ? $active_scheme_key : $scheme_key;

		// Attempt to switch scheme when requesting the price html of a scheme other than the active one.
		$scheme_switch_required = $scheme_key !== $active_scheme_key;
		$switched_scheme        = $scheme_switch_required ? self::set_subscription_scheme( $product, $scheme_key ) : false;

		$price_string = WC_Subscriptions_Product::get_price_string( $product, $args );

		// Switch back to the initially active scheme, if switched.
		if ( $switched_scheme ) {
			self::set_subscription_scheme( $product, $active_scheme_key );
		}

		return $price_string;
	}

	/**
	 * Returns the price html associated with the active subscription scheme.
	 * You may optionally pass a scheme key to get the price html string associated with it.
	 *
	 * @param  WC_Product  $product     Product object.
	 * @param  integer     $scheme_key  Scheme key or the currently active one, if undefined. Optional.
	 * @param  array       $args        Optional args to pass into 'WCS_ATT_Product::get_price_string'. Use 'price_html' to optionally define the bare price html (without subscription details) to use.
	 * @return string
	 */
	public static function get_price_html( $product, $scheme_key = '', $args = array() ) {

		$price_html = isset( $args[ 'price' ] ) ? $args[ 'price' ] : null;

		if ( null === $price_html ) {
			// No infinite loops, thank you.
			WCS_ATT_Scheme_Prices::remove_price_html_filters();
			$price_html = $product->get_price_html();
			WCS_ATT_Scheme_Prices::add_price_html_filters();

			if ( empty( $price_html ) ) {
				return $price_html;
			}
		}

		$active_scheme_key = self::get_subscription_scheme( $product );
		$scheme_key        = '' === $scheme_key ? $active_scheme_key : $scheme_key;

		// Attempt to switch scheme when requesting the price html of a scheme other than the active one.
		$scheme_switch_required = $scheme_key !== $active_scheme_key;
		$switched_scheme        = $scheme_switch_required ? self::set_subscription_scheme( $product, $scheme_key ) : false;

		// Scheme switch required but unsuccessful? Problem. Set price html to an empty string.
		if ( $scheme_switch_required && false === $switched_scheme ) {

			$price_string = '';

		// Add subscription details to the bare product html price.
		} else {

			// Scheme is set on the object? Just add the subscription details.
			if ( self::is_subscription( $product ) ) {

				if ( $switched_scheme ) {
					// No infinite loops, thank you.
					WCS_ATT_Scheme_Prices::remove_price_html_filters();
					$price_html = $product->get_price_html();
					WCS_ATT_Scheme_Prices::add_price_html_filters();
				}

				$args[ 'price' ] = $price_html;

				$price_html = WC_Subscriptions_Product::get_price_string( $product, $args );

			// Subscription state is undefined? Construct a special price string.
			} elseif ( is_null( $scheme_key ) ) {

				$schemes         = self::get_subscription_schemes( $product );
				$base_scheme     = WCS_ATT_Scheme_Prices::get_base_scheme( $product );
				$base_scheme_key = $base_scheme->get_key();

				// Temporarily apply base scheme on product object.
				$switched_scheme = self::set_subscription_scheme( $product, $base_scheme_key );

				if ( $product->is_type( 'variable' ) && $product->get_variation_price( 'min' ) !== $product->get_variation_price( 'max' ) ) {
					$has_variable_price = true;
				} elseif (  $product->is_type( 'bundle' ) && $product->get_bundle_price( 'min' ) !== $product->get_bundle_price( 'max' ) ) {
					$has_variable_price = true;
				} elseif ( $product->is_type( 'composite' ) && $product->get_composite_price( 'min' ) !== $product->get_composite_price( 'max' ) ) {
					$has_variable_price = true;
				} else {
					$has_variable_price = false;
				}

				$html_from_text = WCS_ATT_Core_Compatibility::get_price_html_from_text( $product );

				if ( self::has_forced_subscription( $product ) ) {

					// No infinite loops, thank you.
					WCS_ATT_Scheme_Prices::remove_price_html_filters();
					$price_html = $product->get_price_html();
					WCS_ATT_Scheme_Prices::add_price_html_filters();

					$args[ 'price' ] = $price_html;

					$price_html = WC_Subscriptions_Product::get_price_string( $product, $args );

					if ( ( $has_variable_price || sizeof( $schemes ) > 1 ) && false === strpos( $price_html, $html_from_text ) ) {
						$price_html = sprintf( _x( '%1$s%2$s', 'Price range: from', WCS_ATT::TEXT_DOMAIN ), $html_from_text, $price_html );
					}

				} else {

					$suffix_price_html        = '';
					$use_discount_html_format = false;
					$has_variable_discount    = false;
					$discount                 = '';

					// Show discount format if all schemes are of the 'inherit' pricing mode type.
					$price_filter_exists = WCS_ATT_Scheme_Prices::price_filter_exists( $schemes );

					if ( $price_filter_exists ) {
						foreach ( $schemes as $scheme ) {
							if ( $scheme->has_price_filter() && 'inherit' === $scheme->get_pricing_mode() ) {
								$use_discount_html_format = true;
								if ( $discount !== $scheme->get_discount() ) {
									if ( '' === $discount ) {
										$discount = $scheme->get_discount();
									} else {
										$has_variable_discount = true;
									}
								}
							}
						}
					}

					// Discount format vs Price format. Experimental use only.
					if ( $price_filter_exists && apply_filters( 'wcsatt_price_html_discount_format', $use_discount_html_format, $product ) ) {

						$discount          = $base_scheme->get_discount();
						$discount_html     = '</small> <span class="wcsatt-sub-discount">' . sprintf( __( '%s&#37; off', WCS_ATT::TEXT_DOMAIN ), $discount ) . '</span><small>';
						$suffix_price_html = sprintf( __( 'subscribe and get %1$s%2$s', WCS_ATT::TEXT_DOMAIN ), $has_variable_discount ? __( ' up to', WCS_ATT::TEXT_DOMAIN ) : '', $discount_html );
						$suffix            = ' <small class="wcsatt-sub-options">' . sprintf( __( '&ndash; or %s', WCS_ATT::TEXT_DOMAIN ), $suffix_price_html ) . '</small>';

					} else {

						// No infinite loops, thank you.
						WCS_ATT_Scheme_Prices::remove_price_html_filters();
						$base_scheme_price_html = $product->get_price_html();
						WCS_ATT_Scheme_Prices::add_price_html_filters();

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
							$suffix_price_html = sprintf( _x( '%1$s%2$s', 'Price range: starting at', WCS_ATT::TEXT_DOMAIN ), _x( '<span class="from">starting at </span>', 'subscriptions "starting at" price string', WCS_ATT::TEXT_DOMAIN ), str_replace( $html_from_text, '', $base_scheme_price_html ) );
						} elseif ( $has_variable_price ) {
							$suffix_price_html = sprintf( _x( '%1$s%2$s', 'Price range: from', WCS_ATT::TEXT_DOMAIN ), _x( '<span class="from">from </span>', 'subscription "from" price string', WCS_ATT::TEXT_DOMAIN ), str_replace( $html_from_text, '', $base_scheme_price_html ) );
						} else {
							$suffix_price_html = $base_scheme_price_html;
						}

						if ( WCS_ATT_Scheme_Prices::price_filter_exists( $schemes ) ) {
							$suffix = ' <small class="wcsatt-sub-options">' . sprintf( _n( '&ndash; or %s', '&ndash; subscription plans %s', sizeof( $schemes ), WCS_ATT::TEXT_DOMAIN ), $suffix_price_html ) . '</small>';
						} else {
							$suffix = ' <small class="wcsatt-sub-options">' . sprintf( _n( '&ndash; subscription plan available', '&ndash; subscription plans available', sizeof( $schemes ), WCS_ATT::TEXT_DOMAIN ), $suffix_price_html ) . '</small>';
						}
					}

					$price_html = sprintf( _x( '%1$s%2$s', 'price html sub options suffix', WCS_ATT::TEXT_DOMAIN ), $price_html, $suffix );
				}
			}
		}

		// Switch back to the initially active scheme, if switched.
		if ( $switched_scheme ) {
			self::set_subscription_scheme( $product, $active_scheme_key );
		}

		return $price_html;
	}

	/**
	 * Returns the price per subscription period.
	 *
	 * @param  WC_Product  $product     Product object.
	 * @param  string      $scheme_key  Optional key to get the price of a specific scheme.
	 * @param  string      $context     Function call context.
	 * @return mixed                    The price charged charged per subscription period.
	 */
	public static function get_price( $product, $scheme_key = '', $context = 'view' ) {

		// In 'view' context, switch the active scheme if needed - and call 'WC_Product::get_price'.
		if ( 'view' === $context ) {

			$active_scheme_key = self::get_subscription_scheme( $product );
			$scheme_key        = '' === $scheme_key ? $active_scheme_key : $scheme_key;

			// Attempt to switch scheme when requesting the price html of a scheme other than the active one.
			$scheme_switch_required = $scheme_key !== $active_scheme_key;
			$switched_scheme        = $scheme_switch_required ? self::set_subscription_scheme( $product, $scheme_key ) : false;

			$price = $product->get_price();

			// Switch back to the initially active scheme, if switched.
			if ( $switched_scheme ) {
				self::set_subscription_scheme( $product, $active_scheme_key );
			}

		// In 'edit' context, just grab the raw price from the scheme data + product props.
		} else {

			$subscription_scheme = self::get_subscription_scheme( $product, 'object', $scheme_key );
			$price               = WCS_ATT_Core_Compatibility::get_prop( $product, 'price' );

			if ( ! empty( $subscription_scheme ) ) {

				if ( $subscription_scheme->has_price_filter() && apply_filters( 'wcsatt_price_filters_allowed', true, $product ) ) {

					$prices_array = array(
						'price'         => $price,
						'sale_price'    => WCS_ATT_Core_Compatibility::get_prop( $product, 'sale_price' ),
						'regular_price' => WCS_ATT_Core_Compatibility::get_prop( $product, 'regular_price' )
					);

					$overridden_prices = WCS_ATT_Scheme_Prices::get_scheme_prices( $prices_array, $subscription_scheme );
					$price             = $overridden_prices[ 'price' ];
				}
			}
		}

		return $price;
	}

	/**
	 * Returns the regular price per subscription period.
	 *
	 * @param  WC_Product  $product     Product object.
	 * @param  string      $scheme_key  Optional key to get the regular price of a specific scheme.
	 * @param  string      $context     Function call context.
	 * @return mixed                    The regular price charged per subscription period.
	 */
	public static function get_regular_price( $product, $scheme_key = '', $context = 'view' ) {

		// In 'view' context, switch the active scheme if needed - and call 'WC_Product::get_regular_price'.
		if ( 'view' === $context ) {

			$active_scheme_key = self::get_subscription_scheme( $product );
			$scheme_key        = '' === $scheme_key ? $active_scheme_key : $scheme_key;

			// Attempt to switch scheme when requesting the price html of a scheme other than the active one.
			$scheme_switch_required = $scheme_key !== $active_scheme_key;
			$switched_scheme        = $scheme_switch_required ? self::set_subscription_scheme( $product, $scheme_key ) : false;

			$regular_price = $product->get_regular_price();

			// Switch back to the initially active scheme, if switched.
			if ( $switched_scheme ) {
				self::set_subscription_scheme( $product, $active_scheme_key );
			}

		// In 'edit' context, just grab the raw price from the scheme data + product props.
		} else {

			$subscription_scheme = self::get_subscription_scheme( $product, 'object', $scheme_key );
			$regular_price       = WCS_ATT_Core_Compatibility::get_prop( $product, 'regular_price' );

			if ( ! empty( $subscription_scheme ) ) {

				if ( $subscription_scheme->has_price_filter() && apply_filters( 'wcsatt_price_filters_allowed', true, $product ) ) {

					$prices_array = array(
						'price'         => WCS_ATT_Core_Compatibility::get_prop( $product, 'price' ),
						'sale_price'    => WCS_ATT_Core_Compatibility::get_prop( $product, 'sale_price' ),
						'regular_price' => $regular_price
					);

					$overridden_prices = WCS_ATT_Scheme_Prices::get_scheme_prices( $prices_array, $subscription_scheme );
					$regular_price     = $overridden_prices[ 'regular_price' ];
				}
			}
		}

		return $regular_price;
	}

	/**
	 * Returns the sale price per subscription period.
	 *
	 * @param  WC_Product  $product     Product object.
	 * @param  string      $scheme_key  Optional key to get the price of a specific scheme.
	 * @param  string      $context     Function call context.
	 * @return mixed                    The sale price charged per subscription period.
	 */
	public static function get_sale_price( $product, $scheme_key = '', $context = 'view' ) {

		// In 'view' context, switch the active scheme if needed - and call 'WC_Product::get_sale_price'.
		if ( 'view' === $context ) {

			$active_scheme_key = self::get_subscription_scheme( $product );
			$scheme_key        = '' === $scheme_key ? $active_scheme_key : $scheme_key;

			// Attempt to switch scheme when requesting the price html of a scheme other than the active one.
			$scheme_switch_required = $scheme_key !== $active_scheme_key;
			$switched_scheme        = $scheme_switch_required ? self::set_subscription_scheme( $product, $scheme_key ) : false;

			$sale_price = $product->get_sale_price();

			// Switch back to the initially active scheme, if switched.
			if ( $switched_scheme ) {
				self::set_subscription_scheme( $product, $active_scheme_key );
			}

		// In 'edit' context, just grab the raw price from the scheme data + product props.
		} else {

			$subscription_scheme = self::get_subscription_scheme( $product, 'object', $scheme_key );
			$sale_price          = WCS_ATT_Core_Compatibility::get_prop( $product, 'sale_price' );

			if ( ! empty( $subscription_scheme ) ) {

				if ( $subscription_scheme->has_price_filter() && apply_filters( 'wcsatt_price_filters_allowed', true, $product ) ) {

					$prices_array = array(
						'price'         => WCS_ATT_Core_Compatibility::get_prop( $product, 'price' ),
						'sale_price'    => $sale_price,
						'regular_price' => WCS_ATT_Core_Compatibility::get_prop( $product, 'regular_price' )
					);

					$overridden_prices = WCS_ATT_Scheme_Prices::get_scheme_prices( $prices_array, $subscription_scheme );
					$sale_price        = $overridden_prices[ 'sale_price' ];
				}
			}
		}

		return $sale_price;
	}

	/**
	 * Returns the active subscription period of a product that has been associated with a subscription scheme.
	 *
	 * @param  WC_Product  $product  Product object.
	 * @return string                A string representation of the period, either Day, Week, Month or Year, or an empty string if the product has not been associated with a subscription scheme.
	 */
	public static function get_period( $product ) {
		return WC_Subscriptions_Product::get_period( $product );
	}

	/**
	 * Returns the interval of a subscription scheme activated on a product.
	 *
	 * @param  WC_Product  $product  Product object.
	 * @return int                   Interval of active subscription scheme, or an empty string if the product has not been associated with a subscription scheme.
	 */
	public static function get_interval( $product ) {
		return WC_Subscriptions_Product::get_interval( $product );
	}

	/**
	 * Returns the length of a subscription scheme activated on a product.
	 *
	 * @param  WC_Product  $product  Product object.
	 * @return int                   An integer representing the length of the active subscription scheme, or 0 if the product has no active subscription scheme or the active subscription scheme has an infinite length.
	 */
	public static function get_length( $product ) {
		return WC_Subscriptions_Product::get_length( $product );
	}

	/**
	 * Takes a subscription product object and returns the date on which the subscription scheme activated on the object will expire,
	 * based on the subscription's length and calculated from either the $from_date if specified, or the current date/time.
	 *
	 * @param  WC_Product  $product    Product object.
	 * @param  mixed       $from_date  A MySQL formatted date/time string from which to calculate the expiration date, or empty (default), which will use today's date/time.
	 * @return string
	 */
	public static function get_expiration_date( $product, $from_date = '' ) {
		return WC_Subscriptions_Product::get_expiration_date( $product, $from_date );
	}

	/**
	 * Returns all subscription schemes associated with a product.
	 *
	 * @param  WC_Product  $product  Product object.
	 * @param  string      $context  Context of schemes. Values: 'cart', 'product', 'any'.
	 * @return array
	 */
	public static function get_subscription_schemes( $product, $context = 'any' ) {

		$schemes = self::get_product_property( $product, 'subscription_schemes' );

		// If not explicitly set on object, initialize with schemes defined at product-level.
		if ( '' === $schemes ) {

			$supported_types = WCS_ATT()->get_supported_product_types();
			$schemes         = array();

			if ( in_array( $product->get_type(), $supported_types ) ) {

				$product_schemes_meta = WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ? $product->get_meta( '_wcsatt_schemes', true ) : get_post_meta( WCS_ATT_Core_Compatibility::get_id( $product ), '_wcsatt_schemes', true );

				// Attempt to get schemes from parent if undefined on variation.
				if ( '' === $product_schemes_meta && $product->is_type( 'variation' ) ) {

					if ( WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ) {
						$parent          = wc_get_product( $product->get_parent_id() );
						$product_schemes_meta = $parent ? $parent->get_meta( '_wcsatt_schemes', true ) : array();
					} else {
						$product_schemes_meta = get_post_meta( WCS_ATT_Core_Compatibility::get_parent_id( $product ), '_wcsatt_schemes', true );
					}
				}

				if ( ! empty( $product_schemes_meta ) ) {
					foreach ( $product_schemes_meta as $scheme_meta ) {

						$scheme     = new WCS_ATT_Scheme( array( 'data' => $scheme_meta, 'context' => 'product' ) );
						$scheme_key = $scheme->get_key();

						if ( ! isset( $schemes[ $scheme_key ] ) ) {
							$schemes[ $scheme_key ] = $scheme;
						}
					}
				}

				$schemes = apply_filters( 'wcsatt_product_subscription_schemes', $schemes, $product );

				self::set_product_property( $product, 'subscription_schemes', $schemes );
			}
		}

		if ( 'any' !== $context ) {
			$schemes = self::filter_schemes_by_context( $schemes, $context );
		}

		return $schemes;
	}

	/**
	 * Get the active subscription scheme. Note that:
	 * When requesting the active scheme 'key', the function returns:
	 *
	 * - string  if a valid subscription scheme is activated on the object (subscription state defined);
	 * - false   if the product is set to be sold in a non-recurring manner (subscription state defined); or
	 * - null    if no scheme is set on the object (susbcription state undefined).
	 *
	 * When requesting the active scheme, the function returns:
	 *
	 * - A WCS_ATT_Scheme instance  if a valid subscription scheme is activated on the object;
	 * - false                      if the product is set to be sold in a non-recurring manner; or
	 * - null                       otherwise.
	 *
	 * Optionally pass a specific key to get the associated scheme, if valid.
	 *
	 * @param  WC_Product                        $product     Product object.
	 * @param  string                            $return      What to return - 'object' or 'key'. Optional.
	 * @param  string                            $scheme_key  Optional key to get a specific scheme.
	 * @return string|null|false|WCS_ATT_Scheme               Subscription scheme activated on object.
	 */
	public static function get_subscription_scheme( $product, $return = 'key', $scheme_key = '' ) {

		$active_key   = self::get_product_property( $product, 'active_subscription_scheme_key' );
		$search_key   = '' === $scheme_key ? $active_key : $scheme_key;
		$schemes      = self::get_subscription_schemes( $product );
		$found_scheme = null;

		if ( ! empty( $search_key ) && is_array( $schemes ) && isset( $schemes[ $search_key ] ) ) {
			$found_scheme = $schemes[ $search_key ];
		}

		if ( 'key' === $return ) {

			// Looking for a specific scheme other than the active one?
			if ( '' !== $scheme_key ) {
				// Just return the searched key if found, or null otherwise.
				$return_value = is_null( $found_scheme ) ? null : $scheme_key;
			// Looking for the active scheme?
			} else {
				// Return the active scheme key if it points to a valid scheme...
				if ( ! empty( $active_key ) ) {
					$return_value = is_null( $found_scheme ) ? null : $active_key;
				// Return false if the product is set to be sold in a non-recurring manner, or null otherwise.
				} else {
					$return_value = false === $active_key ? false : null;
				}
			}

		} elseif ( 'object' === $return ) {

			// Looking for a specific scheme other than the active one?
			if ( '' !== $scheme_key ) {
				// Just return the scheme if found, or null otherwise.
				$return_value = $found_scheme;
			// Looking for the active scheme?
			} else {
				// Return false if the product is set to be sold in a non-recurring manner, the active scheme if found, or null otherwise.
				$return_value = false === $active_key ? false : $found_scheme;
			}
		}

		return $return_value;
	}

	/**
	 * Get the default subscription scheme (key).
	 *
	 * @param  WC_Product                        $product  Product object.
	 * @param  string                            $return   What to return - 'object' or 'key'. Optional.
	 * @return string|null|false|WCS_ATT_Scheme            Default subscription scheme.
	 */
	public static function get_default_subscription_scheme( $product, $return = 'key' ) {

		if ( '' === ( $default_scheme_key = self::get_product_property( $product, 'default_subscription_scheme_key' ) ) ) {

			$default_scheme     = null;
			$default_scheme_key = false;

			if ( self::has_subscriptions( $product ) ) {

				$schemes = self::get_subscription_schemes( $product );

				if ( self::has_forced_subscription( $product ) ) {

					$default_scheme     = current( $schemes );
					$default_scheme_key = $default_scheme->get_key();

				} else {

					$default_status = WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ? $product->get_meta( '_wcsatt_default_status', true ) : get_post_meta( WCS_ATT_Core_Compatibility::get_id( $product ), '_wcsatt_default_status', true );

					if ( 'subscription' === $default_status ) {

						$default_scheme     = current( $schemes );
						$default_scheme_key = $default_scheme->get_key();
					}
				}
			}

		} else {
			$default_scheme = self::get_subscription_scheme( $product, 'object', $default_scheme_key );
		}

		return 'object' === $return ? $default_scheme : $default_scheme_key;
	}

	/*
	|--------------------------------------------------------------------------
	| Setters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Associates subscription schemes with a product.
	 * Normally, you wouldn't need to use this since 'WCS_ATT_Product::get_subscription_schemes' will automagically fetch all product-level schemes.
	 * Can be used to append or otherwise modify schemes -- e.g. it is used by 'WCS_ATT_Cart::apply_subscription_schemes' to conditionally attach cart-level schemes on session load.
	 *
	 * @param  WC_Product  $product  Product object.
	 * @param  string      $context  Context of schemes. Values: 'cart', 'product', 'any'.
	 * @return array
	 */
	public static function set_subscription_schemes( $product, $schemes ) {
		self::set_product_property( $product, 'subscription_schemes', $schemes );
	}

	/**
	 * Set the active subscription scheme. Key value should be:
	 *
	 * - string  to activate a subscription scheme (valid key required);
	 * - false   to indicate that the product is sold in a non-recurring manner; or
	 * - null    to indicate that the susbcription state of the product is undefined.
	 *
	 * @param  WC_Product  $product  Product object.
	 * @param  string      $key      Identifier of subscription scheme to activate on object.
	 * @return boolean               Action result.
	 */
	public static function set_subscription_scheme( $product, $key ) {

		$active_scheme_key = self::get_subscription_scheme( $product );
		$schemes           = self::get_subscription_schemes( $product );
		$scheme_set        = false;

		if ( ! empty( $key ) && is_array( $schemes ) && isset( $schemes[ $key ] ) && $key !== $active_scheme_key ) {

			$scheme_to_set = $schemes[ $key ];

			// Set subscription scheme key.
			self::set_product_property( $product, 'active_subscription_scheme_key', $key );

			/*
			 * Set subscription scheme details.
			 *
			 * Note that prices are not set directly on object:
			 * The price strings of many product types depend on more than the values returned by the abstract class price getters.
			 * If we are going to apply filters anyway, there's no need to permanently set raw prices here.
			 */
			self::set_product_property( $product, 'subscription_period', $scheme_to_set[ 'subscription_period' ] );
			self::set_product_property( $product, 'subscription_period_interval', $scheme_to_set[ 'subscription_period_interval' ] );
			self::set_product_property( $product, 'subscription_length', $scheme_to_set[ 'subscription_length' ] );

			$scheme_set = true;

		} elseif ( empty( $key ) ) {

			// Reset subscription scheme key.
			self::set_product_property( $product, 'active_subscription_scheme_key', false === $key ? false : null );

			// Reset subscription scheme details.
			self::set_product_property( $product, 'subscription_period', null );
			self::set_product_property( $product, 'subscription_period_interval', null );
			self::set_product_property( $product, 'subscription_length', null );

			$scheme_set = true;
		}

		do_action( 'wcsatt_set_product_subscription_scheme', $key, $product );

		return $scheme_set;
	}

	/*
	|--------------------------------------------------------------------------
	| Helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Property getter (compatibility wrapper).
	 *
	 * @param  WC_Product  $product   Product object.
	 * @param  string      $property  Property name.
	 * @return mixed
	 */
	public static function get_product_property( $product, $property ) {

		if ( WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ) {
			$value = $product->get_meta( '_' . $property, true );
		} else {
			$value = isset( $product->$property ) ? $product->$property : '';
		}

		return $value;
	}

	/**
	 * Property setter (compatibility wrapper).
	 *
	 * @param  WC_Product  $product  Product object.
	 * @param  string      $name     Property name.
	 * @param  string      $value    Property value.
	 * @return mixed
	 */
	public static function set_product_property( $product, $name, $value ) {

		if ( WCS_ATT_Core_Compatibility::is_wc_version_gte_2_7() ) {
			$product->add_meta_data( '_' . $name, $value, true );
		} else {
			$product->$name = $value;
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Filters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Hooks onto 'woocommerce_is_subscription' to trick Subs into thinking it is dealing with a subscription.
	 *
	 * @param  boolean     $is
	 * @param  int         $product_id
	 * @param  WC_Product  $product
	 * @return boolean
	 */
	public static function filter_is_subscription( $is, $product_id, $product ) {

		if ( ! $product ) {
			return $is;
		}

		if ( self::is_subscription( $product ) ) {
			$is = true;
		}

		return $is;
	}

	/**
	 * Delete object meta in use by the application layer.
	 *
	 * @param  WC_Product  $product
	 */
	public static function delete_reserved_meta( $product ) {
		$reserved_meta_keys = array( 'has_forced_subscription', 'subscription_schemes', 'active_subscription_scheme_key', 'default_subscription_scheme_key' );
		foreach ( $reserved_meta_keys as $reserved_meta_key ) {
			$product->delete_meta_data( '_' . $reserved_meta_key );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Helpers
	|--------------------------------------------------------------------------
	*/

	private static function filter_schemes_by_context( $schemes, $context ) {

		$filtered = array();

		foreach ( $schemes as $key => $scheme ) {
			if ( $context === $scheme->get_context() ) {
				$filtered[ $key ] = $scheme;
			}
		}

		return $filtered;
	}
}

WCS_ATT_Product::init();
