<?php
/**
 * Delivery settings administration template.
 *
 * @package GalaxyOne\Core
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Delivery Settings', 'galaxyone-core' ); ?></h1>

	<?php if ( 'saved' === $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Delivery configuration was saved.', 'galaxyone-core' ); ?></p>
		</div>
	<?php elseif ( 'invalid' === $notice ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Delivery configuration could not be saved. Review the submitted values and try again.', 'galaxyone-core' ); ?></p>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Service Area', 'galaxyone-core' ); ?></h2>
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="galaxyone_save_delivery_configuration">
		<input type="hidden" name="delivery_configuration_action" value="service_area">
		<?php wp_nonce_field( 'galaxyone_save_delivery_configuration', 'galaxyone_delivery_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="galaxyone-postcode"><?php esc_html_e( 'Postcode', 'galaxyone-core' ); ?></label></th>
					<td><input id="galaxyone-postcode" name="postcode" type="text" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="galaxyone-area-label"><?php esc_html_e( 'Area label', 'galaxyone-core' ); ?></label></th>
					<td><input id="galaxyone-area-label" name="label" type="text" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="galaxyone-delivery-fee"><?php esc_html_e( 'Delivery fee', 'galaxyone-core' ); ?></label></th>
					<td><input id="galaxyone-delivery-fee" name="fee" type="text" inputmode="decimal" required></td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Save Service Area', 'galaxyone-core' ) ); ?>
	</form>

	<?php if ( ! empty( $service_areas ) ) : ?>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Postcode', 'galaxyone-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Area', 'galaxyone-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Fee', 'galaxyone-core' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $service_areas as $service_area ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $service_area->service_area ); ?></td>
						<td><?php echo esc_html( (string) $service_area->label ); ?></td>
						<td><?php echo wp_kses_post( wc_price( (string) $service_area->fee ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<hr>

	<h2><?php esc_html_e( 'Delivery Slot', 'galaxyone-core' ); ?></h2>
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="galaxyone_save_delivery_configuration">
		<input type="hidden" name="delivery_configuration_action" value="slot">
		<?php wp_nonce_field( 'galaxyone_save_delivery_configuration', 'galaxyone_delivery_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="galaxyone-slot-key"><?php esc_html_e( 'Slot key', 'galaxyone-core' ); ?></label></th>
					<td><input id="galaxyone-slot-key" name="slot_key" type="text" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="galaxyone-slot-label"><?php esc_html_e( 'Slot label', 'galaxyone-core' ); ?></label></th>
					<td><input id="galaxyone-slot-label" name="label" type="text" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="galaxyone-slot-weekday"><?php esc_html_e( 'Delivery day', 'galaxyone-core' ); ?></label></th>
					<td>
						<select id="galaxyone-slot-weekday" name="weekday">
							<option value="7"><?php esc_html_e( 'Every day', 'galaxyone-core' ); ?></option>
							<option value="0"><?php esc_html_e( 'Sunday', 'galaxyone-core' ); ?></option>
							<option value="1"><?php esc_html_e( 'Monday', 'galaxyone-core' ); ?></option>
							<option value="2"><?php esc_html_e( 'Tuesday', 'galaxyone-core' ); ?></option>
							<option value="3"><?php esc_html_e( 'Wednesday', 'galaxyone-core' ); ?></option>
							<option value="4"><?php esc_html_e( 'Thursday', 'galaxyone-core' ); ?></option>
							<option value="5"><?php esc_html_e( 'Friday', 'galaxyone-core' ); ?></option>
							<option value="6"><?php esc_html_e( 'Saturday', 'galaxyone-core' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="galaxyone-slot-start"><?php esc_html_e( 'Start time', 'galaxyone-core' ); ?></label></th>
					<td><input id="galaxyone-slot-start" name="start_time" type="time" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="galaxyone-slot-end"><?php esc_html_e( 'End time', 'galaxyone-core' ); ?></label></th>
					<td><input id="galaxyone-slot-end" name="end_time" type="time" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="galaxyone-slot-cutoff"><?php esc_html_e( 'Same-day cutoff', 'galaxyone-core' ); ?></label></th>
					<td><input id="galaxyone-slot-cutoff" name="cutoff_time" type="time"></td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Save Delivery Slot', 'galaxyone-core' ) ); ?>
	</form>

	<?php if ( ! empty( $slots ) ) : ?>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Slot', 'galaxyone-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Window', 'galaxyone-core' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Cutoff', 'galaxyone-core' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $slots as $slot ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $slot->label ); ?></td>
						<td>
							<?php
							echo esc_html(
								substr( (string) $slot->start_time, 0, 5 ) .
								' – ' .
								substr( (string) $slot->end_time, 0, 5 )
							);
							?>
						</td>
						<td><?php echo esc_html( substr( (string) $slot->cutoff_time, 0, 5 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<hr>

	<h2><?php esc_html_e( 'Delivery Capacity', 'galaxyone-core' ); ?></h2>
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="galaxyone_save_delivery_configuration">
		<input type="hidden" name="delivery_configuration_action" value="capacity">
		<?php wp_nonce_field( 'galaxyone_save_delivery_configuration', 'galaxyone_delivery_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="galaxyone-capacity-date"><?php esc_html_e( 'Delivery date', 'galaxyone-core' ); ?></label></th>
					<td><input id="galaxyone-capacity-date" name="delivery_date" type="date" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="galaxyone-capacity-slot"><?php esc_html_e( 'Delivery slot', 'galaxyone-core' ); ?></label></th>
					<td>
						<select id="galaxyone-capacity-slot" name="slot_key" required>
							<option value=""><?php esc_html_e( 'Select a slot', 'galaxyone-core' ); ?></option>
							<?php foreach ( $slots as $slot ) : ?>
								<option value="<?php echo esc_attr( (string) $slot->rule_key ); ?>">
									<?php echo esc_html( (string) $slot->label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="galaxyone-capacity-value"><?php esc_html_e( 'Capacity', 'galaxyone-core' ); ?></label></th>
					<td><input id="galaxyone-capacity-value" name="capacity" type="number" min="0" step="1" required></td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Save Capacity', 'galaxyone-core' ) ); ?>
	</form>

	<hr>

	<h2><?php esc_html_e( 'Closed Delivery Date', 'galaxyone-core' ); ?></h2>
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="galaxyone_save_delivery_configuration">
		<input type="hidden" name="delivery_configuration_action" value="closed_date">
		<?php wp_nonce_field( 'galaxyone_save_delivery_configuration', 'galaxyone_delivery_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="galaxyone-closed-date"><?php esc_html_e( 'Date', 'galaxyone-core' ); ?></label></th>
					<td><input id="galaxyone-closed-date" name="delivery_date" type="date" required></td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Close Delivery Date', 'galaxyone-core' ) ); ?>
	</form>
</div>
