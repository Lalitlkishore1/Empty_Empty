<?php
/**
 * Checkout delivery-selection template.
 *
 * @package GalaxyOne\Core
 *
 * @var array<string, string>                  $selection        Current delivery selection.
 * @var array<int, array<string, mixed>>       $delivery_options Selectable delivery dates and slots.
 */

defined( 'ABSPATH' ) || exit;

$has_options = ! empty( $delivery_options );
?>

<section class="galaxyone-checkout-delivery" aria-labelledby="galaxyone-delivery-selection-title">
	<h3 id="galaxyone-delivery-selection-title">
		<?php esc_html_e( 'Delivery selection', 'galaxyone-core' ); ?>
	</h3>

	<?php if ( ! $has_options ) : ?>
		<p role="status">
			<?php esc_html_e( 'Delivery dates and time slots are currently unavailable. Please try again later.', 'galaxyone-core' ); ?>
		</p>
	<?php else : ?>
		<p>
			<?php esc_html_e( 'Your delivery eligibility and final delivery charge are checked using your postcode and selected slot.', 'galaxyone-core' ); ?>
		</p>

		<p class="form-row form-row-wide validate-required" id="galaxyone_delivery_date_field">
			<label for="galaxyone_delivery_date">
				<?php esc_html_e( 'Delivery date', 'galaxyone-core' ); ?>
				<abbr class="required" title="<?php esc_attr_e( 'required', 'galaxyone-core' ); ?>">*</abbr>
			</label>
			<select
				name="galaxyone_delivery_date"
				id="galaxyone_delivery_date"
				class="select update_totals"
				required
			>
				<option value=""><?php esc_html_e( 'Select a delivery date', 'galaxyone-core' ); ?></option>
				<?php foreach ( $delivery_options as $option ) : ?>
					<option
						value="<?php echo esc_attr( (string) $option['date'] ); ?>"
						<?php selected( $selection['delivery_date'], (string) $option['date'] ); ?>
					>
						<?php echo esc_html( (string) $option['label'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<p class="form-row form-row-wide validate-required" id="galaxyone_delivery_slot_field">
			<label for="galaxyone_delivery_slot">
				<?php esc_html_e( 'Delivery time slot', 'galaxyone-core' ); ?>
				<abbr class="required" title="<?php esc_attr_e( 'required', 'galaxyone-core' ); ?>">*</abbr>
			</label>
			<select
				name="galaxyone_delivery_slot"
				id="galaxyone_delivery_slot"
				class="select update_totals"
				required
			>
				<option value=""><?php esc_html_e( 'Select a delivery time slot', 'galaxyone-core' ); ?></option>
				<?php foreach ( $delivery_options as $option ) : ?>
					<?php foreach ( $option['slots'] as $slot ) : ?>
						<option
							value="<?php echo esc_attr( (string) $slot['key'] ); ?>"
							data-delivery-date="<?php echo esc_attr( (string) $option['date'] ); ?>"
							<?php selected( $selection['slot_key'], (string) $slot['key'] ); ?>
						>
							<?php
							printf(
								/* translators: 1: delivery date, 2: delivery slot label. */
								esc_html__( '%1$s — %2$s', 'galaxyone-core' ),
								esc_html( (string) $option['label'] ),
								esc_html( (string) $slot['label'] )
							);
							?>
						</option>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</select>
		</p>
	<?php endif; ?>
</section>
