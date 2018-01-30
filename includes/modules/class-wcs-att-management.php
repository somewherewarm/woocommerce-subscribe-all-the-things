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
class WCS_ATT_Management extends WCS_ATT_Abstract_Module {

	/**
	 * Management modules.
	 *
	 * @var array
	 */
	private static $modules = array();

	/**
	 * Initialization.
	 */
	public static function initialize() {
		self::register_modules();
		parent::initialize();
	}

	/**
	 * Initialize modules.
	 */
	private static function register_modules() {

		// Line item switching module.
		require_once( 'management/class-wcs-att-switch.php' );
		// Add-to-subscription module.
		require_once( 'management/class-wcs-att-add.php' );

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
	 * Register hooks by component type.
	 *
	 * @param  string  $component
	 * @return void
	 */
	public static function register_hooks( $component ) {
		foreach ( self::$modules as $module ) {
			$module::register_hooks( $component );
		}
	}
}
