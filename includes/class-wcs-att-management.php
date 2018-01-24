<?php
/**
 * WCS_ATT_Management class
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
 * Handles subscription object management functions, e.g. add, edit/switch, delete.
 *
 * @class    WCS_ATT_Management
 * @version  2.1.0
 */
class WCS_ATT_Management {

	/**
	 * Management modules.
	 *
	 * @var array
	 */
	private static $modules = array();

	/**
	 * Initialization.
	 */
	public static function init() {
		self::register_modules();
		self::register_hooks( 'core' );
	}

	/**
	 * Initialize modules.
	 */
	private static function register_modules() {

		// Line item switching module.
		require_once( 'modules/management/class-wcs-att-switch.php' );
		// Add-to-subscription module.
		require_once( 'modules/management/class-wcs-att-add.php' );

		// Initialize modules.
		self::$modules = apply_filters( 'wcsatt_management_modules', array(
			'WCS_ATT_Switch',
			'WCS_ATT_Add'
		) );

		foreach ( self::$modules as $module ) {
			$module::initialize();
		}
	}

	/**
	 * Register hooks by type.
	 *
	 * @param  string  $type
	 * @return void
	 */
	public static function register_hooks( $type ) {
		foreach ( self::$modules as $module ) {
			$module::register_hooks( $type );
		}
	}
}

WCS_ATT_Management::init();
