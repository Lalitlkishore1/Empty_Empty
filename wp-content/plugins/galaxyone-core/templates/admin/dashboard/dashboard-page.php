<?php
/**
 * Operational dashboard template.
 *
 * @package GalaxyOne\Core
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'GalaxyOne Operations Dashboard', 'galaxyone-core' ); ?></h1>

	<div class="notice notice-info inline">
		<p><?php esc_html_e( 'Today’s figures are calculated from stored WooCommerce orders.', 'galaxyone-core' ); ?></p>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'Today', 'galaxyone-core' ); ?></h2>
		<table class="widefat striped">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Orders created', 'galaxyone-core' ); ?></th>
					<td><?php echo esc_html( (string) $metrics['today_orders'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Open orders', 'galaxyone-core' ); ?></th>
					<td><?php echo esc_html( (string) $metrics['open_orders'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Completed orders', 'galaxyone-core' ); ?></th>
					<td><?php echo esc_html( (string) $metrics['completed_orders'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Recognized order revenue', 'galaxyone-core' ); ?></th>
					<td><?php echo wp_kses_post( wc_price( (float) $metrics['today_revenue'] ) ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'Operational Configuration', 'galaxyone-core' ); ?></h2>
		<table class="widefat striped">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Active service areas', 'galaxyone-core' ); ?></th>
					<td><?php echo esc_html( (string) $context['service_areas'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Active delivery slots', 'galaxyone-core' ); ?></th>
					<td><?php echo esc_html( (string) $context['delivery_slots'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Configured offer campaigns', 'galaxyone-core' ); ?></th>
					<td><?php echo esc_html( (string) $context['offer_campaigns'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Configured reward campaigns', 'galaxyone-core' ); ?></th>
					<td><?php echo esc_html( (string) $context['reward_campaigns'] ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<h2><?php esc_html_e( 'Recent Activity', 'galaxyone-core' ); ?></h2>

	<?php if ( empty( $recent_activity ) ) : ?>
		<p><?php esc_html_e( 'No GalaxyOne activity has been recorded yet.', 'galaxyone-core' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'When', 'galaxyone-core' ); ?></th>
					<th><?php esc_html_e( 'Action', 'galaxyone-core' ); ?></th>
					<th><?php esc_html_e( 'User ID', 'galaxyone-core' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $recent_activity as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $entry->created_at ); ?></td>
						<td><?php echo esc_html( (string) $entry->action ); ?></td>
						<td><?php echo esc_html( (string) $entry->user_id ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
