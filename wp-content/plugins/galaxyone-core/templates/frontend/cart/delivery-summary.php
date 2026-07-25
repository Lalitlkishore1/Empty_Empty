<?php
/**
 * Cart delivery summary template.
 *
 * @package GalaxyOne\Core
 *
 * @var array<string, string> $selection Current delivery selection.
 * @var array<string, string> $fee       Current delivery-fee snapshot.
 */

defined( 'ABSPATH' ) || exit;

$has_selection = '' !== $selection['delivery_date'] && '' !== $selection['slot_key'];
?>

<section class="galaxyone-cart-delivery-summary" aria-labelledby="galaxyone-cart-delivery-title">
	<h2 id="galaxyone-cart-delivery-title">
		<?php esc_html_e( 'Delivery information', 'galaxyone-core' ); ?>
	</h2>

	<?php if ( $has_selection ) : ?>
		<dl>
			<div>
				<dt><?php esc_html_e( 'Postcode', 'galaxyone-core' ); ?></dt>
				<dd><?php echo esc_html( $selection['postcode'] ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'Delivery date', 'galaxyone-core' ); ?></dt>
				<dd><?php echo esc_html( $selection['delivery_date'] ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'Delivery slot', 'galaxyone-core' ); ?></dt>
				<dd><?php echo esc_html( $selection['slot_key'] ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'Delivery fee', 'galaxyone-core' ); ?></dt>
				<dd>
					<?php
					if ( '' !== $fee['applied_fee'] ) {
						echo wp_kses_post( wc_price( $fee['applied_fee'] ) );
					} else {
						esc_html_e( 'Calculated at checkout', 'galaxyone-core' );
					}
					?>
				</dd>
			</div>
		</dl>

		<?php if ( '' !== $fee['campaign_key'] ) : ?>
			<p>
				<?php esc_html_e( 'An eligible free-delivery offer has been applied.', 'galaxyone-core' ); ?>
			</p>
		<?php endif; ?>
	<?php else : ?>
		<p>
			<?php esc_html_e( 'Choose your delivery date and time slot during checkout.', 'galaxyone-core' ); ?>
		</p>
	<?php endif; ?>
</section>
