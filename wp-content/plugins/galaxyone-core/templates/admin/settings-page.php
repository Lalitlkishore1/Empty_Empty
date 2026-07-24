<?php
/**
 * GalaxyOne settings-page template.
 *
 * @package GalaxyOne\Core
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap galaxyone-admin">
	<h1><?php esc_html_e( 'GalaxyOne Settings', 'galaxyone-core' ); ?></h1>

	<form action="options.php" method="post">
		<?php
		settings_fields( \GalaxyOne\Core\Settings\SettingsRepository::OPTION_GROUP );
		do_settings_sections( \GalaxyOne\Core\Admin\MenuRegistrar::PAGE_SLUG );
		submit_button();
		?>
	</form>

	<section class="galaxyone-admin__activity-log" aria-labelledby="galaxyone-activity-log-heading">
		<h2 id="galaxyone-activity-log-heading"><?php esc_html_e( 'Recent Activity', 'galaxyone-core' ); ?></h2>

		<?php if ( empty( $activity_entries ) ) : ?>
			<p><?php esc_html_e( 'No GalaxyOne administration activity has been recorded yet.', 'galaxyone-core' ); ?></p>
		<?php else : ?>
			<div class="galaxyone-admin__table-wrapper">
				<table class="widefat fixed striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Date', 'galaxyone-core' ); ?></th>
							<th scope="col"><?php esc_html_e( 'User ID', 'galaxyone-core' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Action', 'galaxyone-core' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Previous Value', 'galaxyone-core' ); ?></th>
							<th scope="col"><?php esc_html_e( 'New Value', 'galaxyone-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $activity_entries as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( (string) $entry->created_at ); ?></td>
								<td><?php echo esc_html( (string) $entry->user_id ); ?></td>
								<td><?php echo esc_html( (string) $entry->action ); ?></td>
								<td><code><?php echo esc_html( (string) $entry->old_value ); ?></code></td>
								<td><code><?php echo esc_html( (string) $entry->new_value ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
</div>
