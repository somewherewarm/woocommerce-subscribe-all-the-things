<?php
/**
 * WCS_ATT_Cart class
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
 * Cart support.
 *
 * @class    WCS_ATT_Cart
 * @version  2.0.0
 */
class WCS_ATT_Cart {

	public static function init() {

		// Add scheme data to cart items that can be pruchased on a recurring basis.
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 3 );

		// Load saved session data of cart items that can be pruchased on a recurring basis.
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'load_cart_item_data_from_session' ), 5, 2 );

		// Inspect product-level/cart-level session data and apply subscription schemes to cart items as needed.
		add_action( 'woocommerce_cart_loaded_from_session', array( __CLASS__, 'apply_subscription_schemes' ), 5 );

		// Inspect product-level/cart-level session data on add-to-cart and apply subscription schemes to cart items as needed. Then, recalculate totals.
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'apply_subscription_schemes_on_add_to_cart' ), 1000, 6 );

		// Update the subscription scheme saved on a cart item when chosing a new option.
		add_filter( 'woocommerce_update_cart_action_cart_updated', array( __CLASS__, 'update_cart_item_data' ), 10 );

		// Ajax handler for saving the subscription scheme chosen at cart-level.
		add_action( 'wc_ajax_wcsatt_update_cart_option', array( __CLASS__, 'update_cart_scheme' ) );

		// Save subscription scheme in subscription item meta so it can be re-applied later.
		add_action( 'woocommerce_add_subscription_item_meta', array( __CLASS__, 'save_subscription_scheme' ), 10, 2 );
	}

	/*
	|--------------------------------------------------------------------------
	| API
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns all subscription schemes associated with a cart item - @see 'WCS_ATT_Product::get_subscription_schemes'.
	 *
	 * @since  2.0.0
	 *
	 * @param  array   $cart_item
	 * @param  string  $context
	 * @return array
	 */
	public static function get_subscription_schemes( $cart_item, $context = 'any' ) {
		return apply_filters( 'wcsatt_subscription_schemes', WCS_ATT_Product::get_subscription_schemes( $cart_item[ 'data' ], $context ), $cart_item, $context );
	}

	/**
	 * Returns the active subscription scheme key of a cart item, or false if the cart item is a one-time purchase.
	 *
	 * @since  2.0.0
	 *
	 * @return string
	 */
	public static function get_subscription_scheme( $cart_item ) {

		$active_scheme = isset( $cart_item[ 'wcsatt_data' ][ 'active_subscription_scheme' ] ) ? $cart_item[ 'wcsatt_data' ][ 'active_subscription_scheme' ] : false;

		return $active_scheme;
	}

	/**
	 * Returns cart-level subscription schemes, available only if there are no cart-items with product-level subscription schemes.
	 * Subscription options defined at product-level and "legacy" subscription-type products "block" the display of cart-level subscription options.
	 *
	 * Must be called after all cart session data has been loaded.
	 *
	 * @since  2.0.0
	 *
	 * @return array|boolean
	 */
	public static function get_cart_subscription_schemes() {

		$cart_level_schemes     = array();
		$cart_level_scheme_meta = get_option( 'wcsatt_subscribe_to_cart_schemes', array() );

		if ( empty( $cart_level_scheme_meta ) ) {
			return false;
		}

		foreach ( $cart_level_scheme_meta as $scheme_meta ) {

			$scheme     = new WCS_ATT_Scheme( array( 'data' => $scheme_meta, 'context' => 'cart' ) );
			$scheme_key = $scheme->get_key();

			if ( ! isset( $cart_level_schemes[ $scheme_key ] ) ) {
				$cart_level_schemes[ $scheme_key ] = $scheme;
			}
		}

		foreach ( WC()->cart->cart_contents as $cart_item ) {

			// Unsupported product type?
			if ( ! WCS_ATT_Cart::is_supported_product_type( $cart_item ) ) {
				return false;
			}

			// Has subscription schemes defined at product level?
			if ( $product_level_schemes = WCS_ATT_Product::get_subscription_schemes( $cart_item[ 'data' ], 'product' ) ) {
				return false;
			}

			// Is a legacy subscription product?
			if ( WCS_ATT_Product::is_legacy_subscription( $cart_item[ 'data' ] ) ) {
				return false;
			}
		}

		return $cart_level_schemes;
	}

	/**
	 * Returns the active cart-level subscription scheme id, or false if none is set.
	 *
	 * @since  2.0.0
	 *
	 * @return string|false|null
	 */
	public static function get_cart_subscription_scheme() {

		return WC()->session->get( 'wcsatt-active-scheme', null );
	}

	/**
	 * Returns the active cart-level subscription scheme id, or false if none is set.
	 *
	 * @since  2.0.0
	 *
	 * @param  string|false  $scheme_key
	 */
	public static function set_cart_subscription_scheme( $scheme_key ) {

		WC()->session->set( 'wcsatt-active-scheme', $scheme_key );
	}

	/**
	 * Equivalent of 'WC_Cart::get_product_price' that utilizes 'WCS_ATT_Product::get_price' instead of 'WC_Product::get_price'.
	 *
	 * @since  2.0.0
	 *
	 * @param  WC_Product  $product
	 * @param  string      $scheme_key
	 * @return string
	 */
	public static function get_product_price( $cart_item, $scheme_key = '' ) {

		$product = $cart_item[ 'data' ];

		if ( 'excl' === get_option( 'woocommerce_tax_display_cart' ) ) {
			$product_price = WCS_ATT_Core_Compatibility::wc_get_price_excluding_tax( $product, array( 'price' => WCS_ATT_Product::get_price( $product, $scheme_key ) ) );
		} else {
			$product_price = WCS_ATT_Core_Compatibility::wc_get_price_including_tax( $product, array( 'price' => WCS_ATT_Product::get_price( $product, $scheme_key ) ) );
		}

		return apply_filters( 'wcsatt_cart_product_price', wc_price( $product_price ), $cart_item );
	}

	/*
	|--------------------------------------------------------------------------
	| Filters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Add scheme data to cart items that can be purchased on a recurring basis.
	 *
	 * @param  array  $cart_item
	 * @param  int    $product_id
	 * @param  int    $variation_id
	 * @return array
	 */
	public static function add_cart_item_data( $cart_item, $product_id, $variation_id ) {

		if ( self::is_supported_product_type( $product_id ) ) {

			$posted_subscription_scheme_key = null;

			if ( isset( $_POST[ 'convert_to_sub_' . $product_id ] ) ) {

				$posted_subscription_scheme_option = wc_clean( $_POST[ 'convert_to_sub_' . $product_id ] );
				$posted_subscription_scheme_key    = ! empty( $posted_subscription_scheme_option ) ? $posted_subscription_scheme_option : false;

			} elseif ( isset( $cart_item[ 'subscription_resubscribe' ] ) ) {

				// Grab the scheme id from the order item meta.
				$scheme_id = wc_get_order_item_meta( $cart_item[ 'subscription_resubscribe' ][ 'subscription_line_item_id' ], '_wcsatt_scheme_id', true );

				if ( '' !== $scheme_id ) {
					$posted_subscription_scheme_option = $scheme_id;
					$posted_subscription_scheme_key    = ! empty( $posted_subscription_scheme_option ) ? $posted_subscription_scheme_option : false;
				}
			}

			$cart_item[ 'wcsatt_data' ] = array(
				'active_subscription_scheme' => $posted_subscription_scheme_key,
			);
		}

		return $cart_item;
	}


	/**
	 * Load saved session data of cart items that can be pruchased on a recurring basis.
	 *
	 * @param  array  $cart_item
	 * @param  array  $item_session_values
	 * @return array
	 */
	public static function load_cart_item_data_from_session( $cart_item, $item_session_values ) {

		if ( self::is_supported_product_type( $cart_item ) && isset( $item_session_values[ 'wcsatt_data' ] ) ) {
			$cart_item[ 'wcsatt_data' ] = $item_session_values[ 'wcsatt_data' ];
		}

		return $cart_item;
	}

	/**
	 * Applies a saved subscription key to a cart item.
	 * @see 'WCS_ATT_Product::set_subscription_scheme'.
	 *
	 * @since  2.0.0
	 *
	 * @param  array  $cart_item
	 * @return array
	 */
	public static function apply_subscription_scheme( $cart_item ) {

		if ( self::is_supported_product_type( $cart_item ) ) {
			WCS_ATT_Product::set_subscription_scheme( $cart_item[ 'data' ], self::get_subscription_scheme( $cart_item ) );
		}

		return apply_filters( 'wcsatt_cart_item', $cart_item );
	}

	/**
	 * Inspect product-level/cart-level session data and apply subscription schemes to cart items as needed.
	 *
	 * @param  WC_Cart  $cart
	 * @return void
	 */
	public static function apply_subscription_schemes( $cart ) {

		/*
		 * Can we attach cart-level schemes to the products contained in this cart?
		 * @see 'WCS_ATT_Cart::get_cart_subscription_schemes'.
		 */
		$cart_level_schemes = self::get_cart_subscription_schemes();

		foreach ( $cart->cart_contents as $cart_item_key => $cart_item ) {

			if ( isset( $cart_item[ 'wcsatt_data' ] ) ) {

				// If subscription schemes are available at cart-level, set them on the product object.
				if ( ! empty( $cart_level_schemes ) ) {
					WCS_ATT_Product::set_subscription_schemes( $cart_item[ 'data' ], $cart_level_schemes );
				}

				// Initialize subscription scheme data.
				$cart_item[ 'wcsatt_data' ][ 'active_subscription_scheme' ] = self::get_subscription_scheme_to_apply( $cart_item );

				// Convert the cart item to a subscription, if needed.
				self::apply_subscription_scheme( $cart_item );
			}
		}
	}

	/**
	 * Gets the subscription scheme to apply against a cart item product object on session load.
	 * @see 'WCS_ATT_Cart::apply_subscription_scheme'.
	 *
	 * @param  array  $cart_item
	 * @return string|false
	 */
	private static function get_subscription_scheme_to_apply( $cart_item ) {

		$cart_level_schemes = self::get_subscription_schemes( $cart_item, 'cart' );

		// Currently a cart item can only have product-level or cart-level schemes, not both - @see 'WCS_ATT_Cart::get_cart_subscription_schemes'.
		if ( ! empty( $cart_level_schemes ) ) {

			// Read active cart scheme from session.
			$scheme_key_to_apply = self::get_cart_subscription_scheme();

			if ( null === $scheme_key_to_apply ) {

				// Default to subscription.
				if ( apply_filters( 'wcsatt_enable_cart_subscription_by_default', false ) ) {

					$default_scheme      = current( $cart_level_schemes );
					$scheme_key_to_apply = $default_scheme->get_key();

				// Default to one-time.
				} else {
					$scheme_key_to_apply = false;
				}
			}

		} else {

			$scheme_key_to_apply = $cart_item[ 'wcsatt_data' ][ 'active_subscription_scheme' ];

			if ( null === $scheme_key_to_apply ) {
				if ( WCS_ATT_Product::has_subscriptions( $cart_item[ 'data' ] ) ) {
					$scheme_key_to_apply = WCS_ATT_Product::get_default_subscription_scheme( $cart_item[ 'data' ] );
				}
			}
		}

		return apply_filters( 'wcsatt_set_subscription_scheme_id', $scheme_key_to_apply, $cart_item, $cart_level_schemes );
	}

	/**
	 * Inspect product-level/cart-level session data and apply subscription schemes on cart items as needed.
	 * Then, recalculate totals.
	 *
	 * @return void
	 */
	public static function apply_subscription_schemes_on_add_to_cart( $item_key, $product_id, $quantity, $variation_id, $variation, $item_data ) {

		self::apply_subscription_schemes( WC()->cart );

		WC()->cart->calculate_totals();
	}

	/**
	 * Update the subscription scheme saved on a cart item when chosing a new option.
	 *
	 * @param  boolean  $updated
	 * @return boolean
	 */
	public static function update_cart_item_data( $updated ) {

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! empty( $cart_item[ 'wcsatt_data' ] ) ) {

				$selected_scheme_option = isset( $_POST[ 'cart' ][ $cart_item_key ][ 'convert_to_sub' ] ) ? wc_clean( $_POST[ 'cart' ][ $cart_item_key ][ 'convert_to_sub' ] ) : null;
				$selected_scheme_key    = '0' !== $selected_scheme_option ? $selected_scheme_option : false;
				$selected_scheme_key    = apply_filters( 'wcsatt_updated_cart_item_scheme_id', $selected_scheme_key, $cart_item, $cart_item_key );

				if ( null !== $selected_scheme_key ) {
					WC()->cart->cart_contents[ $cart_item_key ][ 'wcsatt_data' ][ 'active_subscription_scheme' ] = $selected_scheme_key;
				}
			}
		}

		return true;
	}

	/**
	 * Ajax handler for saving the subscription scheme chosen at cart-level.
	 *
	 * @return void
	 */
	public static function update_cart_scheme() {

		check_ajax_referer( 'wcsatt_update_cart_option', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$selected_scheme = false;

		if ( ! empty( $_POST[ 'selected_scheme' ] ) ) {
			$selected_scheme = wc_clean( $_POST[ 'selected_scheme' ] );
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! empty( $cart_item[ 'wcsatt_data' ] ) ) {
				// Save scheme key on cart item.
				$cart_item[ 'wcsatt_data' ][ 'active_subscription_scheme' ] = $selected_scheme;
				// Apply scheme.
				self::apply_subscription_scheme( $cart_item );
			}
		}

		// Save chosen scheme.
		self::set_cart_subscription_scheme( $selected_scheme );

		// Recalculate totals.
		WC()->cart->calculate_totals();

		// Update the cart table apart from the totals in order to show modified price html strings with sub details.
		wc_get_template( 'cart/cart.php' );

		die();
	}

	/**
	 * True if the product corresponding to a cart item is one of the types supported by the plugin.
	 *
	 * @param  mixed  $arg
	 * @return boolean
	 */
	public static function is_supported_product_type( $arg ) {

		if ( is_a( $arg, 'WC_Product' ) ) {
			$is_supported = $arg->is_type( WCS_ATT()->get_supported_product_types() );
		} elseif ( is_array( $arg ) ) {
			if ( isset( $arg[ 'data' ] ) ) {
				$is_supported = $arg[ 'data' ]->is_type( WCS_ATT()->get_supported_product_types() );
			} elseif ( isset( $arg[ 'product_id' ] ) ) {
				$product_type = WCS_ATT_Core_Compatibility::get_product_type( $arg[ 'product_id' ] );
				$is_supported = in_array( $product_type, WCS_ATT()->get_supported_product_types() );
			}
		} else {
			$product_type = WCS_ATT_Core_Compatibility::get_product_type( absint( $arg ) );
			$is_supported = in_array( $product_type, WCS_ATT()->get_supported_product_types() );
		}

		return $is_supported;
	}

	/**
	 * Hooked into woocommerce_add_subscription_item_meta, this will store the scheme key against the order item.
	 * Reason is that the resubscribe cart item data lacks a LOT of detail, so we need to reconstruct some of it.
	 * Normally it could be inferred from the product's available schemes and the old subscription details, but because
	 * length is not stored, it becomes ambiguous. Hence the need to store the ID.
	 *
	 * @param  integer  $item_id    ID of the order itemmeta
	 * @param  array    $cart_item  data about the order item
	 */
	public static function save_subscription_scheme( $item_id, $cart_item ) {
		if ( isset( $cart_item[ 'wcsatt_data' ][ 'active_subscription_scheme' ] ) ) {
			wc_add_order_item_meta( $item_id, '_wcsatt_scheme_id', $cart_item[ 'wcsatt_data' ][ 'active_subscription_scheme' ] );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Deprecated
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns modified raw prices based on subscription scheme settings.
	 *
	 * @deprecated 2.0.0
	 *
	 * @param  array  $raw_prices
	 * @param  array  $subscription_scheme
	 * @return string
	 */
	public static function convert_to_sub( $cart_item ) {
		_deprecated_function( __METHOD__ . '()', '2.0.0', 'WCS_ATT_Cart::apply_subscription_scheme()' );
		return self::apply_subscription_scheme( $cart_item );
	}

	/**
	 * Returns cart item pricing data based on the active subscription scheme settings of a cart item.
	 *
	 * @deprecated 2.0.0
	 *
	 * @return string
	 */
	public static function get_active_subscription_scheme_prices( $cart_item, $active_subscription_scheme = array() ) {
		_deprecated_function( __METHOD__ . '()', '2.0.0', 'WCS_ATT_Product::get_{regular_/sale_}price()' );

		$prices = array();

		if ( empty( $active_subscription_scheme ) ) {
			$active_subscription_scheme_key = WCS_ATT_Product::get_subscription_scheme( self::get_subscription_scheme( $cart_item ) );
		} else {
			$active_subscription_scheme_key = $active_subscription_scheme->get_key();
		}

		$prices = array(
			'regular_price' => WCS_ATT_Product::get_regular_price( $product, $active_subscription_scheme_key ),
			'sale_price'    => WCS_ATT_Product::get_sale_price( $product, $active_subscription_scheme_key ),
			'price'         => WCS_ATT_Product::get_price( $product, $active_subscription_scheme_key )
		);

		return $prices;
	}

	/**
	 * True if a cart item is allowed to have subscription schemes attached by SATT.
	 *
	 * @deprecated 2.0.0
	 *
	 * @param  int|array  $arg
	 * @return boolean
	 */
	public static function is_convertible_to_sub( $arg ) {
		_deprecated_function( __METHOD__ . '()', '2.0.0', 'WCS_ATT_Cart::is_supported_product_type() and WCS_ATT_Product::is_legacy_subscription()' );

		if ( is_array( $arg ) && isset( $arg[ 'product_id' ] ) ) {
			$product_id = $arg[ 'product_id' ];
		} else {
			$product_id = absint( $arg );
		}

		return WC_Subscriptions_Product::is_subscription( $product_id ) ? false : true;
	}
}

WCS_ATT_Cart::init();
