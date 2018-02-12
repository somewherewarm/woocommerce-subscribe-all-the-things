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
 * Modules are groupings of functionality that SATT uses to attach hooks associated with specific application components.
 * This is just a way to organize SATT code better.
 * See 'WCS_ATT::includes', 'WCS_ATT::register_modules' and 'WCS_ATT::register_component_hooks'.
 *
 * @version  2.1.0
 */
abstract class WCS_ATT_Abstract_Module {

	/**
	 * Sub-modules to instantiate.
	 * @var array
	 */
	protected $modules = array();

	/**
	 * Handles module initialization.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->register_hooks( 'core', false );
		$this->register_modules();
		$this->initialize_modules();
	}

	/**
	 * Include submodules.
	 *
	 * @return void
	 */
	protected function register_modules() {}

	/**
	 * Initialize submodules.
	 *
	 * @return void
	 */
	protected function initialize_modules() {

		$modules = array();

		foreach ( $this->modules as $module ) {
			$modules[] = new $module();
		}

		$this->modules = $modules;
	}

	/**
	 * Adds sub-module hooks by component type.
	 *
	 * @param  string  $component
	 * @return void
	 */
	protected function register_module_hooks( $component ) {
		foreach ( $this->modules as $module ) {
			$module->register_hooks( $component );
		}
	}

	/**
	 * Adds module hooks by component type.
	 *
	 * @param  string   $component
	 * @param  boolean  $register_module_hooks
	 * @return void
	 */
	public function register_hooks( $component, $register_module_hooks = true ) {

		$fn_name = 'register_' . $component . '_hooks';

		if ( is_callable( array( $this, $fn_name ) ) ) {
			$this->$fn_name();
		}

		if ( $register_module_hooks ) {
			$this->register_module_hooks( $component );
		}
	}
}
