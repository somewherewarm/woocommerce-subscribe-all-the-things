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

			// Bundled cart items inherit the subscription schemes of their parent if NOT priced Per-Item, or if subscription schemes already exist at parent-level.
			add_filter( 'wcsatt_subscription_schemes', array( __CLASS__, 'get_bundled_item_schemes' ), 10, 3 );

			/*
			 * Subscription schemes attached on a bundle-type product are not supported if they contain price overrides and the bundle is priced per-item.
			 * Also, schemes attached on a Product Bundle should not work if the bundle contains a non-convertible product, such as a "legacy" subscription.
			 */
			add_filter( 'wcsatt_subscription_schemes', array( __CLASS__, 'get_bundle_schemes' ), 10, 3 );
			add_filter( 'wcsatt_product_subscription_schemes', array( __CLASS__, 'get_bundle_product_schemes' ), 10, 2 );

			// Bundled/child items inherit the active subscription scheme id of their parent.
			add_filter( 'wcsatt_set_subscription_scheme_id', array( __CLASS__, 'set_bundled_item_subscription_scheme_id' ), 10, 3 );

			// Add subscription details next to subtotal of per-item-priced bundle-type container cart items.
			add_filter( 'woocommerce_cart_item_subtotal', array( __CLASS__, 'show_ppp_bundle_subtotal_details' ), 1000, 3 );

			// Render bundle-type subscription options in the single-product template.
			add_action( 'wcsatt_single_product_options_bundle', array( __CLASS__, 'convert_to_sub_bundle_product_options' ) );

			// Render composite-type subscription options in the single-product template.
			add_action( 'wcsatt_single_product_options_composite', array( __CLASS__, 'convert_to_sub_bundle_product_options' ) );

			// Render mnm-type subscription options in the single-product template.
			add_action( 'wcsatt_single_product_options_mix-and-match', array( __CLASS__, 'convert_to_sub_bundle_product_options' ) );
		}
	}

	/**
	 * Checks if the passed cart item is a supported bundle type child. Returns the container item key name if yes, or false if not.
	 *
	 * @param  array $cart_item
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
	 * @param  array $cart_item
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
	 * Render bundle-type subscription options in the single-product template.
	 *
	 * @param  WC_Product $product
	 * @return void
	 */
	public static function convert_to_sub_bundle_product_options( $product ) {
		return WCS_ATT_Display::convert_to_sub_simple_product_options( $product );
	}

	/**
	 * Add subscription details next to subtotal of per-item-priced bundle-type container cart items.
	 *
	 * @param  string $subtotal
	 * @param  array  $cart_item
	 * @param  string $cart_item_key
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
	 * @param  string $scheme_id
	 * @param  array  $cart_item
	 * @param  array  $cart_level_schemes
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
	 * @param  array  $schemes
	 * @param  array  $cart_item
	 * @param  string $scope
	 * @return array
	 */
	public static function get_bundled_item_schemes( $schemes, $cart_item, $scope ) {

		$container_key = self::has_bundle_type_container( $cart_item );

		if ( false !== $container_key ) {
			if ( isset( WC()->cart->cart_contents[ $container_key ] ) ) {
				$container_cart_item = WC()->cart->cart_contents[ $container_key ];
				if ( self::overrides_child_schemes( $container_cart_item ) ) {
					$schemes = WCS_ATT_Schemes::get_subscription_schemes( $container_cart_item, $scope );
					foreach ( $schemes as &$scheme ) {
						$scheme[ 'subscription_pricing_method' ] = 'inherit';
					}
				}
			}
		}

		return $schemes;
	}

	/**
	 * Subscription schemes attached on a bundle-type product are not supported if they contain price overrides and the bundle is priced per-item.
	 * Also, schemes attached on a Product Bundle should not work if the bundle contains a non-convertible product, such as a "legacy" subscription.
	 *
	 * @param  array  $schemes
	 * @param  array  $cart_item
	 * @param  string $scope
	 * @return array
	 */
	public static function get_bundle_product_schemes( $schemes, $product ) {

		if ( self::is_bundle_type_product( $product ) ) {
			if ( $product->is_priced_per_product() ) {
				$clean_schemes = array();
				foreach ( $schemes as $scheme ) {
					if ( false === WCS_ATT_Schemes::has_subscription_price_override( $scheme ) ) {
						$clean_schemes[] = $scheme;
					}
				}
				$schemes = $clean_schemes;
			} elseif ( $product->product_type === 'bundle' && $product->contains_sub() ) {
				$schemes = array();
			}
		}

		return $schemes;
	}

	/**
	 * Subscription schemes attached on a bundle-type product are not supported if they contain price overrides and the bundle is priced per-item.
	 * Also, schemes attached on a Product Bundle should not work if the bundle contains a non-convertible product, such as a "legacy" subscription.
	 *
	 * @param  array  $schemes
	 * @param  array  $cart_item
	 * @param  string $scope
	 * @return array
	 */
	public static function get_bundle_schemes( $schemes, $cart_item, $scope ) {

		foreach ( self::$child_key_names as $child_key_name ) {

			if ( ! empty( $cart_item[ $child_key_name ] ) ) {
				$container = $cart_item[ 'data' ];
				if ( $container->is_priced_per_product() ) {
					$clean_schemes = array();
					foreach ( $schemes as $scheme ) {
						if ( false === WCS_ATT_Schemes::has_subscription_price_override( $scheme ) ) {
							$clean_schemes[] = $scheme;
						}
					}
					$schemes = $clean_schemes;
				} elseif ( $container->product_type === 'bundle' && $container->contains_sub() ) {
					$schemes = array();
				}
			}
		}

		return $schemes;
	}

	/**
	 * Hide bundled cart item subscription options if:
	 *  - bundle has a static price, or
	 *  - bundle has subscription schemes defined at bundle-level.
	 *
	 * @param  boolean $show
	 * @param  array   $cart_item
	 * @param  string  $cart_item_key
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
	 * True if there are sub schemes inherited from a container.
	 *
	 * @param  array $cart_item
	 * @return boolean
	 */
	public static function overrides_child_schemes( $cart_item ) {

		$overrides = false;

		if ( isset( $cart_item[ 'wccsub_data' ][ 'active_subscription_scheme_id' ] ) && ( false !== $cart_item[ 'wccsub_data' ][ 'active_subscription_scheme_id' ] ) ) {
			$overrides = true;
		}

		return $overrides;
	}
}

WCS_ATT_Integrations::init();
