<?php
/**
 * Delivery serviceability-check template.
 *
 * @package GalaxyOne\Core
 */

defined( 'ABSPATH' ) || exit;
?>
<form class="galaxyone-delivery-check" method="post">
	<input type="hidden" name="galaxyone_delivery_check_form" value="1">
	<?php wp_nonce_field( 'galaxyone_delivery_check', 'galaxyone_delivery_check_nonce' ); ?>

	<p>
		<label for="galaxyone-delivery-postcode">
			<?php esc_html_e( 'Check delivery availability', 'galaxyone-core' ); ?>
		</label>
		<input
			id="galaxyone-delivery-postcode"
			name="galaxyone_delivery_postcode"
			type="text"
			value="<?php echo esc_attr( $postcode ); ?>"
			autocomplete="postal-code"
			required
		>
		<button type="submit">
			<?php esc_html_e( 'Check', 'galaxyone-core' ); ?>
		</button>
	</p>

	<?php if ( '' !== $result_text ) : ?>
		<p class="galaxyone-delivery-check__result galaxyone-delivery-check__result--<?php echo esc_attr( $result_type ); ?>" role="status">
			<?php echo wp_kses_post( $result_text ); ?>
		</p>
	<?php endif; ?>
</form>
