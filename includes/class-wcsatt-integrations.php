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

			// When a bundle has subscription options, modify bundle contents to account for the defined price overrides of each subscription option.
			add_filter( 'wcsatt_overridden_subscription_prices_product', array( __CLASS__, 'overridden_subscription_prices_product' ), 10, 2 );

			// When a bundle has a single subscription option and one-time purchases are disabled, permanently modify bundle contents to account for the defined price overrides.
			add_action( 'woocommerce_bundles_synced_bundle', array( __CLASS__, 'force_sub_bundle_modification' ) );

			add_filter( 'wcsatt_single_product_subscription_option_description', array( __CLASS__, 'bundle_product_subscription_option_description' ), 10, 7 );
		}
	}

	public static function bundle_product_subscription_option_description( $description, $sub_price_html, $price_overrides_exist, $allow_one_time_option, $_cloned, $subscription_scheme, $product ) {

		if ( self::is_bundle_type_product( $product ) ) {
			$sub_price_html = WC_Subscriptions_Product::get_price_string( $_cloned, array(
				'subscription_price' => $price_overrides_exist,
				'price'              => '<span class="price subscription-price"></span>',
			) );

			$description = ucfirst( $allow_one_time_option ? sprintf( __( '%s', 'product subscription selection - positive response', WCS_ATT::TEXT_DOMAIN ), $sub_price_html ) : $sub_price_html );
		}

		return $description;
	}


	public static function force_sub_bundle_modification( $product ) {

		$product_level_schemes = WCS_ATT_Schemes::get_product_subscription_schemes( $product );

		if ( ! empty( $product_level_schemes ) ) {

			$force_subscription = get_post_meta( $product->id, '_wcsatt_force_subscription', true );

			if ( $force_subscription === 'yes' && sizeof( $product_level_schemes ) === 1 ) {

				$subscription_scheme = current( $product_level_schemes );

				// Modify bundled item prices based on price discount data.
				if ( WCS_ATT_Scheme_Prices::has_subscription_price_override( $subscription_scheme ) ) {

					$_clone = clone $product;
					$_clone->price         = $_clone->base_price;
					$_clone->regular_price = $_clone->base_regular_price;
					$_clone->sale_price    = $_clone->base_sale_price;

					$overridden_product_prop_base_prices = WCS_ATT_Scheme_Prices::get_subscription_scheme_prices( $_clone, $subscription_scheme );

					$product->base_price         = $overridden_product_prop_base_prices[ 'price' ];
					$product->base_regular_price = $overridden_product_prop_base_prices[ 'regular_price' ];
					$product->base_sale_price    = $overridden_product_prop_base_prices[ 'sale_price' ];

					if ( $subscription_scheme[ 'subscription_pricing_method' ] === 'inherit' && $subscription_scheme[ 'subscription_discount' ] > 0 ) {

						$bundled_item_product_props = array( 'min_price_product', 'max_price_product', 'min_regular_price_product', 'max_regular_price_product', 'product' );

						if ( ! empty( $product->bundled_items ) ) {
							foreach ( $product->bundled_items as $bundled_item_id => $bundled_item ) {
								foreach ( $bundled_item_product_props as $bundled_item_product_prop ) {
									if ( ! empty( $bundled_item->$bundled_item_product_prop ) ) {

										$overridden_product_prop_prices = WCS_ATT_Scheme_Prices::get_subscription_scheme_prices( $bundled_item->$bundled_item_product_prop, $subscription_scheme );

										$product->bundled_items[ $bundled_item_id ]->$bundled_item_product_prop->price         = $overridden_product_prop_prices[ 'price' ];
										$product->bundled_items[ $bundled_item_id ]->$bundled_item_product_prop->regular_price = $overridden_product_prop_prices[ 'regular_price' ];
										$product->bundled_items[ $bundled_item_id ]->$bundled_item_product_prop->sale_price    = $overridden_product_prop_prices[ 'sale_price' ];
									}
								}
							}
						}
					}
				}
			}
		}
	}

	public static function overridden_subscription_prices_product( $product, $subscription_scheme ) {

		if ( 'bundle' === $product->product_type ) {

			// Modify bundled item prices based on price discount data.
			if ( WCS_ATT_Scheme_Prices::has_subscription_price_override( $subscription_scheme ) ) {

				$_clone = clone $product;
				$_clone->price         = $_clone->base_price;
				$_clone->regular_price = $_clone->base_regular_price;
				$_clone->sale_price    = $_clone->base_sale_price;

				$overridden_product_prop_base_prices = WCS_ATT_Scheme_Prices::get_subscription_scheme_prices( $_clone, $subscription_scheme );

				$product->base_price         = $overridden_product_prop_base_prices[ 'price' ];
				$product->base_regular_price = $overridden_product_prop_base_prices[ 'regular_price' ];
				$product->base_sale_price    = $overridden_product_prop_base_prices[ 'sale_price' ];

				if ( $subscription_scheme[ 'subscription_pricing_method' ] === 'inherit' && $subscription_scheme[ 'subscription_discount' ] > 0 ) {

					$bundled_item_product_props = array( 'min_price_product', 'max_price_product', 'min_regular_price_product', 'max_regular_price_product', 'product' );

					if ( ! empty( $product->bundled_items ) ) {
						foreach ( $product->bundled_items as $bundled_item_id => $bundled_item ) {
							foreach ( $bundled_item_product_props as $bundled_item_product_prop ) {
								if ( ! empty( $bundled_item->$bundled_item_product_prop ) ) {

									$overridden_product_prop_prices = WCS_ATT_Scheme_Prices::get_subscription_scheme_prices( $bundled_item->$bundled_item_product_prop, $subscription_scheme );

									$product->bundled_items[ $bundled_item_id ]->$bundled_item_product_prop->price         = $overridden_product_prop_prices[ 'price' ];
									$product->bundled_items[ $bundled_item_id ]->$bundled_item_product_prop->regular_price = $overridden_product_prop_prices[ 'regular_price' ];
									$product->bundled_items[ $bundled_item_id ]->$bundled_item_product_prop->sale_price    = $overridden_product_prop_prices[ 'sale_price' ];
								}
							}
						}
					}
				}
			}

			$product->max_bundle_price = $product->min_bundle_price;
		}

		return $product;
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
						if ( $scheme[ 'subscription_pricing_method' ] === 'override' ) {
							$scheme[ 'subscription_pricing_method' ] = 'inherit';
							$scheme[ 'subscription_discount' ]       = '';
						}
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
			if ( $product->product_type === 'bundle' && $product->contains_sub() ) {
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
				if ( $container->product_type === 'bundle' && $container->contains_sub() ) {
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
