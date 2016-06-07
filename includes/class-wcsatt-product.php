<?php
/**
 * Type-agnostic product wrapper.
 *
 * @class 	WCS_ATT_Product
 * @version 1.2.0
 */

class WCS_ATT_Product {

	private $product;

	private $subscription_schemes = array();

	private $active_subscription_scheme_id = false;

	private $min_price_subscription_scheme_id = false;

	private $force_subscription;

	/**
	 * __construct function.
	 *
	 * @param mixed $product
	 */
	public function __construct( $product ) {

		$this->product = WCS_ATT_Products::get_wc_product( $product );

		// Initialize with subscription schemes defined at product level.
		$this->subscription_schemes = WCS_ATT_Schemes::get_product_subscription_schemes( $this->product );

		$this->force_subscription = get_post_meta( $this->product->id, '_wcsatt_force_subscription', true );
	}

	/**
	 * __set function.
	 *
	 * @param  string $key
	 * @param  mixed  $value
	 * @return void
	 */
	public function __set( $key, $value ) {
		$this->product->$key = $value;
	}

	/**
	 * __get function.
	 *
	 * @param  string $key
	 * @return void
	 */
	public function __get( $key ) {
		return $this->product->$key;
	}

	/**
	 * __isset function.
	 *
	 * @param  string $key
	 * @return void
	 */
	public function __isset( $key ) {
		return isset( $this->product->$key );
	}

	/**
	 * __unset function.
	 *
	 * @param  string $key
	 * @return void
	 */
	public function __unset( $key ) {
		unset( $this->product->$key );
	}

	/**
	 * __call function.
	 *
	 * @param  string $name
	 * @param  array  $arguments
	 * @return mixed
	 */
	public function __call( $name, $arguments ) {
		return call_user_func_array( array( $this->product, $name ), $arguments );
	}

	public function is_subscription_only() {
		return $this->force_subscription === 'yes';
	}

	public function get_subscription_scheme_option_price_html( $subscription_scheme_id ) {
		return $this->get_subscription_scheme_price_html( $subscription_scheme_id, true );
	}

	public function get_subscription_scheme_price_html( $subscription_scheme_id, $singular = false ) {

		$product                              = $this->product;
		$has_variable_price                   = false;
		$subscription_scheme                  = WCS_ATT_Schemes::get_subscription_scheme_by_id( $subscription_scheme_id, $this->subscription_schemes );

		if ( ! $subscription_scheme ) {
			return false;
		}

		$subscription_schemes_count            = count( $this->subscription_schemes );
		$is_single_scheme_subscription_product = $this->is_subscription_only() && $subscription_schemes_count === 1;

		// Reinstantiate variable products to re-populate a filtered version of the 'prices_array' property. Otherwise, a clone should do... but re-instantiate just in case.
		$_product = WCS_ATT_Products::get_wc_product( $product->id );

		// ...and let this be filterable.
		$_product = apply_filters( 'wcsatt_overridden_subscription_prices_product', $_product, $subscription_scheme, $product );
		$_product = WCS_ATT_Products::convert_to_sub( $_product, $subscription_scheme );

		// Add price method filters.
		WCS_ATT_Scheme_Prices::add_price_filters( $_product, $subscription_scheme );

		if ( ! $singular ) {
			if ( $subscription_schemes_count > 1 ) {
				$has_variable_price = true;
			} else {
				if ( 'variable' === $product->product_type && $_product->get_variation_price( 'min' ) !== $_product->get_variation_price( 'max' ) ) {
					$has_variable_price = true;

					// If all variations prices are overridden, they will be equal.
					if ( isset( $subscription_scheme[ 'subscription_pricing_method' ] ) && $subscription_scheme[ 'subscription_pricing_method' ] === 'override' ) {
						$has_variable_price = false;
					}

				} elseif ( 'bundle' === $product->product_type && $product->get_bundle_price( 'min' ) !== $product->get_bundle_price( 'max' ) ) {
					$has_variable_price = true;

				} elseif ( 'composite' === $product->product_type && $product->get_composite_price( 'min' ) !== $product->get_composite_price( 'max' ) ) {
					$has_variable_price = true;
				}
			}
		}

		if ( $singular || $this->is_subscription_only() ) {

			if ( $singular && $is_single_scheme_subscription_product ) {
				$price = '';
			} else {
				$price = $_product->get_price_html();
			}

			$show_subscription_price = false === $singular || WCS_ATT_Scheme_Prices::has_subscription_price_override( $subscription_scheme );

			$price = WC_Subscriptions_Product::get_price_string( $_product, array(
				'subscription_price' => $show_subscription_price || $is_single_scheme_subscription_product,
				'price'              => $price
			) );

			if ( $has_variable_price && false === strpos( $price, $_product->get_price_html_from_text() ) ) {
				$price = sprintf( _x( '%1$s%2$s', 'Price range: from', WCS_ATT::TEXT_DOMAIN ), $_product->get_price_html_from_text(), $price );
			}

		} else {

			$price             = $product->get_price_html();
			$suffix_price_html = '';

			// Discount format vs Price format. Experimental use only.
			if ( apply_filters( 'wcsatt_price_html_discount_format', false, $product ) && $subscription_scheme[ 'subscription_pricing_method' ] === 'inherit' ) {

				$discount          = $subscription_scheme[ 'subscription_discount' ];
				$discount_html     = '</small> <span class="wcsatt-sub-discount">' . sprintf( __( '%s&#37; off', WCS_ATT::TEXT_DOMAIN ), $discount ) . '</span><small>';
				$suffix_price_html = sprintf( __( 'at%1$s%2$s', WCS_ATT::TEXT_DOMAIN ), $has_variable_price ? __( ' up to', WCS_ATT::TEXT_DOMAIN ) : '', $discount_html );

			} else {

				$lowest_scheme_price_html = $_product->get_price_html();

				$lowest_scheme_price_html = WC_Subscriptions_Product::get_price_string( $_product, array( 'price' => $lowest_scheme_price_html ) );

				if ( $has_variable_price ) {
					$suffix_price_html = sprintf( _x( '%1$s%2$s', 'Price range: from', WCS_ATT::TEXT_DOMAIN ), _x( '<span class="from">from </span>', 'subscribe from price', WCS_ATT::TEXT_DOMAIN ), str_replace( $_product->get_price_html_from_text(), '', $lowest_scheme_price_html ) );
				} else {
					$suffix_price_html = sprintf( _x( '%1$s%2$s', 'Price range: from', WCS_ATT::TEXT_DOMAIN ), _x( '<span class="for">for </span>', 'subscribe for price', WCS_ATT::TEXT_DOMAIN ), $lowest_scheme_price_html );
				}
			}

			if ( WCS_ATT_Scheme_Prices::subscription_price_overrides_exist( $this->subscription_schemes ) ) {
				$suffix = ' <small class="wcsatt-sub-options">' . sprintf( _n( '&ndash; or subscribe %s', '&ndash; subscription plans available %s', $subscription_schemes_count, WCS_ATT::TEXT_DOMAIN ), $suffix_price_html ) . '</small>';
			} else {
				$suffix = ' <small class="wcsatt-sub-options">' . sprintf( _n( '&ndash; subscription available', '&ndash; subscription plans available', $subscription_schemes_count, WCS_ATT::TEXT_DOMAIN ), $suffix_price_html ) . '</small>';
			}

			$price = sprintf( _x( '%1$s%2$s', 'price html sub options suffix', WCS_ATT::TEXT_DOMAIN ), $price, $suffix );
		}

		WCS_ATT_Scheme_Prices::remove_price_filters();

		return $price;
	}


	public function get_price_html( $price = '' ) {

		if ( ! empty( $this->subscription_schemes ) ) {
			if ( false !== $this->active_subscription_scheme_id ) {
				$price = $this->product->get_price_html();
			} else {
				$price = $this->get_subscription_scheme_price_html( $this->get_min_price_subscription_scheme_id() );
			}
		} else {
			$price = $this->product->get_price_html();
		}

		return $price;
	}

	public function get_subscription_schemes() {
		return $this->subscription_schemes;
	}

	public function get_active_subscription_scheme_id() {
		return $this->active_subscription_scheme_id;
	}

	public function get_min_price_subscription_scheme_id() {

		if ( false === $this->min_price_subscription_scheme_id ) {
			$min_price_subscription_scheme_data     = WCS_ATT_Scheme_Prices::get_min_price_subscription_scheme_data( $this->product, $this->subscription_schemes );
			$min_price_subscription_scheme_id       = $min_price_subscription_scheme_data[ 'scheme' ][ 'id' ];
			$this->min_price_subscription_scheme_id = $min_price_subscription_scheme_id;
		}

		return $this->min_price_subscription_scheme_id;
	}
}
