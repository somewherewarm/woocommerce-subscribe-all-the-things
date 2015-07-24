<?php
/**
 * Cart functionality for converting cart items to subscriptions.
 *
 * @class 	WCCSubs_Cart
 * @version 1.0.0
 */

class WCCSubs_Cart {

	public static function init() {

		// allow subs to recognize a cart item of any product type as a subscription
		add_filter( 'woocommerce_is_subscription', __CLASS__ . '::is_converted_to_sub', 10, 3 );

		// add convert-to-sub configuration data to cart items that can be converted
		add_filter( 'woocommerce_add_cart_item', __CLASS__ . '::add_cart_item_convert_to_sub_data', 10, 2 );

		// load convert-to-sub cart item session data
		add_filter( 'woocommerce_get_cart_item_from_session', __CLASS__ . '::load_convert_to_sub_session_data', 10, 2 );

		// remove the subs price string suffix from cart items that can be converted
		add_filter( 'woocommerce_subscriptions_product_price_string_inclusions', __CLASS__ . '::convertible_sub_price_string', 10, 2 );

		// use radio buttons to mark a cart item as a one-time sale or as a subscription
		add_filter( 'woocommerce_cart_item_subtotal', __CLASS__ . '::convert_to_sub_options', 1000, 3 );

		// save the convert to sub radio button setting when clicking the 'update cart' button
		add_filter( 'woocommerce_update_cart_action_cart_updated', __CLASS__ . '::update_convert_to_sub_options', 10 );
	}

	/**
	 * Updates the convert-to-sub status of a cart item based on the cart item option.
	 *
	 * @param  boolean $updated
	 * @return boolean
	 */
	public static function update_convert_to_sub_options( $updated ) {

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {

			if ( ! empty( $cart_item[ 'wccsub_data' ] ) && ! empty( $_POST[ 'cart' ][ $cart_item_key ][ 'convert_to_sub' ] ) ) {

				WC()->cart->cart_contents[ $cart_item_key ][ 'wccsub_data' ][ 'is_converted' ] = $_POST[ 'cart' ][ $cart_item_key ][ 'convert_to_sub' ];

				$updated = true;
			}
		}

		return $updated;
	}

	/**
	 * Removes the subs price string suffix from cart items that can be converted to subs.
	 * Not needed, since subscription parameters are displayed in the 'convert_to_sub' radio option.
	 *
	 * @param  array      $inclusions
	 * @param  WC_Product $product
	 * @return array
	 */
	public static function convertible_sub_price_string( $inclusions, $product ) {

		if ( isset( $product->is_converted_to_sub ) ) {

			if ( isset( $product->delete_subscription_price_suffix ) && $product->delete_subscription_price_suffix === 'yes' ) {

				$inclusions[ 'subscription_period' ] = false;
				$inclusions[ 'subscription_length' ] = false;
				$inclusions[ 'sign_up_fee' ]         = false;
				$inclusions[ 'trial_length' ]        = false;
			}
		}

		return $inclusions;
	}

	/**
	 * Displays an option to purchase the item once or create a subscription from it.
	 *
	 * @param  string $subtotal
	 * @param  array  $cart_item
	 * @param  string $cart_item_key
	 * @return string
	 */
	public static function convert_to_sub_options( $subtotal, $cart_item, $cart_item_key ) {

		$show_convert_to_sub_options = apply_filters( 'wccsub_show_cart_item_options', isset( $cart_item[ 'wccsub_data' ] ), $cart_item, $cart_item_key );

		// currently show options only in cart
		if ( ! is_cart() || ! $show_convert_to_sub_options ) {
			return $subtotal;
		}

		$sub_checked = ( isset( $cart_item[ 'wccsub_data' ][ 'is_converted' ] ) && $cart_item[ 'wccsub_data' ][ 'is_converted' ] === 'yes' ) ? 'yes' : 'no';

		if ( $sub_checked === 'yes' ) {

			// if the cart item has been converted to a sub, create the subscription price suffix using the already-populated subscription properties
			$cart_item[ 'data' ]->delete_subscription_price_suffix = 'no';
			$sub_suffix  = WC_Subscriptions_Product::get_price_string( $cart_item[ 'data' ], array( 'subscription_price' => false ) );
			$cart_item[ 'data' ]->delete_subscription_price_suffix = 'yes';

		} else {

			// if the cart item has not been converted to a sub, populate the product with the subscription properties, generate the suffix and reset
			$cart_item[ 'data' ]->is_converted_to_sub          = 'yes';
			$cart_item[ 'data' ]->subscription_period          = $cart_item[ 'wccsub_data' ][ 'subscription_period' ];
			$cart_item[ 'data' ]->subscription_period_interval = $cart_item[ 'wccsub_data' ][ 'subscription_period_interval' ];
			$cart_item[ 'data' ]->subscription_length          = $cart_item[ 'wccsub_data' ][ 'subscription_length' ];

			$sub_suffix  = WC_Subscriptions_Product::get_price_string( $cart_item[ 'data' ], array( 'subscription_price' => false ) );

			$cart_item[ 'data' ]->is_converted_to_sub = 'no';
			unset( $cart_item[ 'data' ]->subscription_period );
			unset( $cart_item[ 'data' ]->subscription_period_interval );
			unset( $cart_item[ 'data' ]->subscription_length );

		}

		ob_start();

		?>
		<ul class="wccsubs-convert">
			<li>
				<label>
					<input type="radio" name="cart[<?php echo $cart_item_key; ?>][convert_to_sub]" value="no" <?php checked( $sub_checked, 'no', true ); ?> />
					<?php echo 'only this time'; ?>
				</label>
			</li>
			<li>
				<label>
					<input type="radio" name="cart[<?php echo $cart_item_key; ?>][convert_to_sub]" value="yes" <?php checked( $sub_checked, 'yes', true ); ?> />
					<?php echo $sub_suffix; ?>
				</label>
			</li>
		</ul>
		<?php

		$convert_to_sub_options = ob_get_clean();

		$subtotal = $subtotal . $convert_to_sub_options;

		return $subtotal;
	}

	/**
	 * Add convert-to-sub data to cart items that can be converted.
	 * Sub parameters are still hardcoded, but can be easily loaded from metadata or other sources.
	 *
	 * @param array $cart_item
	 * @param int   $product_id
	 */
	public static function add_cart_item_convert_to_sub_data( $cart_item, $product_id ) {

		if ( self::is_convertible_to_sub( $cart_item ) ) {
			$cart_item[ 'wccsub_data' ] = array(
				'is_converted'                 => 'no',
				'subscription_period'          => 'month',
				'subscription_period_interval' => 2,
				'subscription_length'          => 6,
			);
		}

		return $cart_item;
	}

	/**
	 * Load stored convert-to-sub session data.
	 * Cart items are converted to subscriptions here, then Subs code does all the magic.
	 *
	 * @param  array  $cart_item
	 * @param  array  $item_session_values
	 * @return array
	 */
	public static function load_convert_to_sub_session_data( $cart_item, $item_session_values ) {

		if ( isset( $item_session_values[ 'wccsub_data' ] ) ) {

			$cart_item[ 'wccsub_data' ] = $item_session_values[ 'wccsub_data' ];

			$cart_item[ 'data' ]->is_converted_to_sub              = $item_session_values[ 'wccsub_data' ][ 'is_converted' ];
			$cart_item[ 'data' ]->subscription_period              = $item_session_values[ 'wccsub_data' ][ 'subscription_period' ];
			$cart_item[ 'data' ]->subscription_period_interval     = $item_session_values[ 'wccsub_data' ][ 'subscription_period_interval' ];
			$cart_item[ 'data' ]->subscription_length              = $item_session_values[ 'wccsub_data' ][ 'subscription_length' ];
			$cart_item[ 'data' ]->delete_subscription_price_suffix = $item_session_values[ 'wccsub_data' ][ 'is_converted' ];
		}

		return $cart_item;
	}

	/**
	 * Hooks onto 'woocommerce_is_subscription' to trick Subs into thinking it is dealing with a subscription.
	 * The necessary subscription properties are added to the product in 'load_convert_to_sub_session_data()'.
	 *
	 * @param  boolean    $is
	 * @param  int        $product_id
	 * @param  WC_Product $product
	 * @return boolean
	 */
	public static function is_converted_to_sub( $is, $product_id, $product ) {

		if ( ! $product ) {
			return $is;
		}

		if ( $product->is_converted_to_sub === 'yes' ) {
			$is = true;
		}

		return $is;
	}

	/**
	 * True if a cart item can be converted from a one-shot purchase to a subscription and vice-versa.
	 * Subscription product types can't be converted to non-sub items.
	 *
	 * @param  array  $cart_item
	 * @return boolean
	 */
	public static function is_convertible_to_sub( $cart_item ) {

		$product_id     = $cart_item[ 'product_id' ];
		$is_convertible = true;

		if ( WC_Subscriptions_Product::is_subscription( $product_id ) ) {
			$is_convertible = false;
		}

		return $is_convertible;
	}

}

WCCSubs_Cart::init();
