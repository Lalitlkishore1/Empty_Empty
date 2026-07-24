<?php
/**
 * Activity-log module.
 *
 * @package GalaxyOne\Core\ActivityLog
 */

namespace GalaxyOne\Core\ActivityLog;

use GalaxyOne\Core\Contracts\ModuleInterface;
use GalaxyOne\Core\Settings\SettingsRepository;

final class ActivityLogModule implements ModuleInterface {

	/**
	 * Registers the module with WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'added_option',
			array( $this, 'log_added_option' ),
			10,
			2
		);

		add_action(
			'updated_option',
			array( $this, 'log_updated_option' ),
			10,
			3
		);
	}

	/**
	 * Logs initial GalaxyOne settings creation.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @return void
	 */
	public function log_added_option( string $option, mixed $value ): void {
		if ( SettingsRepository::OPTION_NAME !== $option || ! is_array( $value ) ) {
			return;
		}

		ActivityLogRepository::record(
			'settings_created',
			array(),
			$value,
			array(
				'source' => 'settings',
			)
		);
	}

	/**
	 * Logs GalaxyOne settings updates.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Previous option value.
	 * @param mixed  $value     Updated option value.
	 * @return void
	 */
	public function log_updated_option( string $option, mixed $old_value, mixed $value ): void {
		if (
			SettingsRepository::OPTION_NAME !== $option ||
			! is_array( $old_value ) ||
			! is_array( $value )
		) {
			return;
		}

		ActivityLogRepository::record(
			'settings_updated',
			$old_value,
			$value,
			array(
				'source' => 'settings',
			)
		);
	}
}
