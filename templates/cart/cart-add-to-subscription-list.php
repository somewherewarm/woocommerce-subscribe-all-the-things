<?php
/**
 * Add-Cart-to-Subscription List Template.
 *
 * Override this template by copying it to 'yourtheme/woocommerce/cart/cart-add-to-subscription-list.php'.
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

if ( empty( $subscriptions ) ) {
	_e( 'No matching subscriptions found.', 'woocommerce-subscribe-all-the-things' );
} else {
	wc_get_template( 'myaccount/my-subscriptions.php', array(
		'subscriptions' => $subscriptions,
		'user_id'       => $user_id,
		'current_page'  => 1,
		'max_num_pages' => 1
	), '', plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'templates/' );
}
