<?php
/**
 * Schema version manager.
 *
 * @package GalaxyOne\Core\Database
 */

namespace GalaxyOne\Core\Database;

use GalaxyOne\Core\Database\Migrations\CreateActivityLogTable;
use GalaxyOne\Core\Database\Migrations\CreateDeliveryCapacityTable;
use GalaxyOne\Core\Database\Migrations\CreateDeliveryReservationsTable;
use GalaxyOne\Core\Database\Migrations\CreateDeliveryRulesTable;
use GalaxyOne\Core\Database\Migrations\CreateFlowerDailyPricesTable;

final class SchemaManager {

	/**
	 * Database option used to store the installed schema version.
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'galaxyone_core_schema_version';

	/**
	 * Schema version that introduced the activity-log table.
	 *
	 * @var string
	 */
	private const ACTIVITY_LOG_SCHEMA_VERSION = '0.3.0';

	/**
	 * Schema version that introduced daily flower prices.
	 *
	 * @var string
	 */
	private const FLOWER_PRICE_SCHEMA_VERSION = '0.4.0';

	/**
	 * Current database schema version.
	 *
	 * @var string
	 */
	private const CURRENT_SCHEMA_VERSION = '0.5.0';

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

		if (
			version_compare( $installed_version, self::ACTIVITY_LOG_SCHEMA_VERSION, '<' ) &&
			! CreateActivityLogTable::up()
		) {
			return;
		}

		if (
			version_compare( $installed_version, self::FLOWER_PRICE_SCHEMA_VERSION, '<' ) &&
			! CreateFlowerDailyPricesTable::up()
		) {
			return;
		}

		if ( version_compare( $installed_version, self::CURRENT_SCHEMA_VERSION, '<' ) ) {
			if (
				! CreateDeliveryRulesTable::up() ||
				! CreateDeliveryCapacityTable::up() ||
				! CreateDeliveryReservationsTable::up()
			) {
				return;
			}

			update_option( self::OPTION_NAME, self::CURRENT_SCHEMA_VERSION, false );
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
	 * Removes GalaxyOne schema data during uninstall.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		CreateDeliveryReservationsTable::down();
		CreateDeliveryCapacityTable::down();
		CreateDeliveryRulesTable::down();
		CreateFlowerDailyPricesTable::down();
		CreateActivityLogTable::down();
		delete_option( self::OPTION_NAME );
	}
}
