<?php
/**
 * Admin subscription scheme view.
 * @version 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?><div class="subscription_scheme wc-metabox <?php echo ! $ajax ? 'closed' : ''; ?>">
	<h3>
		<button type="button" class="remove_row button"><?php echo __( 'Remove', 'woocommerce' ); ?></button>
		<div class="subscription_scheme_data">
			<?php do_action( 'wcsatt_subscription_scheme_content', $index, array(), $post_id, $ajax ); ?>
		</div>
		<input type="hidden" name="wcsatt_schemes[<?php echo $index; ?>][position]" class="position" value=""/>
	</h3>

</div>
