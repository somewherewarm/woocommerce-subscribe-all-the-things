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

	// directory storing dependency plugins
	public $modules_dir;

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
		$this->modules_dir    = dirname( dirname( $this->tests_dir ) );
		$this->wp_tests_dir   = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/tmp/wordpress-tests-lib';
		$this->wp_plugins_dir = dirname( dirname( dirname( __FILE__ ) ) );

		require_once( $this->wp_tests_dir . '/includes/functions.php' );

		tests_add_filter( 'plugins_loaded', array( $this, 'load_plugins' ) );

		// load WC
		tests_add_filter( 'muplugins_loaded', array( $this, 'load_wc' ) );

		// load Subscriptions after $this->load_wc() finishes and calls 'woocommerce_init'
		tests_add_filter( 'woocommerce_init', array( $this, 'load_wcs' ) );

		// install WC
		tests_add_filter( 'setup_theme', array( $this, 'install_wc' ) );

		// install WCS
		tests_add_filter( 'setup_theme', array( $this, 'install_wcs' ) );

		// load the WP testing environment
		require_once( $this->wp_tests_dir . '/includes/bootstrap.php' );

		$this->includes();
	}

/**
	 * Load WooCommerce
	 *
	 * @since 2.0
	 */
	public function load_wc() {
		require_once( $this->modules_dir . '/woocommerce/woocommerce.php' );
	}

	/**
	 * Load Subscriptions
	 *
	 * @since  2.0
	 */
	public function load_wcs() {
		require_once( $this->modules_dir . '/woocommerce-subscriptions/woocommerce-subscriptions.php' );
	}

	/**
	 * Load WooCommerce for testing
	 *
	 * @since 2.0
	 */
	function install_wc() {

		echo "Installing WooCommerce..." . PHP_EOL;

		define( 'WP_UNINSTALL_PLUGIN', true );

		include( $this->modules_dir . '/woocommerce/uninstall.php' );

		WC_Install::install();

		// reload capabilities after install, see https://core.trac.wordpress.org/ticket/28374
		if ( version_compare( $GLOBALS['wp_version'], '4.9', '>=' ) && method_exists( $GLOBALS['wp_roles'], 'for_site' ) ) {
			/** @see: https://core.trac.wordpress.org/ticket/38645 */
			$GLOBALS['wp_roles']->for_site();
		} elseif ( version_compare( $GLOBALS['wp_version'], '4.7', '>=' ) ) {
			// Do the right thing based on https://core.trac.wordpress.org/ticket/23016
			$GLOBALS['wp_roles'] = new WP_Roles();
		} else {
			// Fall back to the old method.
			$GLOBALS['wp_roles']->reinit();
		}

		// Set Subscriptions install data so that Gifting won't exit early
		$active_plugins   = get_option( 'active_plugins', array() );
		$active_plugins[] = 'woocommerce/woocommerce.php';
		update_option( 'active_plugins', $active_plugins );

		WC()->init();

		echo "WooCommerce Finished Installing..." . PHP_EOL;
	}

	/**
	 * Set default values on subscriptions
	 *
	 * @since  2.0
	 */
	public function install_wcs() {

		echo "Installing Subscriptions..." . PHP_EOL;

		WC_Subscriptions::maybe_activate_woocommerce_subscriptions();

		WC_Subscriptions::load_dependant_classes();

		// Set Subscriptions install data so that Gifting won't exit early
		$active_plugins   = get_option( 'active_plugins', array() );
		$active_plugins[] = 'woocommerce-subscriptions/woocommerce-subscriptions.php';
		update_option( 'active_plugins', $active_plugins );
		update_option( WC_Subscriptions_Admin::$option_prefix . '_active_version', WC_Subscriptions::$version );

		WC_Subscriptions::register_order_types();

		// set active and inactive subscriber roles
		update_option( WC_Subscriptions_Admin::$option_prefix . '_subscriber_role', 'subscriber' );
		update_option( WC_Subscriptions_Admin::$option_prefix . '_cancelled_role', 'customer' );

		echo "Subscriptions Finished Installing..." . PHP_EOL;
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
