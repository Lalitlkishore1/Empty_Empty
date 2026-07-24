<?php
/**
 * Settings module.
 *
 * @package GalaxyOne\Core\Settings
 */

namespace GalaxyOne\Core\Settings;

use GalaxyOne\Core\Contracts\ModuleInterface;

final class SettingsModule implements ModuleInterface {

	/**
	 * Registers the module with WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'admin_init',
			array( $this, 'register_settings' )
		);
	}

	/**
	 * Registers the GalaxyOne settings option.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			SettingsRepository::OPTION_GROUP,
			SettingsRepository::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( SettingsRepository::class, 'sanitize' ),
				'default'           => SettingsRepository::get_defaults(),
				'show_in_rest'      => false,
			)
		);
	}
}
