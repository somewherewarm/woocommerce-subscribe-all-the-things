<?php
/**
 * Single-Product Subscription Options Template.
 *
 * Override this template by copying it to 'yourtheme/woocommerce/single-product/satt-product-options.php'.
 *
 * On occasion, this template file may need to be updated and you (the theme developer) will need to copy the new files to your theme to maintain compatibility.
 * We try to do this as little as possible, but it does happen.
 * When this occurs the version of the template file will be bumped and the readme will list any important changes.
 *
 * @version 1.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wcsatt-options-wrapper" <?php echo count( $options ) === 1 ? 'style="display:none;"' : '' ?>><?php

	if ( $prompt ) {
		echo $prompt;
	} else {
		?><h3><?php
			_e( 'Choose a subscription plan:', WCS_ATT::TEXT_DOMAIN );
		?></h3><?php
	}

	?><ul class="wcsatt-options-product"><?php
		foreach ( $options as $option_id => $option ) {
			?><li class="<?php echo $option_id !== '0' ? 'subscription-option' : 'one-time-option'; ?>">
				<label>
					<input type="radio" name="convert_to_sub_<?php echo $product->id; ?>" data-custom_data="<?php echo esc_attr( json_encode( $option[ 'data' ] ) ); ?>" value="<?php echo $option_id; ?>" <?php checked( $option[ 'selected' ], true, true ); ?> />
					<?php echo $option[ 'description' ]; ?>
				</label>
			</li><?php
		}
	?></ul>
</div>
