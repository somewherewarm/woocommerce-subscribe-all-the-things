<?php
/**
 * Compatibility with other extensions.
 *
 * @class  WCS_ATT_Integrations
 * @since  1.0.0
 */

class WCS_ATT_Integrations {

	public static $bundle_types        = array();
	public static $container_key_names = array();
	public static $child_key_names     = array();

	public static function init() {

		$bundle_type_exists = false;

		// Bundles.
		if ( class_exists( 'WC_Bundles' ) ) {
			self::$bundle_types[]        = 'bundle';
			self::$container_key_names[] = 'bundled_by';
			self::$child_key_names[]     = 'bundled_items';
			$bundle_type_exists          = true;
		}

		// Composites.
		if ( class_exists( 'WC_Composite_Products' ) ) {
			self::$bundle_types[]        = 'composite';
			self::$container_key_names[] = 'composite_parent';
			self::$child_key_names[]     = 'composite_children';
			$bundle_type_exists          = true;
		}

		// Mix n Match.
		if ( class_exists( 'WC_Mix_and_Match' ) ) {
			self::$bundle_types[]        = 'mix-and-match';
			self::$container_key_names[] = 'mnm_container';
			self::$child_key_names[]     = 'mnm_contents';
			$bundle_type_exists          = true;
		}

		if ( $bundle_type_exists ) {

			// Hide child cart item options if NOT priced Per-Item, or if subscription schemes already exist at parent-level.
			add_filter( 'wcsatt_show_cart_item_options', array( __CLASS__, 'hide_bundled_item_options' ), 10, 3 );

			// Hide parent cart item options if priced per-item.
			add_filter( 'wcsatt_show_cart_item_options', array( __CLASS__, 'hide_bundle_options' ), 10, 3 );

			// Bundled cart items inherit the subscription schemes of their parent if NOT priced Per-Item, or if subscription schemes already exist at parent-level.
			add_filter( 'wcsatt_subscription_schemes', array( __CLASS__, 'get_bundled_item_schemes' ), 10, 3 );

			// Schemes attached on a Product Bundle should not work if the bundle contains a non-convertible product, such as a "legacy" subscription.
			add_filter( 'wcsatt_subscription_schemes', array( __CLASS__, 'get_bundle_schemes' ), 10, 3 );
			add_filter( 'wcsatt_product_subscription_schemes', array( __CLASS__, 'get_bundle_product_schemes' ), 10, 2 );

			// Bundled/child items inherit the active subscription scheme id of their parent.
			add_filter( 'wcsatt_set_subscription_scheme_id', array( __CLASS__, 'set_bundled_item_subscription_scheme_id' ), 10, 3 );

			// Add subscription details next to subtotal of per-item-priced bundle-type container cart items.
			add_filter( 'woocommerce_cart_item_subtotal', array( __CLASS__, 'show_ppp_bundle_subtotal_details' ), 1000, 3 );

			// Add/remove base price filters when a scheme filters prices due to defined overrides.
			add_action( 'wcsatt_add_price_filters', array( __CLASS__, 'add_bundle_type_price_filters' ) );
			add_action( 'wcsatt_remove_price_filters', array( __CLASS__, 'remove_bundle_type_price_filters' ) );

			// Do not filter bundled item prices when the 'override' method is used and the bundle is priced per product.
			// In this case, replace only the base prices with the override price values.
			add_filter( 'wcsatt_price_filters_allowed', array( __CLASS__, 'price_filters_allowed' ), 10, 4 );

			// Filter the prices of the entire bundle when it has a single subscription option and one-time purchases are disabled.
			add_action( 'woocommerce_bundle_add_to_cart', array( __CLASS__ , 'add_force_sub_price_filters' ), 9 );
			add_action( 'woocommerce_composite_add_to_cart', array( __CLASS__ , 'add_force_sub_price_filters' ), 9 );
			add_action( 'woocommerce_mix-and-match_add_to_cart', array( __CLASS__ , 'add_force_sub_price_filters' ), 9 );

			// Filter the prices of composited products loaded via ajax when the composite has a single subscription option and one-time purchases are disabled.
			add_action( 'woocommerce_composite_products_apply_product_filters', array( __CLASS__ , 'add_composited_force_sub_price_filters' ), 10, 3 );
		}
	}

	/**
	 * Removes filters added by the 'add_force_sub_price_filters' function on the 'woocommerce_bundle_add_to_cart' action.
	 *
	 * @return void
	 */
	public static function remove_force_sub_price_filters() {

		WCS_ATT_Scheme_Prices::remove_price_filters();
	}

	/**
	 * Filters the prices of an entire bundle when it has a single subscription option and one-time purchases are disabled.
	 *
	 * @return void
	 */
	public static function add_force_sub_price_filters() {

		global $product;

		if ( $added = self::maybe_add_force_sub_price_filters( $product ) ) {
			add_action( 'woocommerce_' . $product->product_type . '_add_to_cart', array( __CLASS__ , 'remove_force_sub_price_filters' ), 11 );
		}
	}

	/**
	 * Filter the prices of an entire bundle when it has a single subscription option and one-time purchases are disabled.
	 *
	 * @param  WC_Product  $product
	 * @return boolean
	 */
	private static function maybe_add_force_sub_price_filters( $product ) {

		$added = false;

		if ( self::is_bundle_type_product( $product ) ) {

			$product_level_schemes = WCS_ATT_Schemes::get_product_subscription_schemes( $product );

			if ( ! empty( $product_level_schemes ) && sizeof( $product_level_schemes ) === 1 ) {

				$force_subscription = get_post_meta( $product->id, '_wcsatt_force_subscription', true );

				if ( $force_subscription === 'yes' ) {

					$subscription_scheme   = current( $product_level_schemes );
					$price_overrides_exist = WCS_ATT_Scheme_Prices::has_subscription_price_override( $subscription_scheme );

					if ( $price_overrides_exist ) {
						WCS_ATT_Scheme_Prices::add_price_filters( $product, $subscription_scheme );
						$added = true;
					}
				}
			}
		}

		return $added;
	}

	/**
	 * Filter the prices of composited products loaded via ajax when the composite has a single subscription option and one-time purchases are disabled.
	 *
	 * @param  WC_Product  $product
	 * @param  int         $composite_id
	 * @param  object      $composite
	 * @return void
	 */
	public static function add_composited_force_sub_price_filters( $product, $composite_id, $composite ) {

		if ( did_action( 'wc_ajax_woocommerce_show_composited_product' ) ) {
			self::maybe_add_force_sub_price_filters( $composite );
		}
	}

	/**
	 * Do not filter bundled item prices when the 'override' method is used and the bundle is priced per product.
	 * In this case, replace only the base prices with the override price values.
	 *
	 * @param  boolean     $allowed
	 * @param  WC_Product  $product
	 * @param  array       $subscription_scheme
	 * @return boolean
	 */
	public static function price_filters_allowed( $allowed, $product, $subscription_scheme, $filtered_product ) {

		if ( $subscription_scheme[ 'subscription_pricing_method' ] === 'override' && self::is_bundle_type_product( $product ) && self::has_individually_priced_bundled_contents( $product ) ) {
			if ( $filtered_product->id !== $product->id ) {
				$allowed = false;
			}
		}

		return $allowed;
	}

	/**
	 * Add base price filters when a scheme filters prices due to defined overrides.
	 *
	 * @return void
	 */
	public static function add_bundle_type_price_filters() {

		add_filter( 'woocommerce_composite_get_base_price', array( __CLASS__, 'filter_get_base_price' ), 0, 2 );
		add_filter( 'woocommerce_composite_get_base_regular_price', array( __CLASS__, 'filter_get_base_regular_price' ), 0, 2 );
		add_filter( 'woocommerce_composite_get_base_sale_price', array( __CLASS__, 'filter_get_base_sale_price' ), 0, 2 );

		add_filter( 'woocommerce_bundle_get_base_price', array( __CLASS__, 'filter_get_base_price' ), 0, 2 );
		add_filter( 'woocommerce_bundle_get_base_regular_price', array( __CLASS__, 'filter_get_base_regular_price' ), 0, 2 );
		add_filter( 'woocommerce_bundle_get_base_sale_price', array( __CLASS__, 'filter_get_base_sale_price' ), 0, 2 );
	}

	/**
	 * Remove base price filters when a scheme filters prices due to defined overrides.
	 *
	 * @return void
	 */
	public static function remove_bundle_type_price_filters() {

		remove_filter( 'woocommerce_composite_get_base_price', array( __CLASS__, 'filter_get_base_price' ), 0, 2 );
		remove_filter( 'woocommerce_composite_get_base_regular_price', array( __CLASS__, 'filter_get_base_regular_price' ), 0, 2 );
		remove_filter( 'woocommerce_composite_get_base_sale_price', array( __CLASS__, 'filter_get_base_sale_price' ), 0, 2 );

		remove_filter( 'woocommerce_bundle_get_base_price', array( __CLASS__, 'filter_get_base_price' ), 0, 2 );
		remove_filter( 'woocommerce_bundle_get_base_regular_price', array( __CLASS__, 'filter_get_base_regular_price' ), 0, 2 );
		remove_filter( 'woocommerce_bundle_get_base_sale_price', array( __CLASS__, 'filter_get_base_sale_price' ), 0, 2 );
	}

	/**
	 * Filter get_base_price() calls to take price overrides into account.
	 *
	 * @param  double      $price
	 * @param  WC_Product  $product
	 * @return double
	 */
	public static function filter_get_base_price( $price, $product ) {

		$subscription_scheme = WCS_ATT_Scheme_Prices::$price_overriding_scheme;

		if ( $subscription_scheme ) {

			$prices_array = array(
				'price'         => $price,
				'regular_price' => $product->get_base_regular_price(),
				'sale_price'    => $product->get_base_sale_price()
			);

			$overridden_prices = WCS_ATT_Scheme_Prices::get_subscription_scheme_prices( $prices_array, $subscription_scheme );
			$price             = $overridden_prices[ 'price' ];
		}

		return $price;
	}

	/**
	 * Filter get_base_regular_price() calls to take price overrides into account.
	 *
	 * @param  double      $regular_price
	 * @param  WC_Product  $product
	 * @return double
	 */
	public static function filter_get_base_regular_price( $regular_price, $product ) {

		$subscription_scheme = WCS_ATT_Scheme_Prices::$price_overriding_scheme;

		if ( $subscription_scheme ) {

			WCS_ATT_Scheme_Prices::$price_overriding_scheme = false;

			$prices_array = array(
				'price'         => $product->get_base_price(),
				'regular_price' => $regular_price,
				'sale_price'    => $product->get_base_sale_price()
			);

			WCS_ATT_Scheme_Prices::$price_overriding_scheme = $subscription_scheme;

			$overridden_prices = WCS_ATT_Scheme_Prices::get_subscription_scheme_prices( $prices_array, $subscription_scheme );
			$regular_price     = $overridden_prices[ 'regular_price' ];
		}

		return $regular_price;
	}

	/**
	 * Filter get_base_sale_price() calls to take price overrides into account.
	 *
	 * @param  double      $sale_price
	 * @param  WC_Product  $product
	 * @return double
	 */
	public static function filter_get_base_sale_price( $sale_price, $product ) {

		$subscription_scheme = WCS_ATT_Scheme_Prices::$price_overriding_scheme;

		if ( $subscription_scheme ) {

			WCS_ATT_Scheme_Prices::$price_overriding_scheme = false;

			$prices_array = array(
				'price'         => $product->get_base_price(),
				'regular_price' => $product->get_base_regular_price(),
				'sale_price'    => $sale_price
			);

			WCS_ATT_Scheme_Prices::$price_overriding_scheme = $subscription_scheme;

			$overridden_prices = WCS_ATT_Scheme_Prices::get_subscription_scheme_prices( $prices_array, $subscription_scheme );
			$sale_price        = $overridden_prices[ 'sale_price' ];
		}

		return $sale_price;
	}

	/**
	 * Checks if the passed cart item is a supported bundle type child. Returns the container item key name if yes, or false if not.
	 *
	 * @param  array  $cart_item
	 * @return boolean|string
	 */
	public static function has_bundle_type_container( $cart_item ) {

		$container_key = false;

		foreach ( self::$container_key_names as $container_key_name ) {
			if ( ! empty( $cart_item[ $container_key_name ] ) ) {
				$container_key = $cart_item[ $container_key_name ];
				break;
			}
		}

		return $container_key;
	}

	/**
	 * Checks if the passed cart item is a supported bundle type container. Returns the child item key name if yes, or false if not.
	 *
	 * @param  array  $cart_item
	 * @return boolean|string
	 */
	public static function has_bundle_type_children( $cart_item ) {

		$child_key = false;

		foreach ( self::$child_key_names as $child_key_name ) {
			if ( ! empty( $cart_item[ $child_key_name ] ) ) {
				$child_key = $cart_item[ $child_key_name ];
				break;
			}
		}

		return $child_key;
	}

	/**
	 * Checks if the passed product is of a supported bundle type. Returns the type if yes, or false if not.
	 *
	 * @param  WC_Product $product
	 * @return boolean|string
	 */
	public static function is_bundle_type_product( $product ) {
		return in_array( $product->product_type, self::$bundle_types ) ? $product->product_type : false;
	}

	/**
	 * Add subscription details next to subtotal of per-item-priced bundle-type container cart items.
	 *
	 * @param  string  $subtotal
	 * @param  array   $cart_item
	 * @param  string  $cart_item_key
	 * @return string
	 */
	public static function show_ppp_bundle_subtotal_details( $subtotal, $cart_item, $cart_item_key ) {

		$child_key = self::has_bundle_type_children( $cart_item );

		if ( false !== $child_key ) {
			if ( self::overrides_child_schemes( $cart_item ) ) {
				$subtotal = WC_Subscriptions_Cart::get_formatted_product_subtotal( $subtotal, $cart_item[ 'data' ], $cart_item[ 'quantity' ], WC()->cart );
			}
		}

		return $subtotal;
	}

	/**
	 * Bundled items inherit the active subscription scheme id of their parent.
	 *
	 * @param  string  $scheme_id
	 * @param  array   $cart_item
	 * @param  array   $cart_level_schemes
	 * @return string
	 */
	public static function set_bundled_item_subscription_scheme_id( $scheme_id, $cart_item, $cart_level_schemes ) {

		$container_key = self::has_bundle_type_container( $cart_item );

		if ( false !== $container_key ) {
			if ( isset( WC()->cart->cart_contents[ $container_key ] ) ) {
				$container_cart_item = WC()->cart->cart_contents[ $container_key ];
				if ( self::overrides_child_schemes( $container_cart_item ) && isset( $container_cart_item[ 'wccsub_data' ][ 'active_subscription_scheme_id' ] ) ) {
					$scheme_id = $container_cart_item[ 'wccsub_data' ][ 'active_subscription_scheme_id' ];
				}
			}
		}

		return $scheme_id;
	}

	/**
	 * Bundled cart items inherit the subscription schemes of their parent if:
	 *  - parent is statically priced, or
	 *  - parent has subscription schemes defined at product-level.
	 *
	 * @param  array   $schemes
	 * @param  array   $cart_item
	 * @param  string  $scope
	 * @return array
	 */
	public static function get_bundled_item_schemes( $schemes, $cart_item, $scope ) {

		$container_key = self::has_bundle_type_container( $cart_item );

		if ( false !== $container_key ) {
			if ( isset( WC()->cart->cart_contents[ $container_key ] ) ) {
				$container_cart_item = WC()->cart->cart_contents[ $container_key ];
				if ( self::overrides_child_schemes( $container_cart_item ) ) {
					$schemes = WCS_ATT_Schemes::get_subscription_schemes( $container_cart_item, $scope );
					foreach ( $schemes as $scheme_key => $scheme ) {
						if ( WCS_ATT_Scheme_Prices::has_subscription_price_override( $scheme ) && $scheme[ 'subscription_pricing_method' ] === 'override' ) {
							$schemes[ $scheme_key ][ 'subscription_pricing_method' ] = 'inherit';
							$schemes[ $scheme_key ][ 'subscription_discount' ]       = '';
						}
					}
				}
			}
		}

		return $schemes;
	}

	/**
	 * Sub schemes attached on a Product Bundle should not work if the bundle contains a non-convertible product, such as a "legacy" subscription.
	 *
	 * @param  array       $schemes
	 * @param  WC_Product  $product
	 * @return array
	 */
	public static function get_bundle_product_schemes( $schemes, $product ) {

		if ( self::is_bundle_type_product( $product ) ) {
			if ( $product->product_type === 'bundle' && self::bundle_contains_subscription( $product ) ) {
				$schemes = array();
			} elseif ( $product->product_type === 'mix-and-match' && $product->is_priced_per_product() ) {
				$schemes = array();
			}
		}

		return $schemes;
	}

	/**
	 * Sub schemes attached on a Product Bundle should not work if the bundle contains a non-convertible product, such as a "legacy" subscription.
	 *
	 * @param  array   $schemes
	 * @param  array   $cart_item
	 * @param  string  $scope
	 * @return array
	 */
	public static function get_bundle_schemes( $schemes, $cart_item, $scope ) {

		$child_key = self::has_bundle_type_children( $cart_item );

		if ( false !== $child_key ) {
			$container = $cart_item[ 'data' ];
			if ( $container->product_type === 'bundle' && self::bundle_contains_subscription( $container ) ) {
				$schemes = array();
			} elseif ( $container->product_type === 'mix-and-match' && $container->is_priced_per_product() ) {
				$schemes = array();
			}
		}

		return $schemes;
	}

	/**
	 * Hide bundled cart item subscription options if:
	 *  - bundle has a static price, or
	 *  - bundle has subscription schemes defined at bundle-level.
	 *
	 * @param  boolean  $show
	 * @param  array    $cart_item
	 * @param  string   $cart_item_key
	 * @return boolean
	 */
	public static function hide_bundled_item_options( $show, $cart_item, $cart_item_key ) {

		$container_key = self::has_bundle_type_container( $cart_item );

		if ( false !== $container_key ) {
			if ( isset( WC()->cart->cart_contents[ $container_key ] ) ) {
				$container_cart_item = WC()->cart->cart_contents[ $container_key ];
				if ( self::overrides_child_schemes( $container_cart_item ) ) {
					$show = false;
				}
			}
		}

		return $show;
	}

	/**
	 * Hide bundle container cart item subscription options if bundle is priced per-item.
	 *
	 * @param  boolean  $show
	 * @param  array    $cart_item
	 * @param  string   $cart_item_key
	 * @return boolean
	 */
	public static function hide_bundle_options( $show, $cart_item, $cart_item_key ) {

		$child_key = self::has_bundle_type_children( $cart_item );

		if ( false !== $child_key ) {
			$container = $cart_item[ 'data' ];
			if ( self::has_individually_priced_bundled_contents( $container ) ) {
				$show = false;
			}
		}

		return $show;
	}

	/**
	 * True if there are sub schemes inherited from a container.
	 *
	 * @param  array  $cart_item
	 * @return boolean
	 */
	public static function overrides_child_schemes( $cart_item ) {

		$overrides = false;

		if ( isset( $cart_item[ 'wccsub_data' ][ 'active_subscription_scheme_id' ] ) && ( false !== $cart_item[ 'wccsub_data' ][ 'active_subscription_scheme_id' ] ) ) {
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

		if ( 'bundle' === $product->product_type ) {
			return version_compare( WC_PB()->version, '5.0.0' ) < 0 ? $product->is_priced_per_product() : $product->contains( 'priced_individually' );
		} elseif( 'composite' === $product->product_type ) {
			return version_compare( WC_CP()->version, '3.7.0' ) < 0 ? $product->is_priced_per_product() : $product->contains( 'priced_individually' );
		}
	}
}

WCS_ATT_Integrations::init();
