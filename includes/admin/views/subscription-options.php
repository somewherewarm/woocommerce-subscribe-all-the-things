<?php
/**
 * Admin subscription options for a variation.
 * @version 1.0.4
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$subscription_schemes = get_post_meta( $variation->ID, '_wcsatt_schemes', true );
?>
<div class="subscription-options hide_if_variable-subscription show_if_variation_is_subscribable" style="display: none;">
	<p class="form-row form-row-full">
		<label><?php _e( 'Subscription Options', WCS_ATT::TEXT_DOMAIN ); ?> <?php echo wc_help_tip( __( 'Add one or more subscription options for this variation.', WCS_ATT::TEXT_DOMAIN ) ); ?></label>
		<button type="button" class="button button-primary add_variation_subscription_scheme" data-button='{ "variation_id" : "<?php echo $variation->ID; ?>", "order" : "<?php echo $loop; ?>" }'><?php _e( 'Add Option', WCS_ATT::TEXT_DOMAIN ); ?></button>
	</p>
	<div class="variation_subscription_schemes wc-metaboxes ui-sortable" data-count=""><?php

	$variation_id = $variation->ID;

	if ( $subscription_schemes ) {

		$i = 0;

		foreach ( $subscription_schemes as $subscription_scheme ) {
			do_action( 'wcsatt_variable_subscription_scheme', $loop, $i, $subscription_scheme, $variation_id );
			$i++;
		}
	}
	?></div>
</div>
