<?php
/**
 * WCS_ATT_Scheme class
 *
 * @author   SomewhereWarm <info@somewherewarm.gr>
 * @package  WooCommerce Subscribe All The Things
 * @since    2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscription scheme object. May extend the WC_Data class or handle CRUD in the future, if schemes are moved out of meta.
 *
 * @class  WCS_ATT_Scheme
 * @since  2.0.0
 */
class WCS_ATT_Scheme implements ArrayAccess {

	/**
	 * Scheme data.
	 * @var array
	 */
	private $data = array();

	/**
	 * Scheme key - a string representation of the scheme details.
	 * @var array
	 */
	private $key = '';

	/**
	 * Maps meta array key names to object data keys for back-compat.
	 * @var array
	 */
	private $offset_map = array(
		'subscription_period'          => 'period',
		'subscription_period_interval' => 'interval',
		'subscription_length'          => 'length',
		'subscription_trial_period'    => 'trial_period',
		'subscription_trial_length'    => 'trial_length',
		'subscription_pricing_method'  => 'pricing_mode',
		'subscription_discount'        => 'discount',
		'subscription_regular_price'   => 'regular_price',
		'subscription_sale_price'      => 'sale_price',
		'subscription_price'           => 'price'
	);

	/**
	 * Constructor. Currently only initializes the object from raw data.
	 * Later, it could initialize using other source data, such as a DB ID.
	 *
	 * @param  array  $args
	 */
	public function __construct( $args ) {

		if ( isset( $args[ 'data' ] ) ) {

			$this->data[ 'period' ]       = isset( $args[ 'data' ][ 'subscription_period' ] ) ? strval( $args[ 'data' ][ 'subscription_period' ] ) : '';
			$this->data[ 'interval' ]     = isset( $args[ 'data' ][ 'subscription_period_interval' ] ) ? absint( $args[ 'data' ][ 'subscription_period_interval' ] ) : '';
			$this->data[ 'length' ]       = isset( $args[ 'data' ][ 'subscription_length' ] ) ? absint( $args[ 'data' ][ 'subscription_length' ] ) : '';

			$this->data[ 'trial_period' ] = isset( $args[ 'data' ][ 'subscription_trial_period' ] ) ? strval( $args[ 'data' ][ 'subscription_trial_period' ] ) : '';
			$this->data[ 'trial_length' ] = isset( $args[ 'data' ][ 'subscription_trial_length' ] ) ? absint( $args[ 'data' ][ 'subscription_trial_length' ] ) : '';

			$this->data[ 'pricing_mode' ] = isset( $args[ 'data' ][ 'subscription_pricing_method' ] ) && in_array( $args[ 'data' ][ 'subscription_pricing_method' ], array( 'inherit', 'override' ) ) ? strval( $args[ 'data' ][ 'subscription_pricing_method' ] ) : 'inherit';

			if ( 'override' === $this->data[ 'pricing_mode' ] ) {
				$this->data[ 'regular_price' ] = isset( $args[ 'data' ][ 'subscription_regular_price' ] ) ? wc_format_decimal( $args[ 'data' ][ 'subscription_regular_price' ] ) : '';
				$this->data[ 'sale_price' ]    = isset( $args[ 'data' ][ 'subscription_sale_price' ] ) ? wc_format_decimal( $args[ 'data' ][ 'subscription_sale_price' ] ) : '';
				$this->data[ 'price' ]         = '' !== $this->data[ 'sale_price' ] && $this->data[ 'sale_price' ] < $this->data[ 'regular_price' ] ? $this->data[ 'sale_price' ] : $this->data[ 'regular_price' ];

				if ( '' === $this->data[ 'price' ] && '' === $this->data[ 'regular_price' ] ) {
					$this->data[ 'pricing_mode' ] = 'inherit';
				}
			}

			if ( 'inherit' === $this->data[ 'pricing_mode' ] ) {
				$this->data[ 'discount' ] = isset( $args[ 'data' ][ 'subscription_discount' ] ) ? wc_format_decimal( $args[ 'data' ][ 'subscription_discount' ] ) : '';
			}
		}

		$this->data[ 'context' ] = isset( $args[ 'context' ] ) ? strval( $args[ 'context' ] ) : 'product';

		$this->key = implode( '_', array_filter( array( $this->data[ 'interval' ], $this->data[ 'period' ], $this->data[ 'length' ] ) ) );
	}

	/**
	 *
	 * Returns a unique scheme identifier. For now it just returns the key. Later it could be a CRUD object identifier.
	 *
	 * @return string  A unique identifier.
	 */
	public function get_id() {
		return $this->key;
	}

	/**
	 * Returns a string representation of the scheme details.
	 *
	 * @return string  A string representation of the entire scheme.
	 */
	public function get_key() {
		return $this->key;
	}

	/**
	 * Returns the raw scheme data array.
	 *
	 * @return array
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Gets the scheme context. Expected values: 'product', 'cart'.
	 *
	 * @return string
	 */
	public function get_context() {
		return $this->data[ 'context' ];
	}

	/**
	 * Returns the period of the subscription scheme.
	 *
	 * @return string  A string representation of the period, either Day, Week, Month or Year.
	 */
	public function get_period( $product ) {
		return $this->data[ 'period' ];
	}

	/**
	 * Returns the interval of the subscription scheme.
	 *
	 * @return int  Interval of subscription scheme, or an empty string if the product has not been associated with a subscription scheme.
	 */
	public function get_interval() {
		return $this->data[ 'interval' ];
	}

	/**
	 * Returns the length of the subscription scheme.
	 *
	 * @return int  An integer representing the length of the subscription scheme.
	 */
	public function get_length() {
		return $this->data[ 'length' ];
	}

	/**
	 * Returns the trial period of the subscription scheme.
	 *
	 * @return string  A string representation of the trial period, either Day, Week, Month or Year.
	 */
	public function get_trial_period() {
		return $this->data[ 'trial_period' ];
	}

	/**
	 * Returns the trial length of the subscription scheme.
	 *
	 * @return int  An integer representing the trial length of the subscription scheme.
	 */
	public function get_trial_length() {
		return $this->data[ 'trial_length' ];
	}

	/**
	 * Returns the pricing mode of the scheme - 'inherit' or 'override'.
	 * Indicates how the subscription scheme modifies the price of a product when active.
	 *
	 * @return int  String with values 'inherit' or 'override'.
	 */
	public function get_pricing_mode() {
		return $this->data[ 'pricing_mode' ];
	}

	/**
	 * Returns the price discount applied by the scheme when its pricing mode is 'inherit'.
	 *
	 * @return mixed
	 */
	public function get_discount() {
		return 'inherit' === $this->get_pricing_mode() ? $this->data[ 'discount' ] : false;
	}

	/**
	 * Returns the overridden regular price applied by the scheme when its pricing mode is 'override'.
	 *
	 * @return mixed
	 */
	public function get_regular_price() {
		return 'override' === $this->get_pricing_mode() ? $this->data[ 'regular_price' ] : null;
	}

	/**
	 * Returns the overridden sale price applied by the scheme when its pricing mode is 'override'.
	 *
	 * @return mixed
	 */
	public function get_sale_price() {
		return 'override' === $this->get_pricing_mode() ? $this->data[ 'sale_price' ] : null;
	}

	/**
	 * Returns modified prices based on subscription scheme settings.
	 *
	 * @param  array  $raw_prices
	 * @return string
	 */
	public function get_prices( $raw_prices ) {

		$prices = $raw_prices;

		if ( 'override' === $this->get_pricing_mode() ) {

			$prices[ 'regular_price' ] = $this->get_regular_price();
			$prices[ 'sale_price' ]    = $this->get_sale_price();

			$prices[ 'price' ] = '' !== $prices[ 'sale_price' ] && $prices[ 'sale_price' ] < $prices[ 'regular_price' ] ? $prices[ 'sale_price' ] : $prices[ 'regular_price' ];

		} elseif ( 'inherit' === $this->get_pricing_mode() && $this->get_discount() > 0 && $raw_prices[ 'price' ] > 0 ) {

			$prices[ 'regular_price' ] = empty( $prices[ 'regular_price' ] ) ? $prices[ 'price' ] : $prices[ 'regular_price' ];
			$prices[ 'price' ]         = $this->get_discounted_price( $raw_prices );

			if ( $prices[ 'price' ] < $prices[ 'regular_price' ] ) {
				$prices[ 'sale_price' ] = $prices[ 'price' ] ;
			}
		}

		return apply_filters( 'wcsatt_subscription_scheme_prices', $prices, $this );
	}

	/**
	 * Get price after discount.
	 *
	 * @param  array  $raw_prices
	 * @param  string $discount
	 * @return mixed
	 */
	protected function get_discounted_price( $raw_prices ) {

		$price    = $raw_prices[ 'price' ];
		$discount = $this->get_discount();

		if ( $price === '' ) {
			return $price;
		}

		if ( apply_filters( 'wcsatt_discount_from_regular', false ) ) {
			$regular_price = empty( $raw_prices[ 'regular_price' ] ) ? $raw_prices[ 'price' ] : $raw_prices[ 'regular_price' ];
		} else {
			$regular_price = $price;
		}

		if ( ! empty( $discount ) ) {
			$price = empty( $regular_price ) ? $regular_price : round( ( double ) $regular_price * ( 100 - $discount ) / 100, wc_get_price_decimals() );
		}

		return $price;
	}

	/*
	|--------------------------------------------------------------------------
	| Other Getters.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns the date on which the subscription scheme will expire,
	 * based on the subscription's length and calculated from either the $from_date if specified, or the current date/time.
	 *
	 * @param  mixed  $from_date  A MySQL formatted date/time string from which to calculate the expiration date, or empty (default), which will use today's date/time.
	 */
	public function get_expiration_date( $from_date = '' ) {

		$subscription_length = $this->get_length();

		if ( $subscription_length > 0 ) {

			if ( empty( $from_date ) ) {
				$from_date = gmdate( 'Y-m-d H:i:s' );
			}

			if ( $this->get_trial_length() > 0 ) {
				$from_date = $this->get_trial_expiration_date( $from_date );
			}

			$expiration_date = gmdate( 'Y-m-d H:i:s', wcs_add_time( $subscription_length, $this->get_period(), wcs_date_to_time( $from_date ) ) );

		} else {

			$expiration_date = 0;

		}

		return $expiration_date;
	}

	/**
	 * Returns the date on which the subscription scheme trial will expire,
	 * based on the subscription's length and calculated from either the $from_date if specified, or the current date/time.
	 *
	 * @param  mixed  $from_date  A MySQL formatted date/time string from which to calculate the trial expiration date, or empty (default), which will use today's date/time.
	 */
	public function get_trial_expiration_date( $from_date = '' ) {

		$trial_length = $this->get_trial_length();

		if ( $trial_length > 0 ) {

			if ( empty( $from_date ) ) {
				$from_date = gmdate( 'Y-m-d H:i:s' );
			}

			$trial_expiration_date = gmdate( 'Y-m-d H:i:s', wcs_add_time( $trial_length, $this->get_trial_period(), wcs_date_to_time( $from_date ) ) );

		} else {

			$trial_expiration_date = 0;

		}

		return $trial_expiration_date;
	}

	/*
	|--------------------------------------------------------------------------
	| Conditionals.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Indicates whether the scheme modifies the price of the product it's attached onto when active.
	 *
	 * @return boolean
	 */
	public function has_price_filter() {
		return 'override' === $this->get_pricing_mode() || ( 'inherit' === $this->get_pricing_mode() && $this->get_discount() > 0 );
	}

	/*
	|--------------------------------------------------------------------------
	| Setters.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Sets the scheme context. Expected values: 'product', 'cart'.
	 *
	 * @param  string  $value
	 */
	public function set_context( $value ) {
		$this->data[ 'context' ] = strval( $value );
	}

	/**
	 * Sets the period of the subscription scheme. Does not validate input.
	 *
	 * @param  string  $value
	 */
	public function set_period( $value ) {
		$this->data[ 'period' ] = strval( $value );
	}

	/**
	 * Sets the interval of the subscription scheme.
	 *
	 * @param  int  $value
	 */
	public function set_interval( $value ) {
		$this->data[ 'interval' ] = absint( $value );
	}

	/**
	 * Sets the length of the subscription scheme.
	 *
	 * @param  int  $value
	 */
	public function set_length( $value ) {
		$this->data[ 'length' ] = absint( $value );
	}

	/**
	 * Sets the trial period of the subscription scheme.
	 *
	 * @param  string  $value
	 */
	public function set_trial_period( $value ) {
		$this->data[ 'trial_period' ] = strval( $value );
	}

	/**
	 * Sets the trial length of the subscription scheme.
	 *
	 * @param  int  $value
	 */
	public function set_trial_length( $value ) {
		$this->data[ 'trial_length' ] = absint( $value );
	}

	/**
	 * Sets the pricing mode of the scheme - 'inherit' or 'override'.
	 * Indicates how the subscription scheme modifies the price of a product when active.
	 *
	 * @param  string  $value
	 */
	public function set_pricing_mode( $value ) {
		$this->data[ 'pricing_mode' ] = in_array( $value, array( 'inherit', 'override' ) ) ? $value : 'inherit';
	}

	/**
	 * Sets the price discount applied by the scheme when its pricing mode is 'inherit'.
	 *
	 * @param  mixed  $value
	 */
	public function set_discount( $value ) {
		if ( 'inherit' === $this->get_pricing_mode() ) {
			$this->data[ 'discount' ] = wc_format_decimal( $value );
		}
	}

	/**
	 * Sets the overridden regular price applied by the scheme when its pricing mode is 'override'.
	 *
	 * @param  mixed  $value
	 */
	public function set_regular_price( $value ) {
		if ( 'override' === $this->get_pricing_mode() ) {
			$this->data[ 'regular_price' ] = wc_format_decimal( $value );
			$this->data[ 'price' ]         = '' !== $this->data[ 'sale_price' ] && $this->data[ 'sale_price' ] < $this->data[ 'regular_price' ] ? $this->data[ 'sale_price' ] : $this->data[ 'regular_price' ];
		}
	}

	/**
	 * Sets the overridden sale price applied by the scheme when its pricing mode is 'override'.
	 *
	 * @param  mixed  $value
	 */
	public function set_sale_price( $value ) {
		if ( 'override' === $this->get_pricing_mode() ) {
			$this->data[ 'sale_price' ] = wc_format_decimal( $value );
			$this->data[ 'price' ]      = '' !== $this->data[ 'sale_price' ] && $this->data[ 'sale_price' ] < $this->data[ 'regular_price' ] ? $this->data[ 'sale_price' ] : $this->data[ 'regular_price' ];
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Array access methods.
	|--------------------------------------------------------------------------
	*/

	public function offsetGet( $offset ) {
		$key = isset( $this->offset_map[ $offset ] ) ? $this->offset_map[ $offset ] : false;
		return $key ? $this->data[ $key ] : null;
	}

	public function offsetExists( $offset ) {
		$key = isset( $this->offset_map[ $offset ] ) ? $this->offset_map[ $offset ] : false;
		return $key ? isset( $this->data[ $key ] ) : false;
	}

	public function offsetSet( $offset, $value ) {
		if ( is_null( $offset ) ) {
			$this->data[] = $value;
		} else {
			$key = isset( $this->offset_map[ $offset ] ) ? $this->offset_map[ $offset ] : false;
			if ( $key ) {
				$this->data[ $key ] = $value;
			} else {
				$this->data[ $offset ] = $value;
			}
		}
	}

	public function offsetUnset( $offset ) {
		$key = isset( $this->offset_map[ $offset ] ) ? $this->offset_map[ $offset ] : false;
		if ( $key ) {
			unset( $this->data[ $key ] );
		} else {
			unset( $this->data[ $offset ] );
		}
	}
}
