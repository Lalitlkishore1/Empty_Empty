<?php
/**
 * Order operations template.
 *
 * @package GalaxyOne\Core
 */

use GalaxyOne\Core\Orders\AdminOrderActions;
use GalaxyOne\Core\Orders\OrderQueryService;

defined( 'ABSPATH' ) || exit;

$status_options = wc_get_order_statuses();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'GalaxyOne Orders', 'galaxyone-core' ); ?></h1>

	<?php if ( 'updated' === $notice ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'The order status was updated.', 'galaxyone-core' ); ?></p>
		</div>
	<?php elseif ( 'invalid-transition' === $notice ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'That order status transition is not allowed.', 'galaxyone-core' ); ?></p>
		</div>
	<?php elseif ( 'not-found' === $notice ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'The requested order could not be found.', 'galaxyone-core' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="get">
		<input type="hidden" name="page" value="galaxyone-orders">
		<p class="search-box">
			<label class="screen-reader-text" for="galaxyone-order-search"><?php esc_html_e( 'Search orders', 'galaxyone-core' ); ?></label>
			<input
				id="galaxyone-order-search"
				name="order_search"
				type="search"
				value="<?php echo esc_attr( (string) $filters['search'] ); ?>"
				placeholder="<?php esc_attr_e( 'Order number, customer, or email', 'galaxyone-core' ); ?>"
			>
			<select name="order_status" aria-label="<?php esc_attr_e( 'Order status', 'galaxyone-core' ); ?>">
				<option value=""><?php esc_html_e( 'All statuses', 'galaxyone-core' ); ?></option>
				<?php foreach ( $status_options as $status_key => $status_label ) : ?>
					<?php $status_value = str_replace( 'wc-', '', (string) $status_key ); ?>
					<option value="<?php echo esc_attr( $status_value ); ?>" <?php selected( $filters['status'], $status_value ); ?>>
						<?php echo esc_html( $status_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button class="button"><?php esc_html_e( 'Filter orders', 'galaxyone-core' ); ?></button>
		</p>
	</form>

	<table class="widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Order', 'galaxyone-core' ); ?></th>
				<th><?php esc_html_e( 'Customer', 'galaxyone-core' ); ?></th>
				<th><?php esc_html_e( 'Status', 'galaxyone-core' ); ?></th>
				<th><?php esc_html_e( 'Delivery', 'galaxyone-core' ); ?></th>
				<th><?php esc_html_e( 'Total', 'galaxyone-core' ); ?></th>
				<th><?php esc_html_e( 'Created', 'galaxyone-core' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $result['orders'] ) ) : ?>
				<tr>
					<td colspan="6"><?php esc_html_e( 'No orders match the current filter.', 'galaxyone-core' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $result['orders'] as $list_order ) : ?>
					<?php
					$order_url      = add_query_arg(
						array(
							'page'     => 'galaxyone-orders',
							'order_id' => $list_order->get_id(),
						),
						admin_url( 'admin.php' )
					);
					$delivery_date  = (string) $list_order->get_meta( '_galaxyone_delivery_date', true );
					$delivery_slot  = (string) $list_order->get_meta( '_galaxyone_delivery_slot_label', true );
					?>
					<tr>
						<td><a href="<?php echo esc_url( $order_url ); ?>">#<?php echo esc_html( $list_order->get_order_number() ); ?></a></td>
						<td><?php echo esc_html( $list_order->get_formatted_billing_full_name() ); ?></td>
						<td><?php echo esc_html( wc_get_order_status_name( $list_order->get_status() ) ); ?></td>
						<td><?php echo esc_html( trim( $delivery_date . ' ' . $delivery_slot ) ); ?></td>
						<td><?php echo wp_kses_post( $list_order->get_formatted_order_total() ); ?></td>
						<td><?php echo esc_html( $list_order->get_date_created() ? $list_order->get_date_created()->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '' ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $result['pages'] > 1 ) : ?>
		<p>
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => (int) $filters['page_number'],
						'total'     => (int) $result['pages'],
						'add_args'  => array(
							'page'         => 'galaxyone-orders',
							'order_search' => $filters['search'],
							'order_status' => $filters['status'],
						),
					)
				)
			);
			?>
		</p>
	<?php endif; ?>

	<?php if ( $order instanceof WC_Order ) : ?>
		<hr>
		<h2>
			<?php
			printf(
				/* translators: %s: order number. */
				esc_html__( 'Manage Order #%s', 'galaxyone-core' ),
				esc_html( $order->get_order_number() )
			);
			?>
		</h2>

		<table class="widefat striped">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Customer', 'galaxyone-core' ); ?></th>
					<td><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Email', 'galaxyone-core' ); ?></th>
					<td><?php echo esc_html( $order->get_billing_email() ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Current status', 'galaxyone-core' ); ?></th>
					<td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Delivery selection', 'galaxyone-core' ); ?></th>
					<td>
						<?php
						echo esc_html(
							trim(
								(string) $order->get_meta( '_galaxyone_delivery_date', true ) . ' ' .
								(string) $order->get_meta( '_galaxyone_delivery_slot_label', true )
							)
						);
						?>
					</td>
				</tr>
			</tbody>
		</table>

		<?php $allowed_statuses = OrderQueryService::get_allowed_transitions( $order->get_status() ); ?>

		<?php if ( ! empty( $allowed_statuses ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="galaxyone_update_order_status">
				<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>">
				<?php wp_nonce_field( 'galaxyone_update_order_status_' . $order->get_id(), 'galaxyone_order_status_nonce' ); ?>

				<p>
					<label for="galaxyone-new-order-status"><?php esc_html_e( 'Change status', 'galaxyone-core' ); ?></label>
					<select id="galaxyone-new-order-status" name="new_status">
						<?php foreach ( $allowed_statuses as $allowed_status ) : ?>
							<option value="<?php echo esc_attr( $allowed_status ); ?>">
								<?php echo esc_html( wc_get_order_status_name( $allowed_status ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button class="button button-primary"><?php esc_html_e( 'Update order', 'galaxyone-core' ); ?></button>
				</p>
			</form>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Notification Delivery Log', 'galaxyone-core' ); ?></h3>

		<?php if ( empty( $logs ) ) : ?>
			<p><?php esc_html_e( 'No notification deliveries have been recorded for this order.', 'galaxyone-core' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Event', 'galaxyone-core' ); ?></th>
						<th><?php esc_html_e( 'Provider', 'galaxyone-core' ); ?></th>
						<th><?php esc_html_e( 'Status', 'galaxyone-core' ); ?></th>
						<th><?php esc_html_e( 'Created', 'galaxyone-core' ); ?></th>
						<th><?php esc_html_e( 'Error', 'galaxyone-core' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $log->event_key ); ?></td>
							<td><?php echo esc_html( (string) $log->provider_key ); ?></td>
							<td><?php echo esc_html( (string) $log->status ); ?></td>
							<td><?php echo esc_html( (string) $log->created_at ); ?></td>
							<td><?php echo esc_html( (string) $log->error_message ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	<?php endif; ?>
</div>
