<?php
/**
 * Single-Product Subscription Options Template.
 *
 * Override this template by copying it to 'yourtheme/woocommerce/product-options.php'.
 *
 * @version 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( $prompt ) {
	echo $prompt;
} else {
	?><h3><?php
		echo sprintf( __( 'Choose a &quot;%1$s&quot; subscription:', WCS_ATT::TEXT_DOMAIN ), $product->get_title() );
	?></h3><?php
}

?><ul class="wcsatt-convert-product"><?php

	foreach ( $options as $option ) {
		?><li>
			<label>
				<input type="radio" name="convert_to_sub_<?php echo $product->id; ?>" value="<?php echo $option[ 'id' ]; ?>" <?php checked( $option[ 'selected' ], true, true ); ?> />
				<?php echo $option[ 'description' ]; ?>
			</label>
		</li><?php
	}

?></ul>
