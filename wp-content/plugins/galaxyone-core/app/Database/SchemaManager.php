<?php
/**
 * Schema version manager.
 *
 * @package GalaxyOne\Core\Database
 */

namespace GalaxyOne\Core\Database;

final class SchemaManager {

	/**
	 * Database option used to store the installed schema version.
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'galaxyone_core_schema_version';

	/**
	 * Initializes the schema-version record during activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::maybe_upgrade();
	}

	/**
	 * Updates the schema-version record when the plugin version changes.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		$installed_version = (string) get_option( self::OPTION_NAME, '0.0.0' );

		if ( version_compare( $installed_version, GALAXYONE_CORE_VERSION, '<' ) ) {
			update_option( self::OPTION_NAME, GALAXYONE_CORE_VERSION, false );
		}
	}

	/**
	 * Preserves schema information during deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
	}

	/**
	 * Removes the Phase 2 schema-version record during uninstall.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		delete_option( self::OPTION_NAME );
	}
}
