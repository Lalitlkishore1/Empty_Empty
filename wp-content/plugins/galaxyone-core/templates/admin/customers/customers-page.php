<?php
/**
 * Customer operations template.
 *
 * @package GalaxyOne\Core
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'GalaxyOne Customers', 'galaxyone-core' ); ?></h1>

	<form method="get">
		<input type="hidden" name="page" value="galaxyone-customers">
		<p class="search-box">
			<label class="screen-reader-text" for="galaxyone-customer-search"><?php esc_html_e( 'Search customers', 'galaxyone-core' ); ?></label>
			<input
				id="galaxyone-customer-search"
				name="customer_search"
				type="search"
				value="<?php echo esc_attr( $search ); ?>"
				placeholder="<?php esc_attr_e( 'Name or email', 'galaxyone-core' ); ?>"
			>
			<button class="button"><?php esc_html_e( 'Search customers', 'galaxyone-core' ); ?></button>
		</p>
	</form>

	<table class="widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Customer', 'galaxyone-core' ); ?></th>
				<th><?php esc_html_e( 'Email', 'galaxyone-core' ); ?></th>
				<th><?php esc_html_e( 'Registered', 'galaxyone-core' ); ?></th>
				<th><?php esc_html_e( 'Order history', 'galaxyone-core' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $customers ) ) : ?>
				<tr>
					<td colspan="4"><?php esc_html_e( 'No customers match the current search.', 'galaxyone-core' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $customers as $listed_customer ) : ?>
					<?php
					$customer_url = add_query_arg(
						array(
							'page'        => 'galaxyone-customers',
							'customer_id' => $listed_customer->ID,
						),
						admin_url( 'admin.php' )
					);
					?>
					<tr>
						<td><?php echo esc_html( $listed_customer->display_name ); ?></td>
						<td><?php echo esc_html( $listed_customer->user_email ); ?></td>
						<td><?php echo esc_html( $listed_customer->user_registered ); ?></td>
						<td><a href="<?php echo esc_url( $customer_url ); ?>"><?php esc_html_e( 'View orders', 'galaxyone-core' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $customer instanceof WP_User ) : ?>
		<hr>
		<h2><?php echo esc_html( $customer->display_name ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: %s: customer email address. */
				esc_html__( 'Customer email: %s', 'galaxyone-core' ),
				esc_html( $customer->user_email )
			);
			?>
		</p>

		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Order', 'galaxyone-core' ); ?></th>
					<th><?php esc_html_e( 'Status', 'galaxyone-core' ); ?></th>
					<th><?php esc_html_e( 'Delivery', 'galaxyone-core' ); ?></th>
					<th><?php esc_html_e( 'Total', 'galaxyone-core' ); ?></th>
					<th><?php esc_html_e( 'Created', 'galaxyone-core' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $order_history ) ) : ?>
					<tr>
						<td colspan="5"><?php esc_html_e( 'This customer has no orders.', 'galaxyone-core' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $order_history as $history_order ) : ?>
						<?php
						$order_url = add_query_arg(
							array(
								'page'     => 'galaxyone-orders',
								'order_id' => $history_order->get_id(),
							),
							admin_url( 'admin.php' )
						);
						?>
						<tr>
							<td><a href="<?php echo esc_url( $order_url ); ?>">#<?php echo esc_html( $history_order->get_order_number() ); ?></a></td>
							<td><?php echo esc_html( wc_get_order_status_name( $history_order->get_status() ) ); ?></td>
							<td>
								<?php
								echo esc_html(
									trim(
										(string) $history_order->get_meta( '_galaxyone_delivery_date', true ) . ' ' .
										(string) $history_order->get_meta( '_galaxyone_delivery_slot_label', true )
									)
								);
								?>
							</td>
							<td><?php echo wp_kses_post( $history_order->get_formatted_order_total() ); ?></td>
							<td><?php echo esc_html( $history_order->get_date_created() ? $history_order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
