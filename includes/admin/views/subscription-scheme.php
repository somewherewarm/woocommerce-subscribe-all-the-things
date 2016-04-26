<?php
/**
 * Admin subscription scheme view.
 * @version 1.0.4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?><div class="subscription_scheme wc-metabox open" rel="<?php echo isset( $scheme_data[ 'position' ] ) ? $scheme_data[ 'position' ] : ''; ?>">
	<h3>
		<a href="#" class="button remove_scheme delete" rel="<?php echo isset( $scheme_data[ 'position' ] ) ? $scheme_data[ 'position' ] : ''; ?>"><?php _e( 'Remove', WCS_ATT::TEXT_DOMAIN ); ?></a>
		<div class="tips handlediv" data-tip="<?php esc_attr_e( 'Click to toggle', WCS_ATT::TEXT_DOMAIN ); ?>"></div>
		<div class="tips sort" data-tip="<?php esc_attr_e( 'Drag and drop to set the subscription option order', WCS_ATT::TEXT_DOMAIN ); ?>"></div>

		<span class="scheme-title">#<?php echo ( $index + 1 ); ?></span>
	</h3>
	<div class="subscription_scheme_data wc-metabox-content"><?php

		// Basic Subscription Scheme Options
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
