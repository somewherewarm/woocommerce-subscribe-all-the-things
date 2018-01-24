<?php
/**
 * WCS_ATT_Abstract_Module class
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Subscribe All the Things
 * @since    2.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract class used as the foundation for SATT modules.
 *
 * @version 2.1.0
 */
abstract class WCS_ATT_Abstract_Module {

	/**
	 * Handles module initialization.
	 *
	 * @return void
	 */
	public static function initialize() {
		// Use static:: when declaring 'register_hooks' as abstract. Requires PHP 5.3+.
		self::register_hooks( 'core' );
	}

	/**
	 * Adds module hooks by type.
	 *
	 * Empty for PHP 5.2 compatibility, otherwise can be delared as abstract.
	 *
	 * @param  string  $type
	 * @return void
	 */
	public static function register_hooks( $type ) {

	}
}
