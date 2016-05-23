<?php
/**
 * Single-Product Subscription Options Template.
 *
 * Override this template by copying it to 'yourtheme/woocommerce/product-options.php'.
 *
 * @version 1.0.4
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( $prompt ) {
	echo $prompt;
} else {
	?><h3><?php
		_e( 'Choose a subscription plan:', WCS_ATT::TEXT_DOMAIN );
	?></h3><?php
}

?><ul class="wcsatt-options-product"><?php

	foreach ( $options as $option ) {
		?><li class="<?php echo $option[ 'id' ] !== '0' ? 'subscription-option' : 'one-time-option'; ?>">
			<label>
				<input type="radio" name="convert_to_sub_<?php echo $product->id; ?>" data-custom_data="<?php echo esc_attr( json_encode( $option[ 'data' ] ) ); ?>" value="<?php echo $option[ 'id' ]; ?>" <?php checked( $option[ 'selected' ], true, true ); ?> />
				<?php echo $option[ 'description' ]; ?>
			</label>
		</li><?php
	}

?></ul>
