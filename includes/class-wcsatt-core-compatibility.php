<?php
/**
 * WCS_ATT_Core_Compatibility class
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
 * WC Core compatibility functions.
 *
 * @class    WCS_ATT_Core_Compatibility
 * @version  1.0.0
 */
class WCS_ATT_Core_Compatibility {

	/**
	 * Cache 'gte' comparison results.
	 * @var array
	 */
	private static $is_wc_version_gte = array();

	/**
	 * Cache 'gt' comparison results.
	 * @var array
	 */
	private static $is_wc_version_gt = array();

	/*
	|--------------------------------------------------------------------------
	| WC version getters.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Helper method to get the version of the currently installed WooCommerce
	 *
	 * @since  1.0.0
	 * @return string woocommerce version number or null
	 */
	private static function get_wc_version() {
		return defined( 'WC_VERSION' ) && WC_VERSION ? WC_VERSION : null;
	}

	/**
	 * Returns true if the installed version of WooCommerce is 2.7 or greater.
	 *
	 * @since  1.1.2
	 * @return boolean
	 */
	public static function is_wc_version_gte_2_7() {
		return self::is_wc_version_gte( '2.7' );
	}

	/**
	 * Returns true if the installed version of WooCommerce is 2.6 or greater.
	 *
	 * @since  1.0.4
	 * @return boolean
	 */
	public static function is_wc_version_gte_2_6() {
		return self::is_wc_version_gte( '2.6' );
	}

	/**
	 * Returns true if the installed version of WooCommerce is 2.5 or greater.
	 *
	 * @since  1.0.4
	 * @return boolean
	 */
	public static function is_wc_version_gte_2_5() {
		return self::is_wc_version_gte( '2.5' );
	}

	/**
	 * Returns true if the installed version of WooCommerce is 2.4 or greater.
	 *
	 * @since  1.0.0
	 * @return boolean
	 */
	public static function is_wc_version_gte_2_4() {
		return self::is_wc_version_gte( '2.4' );
	}

	/**
	 * Returns true if the installed version of WooCommerce is 2.3 or greater.
	 *
	 * @since  1.0.0
	 * @return boolean
	 */
	public static function is_wc_version_gte_2_3() {
		return self::is_wc_version_gte( '2.3' );
	}

	/**
	 * Returns true if the installed version of WooCommerce is greater than or equal to $version.
	 *
	 * @since  2.0.0
	 *
	 * @param  string  $version
	 * @return boolean
	 */
	public static function is_wc_version_gte( $version ) {
		if ( ! isset( self::$is_wc_version_gte[ $version ] ) ) {
			self::$is_wc_version_gte[ $version ] = self::get_wc_version() && version_compare( self::get_wc_version(), $version, '>=' );
		}
		return self::$is_wc_version_gte[ $version ];
	}

	/**
	 * Returns true if the installed version of WooCommerce is greater than $version.
	 *
	 * @since  1.0.0
	 *
	 * @param  string  $version
	 * @return boolean
	 */
	public static function is_wc_version_gt( $version ) {
		if ( ! isset( self::$is_wc_version_gt[ $version ] ) ) {
			self::$is_wc_version_gt[ $version ] = self::get_wc_version() && version_compare( self::get_wc_version(), $version, '>' );
		}
		return self::$is_wc_version_gt[ $version ];
	}

	/*
	|--------------------------------------------------------------------------
	| Wrapper functions for backwards compatibility.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Back-compat wrapper for getting CRUD object props directly.
	 *
	 * @since  2.0.0
	 *
	 * @param  WC_Product  $product
	 * @return string
	 */
	public static function get_price_html_from_text( $product ) {
		if ( self::is_wc_version_gte_2_7() ) {
			$value = wc_get_price_html_from_text();
		} else {
			$value = $product->get_price_html_from_text();
		}
		return $value;
	}

	/**
	 * Back-compat wrapper for 'get_parent_id'.
	 *
	 * @since  2.0.0
	 *
	 * @param  WC_Product  $product
	 * @return mixed
	 */
	public static function get_parent_id( $product ) {
		if ( self::is_wc_version_gte_2_7() ) {
			return $product->get_parent_id();
		} else {
			return $product->is_type( 'variation' ) ? absint( $product->id ) : 0;
		}
	}

	/**
	 * Back-compat wrapper for 'get_id'.
	 *
	 * @since  2.0.0
	 *
	 * @param  WC_Product  $product
	 * @return mixed
	 */
	public static function get_id( $product ) {
		if ( self::is_wc_version_gte_2_7() ) {
			$id = $product->get_id();
		} else {
			$id = $product->is_type( 'variation' ) ? absint( $product->variation_id ) : absint( $product->id );
		}
		return $id;
	}

	/**
	 * Back-compat wrapper for 'get_parent_id' with fallback to 'get_id'.
	 *
	 * @since  2.0.0
	 *
	 * @param  WC_Product  $product
	 * @return mixed
	 */
	public static function get_product_id( $product ) {
		if ( self::is_wc_version_gte_2_7() ) {
			$parent_id = $product->get_parent_id();
			return $parent_id ? $parent_id : $product->get_id();
		} else {
			return absint( $product->id );
		}
	}

	/**
	 * Back-compat wrapper for getting CRUD object props directly.
	 *
	 * @since  2.0.0
	 *
	 * @param  object  $obj
	 * @param  string  $name
	 * @return mixed
	 */
	public static function get_prop( $obj, $name ) {
		if ( self::is_wc_version_gte_2_7() ) {
			$get_fn = 'get_' . $name;
			$value = is_callable( array( $obj, $get_fn ) ) ? $obj->$get_fn( 'edit' ) : null;
		} else {
			$value = $obj->$name;
		}
		return $value;
	}

	/**
	 * Back-compat wrapper for setting CRUD object props directly.
	 *
	 * @since  2.0.0
	 *
	 * @param  object  $obj
	 * @param  string  $name
	 * @param  mixed   $value
	 * @return void
	 */
	public static function set_prop( $obj, $name, $value ) {
		if ( self::is_wc_version_gte_2_7() ) {
			$set_fn = 'set_' . $name;
			if ( is_callable( array( $obj, $set_fn ) ) ) {
				$obj->$set_fn( $value );
			} else {
				$obj->$name = $value;
			}
		} else {
			$obj->$name = $value;
		}
	}

	/**
	 * Back-compat wrapper for 'wc_get_price_including_tax'.
	 *
	 * @since  2.0.0
	 *
	 * @param  WC_Product  $product
	 * @param  array       $args
	 * @return mixed
	 */
	public static function wc_get_price_including_tax( $product, $args ) {
		if ( self::is_wc_version_gte_2_7() ) {
			return wc_get_price_including_tax( $product, $args );
		} else {

			$qty   = isset( $args[ 'qty' ] ) ? $args[ 'qty' ] : 1;
			$price = isset( $args[ 'price' ] ) ? $args[ 'price' ] : '';

			return $product->get_price_including_tax( $qty, $price );
		}
	}

	/**
	 * Back-compat wrapper for 'wc_get_price_excluding_tax'.
	 *
	 * @since  2.0.0
	 *
	 * @param  WC_Product  $product
	 * @param  array       $args
	 * @return mixed
	 */
	public static function wc_get_price_excluding_tax( $product, $args ) {
		if ( self::is_wc_version_gte_2_7() ) {
			return wc_get_price_excluding_tax( $product, $args );
		} else {

			$qty   = isset( $args[ 'qty' ] ) ? $args[ 'qty' ] : 1;
			$price = isset( $args[ 'price' ] ) ? $args[ 'price' ] : '';

			return $product->get_price_excluding_tax( $qty, $price );
		}
	}

	/**
	 * Back-compat wrapper for 'wc_get_price_to_display'.
	 *
	 * @param  WC_Product  $product
	 * @param  array       $args
	 * @return double
	 */
	public static function wc_get_price_to_display( $product, $args = array() ) {

		if ( self::is_wc_version_gte_2_7() ) {
			return wc_get_price_to_display( $product, $args );
		} else {

			$price = isset( $args[ 'price' ] ) ? $args[ 'price' ] : '';
			$qty   = isset( $args[ 'qty' ] ) ? $args[ 'qty' ] : 1;

			return $product->get_display_price( $price, $qty );
		}
	}

	/**
	 * Back-compat wrapper for 'WC_Product_Factory::get_product_type'.
	 *
	 * @since  3.9.0
	 *
	 * @param  mixed  $product_id
	 * @return mixed
	 */
	public static function get_product_type( $product_id ) {
		$product_type = false;
		if ( $product_id ) {
			if ( self::is_wc_version_gte_2_7() ) {
				$product_type = WC_Product_Factory::get_product_type( $product_id );
			} else {
				$terms        = get_the_terms( $product_id, 'product_type' );
				$product_type = ! empty( $terms ) && isset( current( $terms )->name ) ? sanitize_title( current( $terms )->name ) : 'simple';
			}
		}
		return $product_type;
	}

	/**
	 * Display a WooCommerce help tip.
	 *
	 * @since  1.0.4
	 *
	 * @param  string $tip
	 * @return string
	 */
	public static function wc_help_tip( $tip ) {
		if ( self::is_wc_version_gte_2_5() ) {
			return wc_help_tip( $tip );
		} else {
			return '<img class="help_tip woocommerce-help-tip" data-tip="' . $tip . '" src="' . WC()->plugin_url() . '/assets/images/help.png" />';
		}
	}
}
