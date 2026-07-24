<?php
/**
 * Schema version manager.
 *
 * @package GalaxyOne\Core\Database
 */

namespace GalaxyOne\Core\Database;

use GalaxyOne\Core\Database\Migrations\CreateActivityLogTable;

final class SchemaManager {

	/**
	 * Database option used to store the installed schema version.
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'galaxyone_core_schema_version';

	/**
	 * Current database schema version.
	 *
	 * @var string
	 */
	private const CURRENT_SCHEMA_VERSION = '0.2.0';

	/**
	 * Initializes the schema during activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::maybe_upgrade();
	}

	/**
	 * Runs pending schema migrations.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		$installed_version = (string) get_option( self::OPTION_NAME, '0.0.0' );

		if ( version_compare( $installed_version, self::CURRENT_SCHEMA_VERSION, '>=' ) ) {
			return;
		}

		if ( ! CreateActivityLogTable::up() ) {
			return;
		}

		update_option( self::OPTION_NAME, self::CURRENT_SCHEMA_VERSION, false );
	}

	/**
	 * Preserves schema information during deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
	}

	/**
	 * Removes Phase 4 schema data during uninstall.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		CreateActivityLogTable::down();
		delete_option( self::OPTION_NAME );
	}
}
