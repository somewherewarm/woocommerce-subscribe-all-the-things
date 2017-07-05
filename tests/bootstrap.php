<?php
/**
 * Bootstrap class
 *
 * @author   SomewhereWarm <sw@somewherewarm.net>
 * @package  WooCommerce Subscribe All The Things
 * @since    1.2.0
 */

/**
 * The test suite bootstrap.
 *
 * @since 1.2.0
 */
class WCS_ATT_Unit_Tests_Bootstrap {

	/**
	 * The instance.
	 * @var WCS_ATT_Unit_Tests_Bootstrap
	 */
	protected static $instance = null;

	/**
	 * The ID of the plugin.
	 * @var string
	 */
	private $plugin_id = 'woocommerce-subscribe-all-the-things';

	/**
	 * The plugin tests directory.
	 * @var string
	 */
	private $tests_dir;

	/**
	 * The WP tests library directory.
	 * @var string
	 */
	private $wp_tests_dir;

	/**
	 * The required plugins directory.
	 * @var string
	 */
	private $wp_plugins_dir;

	/**
	 * Get the single class instance.
	 *
	 * @since  1.2.0
	 *
	 * @return WCS_ATT_Unit_Tests_Bootstrap
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructs the bootstrap class.
	 *
	 * @since 1.2.0
	 */
	public function __construct() {

		$this->tests_dir      = dirname( __FILE__ );
		$this->wp_tests_dir   = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/tmp/wordpress-tests-lib';
		$this->wp_plugins_dir = dirname( dirname( dirname( __FILE__ ) ) );

		require_once( $this->wp_tests_dir . '/includes/functions.php' );

		tests_add_filter( 'plugins_loaded', array( $this, 'load_plugins' ) );

		// Load the WCS testing environment.
		require_once( $this->wp_plugins_dir . '/woocommerce-subscriptions/tests/bootstrap.php' );

		$this->includes();
	}

	/**
	 * Loads the required files.
	 *
	 * @since 1.2.0
	 */
	private function includes() {

		// Load WC Helper functions/Frameworks and Factories.
		require_once( $this->wp_plugins_dir . '/woocommerce/tests/framework/helpers/class-wc-helper-product.php' );
		require_once( $this->wp_plugins_dir . '/woocommerce/tests/framework/helpers/class-wc-helper-order.php' );
		require_once( $this->wp_plugins_dir . '/woocommerce/tests/framework/helpers/class-wc-helper-shipping.php' );

		// Helpers.
		require_once( $this->tests_dir . '/framework/helpers/class-wcs-att-test-helpers-product.php' );

		// Test cases.
		require_once( $this->tests_dir . '/framework/class-wcs-att-test-case.php' );
	}

	/**
	 * Loads plugins.
	 *
	 * @since 1.2.0
	 */
	public function load_plugins() {

		// Load SATT.
		require_once( trailingslashit( dirname( $this->tests_dir ) ) . $this->plugin_id . '.php' );

		WCS_ATT()->includes();
	}
}

WCS_ATT_Unit_Tests_Bootstrap::instance();
