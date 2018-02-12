<?php
/**
 * WCS_ATT_Add class
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Subscribe All the Things
 * @since    2.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add stuff to existing subscriptions.
 *
 * @class    WCS_ATT_Add
 * @version  2.1.0
 */
class WCS_ATT_Add extends WCS_ATT_Abstract_Module {

	/**
	 * Using this to pass data from 'WC_Form_Handler::add_to_cart_action' into our own logic.
	 * @var array
	 */
	private static $add_product_to_subscription_args = array();

	/**
	 * Register hooks.
	 *
	 * @param  string  $type
	 * @return void
	 */
	public static function register_hooks( $type ) {

		if ( 'display' === $type ) {
			self::register_template_hooks();
			self::register_ajax_hooks();
		} elseif ( 'form' === $type ) {
			self::register_form_hooks();
		}
	}

	/**
	 * Register template hooks.
	 */
	private static function register_template_hooks() {

		/*
		 * Add Product to Subscription.
		 */

		// Render the add-to-subscription wrapper element in single-product pages.
		add_filter( 'woocommerce_after_add_to_cart_button', array( __CLASS__, 'add_product_to_subscription_template' ), 1000 );

		// Render subscriptions list.
		add_action( 'wcsatt_add_product_to_subscription_html', array( __CLASS__, 'subscriptions_matching_product_template' ), 10, 3 );

		// Adds a product to a subscription.
		add_action( 'wcsatt_add_product_to_subscription', array( __CLASS__, 'add_product_to_subscription' ), 10, 3 );

		/*
		 * Add Cart to Subscription.
		 */

		// Render the "Add-to-Subscription" options under the "Proceed to Checkout" button.
		add_action( 'woocommerce_after_cart_totals', array( __CLASS__, 'add_cart_to_subscription_template' ), 100 );

		// Render subscriptions matching cart (server-side).
		add_action( 'wcsatt_display_subscriptions_matching_cart', array( __CLASS__, 'display_subscriptions_matching_cart' ) );

		// Render subscriptions list.
		add_action( 'wcsatt_add_cart_to_subscription_html', array( __CLASS__, 'subscriptions_matching_cart_template' ), 10, 2 );

		// Adds all items in a recurring cart to a subscription.
		add_action( 'wcsatt_add_cart_to_subscription', array( __CLASS__, 'add_cart_to_subscription' ), 10 );
	}

	/**
	 * Register ajax hooks.
	 */
	private static function register_ajax_hooks() {

		// Fetch subscriptions matching product scheme via ajax.
		add_action( 'wc_ajax_wcsatt_load_subscriptions_matching_product', array( __CLASS__, 'load_subscriptions_matching_product' ) );

		// Fetch subscriptions matching cart scheme via ajax.
		add_action( 'wc_ajax_wcsatt_load_subscriptions_matching_cart', array( __CLASS__, 'load_subscriptions_matching_cart' ) );
	}

	/**
	 * Register form hooks.
	 */
	private static function register_form_hooks() {

		// Adds products to subscriptions after validating.
		add_action( 'wp_loaded', array( __CLASS__, 'add_product_to_subscription_form_handler' ), 15 );

		// Adds carts to subscriptions.
		add_action( 'wp_loaded', array( __CLASS__, 'add_cart_to_subscription_form_handler' ), 100 );
	}

	/*
	|--------------------------------------------------------------------------
	| Application
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get posted data.
	 *
	 * @param  string  $context
	 * @return array
	 */
	public static function get_posted_add_to_subscription_data( $context ) {

		$posted_data = array();

		if ( 'product' === $context ) {

			$posted_data = array(
				'product_id'          => false,
				'subscription_id'     => false,
				'subscription_scheme' => false
			);

			if ( ! empty( $_REQUEST[ 'add-to-subscription' ] ) && is_numeric( $_REQUEST[ 'add-to-subscription' ] ) ) {

				if ( ! empty( $_REQUEST[ 'add-product-to-subscription' ] ) && is_numeric( $_REQUEST[ 'add-product-to-subscription' ] ) ) {

					$posted_data[ 'product_id' ]          = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $_REQUEST[ 'add-product-to-subscription' ] ) );
					$posted_data[ 'nonce' ]               = ! empty( $_REQUEST[ 'wcsatt_nonce_' . $posted_data[ 'product_id' ] ] ) ? $_REQUEST[ 'wcsatt_nonce_' . $posted_data[ 'product_id' ] ] : '';
					$posted_data[ 'subscription_id' ]     = absint( $_REQUEST[ 'add-to-subscription' ] );
					$posted_data[ 'subscription_scheme' ] = WCS_ATT_Form_Handler::get_posted_subscription_scheme( 'product', array( 'product_id' => $posted_data[ 'product_id' ] ) );
				}
			}

		} elseif ( 'cart' === $context ) {

			$posted_data = array(
				'subscription_id'     => false,
				'subscription_scheme' => false
			);

			if ( ! empty( $_REQUEST[ 'add-to-subscription-checked' ] ) ) {

				if ( ! empty( $_REQUEST[ 'add-cart-to-subscription' ] ) && is_numeric( $_REQUEST[ 'add-cart-to-subscription' ] ) ) {

					$posted_data[ 'nonce' ]               = ! empty( $_REQUEST[ 'wcsatt_nonce' ] ) ? $_REQUEST[ 'wcsatt_nonce' ] : '';
					$posted_data[ 'subscription_id' ]     = absint( $_REQUEST[ 'add-cart-to-subscription' ] );
					$posted_data[ 'subscription_scheme' ] = WCS_ATT_Cart::get_cart_subscription_scheme();
				}
			}

		} elseif ( 'update-cart' === $context ) {

			$posted_data = array(
				'add_to_subscription_checked' => false
			);

			$key = doing_action( 'wc_ajax_wcsatt_update_cart_option' ) ? 'add_to_subscription_checked' : 'add-to-subscription-checked';

			if ( ! empty( $_REQUEST[ $key ] ) && 'yes' === $_REQUEST[ $key ] ) {
				$posted_data[ 'add_to_subscription_checked' ] = true;
			}
		}

		return $posted_data;
	}

	/**
	 * Gets all subscriptions matching a scheme.
	 *
	 * @param  WCS_ATT_Scheme  $scheme
	 * @return array
	 */
	public static function get_subscriptions_matching_scheme( $scheme ) {

		// Get all subscriptions of the current user.
		$subscriptions = wcs_get_subscriptions( array(
			'subscription_status'    => array( 'active' ),
			'subscriptions_per_page' => -1,
			'customer_id'            => get_current_user_id()
		) );

		// Filter them by period + interval. PHP 5.2 be damned.
		$matching_subscriptions = array();

		if ( ! empty( $subscriptions ) ) {
			foreach ( $subscriptions as $subscription_id => $subscription ) {

				if ( ! $scheme->matches_subscription( $subscription ) ) {
					continue;
				}

				$matching_subscriptions[ $subscription_id ] = $subscription;
			}
		}

		return $matching_subscriptions;
	}

	/**
	 * Adds a product to a subscription.
	 *
	 * @param  WC_Subscription  $subscription
	 * @param  WC_Product       $product
	 * @param  array            $args
	 * @return boolean
	 */
	public static function add_product_to_subscription( $subscription, $product, $args ) {

		$subscription_scheme = WCS_ATT_Product_Schemes::get_subscription_scheme( $product );
		$default_args        = array(
			'product_id'   => $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(),
			'variation_id' => $product->is_type( 'variation' ) ? $product->get_id() : 0,
			'quantity'     => 1,
			'variations'   => array()
		);

		$parsed_args = wp_parse_args( $args, $default_args );

		/*
		 * Add the product to cart first to ensure all hooks get fired.
		 */

		// Back up the existing cart contents.
		$add_to_subscription_args = array(
			'adding_product'        => $product,
			'restore_cart_contents' => WC()->cart->get_cart()
		);

		$add_to_subscription_args[ 'restore_cart_contents' ] = empty( $add_to_subscription_args[ 'restore_cart_contents' ] ) ? false : $add_to_subscription_args[ 'restore_cart_contents' ];

		// Empty the cart.
		WC()->cart->empty_cart( false );

		$cart_item_key = WC()->cart->add_to_cart( $parsed_args[ 'product_id' ], $parsed_args[ 'quantity' ], $parsed_args[ 'variation_id' ], $parsed_args[ 'variations' ] );

		// Add the product to cart.
		if ( ! $cart_item_key ) {

			wc_clear_notices();

			$subscription_url  = $subscription->get_view_order_url();
			$subscription_link = sprintf( _x( '<a href="%1$s">#%2$s</a>', 'link to subscription', 'woocommerce-subscribe-all-the-things' ), esc_url( $subscription_url ), $subscription->get_id() );

			wc_add_notice( sprintf( __( 'There was a problem adding "%1$s" to subscription %2$s. Please get in touch with us for assistance.', 'woocommerce-subscribe-all-the-things' ), $product->get_name(), $subscription_link ), 'error' );

			if ( $add_to_subscription_args[ 'restore_cart_contents' ] ) {
				WC()->cart->cart_contents = $parsed_args[ 'restore_cart_contents' ];
				WC()->cart->calculate_totals();
			}

			return false;
		}

		// Set scheme on product in cart to ensure it gets seen as a subscription by WCS.
		WCS_ATT_Product_Schemes::set_subscription_scheme( WC()->cart->cart_contents[ $cart_item_key ][ 'data' ], $subscription_scheme );

		// Calculate totals.
		WC()->cart->calculate_totals();

		/*
		 * Now -- add the cart contents to our subscription.
		 */

		self::add_cart_to_subscription( $subscription, $add_to_subscription_args );
	}

	/**
	 * Adds the contents of a (recurring) cart to a subscription.
	 *
	 * @param  WC_Subscription  $subscription
	 * @param  boolean          $args
	 */
	public static function add_cart_to_subscription( $subscription, $args = array() ) {

		if ( 1 !== sizeof( WC()->cart->recurring_carts ) ) {
			return;
		}

		$default_args = array(
			'adding_product'        => false,
			'restore_cart_contents' => false
		);

		$parsed_args = wp_parse_args( $args, $default_args );

		$cart               = current( WC()->cart->recurring_carts );
		$item_added         = false;
		$found_items        = array();
		$subscription_items = $subscription->get_items();
		$subscription_url   = $subscription->get_view_order_url();
		$subscription_link  = sprintf( _x( '<a href="%1$s">#%2$s</a>', 'link to subscription', 'woocommerce-subscribe-all-the-things' ), esc_url( $subscription_url ), $subscription->get_id() );

		// First, map out identical items.
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {

			$product        = $cart_item[ 'data' ];
			$variation_data = $cart_item[ 'variation' ];
			$product_id     = $cart_item[ 'product_id' ];
			$variation_id   = $cart_item[ 'variation_id' ];
			$quantity       = $cart_item[ 'quantity' ];
			$found_item     = false;

			/*
			 * Does an identical line item already exist (hm, what does identical really mean in this context :S)?
			 */
			foreach ( $subscription->get_items() as $item_id => $item ) {

				// Same ID?
				if ( $product_id === $item->get_product_id() && $variation_id === $item->get_variation_id() ) {

					/*
					 * Totals match?
					 */

					$quantity_changed = false;

					// Are we comparing apples to apples?
					if ( $quantity !== $item->get_quantity() ) {

						$cart->set_quantity( $cart_item_key, $item->get_quantity() );
						$cart->calculate_totals();

						$quantity_changed = true;
					}

					// Compare totals.
					if ( $cart->cart_contents[ $cart_item_key ][ 'line_total' ] == $item->get_total() && $cart->cart_contents[ $cart_item_key ][ 'line_subtotal' ] == $item->get_subtotal() ) {
						$found_item = $item;
					}

					// Reset cart item quantity.
					if ( $quantity_changed ) {
						$cart->set_quantity( $cart_item_key, $quantity );
					}

					/*
					 * Variation? Check if attribute values match.
					 */

					if ( $found_item ) {
						if ( $product->is_type( 'variation' ) ) {
							foreach ( $variation_data as $key => $value ) {
								if ( $value !== $item->get_meta( str_replace( 'attribute_', '', $key ), true ) ) {
									$found_item = false;
									break;
								}
							}
						}
					}
				}

				// There's still a chance something else might be different, so let's add a filter here.

				/**
				 * 'wcsatt_add_cart_to_subscription_found_item' filter.
				 *
				 * @param  WC_Order_Item_Product|false  $found_item
				 * @param  array                        $cart_item
				 * @param  WC_Cart                      $cart
				 * @param  WC_Subscription              $subscription
				 */
				$found_item = apply_filters( 'wcsatt_add_cart_to_subscription_found_item', $found_item, $cart_item, $cart, $subscription );

				if ( $found_item ) {
					// Save.
					$found_items[ $cart_item_key ] = $item_id;
					// Break.
					break;
				}
			}
		}

		// If any identical items were found, increment their quantities and recalculate cart totals :)
		if ( ! empty( $found_items ) ) {

			foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {

				if ( isset( $found_items[ $cart_item_key ] ) ) {

					$quantity   = $cart_item[ 'quantity' ];
					$found_item = $subscription_items[ $found_items[ $cart_item_key ] ];

					$cart->set_quantity( $cart_item_key, $quantity + $found_item->get_quantity() );
				}
			}

			$cart->calculate_totals();
		}

		// Now, get to work.
		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {

			$product        = $cart_item[ 'data' ];
			$variation_data = $cart_item[ 'variation' ];
			$quantity       = $cart_item[ 'quantity' ];
			$total          = $cart_item[ 'line_total' ];
			$subtotal       = $cart_item[ 'line_subtotal' ];

			$product_id     = $cart_item[ 'product_id' ];
			$variation_id   = $cart_item[ 'variation_id' ];

			// If an identical line item was found, increase its quantity in the subscription.
			if ( isset( $found_items[ $cart_item_key ] ) ) {

				$found_item    = $subscription_items[ $found_items[ $cart_item_key ] ];
				$existing_item = clone $found_item;

				$item_qty          = $found_item->get_quantity();
				$item_qty_new      = $quantity;
				$item_total_new    = $cart_item[ 'line_total' ];
				$item_subtotal_new = $cart_item[ 'line_subtotal' ];

				$found_item->set_quantity( $item_qty_new );
				$found_item->set_total( $item_total_new );
				$found_item->set_subtotal( $item_subtotal_new );

				$subscription->add_order_note( sprintf( _x( 'Customer increased the quantity of "%1$s" (Product ID: #%2$d) from %3$s to %4$s.', 'used in order note', 'woocommerce-subscribe-all-the-things' ), $found_item->get_name(), $product_id, $item_qty, $item_qty_new ) );

				/**
				 * 'wcsatt_add_cart_item_to_subscription_item_updated' action.
				 *
				 * Fired when an identical item is found in the subscription.
				 *
				 * @param  WC_Order_Item_Product  $found_item
				 * @param  WC_Order_Item_Product  $existing_item
				 * @param  array                  $cart_item
				 * @param  WC_Cart                $cart
				 * @param  WC_Subscription        $subscription
				 */
				do_action( 'wcsatt_add_cart_item_to_subscription_item_updated', $found_item, $existing_item, $cart_item, $cart, $subscription );

				$found_item->save();

				$item_added = true;

			// Otherwise, add a new line item.
			} else {

				/**
				 * Custom callback for adding cart items to subscriptions.
				 *
				 * @param  array|false  $callback
				 * @param  array        $cart_item
				 * @param  WC_Cart      $cart
				 */
				$add_cart_item_to_subscription_callback = apply_filters( 'wscatt_add_cart_item_to_subscription_callback', false, $cart_item, $cart );

				// Do not add cart item.
				if ( is_null( $add_cart_item_to_subscription_callback ) ) {

					continue;

				// Use custom callback to add cart item.
				} if ( is_callable( $add_cart_item_to_subscription_callback ) ) {

					$added_item_id = call_user_func_array( $add_cart_item_to_subscription_callback, array( $subscription, $cart_item, $cart ) );

				// Use standard method.
				} else {

					// Copy subtotals over from the cart item :)
					$added_item_id = $subscription->add_product( $product, $quantity, array(
						'product_id'   => $product_id,
						'variation_id' => $variation_id,
						'variations'   => $variation_data,
						'subtotal'     => $subtotal,
						'total'        => $total
					) );
				}

				if ( ! $added_item_id || is_wp_error( $added_item_id ) ) {

					wc_add_notice( sprintf( __( 'There was a problem adding "%1$s" to subscription %2$s.', 'woocommerce-subscribe-all-the-things' ), $product->get_name(), $subscription_link ), 'error' );

				} else {

					$item_added = true;
					$added_item = wcs_get_order_item( $added_item_id, $subscription );

					// Save the scheme key!
					$added_item->add_meta_data( '_wcsatt_scheme', WCS_ATT_Product_Schemes::get_subscription_scheme( $product ), true );

					$subscription->add_order_note( sprintf( _x( 'Customer added "%1$s" (Product ID: #%2$d).', 'used in order note', 'woocommerce-subscribe-all-the-things' ), $added_item->get_name(), $product_id ) );

					/**
					 * 'wcsatt_add_cart_item_to_subscription_item_added' action.
					 *
					 * Fired when a new item is added to the subscription.
					 *
					 * @param  WC_Order_Item_Product  $found_item
					 * @param  array                  $cart_item
					 * @param  WC_Cart                $cart
					 * @param  WC_Subscription        $subscription
					 */
					do_action( 'wcsatt_add_cart_item_to_subscription_item_added', $added_item, $cart_item, $cart, $subscription );

					$added_item->save();
				}
			}
		}

		// Success, something was added. Note that we don't handle partial failures here, maybe we should?
		if ( $item_added ) {

			$subscription->calculate_totals();
			$subscription->save();

			// Adding a product to a subscription from the single-product page?
			if ( is_a( $parsed_args[ 'adding_product' ], 'WC_Product' ) ) {
				$success_message = sprintf( __( 'You have successfully added "%1$s" to subscription %2$s.', 'woocommerce-subscribe-all-the-things' ), $parsed_args[ 'adding_product' ]->get_name(), $subscription_link );
			} else {
				$success_message = sprintf( __( 'You have successfully added the contents of your cart to subscription %s.', 'woocommerce-subscribe-all-the-things' ), $subscription_link );
			}

			wc_add_notice( $success_message );

			/**
			 * Filter redirect url.
			 *
			 * @param  string           $url
			 * @param  WC_Subscription  $subscription
			 */
			$redirect_url = apply_filters( 'wcsatt_add_cart_to_subscription_redirect_url', $subscription_url, $subscription );

			// Adding a product to a subscription from the single-product page?
			if ( is_a( $parsed_args[ 'adding_product' ], 'WC_Product' ) ) {
				// Reset cart contents to an earlier state if needed - @see 'add_product_to_subscription'.
				if ( is_array( $parsed_args[ 'restore_cart_contents' ] ) ) {
					WC()->cart->cart_contents = $parsed_args[ 'restore_cart_contents' ];
					WC()->cart->calculate_totals();
				// Otherwise nothing must have been in the cart in the first place.
				} else {
					WC()->cart->empty_cart();
				}
			// Just empty the cart, assuming success at this point... or?
			} else {
				WC()->cart->empty_cart();
			}

			wp_safe_redirect( $subscription_url );
			exit;
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Template Functions -- Add Product to Subscription
	|--------------------------------------------------------------------------
	*/

	/**
	 * 'Add to subscription' view -- wrapper element.
	 */
	public static function add_product_to_subscription_template() {

		global $product;

		if ( ! WCS_ATT_Product::supports_feature( $product, 'subscription_management_add_to_subscription' ) ) {
			return;
		}

		// Bypass when switching.
		if ( WCS_ATT_Switch::switching_product( $product ) ) {
			return;
		}

		// User must be logged in.
		if ( ! is_user_logged_in() ) {
			return;
		}

		$subscription_options_visible = false;

		/*
		 * Subscription options for variable products are embedded inside the variation data 'price_html' field and updated by the core variations script.
		 * The add-to-subscription template is displayed when a variation is found.
		 */
		if ( ! $product->is_type( 'variable' ) ) {

			$subscription_schemes                 = WCS_ATT_Product_Schemes::get_subscription_schemes( $product );
			$force_subscription                   = WCS_ATT_Product_Schemes::has_forced_subscription_scheme( $product );
			$is_single_scheme_forced_subscription = $force_subscription && sizeof( $subscription_schemes ) === 1;
			$default_subscription_scheme_key      = apply_filters( 'wcsatt_get_default_subscription_scheme_id', WCS_ATT_Product_Schemes::get_default_subscription_scheme( $product, 'key' ), $subscription_schemes, false === $force_subscription, $product ); // Why 'false === $force_subscription'? The answer is back-compat.
			$default_subscription_scheme          = WCS_ATT_Product_Schemes::get_subscription_scheme( $product, 'object', $default_subscription_scheme_key );

			$subscription_options_visible = $is_single_scheme_forced_subscription || ( is_object( $default_subscription_scheme ) && ! $default_subscription_scheme->is_prorated() );
		}

		wc_get_template( 'single-product/product-add-to-subscription.php', array(
			'product_id' => $product->get_id(),
			'is_visible' => $subscription_options_visible
		), false, WCS_ATT()->plugin_path() . '/templates/' );
	}

	/**
	 * 'Add to subscription' view -- 'Add' button template.
	 *
	 * @param  WC_Subscription  $subscription
	 */
	public static function add_product_to_subscription_button_template( $subscription ) {

		wc_get_template( 'single-product/product-add-to-subscription-button.php', array(
			'subscription_id' => $subscription->get_id()
		), false, WCS_ATT()->plugin_path() . '/templates/' );
	}

	/**
	 * 'Add to subscription' view -- matching list of subscriptions.
	 *
	 * @param  array                $subscriptions
	 * @param  WC_Product           $product
	 * @param  WCS_ATT_Scheme|null  $scheme
	 * @return void
	 */
	public static function subscriptions_matching_product_template( $subscriptions, $product, $scheme ) {

		add_action( 'woocommerce_my_subscriptions_actions', array( __CLASS__, 'add_product_to_subscription_button_template' ) );

		wp_nonce_field( 'wcsatt_add_product_to_subscription', 'wcsatt_nonce_' . $product->get_id() );

		wc_get_template( 'single-product/product-add-to-subscription-list.php', array(
			'subscriptions' => $subscriptions,
			'product'       => $product,
			'scheme'        => $scheme,
			'user_id'       => get_current_user_id()
		), false, WCS_ATT()->plugin_path() . '/templates/' );

		remove_action( 'woocommerce_my_subscriptions_actions', array( __CLASS__, 'add_product_to_subscription_button_template' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Template Functions -- Add Cart to Subscription
	|--------------------------------------------------------------------------
	*/

	/**
	 * 'Add cart to subscription' view -- template wrapper element.
	 */
	public static function add_cart_to_subscription_template() {

		$subscription_options_visible = false;
		$active_cart_scheme_key       = WCS_ATT_Cart::get_cart_subscription_scheme();
		$posted_data                  = self::get_posted_add_to_subscription_data( 'update-cart' );

		wc_get_template( 'cart/cart-add-to-subscription.php', array(
			'is_visible' => false !== $active_cart_scheme_key,
			'is_checked' => $posted_data[ 'add_to_subscription_checked' ]
		), false, WCS_ATT()->plugin_path() . '/templates/' );
	}

	/**
	 * Displays list of subscriptions matching a cart.
	 */
	public static function display_subscriptions_matching_cart() {

		$cart_schemes           = WCS_ATT_Cart::get_cart_subscription_schemes( 'cart-display' );
		$active_cart_scheme_key = WCS_ATT_Cart::get_cart_subscription_scheme();
		$active_cart_scheme     = isset( $cart_schemes[ $active_cart_scheme_key ] ) ? $cart_schemes[ $active_cart_scheme_key ] : false;

		if ( $active_cart_scheme ) {

			/**
			 * 'wcsatt_subscriptions_matching_cart' filter.
			 *
			 * Last chance to filter matched subscriptions.
			 *
			 * @param  array                $matching_subscriptions
			 * @param  WCS_ATT_Scheme|null  $scheme
			 */
			$matching_subscriptions = apply_filters( 'wcsatt_subscriptions_matching_cart', self::get_subscriptions_matching_scheme( $active_cart_scheme ), $active_cart_scheme );

			/**
			 * 'wcsatt_add_cart_to_subscription_html' action.
			 *
			 * @param  array                $matching_subscriptions
			 * @param  WCS_ATT_Scheme|null  $scheme
			 *
			 */
			do_action( 'wcsatt_add_cart_to_subscription_html', $matching_subscriptions, $active_cart_scheme );
		}
	}

	/**
	 * 'Add to subscription' view -- matching list of subscriptions.
	 *
	 * @param  array                $subscriptions
	 * @param  WCS_ATT_Scheme|null  $scheme
	 * @return void
	 */
	public static function subscriptions_matching_cart_template( $subscriptions, $scheme ) {

		add_action( 'woocommerce_my_subscriptions_actions', array( __CLASS__, 'add_cart_to_subscription_button_template' ) );

		wp_nonce_field( 'wcsatt_add_cart_to_subscription', 'wcsatt_nonce' );

		wc_get_template( 'cart/cart-add-to-subscription-list.php', array(
			'subscriptions' => $subscriptions,
			'scheme'        => $scheme,
			'user_id'       => get_current_user_id()
		), false, WCS_ATT()->plugin_path() . '/templates/' );

		remove_action( 'woocommerce_my_subscriptions_actions', array( __CLASS__, 'add_cart_to_subscription_button_template' ) );
	}

	/**
	 * 'Add to subscription' view -- 'Add' button template.
	 *
	 * @param  WC_Subscription  $subscription
	 */
	public static function add_cart_to_subscription_button_template( $subscription ) {

		wc_get_template( 'cart/cart-add-to-subscription-button.php', array(
			'subscription_id' => $subscription->get_id()
		), false, WCS_ATT()->plugin_path() . '/templates/' );
	}

	/*
	|--------------------------------------------------------------------------
	| Ajax Handlers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Load all user subscriptions matching a product + scheme key (known billing period and interval).
	 *
	 * @return void
	 */
	public static function load_subscriptions_matching_product() {

		$failure = array(
			'result' => 'failure',
			'html'   => ''
		);

		// User must be logged in.
		if ( ! is_user_logged_in() ) {
			wp_send_json( $failure );
		}

		$product_id = ! empty( $_POST[ 'product_id' ] ) ? absint( $_POST[ 'product_id' ] ) : false;
		$scheme_key = ! empty( $_POST[ 'subscription_scheme' ] ) ? wc_clean( $_POST[ 'subscription_scheme' ] ) : false;

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			wp_send_json( $failure );
		}

		$scheme = WCS_ATT_Product_Schemes::get_subscription_scheme( $product, 'object', $scheme_key );

		if ( ! $scheme ) {
			wp_send_json( $failure );
		}

		$matching_subscriptions = self::get_subscriptions_matching_scheme( $scheme );

		/**
		 * 'wcsatt_subscriptions_matching_product' filter.
		 *
		 * Last chance to filter matched subscriptions.
		 *
		 * @param  array                $matching_subscriptions
		 * @param  WC_Product           $product
		 * @param  WCS_ATT_Scheme|null  $scheme
		 */
		$matching_subscriptions = apply_filters( 'wcsatt_subscriptions_matching_product', $matching_subscriptions, $product, $scheme );

		ob_start();

		/**
		 * 'wcsatt_add_product_to_subscription_html' action.
		 *
		 * @param  array                $matching_subscriptions
		 * @param  WC_Product           $product
		 * @param  WCS_ATT_Scheme|null  $scheme
		 *
		 * @hooked WCS_ATT_Add::subscriptions_matching_product_template - 10
		 */
		do_action( 'wcsatt_add_product_to_subscription_html', $matching_subscriptions, $product, $scheme );

		$html = ob_get_clean();

		if ( ! $html ) {
			$result = $failure;
		} else {
			$result = array(
				'result' => 'success',
				'html'   => $html
			);
		}

		wp_send_json( $result );
	}

	/**
	 * Load all user subscriptions matching a cart + scheme key (known billing period and interval).
	 *
	 * @return void
	 */
	public static function load_subscriptions_matching_cart() {

		$failure = array(
			'result' => 'failure',
			'html'   => ''
		);

		// User must be logged in.
		if ( ! is_user_logged_in() ) {
			wp_send_json( $failure );
		}

		ob_start();

		self::display_subscriptions_matching_cart();

		$html = ob_get_clean();

		if ( ! $html ) {
			$result = $failure;
		} else {
			$result = array(
				'result' => 'success',
				'html'   => $html
			);
		}

		wp_send_json( $result );
	}

	/*
	|--------------------------------------------------------------------------
	| Form Handlers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Adds products to subscriptions after validating.
	 */
	public static function add_product_to_subscription_form_handler() {

		$posted_data = self::get_posted_add_to_subscription_data( 'product' );

		if ( empty( $posted_data[ 'product_id' ] ) ) {
			return;
		}

		if ( empty( $posted_data[ 'subscription_id' ] ) ) {
			return;
		}

		if ( empty( $posted_data[ 'subscription_scheme' ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $posted_data[ 'nonce' ], 'wcsatt_add_product_to_subscription' ) ) {
			return;
		}

		$product_id      = $posted_data[ 'product_id' ];
		$subscription_id = $posted_data[ 'subscription_id' ];
		$subscription    = wcs_get_subscription( $subscription_id );

		if ( ! $subscription ) {
			wc_add_notice( sprintf( __( 'Subscription #%d cannot be edited. Please get in touch with us for assistance.', 'woocommerce-subscribe-all-the-things' ), $subscription_id ), 'error' );
			return;
		}

		/*
		 * Relay form validation to 'WC_Form_Handler::add_to_cart_action'.
		 * Use 'woocommerce_add_to_cart_validation' filter to:
		 *
		 * - Let WC validate the form.
		 * - If invalid, stop.
		 * - If valid, add the validated product to the selected subscription.
		 */

		self::$add_product_to_subscription_args = false;

		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_product_to_subscription' ), 9999, 5 );

		/**
		 * 'wcsatt_pre_add_product_to_subscription_validation' action.
		 *
		 * @param  int  $product_id
		 * @param  int  $subscription_id
		 */
		do_action( 'wcsatt_pre_add_product_to_subscription_validation', $product_id, $subscription_id );

		$_REQUEST[ 'add-to-cart' ] = $product_id;

		// No worries, nothing gets added to the cart at this point.
		WC_Form_Handler::add_to_cart_action();

		// Disarm 'WC_Form_Handler::add_to_cart_action'.
		$_REQUEST[ 'add-to-cart' ] = false;

		/**
		 * 'wcsatt_pre_add_product_to_subscription_validation' action.
		 *
		 * @param  int  $product_id
		 * @param  int  $subscription_id
		 */
		do_action( 'wcsatt_post_add_product_to_subscription_validation', $product_id, $subscription_id );


		// Remove filter.
		remove_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_product_to_subscription' ), 9999 );

		// Validation passed?
		if ( ! self::$add_product_to_subscription_args ) {
			return;
		}

		// At this point we've got the green light to proceed.
		$subscription_scheme = $posted_data[ 'subscription_scheme' ];
		$product             = self::$add_product_to_subscription_args[ 'product' ];
		$args                = array_diff_key( self::$add_product_to_subscription_args, array( 'product' => 1 ) );

		// Set scheme on product object for later reference.
		WCS_ATT_Product_Schemes::set_subscription_scheme( $product, $subscription_scheme );

		try {

			/**
			 * 'wcsatt_add_product_to_subscription' action.
			 *
			 * @param  WC_Subscription  $subscription
			 * @param  WC_Product       $product
			 * @param  array            $args
			 *
			 * @hooked WCS_ATT_Add::add_product_to_subscription - 10
			 */
			do_action( 'wcsatt_add_product_to_subscription', $subscription, $product, $args );

		} catch ( Exception $e ) {

			if ( $notice = $e->getMessage() ) {

				wc_add_notice( $notice, 'error' );
				return false;
			}
		}
	}

	/**
	 * Adds carts to subscriptions.
	 */
	public static function add_cart_to_subscription_form_handler() {

		$posted_data = self::get_posted_add_to_subscription_data( 'cart' );

		if ( empty( $posted_data[ 'subscription_id' ] ) ) {
			return;
		}

		if ( empty( $posted_data[ 'subscription_scheme' ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $posted_data[ 'nonce' ], 'wcsatt_add_cart_to_subscription' ) ) {
			return;
		}

		$subscription_id         = $posted_data[ 'subscription_id' ];
		$subscription            = wcs_get_subscription( $subscription_id );

		if ( ! $subscription ) {
			wc_add_notice( sprintf( __( 'Subscription #%d cannot be edited. Please get in touch with us for assistance.', 'woocommerce-subscribe-all-the-things' ), $subscription_id ), 'error' );
			return;
		}

		$cart_schemes            = WCS_ATT_Cart::get_cart_subscription_schemes( 'cart' );
		$subscription_scheme_key = $posted_data[ 'subscription_scheme' ];
		$subscription_scheme_obj = isset( $cart_schemes[ $subscription_scheme_key ] ) ? $cart_schemes[ $subscription_scheme_key ] : false;

		if ( ! $subscription_scheme_obj || ! WC_Subscriptions_Cart::cart_contains_subscription() || 1 !== sizeof( WC()->cart->recurring_carts ) || ! $subscription_scheme_obj->matches_subscription( $subscription ) ) {
			wc_add_notice( sprintf( __( 'Your cart cannot be added to subscription #%d. Please get in touch with us for assistance.', 'woocommerce-subscribe-all-the-things' ), $subscription_id ), 'error' );
			return;
		}

		try {

			/**
			 * 'wcsatt_add_cart_to_subscription' action.
			 *
			 * @param  WC_Subscription  $subscription
			 *
			 * @hooked WCS_ATT_Add::add_cart_to_subscription - 10
			 */
			do_action( 'wcsatt_add_cart_to_subscription', $subscription );

		} catch ( Exception $e ) {

			if ( $notice = $e->getMessage() ) {

				wc_add_notice( $notice, 'error' );
				return false;
			}
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Form Handling Hooks
	|--------------------------------------------------------------------------
	*/

	/**
	 * Signals 'add_product_to_subscription_form_handler' that validation failed.
	 * Data is exchanged via the 'add_product_to_subscription' static prop.
	 * Always returns false to ensure nothing gets added to the cart.
	 *
	 * @param  boolean  $result
	 * @param  int      $product_id
	 * @param  mixed    $quantity
	 * @param  int      $variation_id
	 * @param  array    $variations
	 * @return bool
	 */
	public static function validate_add_product_to_subscription( $result, $product_id, $quantity, $variation_id = 0, $variations = array() ) {

		if ( $result ) {

			$product = wc_get_product( $variation_id ? $variation_id : $product_id );

			/*
			 * Validate stock.
			 */

			if ( ! $product->is_in_stock() ) {
				wc_add_notice( sprintf( __( '&quot;%s&quot; is out of stock.', 'woocommerce-subscribe-all-the-things' ), $product->get_name() ), 'error' );
				return false;
			}

			if ( ! $product->has_enough_stock( $quantity ) ) {
				/* translators: 1: product name 2: quantity in stock */
				wc_add_notice( sprintf( __( '&quot;%1$s&quot; does not have enough stock (%2$s remaining).', 'woocommerce-subscribe-all-the-things' ), $product->get_name(), wc_format_stock_quantity_for_display( $product->get_stock_quantity(), $product ) ), 'error' );
				return false;
			}

			/*
			 * Flash the green light.
			 */

			self::$add_product_to_subscription_args = array(
				'product'      => $product,
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'quantity'     => $quantity,
				'variations'   => $variations
			);
		}

		return false;
	}
}
