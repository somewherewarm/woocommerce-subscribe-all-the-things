<?php
/**
 * Admin subscription scheme view.
 *
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?><div class="subscription_scheme wc-metabox open" rel="<?php echo isset( $scheme_data[ 'position' ] ) ? $scheme_data[ 'position' ] : ''; ?>">
	<div class="handle">
		<span class="scheme-handle" aria-label="<?php _e( "Drag to sort", "woocommerce-subscribe-all-the-things" ); ?>"></span>
		<span class="scheme-remove" aria-label="<?php _e( "Remove", "woocommerce-subscribe-all-the-things" ); ?>"></span>
	</div>
	<div class="data subscription_scheme_data"><?php

		// Basic Subscription Scheme Options.
		do_action( 'wcsatt_subscription_scheme_content', $index, $scheme_data, $post_id );

		// Additional Subscription Options for Products.
		if ( $post_id > 0 ) {
			?><div class="subscription_scheme_product_data"><?php

				do_action( 'wcsatt_subscription_scheme_product_content', $index, $scheme_data, $post_id );

			?></div><?php
		}
	?></div>
	<?php

	if ( isset( $scheme_data[ 'id' ] ) ) {
		?><input type="hidden" name="wcsatt_schemes[<?php echo $index; ?>][id]" class="scheme_id" value="<?php echo $scheme_data[ 'id' ]; ?>" /><?php
	}
	?><input type="hidden" name="wcsatt_schemes[<?php echo $index; ?>][position]" class="position" value="<?php echo isset( $scheme_data[ 'position' ] ) ? $index : ''; ?>"/>
</div>
