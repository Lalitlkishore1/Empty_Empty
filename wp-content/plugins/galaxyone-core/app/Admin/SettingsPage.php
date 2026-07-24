<?php
/**
 * Administration settings page.
 *
 * @package GalaxyOne\Core\Admin
 */

namespace GalaxyOne\Core\Admin;

use GalaxyOne\Core\ActivityLog\ActivityLogRepository;
use GalaxyOne\Core\Security\Capabilities;
use GalaxyOne\Core\Settings\SettingsRepository;

final class SettingsPage {

	/**
	 * Registers settings page sections and fields.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'admin_init',
			array( $this, 'register_fields' )
		);
	}

	/**
	 * Registers Phase 4 settings fields.
	 *
	 * @return void
	 */
	public function register_fields(): void {
		add_settings_section(
			'galaxyone_core_activity_log',
			__( 'Activity Log', 'galaxyone-core' ),
			array( $this, 'render_activity_log_section' ),
			MenuRegistrar::PAGE_SLUG
		);

		add_settings_field(
			'activity_log_display_limit',
			__( 'Entries to display', 'galaxyone-core' ),
			array( $this, 'render_activity_log_display_limit_field' ),
			MenuRegistrar::PAGE_SLUG,
			'galaxyone_core_activity_log'
		);
	}

	/**
	 * Renders the activity-log settings section description.
	 *
	 * @return void
	 */
	public function render_activity_log_section(): void {
		echo '<p>' . esc_html__( 'Configure how many recent administrator activity entries are visible on this page.', 'galaxyone-core' ) . '</p>';
	}

	/**
	 * Renders the activity-log display-limit field.
	 *
	 * @return void
	 */
	public function render_activity_log_display_limit_field(): void {
		$settings = SettingsRepository::get_settings();
		?>
		<input
			id="galaxyone-activity-log-display-limit"
			name="<?php echo esc_attr( SettingsRepository::OPTION_NAME ); ?>[activity_log_display_limit]"
			type="number"
			min="10"
			max="100"
			step="1"
			value="<?php echo esc_attr( (string) $settings['activity_log_display_limit'] ); ?>"
		/>
		<p class="description">
			<?php esc_html_e( 'Choose a value from 10 to 100.', 'galaxyone-core' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders the GalaxyOne settings page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! Capabilities::can_manage_galaxyone() ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'galaxyone-core' ),
				esc_html__( 'GalaxyOne Settings', 'galaxyone-core' ),
				array(
					'response' => 403,
				)
			);
		}

		$settings         = SettingsRepository::get_settings();
		$activity_entries = ActivityLogRepository::get_recent( $settings['activity_log_display_limit'] );

		require GALAXYONE_CORE_PATH . 'templates/admin/settings-page.php';
	}
}
