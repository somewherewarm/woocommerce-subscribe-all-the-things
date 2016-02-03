<?php
/**
 * Compatibility with other extensions.
 *
 * @class 	WCS_ATT_Integrations
 * @version 1.0.0
 */

class WCS_ATT_Integrations {

	public static $container_key_names = array();
	public static $child_keys_names    = array();

	public static function init() {

		$bundle_type_exists = false;

		// Bundles
		if ( class_exists( 'WC_Bundles' ) ) {
			self::$container_key_names[] = 'bundled_by';
			self::$child_keys_names[]    = 'bundled_items';
			$bundle_type_exists          = true;
		}

		// Composites
		if ( class_exists( 'WC_Composite_Products' ) ) {
			self::$container_key_names[] = 'composite_parent';
			self::$child_keys_names[]    = 'composite_children';
			$bundle_type_exists          = true;
		}

		// Mix n Match
		if ( class_exists( 'WC_Mix_and_Match' ) ) {
			self::$container_key_names[] = 'mnm_container';
			self::$child_keys_names[]    = 'mnm_contents';
			$bundle_type_exists          = true;
		}

		if ( $bundle_type_exists ) {
			add_filter( 'wcsatt_show_cart_item_options', __CLASS__ . '::hide_bundle_options', 10, 3 );
			add_filter( 'wcsatt_show_cart_item_options', __CLASS__ . '::hide_bundled_item_options', 10, 3 );
			add_filter( 'wcsatt_subscription_schemes', __CLASS__ . '::get_bundled_item_schemes', 10, 3 );
			add_filter( 'wcsatt_subscription_schemes', __CLASS__ . '::get_bundle_schemes', 10, 3 );
			add_filter( 'wcsatt_set_subscription_scheme_id', __CLASS__ . '::set_bundled_item_subscription_scheme_id', 10, 3 );
			add_filter( 'woocommerce_cart_item_subtotal', __CLASS__ . '::show_ppp_bundle_subtotal_details', 1000, 3 );
		}
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

		foreach ( self::$child_keys_names as $child_keys_name ) {
			if ( ! empty( $cart_item[ $child_keys_name ] ) ) {
				if ( self::overrides_child_schemes( $cart_item ) ) {
					$subtotal = WC_Subscriptions_Cart::get_formatted_product_subtotal( $subtotal, $cart_item[ 'data' ], $cart_item[ 'quantity' ], WC()->cart );
				}
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

		foreach ( self::$container_key_names as $container_key_name ) {

			if ( ! empty( $cart_item[ $container_key_name ] ) ) {
				$container_key = $cart_item[ $container_key_name ];
				if ( isset( WC()->cart->cart_contents[ $container_key ] ) ) {

					$container_cart_item = WC()->cart->cart_contents[ $container_key ];

					if ( self::overrides_child_schemes( $container_cart_item ) && isset( $container_cart_item[ 'wccsub_data' ][ 'active_subscription_scheme_id' ] ) ) {
						$scheme_id = $container_cart_item[ 'wccsub_data' ][ 'active_subscription_scheme_id' ];
					}
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

		foreach ( self::$container_key_names as $container_key_name ) {

			if ( ! empty( $cart_item[ $container_key_name ] ) ) {
				$container_key = $cart_item[ $container_key_name ];
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
		}

		return $schemes;
	}

	/**
	 * Subscription schemes attached on a Product Bundle should not work if the bundle contains a non-convertible product, such as a "legacy" subscription.
	 *
	 * @param  array  $schemes
	 * @param  array  $cart_item
	 * @param  string $scope
	 * @return array
	 */
	public static function get_bundle_schemes( $schemes, $cart_item, $scope ) {

		foreach ( self::$child_keys_names as $child_keys_name ) {

			if ( ! empty( $cart_item[ $child_keys_name ] ) ) {
				$container = $cart_item[ 'data' ];
				if ( $container->product_type === 'bundle' && $container->contains_sub() ) {
					$schemes = array();
				}
			}
		}

		return $schemes;
	}

	/**
	 * Hide bundle container cart item subscription options if bundle has a per-item price.
	 *
	 * @param  boolean $show
	 * @param  array   $cart_item
	 * @param  string  $cart_item_key
	 * @return boolean
	 */
	public static function hide_bundle_options( $show, $cart_item, $cart_item_key ) {

		foreach ( self::$child_keys_names as $child_key_name ) {
			if ( ! empty( $cart_item[ $child_key_name ] ) ) {
				if ( $cart_item[ 'data' ]->is_priced_per_product() ) {
					$show = false;
				}
			}
		}

		return $show;
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

		foreach ( self::$container_key_names as $container_key_name ) {

			if ( ! empty( $cart_item[ $container_key_name ] ) ) {
				$container_key = $cart_item[ $container_key_name ];
				if ( isset( WC()->cart->cart_contents[ $container_key ] ) ) {

					$container_cart_item = WC()->cart->cart_contents[ $container_key ];

					if ( self::overrides_child_schemes( $container_cart_item ) ) {
						$show = false;
					}
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
