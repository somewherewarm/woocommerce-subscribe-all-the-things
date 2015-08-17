<?php
/*
* Plugin Name: WooCommerce Cart Subscriptions
* Plugin URI: http://www.woothemes.com/products/woocommerce-subscriptions/
* Description: Create subscriptions from physical products in the cart.
* Version: 1.0.0
* Author: Prospress
* Author URI: http://prospress.com/
* Developer: Manos Psychogyiopoulos
* Developer URI: http://somewherewarm.net/
*
* Text Domain: woocommerce-subscriptions-cart
* Domain Path: /languages/
*
* Requires at least: 3.8
* Tested up to: 4.2
*
* Copyright: © 2009-2015 Prospress, Inc.
* License: GNU General Public License v3.0
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WCCSubs' ) ) :

class WCCSubs {

	/* plugin version */
	const VERSION = '1.0.0';

	/* required WC version */
	const REQ_WC_VERSION = '2.3.0';

	/* text domain */
	const TEXT_DOMAIN = 'woocommerce-subscriptions-cart';

	/**
	 * @var WCCSubs - the single instance of the class.
	 *
	 * @since 1.0.0
	 */
	protected static $_instance = null;

	/**
	 * Main WCCSubs Instance.
	 *
	 * Ensures only one instance of WCCSubs is loaded or can be loaded.
	 *
	 * @static
	 * @see WCCSubs()
	 * @return WCCSubs - Main instance
	 * @since 1.0.0
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Foul!' ), '1.0.0' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Foul!' ), '1.0.0' );
	}

	/**
	 * Do some work.
	 */
	public function __construct() {

		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
		add_action( 'init', array( $this, 'init_textdomain' ) );
		add_action( 'admin_init', array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	public function plugin_url() {
		return plugins_url( basename( plugin_dir_path(__FILE__) ), basename( __FILE__ ) );
	}

	public function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

	public function plugins_loaded() {

		global $woocommerce;

		// WC 2 check
		if ( version_compare( $woocommerce->version, self::REQ_WC_VERSION ) < 0 ) {
			add_action( 'admin_notices', array( $this, 'admin_notice' ) );
			return false;
		}

		require_once( 'includes/class-wccsubs-schemes.php' );
		require_once( 'includes/class-wccsubs-cart.php' );
		require_once( 'includes/class-wccsubs-display.php' );

		// Admin includes
		if ( is_admin() ) {
			$this->admin_includes();
		}

	}

	/**
	 * Loads the Admin & AJAX filters / hooks.
	 *
	 * @return void
	 */
	public function admin_includes() {

		require_once( 'includes/admin/class-wccsubs-admin.php' );
	}

	/**
	 * Display a warning message if WC version check fails.
	 *
	 * @return void
	 */
	public function admin_notice() {

	    echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Cart Subscriptions requires at least WooCommerce %s in order to function. Please upgrade WooCommerce.', self::TEXT_DOMAIN ), self::REQ_WC_VERSION ) . '</p></div>';
	}

	/**
	 * Load textdomain.
	 *
	 * @return void
	 */
	public function init_textdomain() {

		load_plugin_textdomain( 'woocommerce-subscriptions-cart', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Store plugin version.
	 *
	 * @return void
	 */
	public function activate() {

		global $wpdb;

		$version = get_option( 'wccsubs_version', false );

		if ( $version === false ) {
			add_option( 'wccsubs_version', self::VERSION );
		} elseif ( version_compare( $version, self::VERSION, '<' ) ) {
			update_option( 'wccsubs_version', self::VERSION );
		}
	}

	/**
	 * Deactivate extension.
	 *
	 * @return void
	 */
	public function deactivate() {

		delete_option( 'wccsubs_version' );
	}

	/**
	 * True if the product corresponding to a cart item is one of the types supported by the plugin.
	 *
	 * @param  array  $cart_item
	 * @return boolean
	 */
	public function is_supported_product_type( $cart_item ) {

		$product         = $cart_item[ 'data' ];
		$product_type    = $cart_item[ 'data' ]->product_type;
		$supported_types = array( 'simple', 'variable', 'subscription', 'bundle', 'composite', 'mix-and-match', 'subscription_variation' );

		if ( in_array( $product_type, $supported_types ) ) {
			return true;
		}

		return false;
	}
}

endif; // end class_exists check

/**
 * Returns the main instance of WCCSubs to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return WooCommerce Cart Subscriptions
 */
function WCCSubs() {

  return WCCSubs::instance();
}

// Launch the whole plugin
$GLOBALS[ 'woocommerce_subscriptions_cart' ] = WCCSubs();
