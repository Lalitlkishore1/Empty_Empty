<?php
/**
 * Settings module.
 *
 * @package GalaxyOne\Core\Settings
 */

namespace GalaxyOne\Core\Settings;

use GalaxyOne\Core\Contracts\ModuleInterface;
use GalaxyOne\Core\Security\Capabilities;

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

		add_filter(
			'option_page_capability_' . SettingsRepository::OPTION_GROUP,
			array( $this, 'get_option_page_capability' )
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

	/**
	 * Returns the capability required to save GalaxyOne settings.
	 *
	 * @param string $capability Default Settings API capability.
	 * @return string
	 */
	public function get_option_page_capability( string $capability ): string {
		return Capabilities::get_manage_capability();
	}
}
