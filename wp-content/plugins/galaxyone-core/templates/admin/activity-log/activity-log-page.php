<?php
/**
 * Activity-log template.
 *
 * @package GalaxyOne\Core
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'GalaxyOne Activity Log', 'galaxyone-core' ); ?></h1>

	<?php if ( empty( $entries ) ) : ?>
		<p><?php esc_html_e( 'No GalaxyOne activity has been recorded yet.', 'galaxyone-core' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'When', 'galaxyone-core' ); ?></th>
					<th><?php esc_html_e( 'User ID', 'galaxyone-core' ); ?></th>
					<th><?php esc_html_e( 'Action', 'galaxyone-core' ); ?></th>
					<th><?php esc_html_e( 'Previous Value', 'galaxyone-core' ); ?></th>
					<th><?php esc_html_e( 'New Value', 'galaxyone-core' ); ?></th>
					<th><?php esc_html_e( 'Context', 'galaxyone-core' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $entry->created_at ); ?></td>
						<td><?php echo esc_html( (string) $entry->user_id ); ?></td>
						<td><?php echo esc_html( (string) $entry->action ); ?></td>
						<td><code><?php echo esc_html( (string) $entry->old_value ); ?></code></td>
						<td><code><?php echo esc_html( (string) $entry->new_value ); ?></code></td>
						<td><code><?php echo esc_html( (string) $entry->context ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
