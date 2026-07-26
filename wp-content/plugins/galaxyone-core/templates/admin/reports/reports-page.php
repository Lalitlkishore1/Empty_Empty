<?php
/**
 * Reports template.
 *
 * @package GalaxyOne\Core
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'GalaxyOne Reports', 'galaxyone-core' ); ?></h1>

	<form method="get">
		<input type="hidden" name="page" value="galaxyone-reports">
		<p>
			<label for="galaxyone-report-date-from"><?php esc_html_e( 'From', 'galaxyone-core' ); ?></label>
			<input id="galaxyone-report-date-from" name="date_from" type="date" value="<?php echo esc_attr( $date_from ); ?>">

			<label for="galaxyone-report-date-to"><?php esc_html_e( 'To', 'galaxyone-core' ); ?></label>
			<input id="galaxyone-report-date-to" name="date_to" type="date" value="<?php echo esc_attr( $date_to ); ?>">

			<button class="button"><?php esc_html_e( 'Run report', 'galaxyone-core' ); ?></button>
		</p>
	</form>

	<div class="card">
		<h2><?php esc_html_e( 'Report Summary', 'galaxyone-core' ); ?></h2>
		<table class="widefat striped">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Orders shown', 'galaxyone-core' ); ?></th>
					<td><?php echo esc_html( (string) $summary['order_count'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Processing and completed revenue', 'galaxyone-core' ); ?></th>
					<td><?php echo wp_kses_post( wc_price( (float) $summary['revenue'] ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Completed orders', 'galaxyone-core' ); ?></th>
					<td><?php echo esc_html( (string) $summary['completed'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Cancelled, failed, or refunded orders', 'galaxyone-core' ); ?></th>
					<td><?php echo esc_html( (string) $summary['cancelled'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Stored delivery fees', 'galaxyone-core' ); ?></th>
					<td><?php echo wp_kses_post( wc_price( (float) $summary['delivery_fees'] ) ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<h2><?php esc_html_e( 'Orders Included', 'galaxyone-core' ); ?></h2>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Order', 'galaxyone-core' ); ?></th>
				<th><?php esc_html_e( 'Status', 'galaxyone-core' ); ?></th>
				<th><?php esc_html_e( 'Total', 'galaxyone-core' ); ?></th>
				<th><?php esc_html_e( 'Delivery fee', 'galaxyone-core' ); ?></th>
				<th><?php esc_html_e( 'Created', 'galaxyone-core' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $result['orders'] ) ) : ?>
				<tr>
					<td colspan="5"><?php esc_html_e( 'No orders were found for this report.', 'galaxyone-core' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $result['orders'] as $report_order ) : ?>
					<?php
					$order_url = add_query_arg(
						array(
							'page'     => 'galaxyone-orders',
							'order_id' => $report_order->get_id(),
						),
						admin_url( 'admin.php' )
					);
					?>
					<tr>
						<td><a href="<?php echo esc_url( $order_url ); ?>">#<?php echo esc_html( $report_order->get_order_number() ); ?></a></td>
						<td><?php echo esc_html( wc_get_order_status_name( $report_order->get_status() ) ); ?></td>
						<td><?php echo wp_kses_post( $report_order->get_formatted_order_total() ); ?></td>
						<td><?php echo wp_kses_post( wc_price( (float) $report_order->get_meta( '_galaxyone_delivery_fee', true ) ) ); ?></td>
						<td><?php echo esc_html( $report_order->get_date_created() ? $report_order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '' ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
