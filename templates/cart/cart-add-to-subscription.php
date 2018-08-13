<?php
/**
 * Add-Cart-to-Subscription Template.
 *
 * Override this template by copying it to 'yourtheme/woocommerce/cart/cart-add-to-subscription.php'.
 *
 * On occasion, this template file may need to be updated and you (the theme developer) will need to copy the new files to your theme to maintain compatibility.
 * We try to do this as little as possible, but it does happen.
 * When this occurs the version of the template file will be bumped and the readme will list any important changes.
 *
 * @version 2.1.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wcsatt-add-cart-to-subscription-wrapper <?php echo $is_checked ? 'open' : 'closed'; ?>" <?php echo $is_visible ? '' : 'style="display:none;"'; ?>>
	<form class="wcsatt-add-cart-to-subscription-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
		<h4 class="wcsatt-add-cart-to-subscription-intro"><?php _e( '&mdash; or &mdash;', 'woocommerce-subscribe-all-the-things' ); ?></h4>
		<p class="wcsatt-add-cart-to-subscription-action-wrapper">
			<label class="wcsatt-add-cart-to-subscription-action-label">
				<input class="wcsatt-add-cart-to-subscription-action-input" type="checkbox" name="add-to-subscription-checked" value="yes" <?php checked( $is_checked, true ); ?> />
				<span class="wcsatt-add-cart-to-subscription-action"><?php _e( 'Add this cart to an existing subscription?', 'woocommerce-subscribe-all-the-things' ); ?></span>
			</label>
		</p>
		<div class="wcsatt-add-cart-to-subscription-options" <?php echo $is_checked ? '' : 'style="display:none;"'; ?> >
			<?php
				if ( $is_checked ) {

					/**
					 * 'wcsatt_display_subscriptions_matching_cart' action.
					 *
					 * @since  2.1.0
					 *
					 * @hooked WCS_ATT_Manage_Add::display_subscriptions_matching_cart - 10
					 */
					do_action( 'wcsatt_display_subscriptions_matching_cart' );
				}
			?>
		</div>
	</form>
</div>
