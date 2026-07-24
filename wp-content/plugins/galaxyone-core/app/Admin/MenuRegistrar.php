<?php
/**
 * Administration menu registrar.
 *
 * @package GalaxyOne\Core\Admin
 */

namespace GalaxyOne\Core\Admin;

use GalaxyOne\Core\Security\Capabilities;

final class MenuRegistrar {

	/**
	 * GalaxyOne settings page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'galaxyone-core';

	/**
	 * Settings page renderer.
	 *
	 * @var SettingsPage
	 */
	private SettingsPage $settings_page;

	/**
	 * Creates the menu registrar.
	 *
	 * @param SettingsPage $settings_page Settings page renderer.
	 */
	public function __construct( SettingsPage $settings_page ) {
		$this->settings_page = $settings_page;
	}

	/**
	 * Registers the top-level GalaxyOne administration page.
	 *
	 * @return void
	 */
	public function register(): void {
		add_menu_page(
			__( 'GalaxyOne Settings', 'galaxyone-core' ),
			__( 'GalaxyOne', 'galaxyone-core' ),
			Capabilities::get_manage_capability(),
			self::PAGE_SLUG,
			array( $this->settings_page, 'render' ),
			'dashicons-store',
			56
		);
	}
}
