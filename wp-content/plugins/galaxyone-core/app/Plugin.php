<?php
/**
 * GalaxyOne Core plugin coordinator.
 *
 * @package GalaxyOne\Core
 */

namespace GalaxyOne\Core;

use GalaxyOne\Core\Contracts\ModuleInterface;
use GalaxyOne\Core\Database\SchemaManager;
use GalaxyOne\Core\Providers\ModuleProvider;
use GalaxyOne\Core\Support\PluginNotice;
use GalaxyOne\Core\Support\Requirements;

final class Plugin {

	/**
	 * Indicates whether modules have been registered.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * Starts the plugin when dependencies are available.
	 *
	 * @return void
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}

		if ( ! Requirements::are_met() ) {
			PluginNotice::register_requirement_notice();

			return;
		}

		SchemaManager::maybe_upgrade();

		foreach ( ModuleProvider::get_modules() as $module ) {
			if ( $module instanceof ModuleInterface ) {
				$module->register();
			}
		}

		self::$booted = true;

		/**
		 * Fires after GalaxyOne Core has completed Phase 2 bootstrap.
		 */
		do_action( 'galaxyone_core_loaded' );
	}

	/**
	 * Handles plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if ( ! Requirements::are_met() ) {
			deactivate_plugins( plugin_basename( GALAXYONE_CORE_FILE ) );

			wp_die(
				esc_html( implode( ' ', Requirements::get_errors() ) ),
				esc_html__( 'GalaxyOne Core activation failed', 'galaxyone-core' ),
				array(
					'back_link' => true,
				)
			);
		}

		SchemaManager::activate();
	}

	/**
	 * Handles plugin deactivation without deleting operational data.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		SchemaManager::deactivate();
	}
}
