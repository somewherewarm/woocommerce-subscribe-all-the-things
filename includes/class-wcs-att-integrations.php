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
 * @version  2.1.0
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
		 * All types.
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

		// Add subscription details next to subtotal of per-item-priced bundle-type container cart items.
		add_filter( 'woocommerce_cart_item_subtotal', array( __CLASS__, 'add_container_item_subtotal_subscription_details' ), 1000, 3 );

		// Modify bundle container cart item options to include child item prices.
		add_filter( 'wcsatt_cart_item_options', array( __CLASS__, 'container_item_options' ), 10, 4 );

		/*
		 * Bundles.
		 */

		if ( class_exists( 'WC_Bundles' ) ) {

			// When loading bundled items, always set the active bundle scheme on the bundled objects.
			add_action( 'woocommerce_bundled_items', array( __CLASS__, 'set_bundled_items_scheme' ), 10, 2 );

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
			add_action( 'woocommerce_composite_component_option', array( __CLASS__, 'set_component_option_scheme' ), 10, 3 );

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

	/*
	|--------------------------------------------------------------------------
	| Hooks
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
			} elseif ( $product->is_type( 'mix-and-match' ) && $product->is_priced_per_product() ) { // TODO: Add support for Per-Item Pricing.
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

		// Copy container schemes to child.
		if ( ! empty( $container_schemes ) && array_keys( $container_schemes ) !== array_keys( $bundled_product_schemes ) ) {

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

		// Set active container scheme on child.
		if ( $container_scheme !== $bundled_product_scheme ) {
			WCS_ATT_Product_Schemes::set_subscription_scheme( $bundled_product, $container_scheme );
		}

		// Copy "Force Subscription" state.
		WCS_ATT_Product_Schemes::set_forced_subscription_scheme( $bundled_product, WCS_ATT_Product_Schemes::has_forced_subscription_scheme( $container_product ) );
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

	/**
	 * Add subscription details next to subtotal of per-item-priced bundle-type container cart items.
	 *
	 * @param  string  $subtotal
	 * @param  array   $cart_item
	 * @param  string  $cart_item_key
	 * @return string
	 */
	public static function add_container_item_subtotal_subscription_details( $subtotal, $cart_item, $cart_item_key ) {

		$is_mnm = $cart_item[ 'data' ]->is_type( 'mix-and-match' );

		// Note: MnM container subtotals originally modified by WCS are not overwritten by MnM.
		if ( self::is_bundle_type_container_cart_item( $cart_item ) && false === $is_mnm && self::has_scheme_data( $cart_item ) ) {
			$subtotal = WCS_ATT_Product_Prices::get_price_string( $cart_item[ 'data' ], array(
				'price' => $subtotal
			) );
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

				$tax_display_cart = get_option( 'woocommerce_tax_display_cart' );

				foreach ( $scheme_keys as $scheme_key ) {

					$price_key = false === $scheme_key ? '0' : $scheme_key;

					if ( 'excl' === $tax_display_cart ) {
						$bundle_price[ $price_key ] = wc_get_price_excluding_tax( $product, array( 'price' => WCS_ATT_Product_Prices::get_price( $product, $scheme_key ) ) );
					} else {
						$bundle_price[ $price_key ] = wc_get_price_including_tax( $product, array( 'price' => WCS_ATT_Product_Prices::get_price( $product, $scheme_key ) ) );
					}

					foreach ( $child_items as $child_key => $child_item ) {

						$child_qty = ceil( $child_item[ 'quantity' ] / $cart_item[ 'quantity' ] );

						if ( 'excl' === $tax_display_cart ) {
							$bundle_price[ $price_key ] += wc_get_price_excluding_tax( $child_item[ 'data' ], array( 'price' => WCS_ATT_Product_Prices::get_price( $child_item[ 'data' ], $scheme_key ), 'qty' => $child_qty ) );
						} else {
							$bundle_price[ $price_key ] += wc_get_price_including_tax( $child_item[ 'data' ], array( 'price' => WCS_ATT_Product_Prices::get_price( $child_item[ 'data' ], $scheme_key ), 'qty' => $child_qty ) );
						}
					}
				}

				$options = array();

				// Non-recurring (one-time) option.
				if ( false === $force_subscription ) {

					$options[] = array(
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
						'description' => $description,
						'value'       => $subscription_scheme_key,
						'selected'    => $active_subscription_scheme_key === $subscription_scheme_key,
					);
				}
			}
		}

		return $options;
	}

	/**
	 * When loading bundled items, always set the active bundle scheme on the bundled objects.
	 *
	 * @param  array              $bundled_items
	 * @param  WC_Product_Bundle  $bundle
	 */
	public static function set_bundled_items_scheme( $bundled_items, $bundle ) {

		if ( ! empty( $bundled_items ) && $bundle->is_synced() ) {

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

		if ( $component_option ) {

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
