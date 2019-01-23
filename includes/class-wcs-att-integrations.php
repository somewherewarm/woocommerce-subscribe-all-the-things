<?php
/**
 * WCS_ATT_Integrations class
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
 * Compatibility with other extensions.
 *
 * @class    WCS_ATT_Integrations
 * @version  2.1.5
 */
class WCS_ATT_Integrations {

	/**
	 * Complex product types integrated with SATT.
	 * @var array
	 */
	private static $bundle_types = array();

	/**
	 * Complex type container cart item getter function names.
	 * @var array
	 */
	private static $container_cart_item_getters = array();

	/**
	 * Complex type container order item getter function names.
	 * @var array
	 */
	private static $container_order_item_getters = array();

	/**
	 * Complex type container cart item getter function names.
	 * @var array
	 */
	private static $child_cart_item_getters = array();

	/**
	 * Complex type container order item getter function names.
	 * @var array
	 */
	private static $child_order_item_getters = array();

	/**
	 * Complex type container cart item conditional function names.
	 * @var array
	 */
	private static $container_cart_item_conditionals = array();

	/**
	 * Complex type container order item conditional function names.
	 * @var array
	 */
	private static $container_order_item_conditionals = array();

	/**
	 * Complex type container cart item conditional function names.
	 * @var array
	 */
	private static $child_cart_item_conditionals = array();

	/**
	 * Complex type container order item conditional function names.
	 * @var array
	 */
	private static $child_order_item_conditionals = array();

	/**
	 * Complex type container cart/order item key names.
	 *
	 * @deprecated  2.1.0
	 * @var         array
	 */
	public static $container_key_names = array();

	/**
	 * Complex type child cart/order item key names.
	 *
	 * @deprecated  2.1.0
	 * @var         array
	 */
	public static $child_key_names = array();

	/**
	 * Initialize.
	 */
	public static function init() {

		// Bundles.
		if ( class_exists( 'WC_Bundles' ) ) {
			self::$bundle_types[]                      = 'bundle';
			self::$container_key_names[]               = 'bundled_by';
			self::$child_key_names[]                   = 'bundled_items';
			self::$container_cart_item_getters[]       = 'wc_pb_get_bundled_cart_item_container';
			self::$container_order_item_getters[]      = 'wc_pb_get_bundled_order_item_container';
			self::$child_cart_item_getters[]           = 'wc_pb_get_bundled_cart_items';
			self::$child_order_item_getters[]          = 'wc_pb_get_bundled_order_items';
			self::$container_cart_item_conditionals[]  = 'wc_pb_is_bundle_container_cart_item';
			self::$container_order_item_conditionals[] = 'wc_pb_is_bundle_container_order_item';
			self::$child_cart_item_conditionals[]      = 'wc_pb_is_bundled_cart_item';
			self::$child_order_item_conditionals[]     = 'wc_pb_is_bundled_order_item';
		}

		// Composites.
		if ( class_exists( 'WC_Composite_Products' ) ) {
			self::$bundle_types[]                      = 'composite';
			self::$container_key_names[]               = 'composite_parent';
			self::$child_key_names[]                   = 'composite_children';
			self::$container_cart_item_getters[]       = 'wc_cp_get_composited_cart_item_container';
			self::$container_order_item_getters[]      = 'wc_cp_get_composited_order_item_container';
			self::$child_cart_item_getters[]           = 'wc_cp_get_composited_cart_items';
			self::$child_order_item_getters[]          = 'wc_cp_get_composited_order_items';
			self::$container_cart_item_conditionals[]  = 'wc_cp_is_composite_container_cart_item';
			self::$container_order_item_conditionals[] = 'wc_cp_is_composite_container_order_item';
			self::$child_cart_item_conditionals[]      = 'wc_cp_is_composited_cart_item';
			self::$child_order_item_conditionals[]     = 'wc_cp_is_composited_order_item';
		}

		// Mix n Match.
		if ( class_exists( 'WC_Mix_and_Match' ) ) {
			self::$bundle_types[]                      = 'mix-and-match';
			self::$container_key_names[]               = 'mnm_container';
			self::$child_key_names[]                   = 'mnm_contents';
			self::$container_cart_item_getters[]       = 'wc_mnm_get_mnm_cart_item_container';
			self::$container_order_item_getters[]      = 'wc_mnm_get_mnm_order_item_container';
			self::$child_cart_item_getters[]           = 'wc_mnm_get_mnm_cart_items';
			self::$child_order_item_getters[]          = 'wc_mnm_get_mnm_order_items';
			self::$container_cart_item_conditionals[]  = 'wc_mnm_is_mnm_container_cart_item';
			self::$container_order_item_conditionals[] = 'wc_mnm_is_mnm_container_order_item';
			self::$child_cart_item_conditionals[]      = 'wc_mnm_is_mnm_cart_item';
			self::$child_order_item_conditionals[]     = 'wc_mnm_is_mnm_order_item';
		}

		if ( ! empty( self::$bundle_types ) ) {
			self::add_hooks();
		}
	}

	/**
	 * Hook-in.
	 */
	private static function add_hooks() {

		/*
		 * All types: Application layer integration.
		 */

		// Schemes attached on bundles should not work if the bundle contains non-supported products, such as "legacy" subscription products.
		add_filter( 'wcsatt_product_subscription_schemes', array( __CLASS__, 'get_product_bundle_schemes' ), 10, 2 );

		// Hide child cart item options.
		add_filter( 'wcsatt_show_cart_item_options', array( __CLASS__, 'hide_child_item_options' ), 10, 3 );

		// Bundled/child items inherit the active subscription scheme of their parent.
		add_filter( 'wcsatt_set_subscription_scheme_id', array( __CLASS__, 'set_child_item_subscription_scheme' ), 10, 3 );

		// Bundled cart items inherit the subscription schemes of their parent, with some modifications.
		add_action( 'woocommerce_cart_loaded_from_session', array( __CLASS__, 'apply_child_item_subscription_schemes' ), 0 );

		// Bundled cart items inherit the subscription schemes of their parent, with some modifications (first add).
		add_filter( 'woocommerce_add_cart_item', array( __CLASS__, 'set_child_item_schemes' ), 0, 2 );

		/*
		 * All types: Display/templates integration.
		 */

		/*
		 * Cart.
		 */

		// Add subscription details next to subtotal of per-item-priced bundle-type container cart items.
		add_filter( 'woocommerce_cart_item_subtotal', array( __CLASS__, 'filter_container_item_subtotal' ), 1000, 3 );

		// Modify bundle container cart item options to include child item prices.
		add_filter( 'wcsatt_cart_item_options', array( __CLASS__, 'container_item_options' ), 10, 4 );

		/*
		 * Subscriptions management: 'My Account > Subscriptions' actions.
		 */

		// Don't count bundle-type child items and hidden bundle-type container/child items.
		add_filter( 'wcs_can_items_be_removed', array( __CLASS__, 'can_remove_subscription_items' ), 10, 2 );

		// Hide "Remove" buttons of child line items under 'My Account > Subscriptions'.
		add_filter( 'wcs_can_item_be_removed', array( __CLASS__, 'can_remove_child_subscription_item' ), 10, 3 );

		// Handle parent subscription line item removals under 'My Account > Subscriptions'.
		add_action( 'wcs_user_removed_item', array( __CLASS__, 'user_removed_parent_subscription_item' ), 10, 2 );

		// Handle parent subscription line item re-additions under 'My Account > Subscriptions'.
		add_action( 'wcs_user_readded_item', array( __CLASS__, 'user_readded_parent_subscription_item' ), 10, 2 );

		// Bundle-type products don't support scheme or content switching just yet. OK?
		add_filter( 'wcsatt_product_supports_feature', array( __CLASS__, 'bundle_supports_switching' ), 10, 4 );

		/*
		 * Subscriptions management: Add products/carts to subscriptions.
		 */

		// Modify the validation context when adding a bundle to an order.
		add_action( 'wcsatt_pre_add_product_to_subscription_validation', array( __CLASS__, 'set_bundle_type_validation_context' ), 10 );

		// Modify the validation context when adding a bundle to an order.
		add_action( 'wcsatt_post_add_product_to_subscription_validation', array( __CLASS__, 'reset_bundle_type_validation_context' ), 10 );

		// Don't attempt to increment the quantity of bundle-type subscription items when adding to an existing subscription.
		add_filter( 'wcsatt_add_cart_to_subscription_found_item', array( __CLASS__, 'found_bundle_in_subscription' ), 10, 4 );

		// Add bundles/composites to subscriptions.
		add_filter( 'wscatt_add_cart_item_to_subscription_callback', array( __CLASS__, 'add_bundle_to_subscription_callback' ), 10, 3 );

		/*
		 * Bundles.
		 */

		if ( class_exists( 'WC_Bundles' ) ) {

			// When loading bundled items, always set the active bundle scheme on the bundled objects.
			add_filter( 'woocommerce_bundled_items', array( __CLASS__, 'set_bundled_items_scheme' ), 10, 2 );

			// Add scheme data to runtime price cache hashes.
			add_filter( 'woocommerce_bundle_prices_hash', array( __CLASS__, 'bundle_prices_hash' ), 10, 2 );
		}

		/*
		 * Composites.
		 */

		if ( class_exists( 'WC_Composite_Products' ) ) {

			// Set the default scheme when one-time purchases are disabled, no scheme is set on the object, and only a single sub scheme exists.
			add_action( 'woocommerce_composite_synced', array( __CLASS__, 'set_single_composite_subscription_scheme' ) );

			// Ensure composites in cached component objects have up-to-date scheme data.
			add_action( 'wcsatt_set_product_subscription_scheme', array( __CLASS__, 'set_composite_product_scheme' ), 10, 3 );

			// Products in component option objects inherit the subscription schemes of their container object -- SLOW!
			add_filter( 'woocommerce_composite_component_option', array( __CLASS__, 'set_component_option_scheme' ), 10, 3 );

			// Add scheme data to runtime price cache hashes.
			add_filter( 'woocommerce_composite_prices_hash', array( __CLASS__, 'composite_prices_hash' ), 10, 2 );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Checks if the passed product is of a supported bundle type. Returns the type if yes, or false if not.
	 *
	 * @param  WC_Product  $product
	 * @return boolean
	 */
	public static function is_bundle_type_product( $product ) {
		return $product->is_type( self::$bundle_types );
	}

	/**
	 * Given a bundle-type child cart item, find and return its container cart item or its cart id when the $return_id arg is true.
	 *
	 * @since  2.1.0
	 *
	 * @param  array    $cart_item
	 * @param  array    $cart_contents
	 * @param  boolean  $return_id
	 * @return mixed
	 */
	public static function get_bundle_type_cart_item_container( $cart_item, $cart_contents = false, $return_id = false ) {

		$container = false;

		foreach ( self::$container_cart_item_getters as $container_cart_item_getter ) {
			$container = call_user_func_array( $container_cart_item_getter, array( $cart_item, $cart_contents, $return_id ) );
			if ( ! empty( $container ) ) {
				break;
			}
		}

		return $container;
	}

	/**
	 * Given a bundle-type container cart item, find and return its child cart items - or their cart ids when the $return_ids arg is true.
	 *
	 * @since  2.1.0
	 *
	 * @param  array    $cart_item
	 * @param  array    $cart_contents
	 * @param  boolean  $return_ids
	 * @return mixed
	 */
	public static function get_bundle_type_cart_items( $cart_item, $cart_contents = false, $return_ids = false ) {

		$children = array();

		foreach ( self::$child_cart_item_getters as $child_cart_item_getter ) {
			$children = call_user_func_array( $child_cart_item_getter, array( $cart_item, $cart_contents, $return_ids ) );
			if ( ! empty( $children ) ) {
				break;
			}
		}

		return $children;
	}

	/**
	 * True if a cart item appears to be a bundle-type container item.
	 *
	 * @since  2.1.0
	 *
	 * @param  array  $cart_item
	 * @return boolean
	 */
	public static function is_bundle_type_container_cart_item( $cart_item ) {

		$is = false;

		foreach ( self::$container_cart_item_conditionals as $container_cart_item_conditional ) {
			$is = call_user_func_array( $container_cart_item_conditional, array( $cart_item ) );
			if ( $is ) {
				break;
			}
		}

		return $is;
	}

	/**
	 * True if a cart item is part of a bundle-type product.
	 *
	 * @since  2.1.0
	 *
	 * @param  array  $cart_item
	 * @param  array  $cart_contents
	 * @return boolean
	 */
	public static function is_bundle_type_cart_item( $cart_item, $cart_contents = false ) {

		$is = false;

		foreach ( self::$child_cart_item_conditionals as $child_cart_item_conditional ) {
			$is = call_user_func_array( $child_cart_item_conditional, array( $cart_item, $cart_contents ) );
			if ( $is ) {
				break;
			}
		}

		return $is;
	}

	/**
	 * Given a bundle-type child order item, find and return its container order item or its order item id when the $return_id arg is true.
	 *
	 * @since  2.1.0
	 *
	 * @param  array     $order_item
	 * @param  WC_Order  $order
	 * @param  boolean   $return_id
	 * @return mixed
	 */
	public static function get_bundle_type_order_item_container( $order_item, $order = false, $return_id = false ) {

		$container = false;

		foreach ( self::$container_order_item_getters as $container_order_item_getter ) {
			$container = call_user_func_array( $container_order_item_getter, array( $order_item, $order, $return_id ) );
			if ( ! empty( $container ) ) {
				break;
			}
		}

		return $container;
	}

	/**
	 * Given a bundle-type container order item, find and return its child order items - or their order item ids when the $return_ids arg is true.
	 *
	 * @since  2.1.0
	 *
	 * @param  array     $order_item
	 * @param  WC_Order  $order
	 * @param  boolean   $return_ids
	 * @return mixed
	 */
	public static function get_bundle_type_order_items( $order_item, $order = false, $return_ids = false ) {

		$children = array();

		foreach ( self::$child_order_item_getters as $child_order_item_getter ) {
			$children = call_user_func_array( $child_order_item_getter, array( $order_item, $order, $return_ids ) );
			if ( ! empty( $children ) ) {
				break;
			}
		}

		return $children;
	}

	/**
	 * True if an order item appears to be a bundle-type container item.
	 *
	 * @since  2.1.0
	 *
	 * @param  array     $order_item
	 * @param  WC_Order  $order
	 * @return boolean
	 */
	public static function is_bundle_type_container_order_item( $order_item, $order = false ) {

		$is = false;

		foreach ( self::$container_order_item_conditionals as $container_order_item_conditional ) {
			$is = call_user_func_array( $container_order_item_conditional, array( $order_item, $order ) );
			if ( $is ) {
				break;
			}
		}

		return $is;
	}

	/**
	 * True if an order item is part of a bundle-type product.
	 *
	 * @since  2.1.0
	 *
	 * @param  array     $cart_item
	 * @param  WC_Order  $order
	 * @return boolean
	 */
	public static function is_bundle_type_order_item( $order_item, $order = false ) {

		$is = false;

		foreach ( self::$child_order_item_conditionals as $child_order_item_conditional ) {
			$is = call_user_func_array( $child_order_item_conditional, array( $order_item, $order ) );
			if ( $is ) {
				break;
			}
		}

		return $is;
	}

	/**
	 * True if there are sub schemes inherited from a container.
	 *
	 * @param  array  $cart_item
	 * @return boolean
	 */
	private static function has_scheme_data( $cart_item ) {

		$overrides = false;

		if ( isset( $cart_item[ 'wcsatt_data' ][ 'active_subscription_scheme' ] ) && null !== $cart_item[ 'wcsatt_data' ][ 'active_subscription_scheme' ] ) {
			$overrides = true;
		}

		return $overrides;
	}

	/**
	 * WC_Product_Bundle 'contains_sub' back-compat wrapper.
	 *
	 * @param  WC_Product_Bundle  $bundle
	 * @return boolean
	 */
	private static function bundle_contains_subscription( $bundle ) {

		if ( version_compare( WC_PB()->version, '5.0.0' ) < 0 ) {
			return $bundle->contains_sub();
		} else {
			return $bundle->contains( 'subscription' );
		}
	}

	/**
	 * WC_Product_Bundle and WC_Product_Composite 'is_priced_per_product' back-compat wrapper.
	 *
	 * @param  WC_Product  $bundle
	 * @return boolean
	 */
	private static function has_individually_priced_bundled_contents( $product ) {

		if ( 'bundle' === $product->get_type() ) {
			return version_compare( WC_PB()->version, '5.0.0' ) < 0 ? $product->is_priced_per_product() : $product->contains( 'priced_individually' );
		} elseif( 'composite' === $product->get_type() ) {
			return version_compare( WC_CP()->version, '3.7.0' ) < 0 ? $product->is_priced_per_product() : $product->contains( 'priced_individually' );
		}
	}

	/**
	 * Set the active bundle scheme on a bundled item.
	 *
	 * @param  WC_Bundled_Item    $bundled_item
	 * @param  WC_Product_Bundle  $bundle
	 */
	public static function set_bundled_item_scheme( $bundled_item, $bundle ) {

		// Callable since PB 5.2.4.
		if ( is_callable( array( $bundled_item, 'get_product' ) ) ) {

			$having = array(
				'price',
				'regular_price'
			);

			$what = array(
				'min',
				'max'
			);

			if ( $bundled_product = $bundled_item->get_product() ) {
				self::set_bundled_product_subscription_schemes( $bundled_product, $bundle );
			}

			foreach ( $having as $price ) {
				foreach ( $what as $min_or_max ) {
					if ( $bundled_product = $bundled_item->get_product( array( 'having' => $price, 'what' => $min_or_max ) ) ) {
						self::set_bundled_product_subscription_schemes( $bundled_product, $bundle );
					}
				}
			}
		}
	}

	/**
	 * Calculates bundle container item subtotals.
	 *
	 * @since  2.1.0
	 *
	 * @param  array   $cart_item
	 * @param  string  $scheme_key
	 * @return double
	 */
	private static function calculate_container_item_subtotal( $cart_item, $scheme_key ) {

		$product          = $cart_item[ 'data' ];
		$tax_display_cart = get_option( 'woocommerce_tax_display_cart' );

		if ( 'excl' === $tax_display_cart ) {
			$subtotal = wc_get_price_excluding_tax( $product, array( 'price' => WCS_ATT_Product_Prices::get_price( $product, $scheme_key ) ) );
		} else {
			$subtotal = wc_get_price_including_tax( $product, array( 'price' => WCS_ATT_Product_Prices::get_price( $product, $scheme_key ) ) );
		}

		$child_items = self::get_bundle_type_cart_items( $cart_item );

		if ( ! empty( $child_items ) ) {

			foreach ( $child_items as $child_key => $child_item ) {

				$child_qty = ceil( $child_item[ 'quantity' ] / $cart_item[ 'quantity' ] );

				if ( 'excl' === $tax_display_cart ) {
					$subtotal += wc_get_price_excluding_tax( $child_item[ 'data' ], array( 'price' => WCS_ATT_Product_Prices::get_price( $child_item[ 'data' ], $scheme_key ), 'qty' => $child_qty ) );
				} else {
					$subtotal += wc_get_price_including_tax( $child_item[ 'data' ], array( 'price' => WCS_ATT_Product_Prices::get_price( $child_item[ 'data' ], $scheme_key ), 'qty' => $child_qty ) );
				}
			}
		}

		return $subtotal;
	}

	/**
	 * Add bundles to subscriptions using 'WC_PB_Order::add_bundle_to_order'.
	 *
	 * @since  2.1.0
	 *
	 * @param  WC_Subscription  $subscription
	 * @param  array            $cart_item
	 * @param  WC_Cart          $recurring_cart
	 */
	public static function add_bundle_to_order( $subscription, $cart_item, $recurring_cart ) {

		$configuration = $cart_item[ 'stamp' ];

		// Copy child item totals over from recurring cart.
		foreach ( wc_pb_get_bundled_cart_items( $cart_item, $recurring_cart->cart_contents ) as $child_cart_item_key => $child_cart_item ) {

			$bundled_item_id = $child_cart_item[ 'bundled_item_id' ];

			$configuration[ $bundled_item_id ][ 'args' ] = array(
				'subtotal' => $child_cart_item[ 'line_total' ],
				'total'    => $child_cart_item[ 'line_subtotal' ]
			);
		}

		return WC_PB()->order->add_bundle_to_order( $cart_item[ 'data' ], $subscription, $cart_item[ 'quantity' ], array( 'configuration' => $configuration ) );
	}

	/**
	 * Add composites to subscriptions using 'WC_CP_Order::add_composite_to_order'.
	 *
	 * @since  2.1.0
	 *
	 * @param  WC_Subscription  $subscription
	 * @param  array            $cart_item
	 * @param  WC_Cart          $recurring_cart
	 */
	public static function add_composite_to_order( $subscription, $cart_item, $recurring_cart ) {

		$configuration = $cart_item[ 'composite_data' ];

		// Copy child item totals over from recurring cart.
		foreach ( wc_cp_get_composited_cart_items( $cart_item, $recurring_cart->cart_contents ) as $child_cart_item_key => $child_cart_item ) {

			$component_id = $child_cart_item[ 'composite_item' ];

			$configuration[ $component_id ][ 'args' ] = array(
				'subtotal' => $child_cart_item[ 'line_total' ],
				'total'    => $child_cart_item[ 'line_subtotal' ]
			);
		}

		return WC_CP()->order->add_composite_to_order( $cart_item[ 'data' ], $subscription, $cart_item[ 'quantity' ], array( 'configuration' => $configuration ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Hooks - Application
	|--------------------------------------------------------------------------
	*/

	/**
	 * Sub schemes attached on a Product Bundle should not work if the bundle contains a non-convertible product, such as a "legacy" subscription.
	 *
	 * @param  array       $schemes
	 * @param  WC_Product  $product
	 * @return array
	 */
	public static function get_product_bundle_schemes( $schemes, $product ) {

		if ( self::is_bundle_type_product( $product ) ) {
			if ( $product->is_type( 'bundle' ) && self::bundle_contains_subscription( $product ) ) {
				$schemes = array();
			} elseif ( $product->is_type( 'mix-and-match' ) && $product->is_priced_per_product() ) {
				$schemes = array();
			}
		}

		return $schemes;
	}

	/**
	 * Hide bundled cart item subscription options.
	 *
	 * @param  boolean  $show
	 * @param  array    $cart_item
	 * @param  string   $cart_item_key
	 * @return boolean
	 */
	public static function hide_child_item_options( $show, $cart_item, $cart_item_key ) {

		$container_cart_item = self::get_bundle_type_cart_item_container( $cart_item );

		if ( false !== $container_cart_item ) {
			if ( self::has_scheme_data( $container_cart_item ) ) {
				$show = false;
			}
		}

		return $show;
	}

	/**
	 * Bundled items inherit the active subscription scheme id of their parent.
	 *
	 * @param  string  $scheme_key
	 * @param  array   $cart_item
	 * @param  array   $cart_level_schemes
	 * @return string
	 */
	public static function set_child_item_subscription_scheme( $scheme_key, $cart_item, $cart_level_schemes ) {

		$container_cart_item = self::get_bundle_type_cart_item_container( $cart_item );

		if ( false !== $container_cart_item ) {
			if ( self::has_scheme_data( $container_cart_item ) ) {
				$scheme_key = $container_cart_item[ 'wcsatt_data' ][ 'active_subscription_scheme' ];
			}
		}

		return $scheme_key;
	}

	/**
	 * Bundled cart items inherit the subscription schemes of their parent, with some modifications.
	 *
	 * @param  WC_Cart  $cart
	 * @return void
	 */
	public static function apply_child_item_subscription_schemes( $cart ) {

		foreach ( $cart->cart_contents as $cart_item_key => $cart_item ) {

			// Is it a bundled item?
			$container_cart_item = self::get_bundle_type_cart_item_container( $cart_item );

			if ( false !== $container_cart_item ) {
				if ( self::has_scheme_data( $container_cart_item ) ) {
					self::set_bundled_product_subscription_schemes( $cart_item[ 'data' ], $container_cart_item[ 'data' ] );
				}
			}
		}
	}

	/**
	 * Copies product schemes to a child product.
	 *
	 * @param  WC_Product  $bundled_product
	 * @param  WC_Product  $container_product
	 */
	private static function set_bundled_product_subscription_schemes( $bundled_product, $container_product ) {

		$container_schemes       = WCS_ATT_Product_Schemes::get_subscription_schemes( $container_product );
		$bundled_product_schemes = WCS_ATT_Product_Schemes::get_subscription_schemes( $bundled_product );

		$container_schemes_hash       = '';
		$bundled_product_schemes_hash = '';

		foreach ( $container_schemes as $scheme_key => $scheme ) {
			$container_schemes_hash .= $scheme->get_hash();
		}

		foreach ( $bundled_product_schemes as $scheme_key => $scheme ) {
			$bundled_product_schemes_hash .= $scheme->get_hash();
		}

		// Copy container schemes to child.
		if ( $container_schemes_hash !== $bundled_product_schemes_hash ) {

			$bundled_product_schemes = array();

			// Modify child object schemes: "Override" pricing mode is only applicable for container.
			foreach ( $container_schemes as $scheme_key => $scheme ) {

				$bundled_product_schemes[ $scheme_key ] = clone $scheme;
				$bundled_product_scheme                 = $bundled_product_schemes[ $scheme_key ];

				if ( $bundled_product_scheme->has_price_filter() && 'override' === $bundled_product_scheme->get_pricing_mode() ) {
					$bundled_product_scheme->set_pricing_mode( 'inherit' );
					$bundled_product_scheme->set_discount( '' );
				}
			}

			WCS_ATT_Product_Schemes::set_subscription_schemes( $bundled_product, $bundled_product_schemes );
		}

		$container_scheme       = WCS_ATT_Product_Schemes::get_subscription_scheme( $container_product );
		$bundled_product_scheme = WCS_ATT_Product_Schemes::get_subscription_scheme( $bundled_product );
		$scheme_to_set          = is_null( $container_scheme ) ? false : $container_scheme;

		// Set active container scheme on child.
		if ( $scheme_to_set !== $bundled_product_scheme ) {
			WCS_ATT_Product_Schemes::set_subscription_scheme( $bundled_product, $scheme_to_set );
		}

		// Copy "Force Subscription" state.
		WCS_ATT_Product_Schemes::set_forced_subscription_scheme( $bundled_product, $scheme_to_set ? WCS_ATT_Product_Schemes::has_forced_subscription_scheme( $container_product ) : false );
	}

	/**
	 * Bundled cart items inherit the subscription schemes of their parent, with some modifications (first add).
	 *
	 * @param  array   $cart_item
	 * @param  string  $cart_item_key
	 * @return array
	 */
	public static function set_child_item_schemes( $cart_item, $cart_item_key ) {

		// Is it a bundled item?
		$container_cart_item = self::get_bundle_type_cart_item_container( $cart_item );

		if ( false !== $container_cart_item ) {
			if ( self::has_scheme_data( $container_cart_item ) ) {
				self::set_bundled_product_subscription_schemes( $cart_item[ 'data' ], $container_cart_item[ 'data' ] );
			}
		}

		return $cart_item;
	}

	/*
	|--------------------------------------------------------------------------
	| Hooks - Cart Templates
	|--------------------------------------------------------------------------
	*/

	/**
	 * Add subscription details next to subtotal of per-item-priced bundle-type container cart items.
	 *
	 * @param  string  $subtotal
	 * @param  array   $cart_item
	 * @param  string  $cart_item_key
	 * @return string
	 */
	public static function filter_container_item_subtotal( $subtotal, $cart_item, $cart_item_key ) {

		// MnM container subtotals originally modified by WCS are not overwritten by MnM.
		if ( $cart_item[ 'data' ]->is_type( 'mix-and-match' ) ) {
			return $subtotal;
		}

		if ( self::is_bundle_type_container_cart_item( $cart_item ) && self::has_scheme_data( $cart_item ) ) {

			if ( $scheme = WCS_ATT_Product_Schemes::get_subscription_scheme( $cart_item[ 'data' ], 'object' ) ) {

				if ( $scheme->is_synced() ) {
					$subtotal = wc_price( self::calculate_container_item_subtotal( $cart_item, $scheme->get_key() ) );
				}

				$subtotal = WCS_ATT_Product_Prices::get_price_string( $cart_item[ 'data' ], array(
					'price' => $subtotal
				) );
			}
		}

		return $subtotal;
	}

	/**
	 * Modify bundle container cart item subscription options to include child item prices.
	 *
	 * @param  array   $options
	 * @param  array   $subscription_schemes
	 * @param  array   $cart_item
	 * @param  string  $cart_item_key
	 * @return boolean
	 */
	public static function container_item_options( $options, $subscription_schemes, $cart_item, $cart_item_key ) {

		$child_items = self::get_bundle_type_cart_items( $cart_item );

		if ( ! empty( $child_items ) ) {

			$product                        = $cart_item[ 'data' ];
			$price_filter_exists            = WCS_ATT_Product_Schemes::price_filter_exists( $subscription_schemes );
			$force_subscription             = WCS_ATT_Product_Schemes::has_forced_subscription_scheme( $product );
			$active_subscription_scheme_key = WCS_ATT_Product_Schemes::get_subscription_scheme( $product );
			$scheme_keys                    = array_merge( $force_subscription ? array() : array( false ), array_keys( $subscription_schemes ) );

			if ( $price_filter_exists ) {

				$bundle_price = array();

				foreach ( $scheme_keys as $scheme_key ) {
					$price_key                  = false === $scheme_key ? '0' : $scheme_key;
					$bundle_price[ $price_key ] = self::calculate_container_item_subtotal( $cart_item, $scheme_key );
				}

				$options = array();

				// Non-recurring (one-time) option.
				if ( false === $force_subscription ) {

					$options[] = array(
						'class'       => 'one-time-option',
						'description' => wc_price( $bundle_price[ '0' ] ),
						'value'       => '0',
						'selected'    => false === $active_subscription_scheme_key,
					);
				}

				// Subscription options.
				foreach ( $subscription_schemes as $subscription_scheme ) {

					$subscription_scheme_key = $subscription_scheme->get_key();

					$description = WCS_ATT_Product_Prices::get_price_string( $product, array(
						'scheme_key' => $subscription_scheme_key,
						'price'      => wc_price( $bundle_price[ $subscription_scheme_key ] )
					) );

					$options[] = array(
						'class'       => 'subscription-option',
						'description' => $description,
						'value'       => $subscription_scheme_key,
						'selected'    => $active_subscription_scheme_key === $subscription_scheme_key,
					);
				}
			}
		}

		return $options;
	}

	/*
	|--------------------------------------------------------------------------
	| Hooks - Subscriptions View
	|--------------------------------------------------------------------------
	*/

	/**
	 * Don't count bundle-type child items and hidden bundle-type container/child items.
	 *
	 * @param  boolean          $can
	 * @param  WC_Subscription  $subscription
	 * @return boolean
	 */
	public static function can_remove_subscription_items( $can, $subscription ) {

		if ( $can ) {

			$items    = $subscription->get_items();
			$count    = sizeof( $items );
			$subtract = 0;

			foreach ( $items as $item ) {

				if ( self::is_bundle_type_container_order_item( $item, $subscription ) ) {

					$parent_item_visible = apply_filters( 'woocommerce_order_item_visible', true, $item );

					if ( ! $parent_item_visible ) {
						$subtract += 1;
					}


					$bundled_order_items = self::get_bundle_type_order_items( $item, $subscription );

					foreach ( $bundled_order_items as $bundled_item_key => $bundled_order_item ) {
						if ( ! $parent_item_visible ) {
							if ( ! apply_filters( 'woocommerce_order_item_visible', true, $bundled_order_item ) ) {
								$subtract += 1;
							}
						} else {
							$subtract += 1;
						}
					}
				}
			}

			$can = $count - $subtract > 1;
		}

		return $can;
	}

	/**
	 * Prevent direct removal of child subscription items from 'My Account > Subscriptions'.
	 * Does ~nothing~ to prevent removal at an application level, e.g. via a REST API call.
	 *
	 * @param  boolean          $can
	 * @param  WC_Order_Item    $item
	 * @param  WC_Subscription  $subscription
	 * @return boolean
	 */
	public static function can_remove_child_subscription_item( $can, $item, $subscription ) {

		if ( self::is_bundle_type_order_item( $item ) ) {
			$can = false;
		}

		return $can;
	}

	/**
	 * Handle parent subscription line item removals under 'My Account > Subscriptions'.
	 *
	 * @param  WC_Order_Item  $item
	 * @param  WC_Order       $subscription
	 * @return void
	 */
	public static function user_removed_parent_subscription_item( $item, $subscription ) {

		if ( self::is_bundle_type_container_order_item( $item, $subscription ) ) {

			$bundled_items     = self::get_bundle_type_order_items( $item, $subscription );
			$bundled_item_keys = array();

			if ( ! empty( $bundled_items ) ) {
				foreach ( $bundled_items as $bundled_item ) {

					$bundled_item_keys[] = $bundled_item->get_id();

					$bundled_product_id = wcs_get_canonical_product_id( $bundled_item );

					// Remove the line item from subscription but preserve its data in the DB.
					wcs_update_order_item_type( $bundled_item->get_id(), 'line_item_removed', $subscription->get_id() );

					WCS_Download_Handler::revoke_downloadable_file_permission( $bundled_product_id, $subscription->get_id(), $subscription->get_user_id() );

					// Add order note.
					$subscription->add_order_note( sprintf( _x( '"%1$s" (Product ID: #%2$d) removal triggered by "%3$s" via the My Account page.', 'used in order note', 'woocommerce-subscribe-all-the-things' ), wcs_get_line_item_name( $bundled_item ), $bundled_product_id, wcs_get_line_item_name( $item ) ) );

					// Trigger WCS action.
					do_action( 'wcs_user_removed_item', $bundled_item, $subscription );
				}

				// Update session data for un-doing.
				$removed_bundled_item_ids = WC()->session->get( 'removed_bundled_subscription_items', array() );
				$removed_bundled_item_ids[ $item->get_id() ] = $bundled_item_keys;
				WC()->session->set( 'removed_bundled_subscription_items', $removed_bundled_item_ids );
			}
		}
	}

	/**
	 * Handle parent subscription line item re-additions under 'My Account > Subscriptions'.
	 *
	 * @param  WC_Order_Item  $item
	 * @param  WC_Order       $subscription
	 * @return void
	 */
	public static function user_readded_parent_subscription_item( $item, $subscription ) {

		if ( self::is_bundle_type_container_order_item( $item, $subscription ) ) {

			$removed_bundled_item_ids = WC()->session->get( 'removed_bundled_subscription_items', array() );
			$removed_bundled_item_ids = isset( $removed_bundled_item_ids[ $item->get_id() ] ) ? $removed_bundled_item_ids[ $item->get_id() ] : array();

			if ( ! empty( $removed_bundled_item_ids ) ) {

				foreach ( $removed_bundled_item_ids as $removed_bundled_item_id ) {

					// Update the line item type.
					wcs_update_order_item_type( $removed_bundled_item_id, 'line_item', $subscription->get_id() );
				}
			}

			$bundled_items = self::get_bundle_type_order_items( $item, $subscription );

			if ( ! empty( $bundled_items ) ) {
				foreach ( $bundled_items as $bundled_item ) {

					$bundled_product    = $subscription->get_product_from_item( $bundled_item );
					$bundled_product_id = wcs_get_canonical_product_id( $bundled_item );

					if ( $bundled_product && $bundled_product->exists() && $bundled_product->is_downloadable() ) {

						$downloads = wcs_get_objects_property( $bundled_product, 'downloads' );

						foreach ( array_keys( $downloads ) as $download_id ) {
							wc_downloadable_file_permission( $download_id, $bundled_product_id, $subscription, $bundled_item[ 'qty' ] );
						}
					}

					// Add order note.
					$subscription->add_order_note( sprintf( _x( '"%1$s" (Product ID: #%2$d) removal un-done by "%3$s" via the My Account page.', 'used in order note', 'woocommerce-subscribe-all-the-things' ), wcs_get_line_item_name( $bundled_item ), wcs_get_canonical_product_id( $bundled_item ), wcs_get_line_item_name( $item ) ) );

					// Trigger WCS action.
					do_action( 'wcs_user_readded_item', $bundled_item, $subscription );
				}
			}
		}
	}

	/**
	 * Bundles and Composites don't support switching just yet.
	 *
	 * @since  2.1.0
	 *
	 * @param  bool        $is_feature_supported
	 * @param  WC_Product  $product
	 * @param  string      $feature
	 * @param  array       $args
	 * @return bool
	 */
	public static function bundle_supports_switching( $is_feature_supported, $product, $feature, $args ) {

		if ( 'subscription_scheme_switching' === $feature && self::is_bundle_type_product( $product ) ) {
			$is_feature_supported = false;
		}

		return $is_feature_supported;
	}

	/*
	|--------------------------------------------------------------------------
	| Hooks - Add to Subscription
	|--------------------------------------------------------------------------
	*/

	/**
	 * Modify the validation context when adding a bundle-type product to an order.
	 *
	 * @since  2.1.0
	 *
	 * @param  int  $product_id
	 */
	public static function set_bundle_type_validation_context( $product_id ) {
		add_filter( 'woocommerce_composite_validation_context', array( __CLASS__, 'set_add_to_order_validation_context' ) );
		add_filter( 'woocommerce_bundle_validation_context', array( __CLASS__, 'set_add_to_order_validation_context' ) );
		add_filter( 'woocommerce_add_to_order_bundle_validation', array( __CLASS__, 'validate_bundle_type_stock' ), 10, 4 );
		add_filter( 'woocommerce_add_to_order_composite_validation', array( __CLASS__, 'validate_bundle_type_stock' ), 10, 4 );
	}

	/**
	 * Modify the validation context when adding a bundle-type product to an order.
	 *
	 * @since  2.1.0
	 *
	 * @param  int  $product_id
	 */
	public static function reset_bundle_type_validation_context( $product_id ) {
		remove_filter( 'woocommerce_composite_validation_context', array( __CLASS__, 'set_add_to_order_validation_context' ) );
		remove_filter( 'woocommerce_bundle_validation_context', array( __CLASS__, 'set_add_to_order_validation_context' ) );
		remove_filter( 'woocommerce_add_to_order_bundle_validation', array( __CLASS__, 'validate_bundle_type_stock' ) );
		remove_filter( 'woocommerce_add_to_order_composite_validation', array( __CLASS__, 'validate_bundle_type_stock' ) );
	}

	/**
	 * Sets the validation context to 'add-to-order'.
	 *
	 * @since  2.1.0
	 *
	 * @param  WC_Product_Bundle  $bundle
	 */
	public static function set_add_to_order_validation_context( $product ) {
		return 'add-to-order';
	}

	/**
	 * Validates bundle-type stock in 'add-to-order' context.
	 *
	 * @since  2.1.0
	 *
	 * @param  boolean  $is_valid
	 */
	public static function validate_bundle_type_stock( $is_valid, $bundle_id, $stock_manager, $configuration ) {

		if ( $is_valid ) {

			try {

				$stock_manager->validate_stock( array( 'throw_exception' => true, 'context' => 'add-to-order' ) );

			} catch ( Exception $e ) {

				$notice = $e->getMessage();

				if ( $notice ) {
					wc_add_notice( $notice, 'error' );
				}

				$is_valid = false;
			}
		}

		return $is_valid;
	}

	/**
	 * Don't attempt to increment the quantity of bundle-type subscription items when adding to an existing subscription.
	 * Also omit child items -- they'll be added by their parent.
	 *
	 * @since  2.1.0
	 *
	 * @param  false|WC_Order_Item_Product  $found_order_item
	 * @param  array                        $matching_cart_item
	 * @param  WC_Cart                      $recurring_cart
	 * @param  WC_Subscription              $subscription
	 * @return false|WC_Order_Item_Product
	 */
	public static function found_bundle_in_subscription( $found_order_item, $matching_cart_item, $recurring_cart, $subscription ) {

		if ( $found_order_item ) {
			if ( self::is_bundle_type_product( $matching_cart_item[ 'data' ] ) ) {
				$found_order_item = false;
			} elseif ( self::is_bundle_type_cart_item( $matching_cart_item,$recurring_cart->cart_contents ) ) {
				$found_order_item = false;
			}
		}

		return $found_order_item;
	}

	/**
	 * Return 'add_bundle_to_order' as a callback for adding bundles to subscriptions.
	 * Do not add child items as they'll be added by their parent.
	 *
	 * @since  2.1.0
	 *
	 * @param  array    $callback
	 * @param  array    $cart_item
	 * @param  WC_Cart  $recurring_cart
	 */
	public static function add_bundle_to_subscription_callback( $callback, $cart_item, $recurring_cart ) {

		if ( self::is_bundle_type_container_cart_item( $cart_item, $recurring_cart->cart_contents ) ) {

			if ( $cart_item[ 'data' ]->is_type( 'bundle' ) ) {
				$callback = array( __CLASS__, 'add_bundle_to_order' );
			} elseif ( $cart_item[ 'data' ]->is_type( 'composite' ) ) {
				$callback = array( __CLASS__, 'add_composite_to_order' );
			}

		} elseif ( self::is_bundle_type_cart_item( $cart_item, $recurring_cart->cart_contents ) ) {
			$callback = null;
		}

		return $callback;
	}

	/*
	|--------------------------------------------------------------------------
	| Hooks - Bundles
	|--------------------------------------------------------------------------
	*/

	/**
	 * When loading bundled items, always set the active bundle scheme on the bundled objects.
	 *
	 * @param  array              $bundled_items
	 * @param  WC_Product_Bundle  $bundle
	 */
	public static function set_bundled_items_scheme( $bundled_items, $bundle ) {

		if ( ! empty( $bundled_items ) && $bundle->is_synced() && WCS_ATT_Product_Schemes::has_subscription_schemes( $bundle ) ) {

			// Set the default scheme when one-time purchases are disabled, no scheme is set on the object, and only a single sub scheme exists.
			if ( 1 === sizeof( WCS_ATT_Product_Schemes::get_subscription_schemes( $bundle ) ) && WCS_ATT_Product_Schemes::has_forced_subscription_scheme( $bundle ) && ! WCS_ATT_Product_Schemes::get_subscription_scheme( $bundle ) ) {
				WCS_ATT_Product_Schemes::set_subscription_scheme( $bundle, WCS_ATT_Product_Schemes::get_default_subscription_scheme( $bundle ) );
			}

			foreach ( $bundled_items as $bundled_item ) {
				self::set_bundled_item_scheme( $bundled_item, $bundle );
			}
		}

		return $bundled_items;
	}

	/**
	 * Add scheme data to runtime price cache hashes.
	 *
	 * @param  array              $hash
	 * @param  WC_Product_Bundle  $bundle
	 * @return array
	 */
	public static function bundle_prices_hash( $hash, $bundle ) {

		if ( $scheme = WCS_ATT_Product_Schemes::get_subscription_scheme( $bundle ) ) {
			$hash[ 'satt_scheme' ] = $scheme;
		}

		return $hash;
	}

	/*
	|--------------------------------------------------------------------------
	| Hooks - Composites
	|--------------------------------------------------------------------------
	*/

	/**
	 * Set the default scheme when one-time purchases are disabled, no scheme is set on the object, and only a single sub scheme exists.
	 *
	 * @param  WC_Product_Composite  $composite
	 */
	public static function set_single_composite_subscription_scheme( $composite ) {
		if ( 1 === sizeof( WCS_ATT_Product_Schemes::get_subscription_schemes( $composite ) ) && WCS_ATT_Product_Schemes::has_forced_subscription_scheme( $composite ) && ! WCS_ATT_Product_Schemes::get_subscription_scheme( $composite ) ) {
			WCS_ATT_Product_Schemes::set_subscription_scheme( $composite, WCS_ATT_Product_Schemes::get_default_subscription_scheme( $composite ) );
		}
	}

	/**
	 * Ensure composites in cached component objects have up-to-date scheme data.
	 *
	 * @param  string      $scheme_key
	 * @param  string      $previous_scheme_key
	 * @param  WC_Product  $product
	 */
	public static function set_composite_product_scheme( $scheme_key, $previous_scheme_key, $product ) {

		if ( $product->is_type( 'composite' ) && $scheme_key !== $previous_scheme_key ) {

			$components = $product->get_components();

			if ( ! empty( $components ) ) {
				foreach ( $components as $component ) {
					WCS_ATT_Product_Schemes::set_subscription_scheme( $component->get_composite(), WCS_ATT_Product_Schemes::get_subscription_scheme( $product ) );
				}
			}
		}
	}

	/**
	 * Composited products inherit the subscription schemes of their container object.
	 *
	 * @param  WC_CP_Product         $component_option
	 * @param  string                $component_id
	 * @param  WC_Product_Composite  $composite
	 */
	public static function set_component_option_scheme( $component_option, $component_id, $composite ) {

		if ( $component_option && WCS_ATT_Product_Schemes::has_subscription_schemes( $composite ) ) {

			$having = array(
				'price',
				'regular_price'
			);

			$what = array(
				'min',
				'max'
			);

			if ( $product = $component_option->get_product() ) {
				self::set_bundled_product_subscription_schemes( $product, $composite );
			}

			foreach ( $having as $price ) {
				foreach ( $what as $min_or_max ) {
					if ( $product = $component_option->get_product( array( 'having' => $price, 'what' => $min_or_max ) ) ) {
						self::set_bundled_product_subscription_schemes( $product, $composite );
					}
				}
			}
		}

		return $component_option;
	}

	/**
	 * Add scheme data to runtime price cache hashes.
	 *
	 * @param  array                 $hash
	 * @param  WC_Product_Composite  $composite
	 * @return array
	 */
	public static function composite_prices_hash( $hash, $composite ) {

		if ( $scheme = WCS_ATT_Product_Schemes::get_subscription_scheme( $composite ) ) {
			$hash[ 'satt_scheme' ] = $scheme;
		}

		return $hash;
	}

	/*
	|--------------------------------------------------------------------------
	| Deprecated
	|--------------------------------------------------------------------------
	*/

	/**
	 * Checks if the passed cart item is a supported bundle type child. Returns the container item key name if yes, or false if not.
	 *
	 * @param  array  $cart_item
	 * @return boolean|string
	 */
	public static function has_bundle_type_container( $cart_item ) {
		_deprecated_function( __METHOD__ . '()', '2.1.0', 'WCS_ATT_Integrations::get_bundle_type_cart_item_container()' );
		return self::get_bundle_type_cart_item_container( $cart_item, false, true );
	}

	/**
	 * Checks if the passed cart item is a supported bundle type container. Returns the child item key name if yes, or false if not.
	 *
	 * @param  array  $cart_item
	 * @return boolean|string
	 */
	public static function has_bundle_type_children( $cart_item ) {
		_deprecated_function( __METHOD__ . '()', '2.1.0', 'WCS_ATT_Integrations::get_bundle_type_cart_items()' );
		return self::get_bundle_type_cart_items( $cart_item, false, true );
	}
}

WCS_ATT_Integrations::init();
