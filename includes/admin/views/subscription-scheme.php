<?php
/**
 * Admin subscription scheme view.
 * @version 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?><div class="subscription_scheme wc-metabox open" rel="<?php echo isset( $scheme_data[ 'position' ] ) ? $scheme_data[ 'position' ] : ''; ?>">
	<h3>
		<table class="subscription_scheme_table" cellspacing="0" cellpadding="6" style="width: 100%; border: none;">
			<tbody>
				<tr>
					<td class="scheme-title">
						<span class="scheme-title"><?php echo '#' . ( $index + 1 ); ?></span>
					</td>
					<td class="scheme-data">
						<div class="subscription_scheme_data wc-metabox-content">
							<?php do_action( 'wcsatt_subscription_scheme_content', $index, $scheme_data, $post_id ); ?>
						</div>
					</td>
					<td class="scheme-remove">
						<button type="button" class="remove_row button"><?php echo __( 'Remove', 'woocommerce' ); ?></button>
					</td>
				</tr>
			</tbody>
		</table>
		<input type="hidden" name="wcsatt_schemes[<?php echo $index; ?>][position]" class="position" value="<?php echo isset( $scheme_data[ 'position' ] ) ? $index : ''; ?>"/>
	</h3>

</div>
