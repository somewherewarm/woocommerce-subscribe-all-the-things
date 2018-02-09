<?php
/**
 * Single-Product Add-to-Subscription Button Template.
 *
 * Override this template by copying it to 'yourtheme/woocommerce/single-product/product-add-to-subscription-button.php'.
 *
 * On occasion, this template file may need to be updated and you (the theme developer) will need to copy the new files to your theme to maintain compatibility.
 * We try to do this as little as possible, but it does happen.
 * When this occurs the version of the template file will be bumped and the readme will list any important changes.
 *
 * @version 2.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<button type="submit" class="wcsatt-add-to-subscription-button button add alt" name="add-to-subscription" value="<?php echo $subscription_id; ?>" ><?php _e( 'Add', 'woocommerce-subscribe-all-the-things' ); ?></button>

