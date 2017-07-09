<?php
/**
 * WCS_ATT_Product_Schemes API
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
 * API for working with the subscription schemes of subscription-enabled product objects.
 *
 * @class    WCS_ATT_Product_Schemes
 * @version  2.0.0
 */
class WCS_ATT_Product_Schemes {

	/*
	|--------------------------------------------------------------------------
	| Conditionals
	|--------------------------------------------------------------------------
	*/

	/**
	 * Determines if the product can be purchased on a recurring basis.
	 *
	 * @param  WC_Product  $product  Product object to check.
	 * @return boolean               Result of check.
	 */
	public static function has_subscription_schemes( $product ) {
		return sizeof( self::get_subscription_schemes( $product ) ) > 0;
	}

	/**
	 * Determines if the product is purchasable on a recurring basis only.
	 *
	 * @param  WC_Product  $product  Product object to check.
	 * @return boolean               Result of check.
	 */
	public static function has_forced_subscription_scheme( $product ) {

		if ( '' === ( $forced = WCS_ATT_Product::get_product_property( $product, 'has_forced_subscription' ) ) && self::has_subscription_schemes( $product ) ) {

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

			WCS_ATT_Product::set_product_property( $product, 'has_forced_subscription', $forced );
		}

		return 'yes' === $forced;
	}

	/**
	 * Determines if the product is currently set to be purchased on a recurring basis.
	 *
	 * @param  WC_Product  $product  Product object to check.
	 * @return boolean               Result of check.
	 */
	public static function has_active_subscription_scheme( $product ) {
		$active_scheme_key = self::get_subscription_scheme( $product );
		return ! empty( $active_scheme_key );
	}

	/*
	|--------------------------------------------------------------------------
	| Getters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns all subscription schemes associated with a product.
	 *
	 * @param  WC_Product  $product  Product object.
	 * @param  string      $context  Context of schemes. Values: 'cart', 'product', 'any'.
	 * @return array
	 */
	public static function get_subscription_schemes( $product, $context = 'any' ) {

		$schemes = WCS_ATT_Product::get_product_property( $product, 'subscription_schemes' );

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

				WCS_ATT_Product::set_product_property( $product, 'subscription_schemes', $schemes );
			}
		}

		if ( 'any' !== $context ) {
			$schemes = self::filter_by_context( $schemes, $context );
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

		$active_key   = WCS_ATT_Product::get_product_property( $product, 'active_subscription_scheme_key' );
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

		if ( '' === ( $default_scheme_key = WCS_ATT_Product::get_product_property( $product, 'default_subscription_scheme_key' ) ) ) {

			$default_scheme     = null;
			$default_scheme_key = false;

			if ( self::has_subscription_schemes( $product ) ) {

				$schemes = self::get_subscription_schemes( $product );

				if ( self::has_forced_subscription_scheme( $product ) ) {

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

	/**
	 * Returns the "base" subscription scheme by finding the one with the lowest recurring price.
	 * If prices are equal, no interval-based comparison is carried out:
	 * Reason: In some applications "$5 every week for 2 weeks" (=$10) might be seen as "cheaper" than "$5 every month for 3 months" (=$15), and in some the opposite.
	 * Instead of making guesswork and complex calculations, we can let scheme order be used to define the "base" scheme manually.
	 *
	 * @param  WC_Product  $product
	 * @return string
	 */
	public static function get_base_subscription_scheme( $product ) {

		$base_scheme = null;
		$schemes     = self::get_subscription_schemes( $product );

		if ( ! empty( $schemes ) ) {

			$product_price       = WCS_ATT_Core_Compatibility::get_prop( $product, 'price' );
			$price_filter_exists = self::price_filter_exists( $schemes );
			$base_scheme         = current( $schemes );
			$base_scheme_price   = $product_price;

			if ( $price_filter_exists ) {

				foreach ( $schemes as $scheme ) {

					$scheme_price = WCS_ATT_Product_Prices::get_price( $product, $scheme->get_key(), 'edit' );

					if ( $scheme_price < $base_scheme_price ) {

						$base_scheme       = $scheme;
						$base_scheme_price = $scheme_price;

					} elseif ( $scheme_price === $base_scheme_price ) {

						$scheme_discount = $scheme->get_discount();

						if ( $scheme_discount && ( is_null( $base_scheme ) || $base_scheme->get_discount() < $scheme_discount ) ) {
							$base_scheme       = $scheme;
							$base_scheme_price = $scheme_price;
						}
					}
				}
			}
		}

		return apply_filters( 'wcsatt_get_base_scheme', $base_scheme, $product );
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
		WCS_ATT_Product::set_product_property( $product, 'subscription_schemes', $schemes );
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
			WCS_ATT_Product::set_product_property( $product, 'active_subscription_scheme_key', $key );

			/*
			 * Set subscription scheme details.
			 *
			 * Note that prices are not set directly on object:
			 * The price strings of many product types depend on more than the values returned by the abstract class price getters.
			 * If we are going to apply filters anyway, there's no need to permanently set raw prices here.
			 */
			WCS_ATT_Product::set_product_property( $product, 'subscription_period', $scheme_to_set[ 'subscription_period' ] );
			WCS_ATT_Product::set_product_property( $product, 'subscription_period_interval', $scheme_to_set[ 'subscription_period_interval' ] );
			WCS_ATT_Product::set_product_property( $product, 'subscription_length', $scheme_to_set[ 'subscription_length' ] );

			$scheme_set = true;

		} elseif ( empty( $key ) ) {

			// Reset subscription scheme key.
			WCS_ATT_Product::set_product_property( $product, 'active_subscription_scheme_key', false === $key ? false : null );

			// Reset subscription scheme details.
			WCS_ATT_Product::set_product_property( $product, 'subscription_period', null );
			WCS_ATT_Product::set_product_property( $product, 'subscription_period_interval', null );
			WCS_ATT_Product::set_product_property( $product, 'subscription_length', null );

			$scheme_set = true;
		}

		/**
		 * Action 'wcsatt_set_product_subscription_scheme'.
		 *
		 * @param  mixed       $key
		 * @param  mixed       $active_scheme_key
		 * @param  WC_Product  $product
		 */
		do_action( 'wcsatt_set_product_subscription_scheme', $key, $active_scheme_key, $product );

		return $scheme_set;
	}

	/**
	 * Set the product as purchasable on a recurring basis only.
	 *
	 * @param  WC_Product  $product                 Product object to set.
	 * @param  boolean     $is_forced_subscription  Value.
	 */
	public static function set_forced_subscription_scheme( $product, $is_forced_subscription ) {
		WCS_ATT_Product::set_product_property( $product, 'has_forced_subscription', $is_forced_subscription ? 'yes' : 'no' );
	}

	/*
	|--------------------------------------------------------------------------
	| Helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Indicates whether the product price is modified by one or more subscription schemes.
	 *
	 * @param  array  $subscription_schemes
	 * @return boolean
	 */
	public static function price_filter_exists( $subscription_schemes ) {

		$price_filter_exists = false;

		foreach ( $subscription_schemes as $subscription_scheme ) {
			if ( $subscription_scheme->has_price_filter() ) {
				$price_filter_exists = true;
				break;
			}
		}

		return $price_filter_exists;
	}

	/**
	 * Filter schemes by context.
	 *
	 * @param  array   $schemes
	 * @param  string  $context
	 * @return array
	 */
	private static function filter_by_context( $schemes, $context ) {

		$filtered = array();

		foreach ( $schemes as $key => $scheme ) {
			if ( $context === $scheme->get_context() ) {
				$filtered[ $key ] = $scheme;
			}
		}

		return $filtered;
	}
}
