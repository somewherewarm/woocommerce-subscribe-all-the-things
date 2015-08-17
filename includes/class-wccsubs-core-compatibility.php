<?php
/**
 * Functions related to core back-compatibility.
 *
 * @class  WCCSubs_Core_Compatibility
 * @since  1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCSubs_Core_Compatibility {

	/**
	 * Helper method to get the version of the currently installed WooCommerce
	 *
	 * @since 1.0.0
	 * @return string woocommerce version number or null
	 */
	private static function get_wc_version() {

		return defined( 'WC_VERSION' ) && WC_VERSION ? WC_VERSION : null;
	}

	/**
	 * Returns true if the installed version of WooCommerce is 2.4 or greater
	 *
	 * @since 1.0.0
	 * @return boolean true if the installed version of WooCommerce is 2.2 or greater
	 */
	public static function is_wc_version_gte_2_4() {
		return self::get_wc_version() && version_compare( self::get_wc_version(), '2.4', '>=' );
	}

	/**
	 * Returns true if the installed version of WooCommerce is 2.3 or greater
	 *
	 * @since 1.0.0
	 * @return boolean true if the installed version of WooCommerce is 2.2 or greater
	 */
	public static function is_wc_version_gte_2_3() {
		return self::get_wc_version() && version_compare( self::get_wc_version(), '2.3', '>=' );
	}

	/**
	 * Returns true if the installed version of WooCommerce is 2.2 or greater
	 *
	 * @since 1.0.0
	 * @return boolean true if the installed version of WooCommerce is 2.2 or greater
	 */
	public static function is_wc_version_gte_2_2() {
		return self::get_wc_version() && version_compare( self::get_wc_version(), '2.2', '>=' );
	}

	/**
	 * Returns true if the installed version of WooCommerce is less than 2.2
	 *
	 * @since 1.0.0
	 * @return boolean true if the installed version of WooCommerce is less than 2.2
	 */
	public static function is_wc_version_lt_2_2() {
		return self::get_wc_version() && version_compare( self::get_wc_version(), '2.2', '<' );
	}

	/**
	 * Returns true if the installed version of WooCommerce is greater than $version
	 *
	 * @since 1.0.0
	 * @param string $version the version to compare
	 * @return boolean true if the installed version of WooCommerce is > $version
	 */
	public static function is_wc_version_gt( $version ) {
		return self::get_wc_version() && version_compare( self::get_wc_version(), $version, '>' );
	}
}
