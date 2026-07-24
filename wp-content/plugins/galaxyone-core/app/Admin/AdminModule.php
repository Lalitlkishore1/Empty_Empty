<?php
/**
 * Administration module.
 *
 * @package GalaxyOne\Core\Admin
 */

namespace GalaxyOne\Core\Admin;

use GalaxyOne\Core\Contracts\ModuleInterface;

final class AdminModule implements ModuleInterface {

	/**
	 * Menu registrar.
	 *
	 * @var MenuRegistrar
	 */
	private MenuRegistrar $menu_registrar;

	/**
	 * Settings page.
	 *
	 * @var SettingsPage
	 */
	private SettingsPage $settings_page;

	/**
	 * Creates the administration module.
	 */
	public function __construct() {
		$this->settings_page  = new SettingsPage();
		$this->menu_registrar = new MenuRegistrar( $this->settings_page );
	}

	/**
	 * Registers the module with WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'admin_menu',
			array( $this->menu_registrar, 'register' )
		);

		$this->settings_page->register();

		add_action(
			'admin_enqueue_scripts',
			array( $this, 'enqueue_assets' )
		);
	}

	/**
	 * Enqueues assets only for the GalaxyOne administration page.
	 *
	 * @param string $hook_suffix Current administration page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_' . MenuRegistrar::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'galaxyone-core-admin',
			plugins_url( 'assets/css/admin/settings.css', GALAXYONE_CORE_FILE ),
			array(),
			GALAXYONE_CORE_VERSION
		);

		wp_enqueue_script(
			'galaxyone-core-admin',
			plugins_url( 'assets/js/admin/settings.js', GALAXYONE_CORE_FILE ),
			array(),
			GALAXYONE_CORE_VERSION,
			true
		);
	}
}
