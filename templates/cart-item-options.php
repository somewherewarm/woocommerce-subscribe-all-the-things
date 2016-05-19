<?php
/**
 * Cart Item Subscription Options Template.
 *
 * Override this template by copying it to 'yourtheme/woocommerce/cart-item-options.php'.
 *
 * @version 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?><ul class="wcsatt-options <?php echo esc_attr( $classes ); ?>"><?php

	foreach ( $options as $option ) {
		?><li>
			<label>
				<input type="radio" name="cart[<?php echo $cart_item_key; ?>][convert_to_sub]" value="<?php echo $option[ 'id' ] ?>" <?php checked( $option[ 'selected' ], true, true ); ?> />
				<?php echo $option[ 'description' ]; ?>
			</label>
		</li><?php
	}

?></ul>
