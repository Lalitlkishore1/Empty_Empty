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
use GalaxyOne\Core\Database\Migrations\CreateNotificationLogTable;
use GalaxyOne\Core\Database\Migrations\CreateOfferCampaignsTable;
use GalaxyOne\Core\Database\Migrations\CreateRewardCampaignsTable;
use GalaxyOne\Core\Database\Migrations\CreateRewardEventsTable;
use GalaxyOne\Core\Database\Migrations\UpgradeDeliveryReservationAtomicity;

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
	 * Schema version that introduced delivery tables.
	 *
	 * @var string
	 */
	private const DELIVERY_SCHEMA_VERSION = '0.5.0';

	/**
	 * Schema version that introduced offer campaigns.
	 *
	 * @var string
	 */
	private const OFFER_SCHEMA_VERSION = '0.6.0';

	/**
	 * Schema version that introduced rewarded-offer tables.
	 *
	 * @var string
	 */
	private const REWARD_SCHEMA_VERSION = '0.7.0';

	/**
	 * Schema version that introduced notification-delivery logs.
	 *
	 * @var string
	 */
	private const NOTIFICATION_SCHEMA_VERSION = '0.8.0';

	/**
	 * Schema version that introduced atomic delivery reservations.
	 *
	 * @var string
	 */
	private const DELIVERY_ATOMICITY_SCHEMA_VERSION = '0.9.0';

	/**
	 * Current database schema version.
	 *
	 * @var string
	 */
	private const CURRENT_SCHEMA_VERSION = self::DELIVERY_ATOMICITY_SCHEMA_VERSION;

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

		if ( version_compare( $installed_version, self::DELIVERY_SCHEMA_VERSION, '<' ) ) {
			if (
				! CreateDeliveryRulesTable::up() ||
				! CreateDeliveryCapacityTable::up() ||
				! CreateDeliveryReservationsTable::up()
			) {
				return;
			}
		}

		if (
			version_compare( $installed_version, self::OFFER_SCHEMA_VERSION, '<' ) &&
			! CreateOfferCampaignsTable::up()
		) {
			return;
		}

		if ( version_compare( $installed_version, self::REWARD_SCHEMA_VERSION, '<' ) ) {
			if (
				! CreateRewardCampaignsTable::up() ||
				! CreateRewardEventsTable::up()
			) {
				return;
			}
		}

		if (
			version_compare( $installed_version, self::NOTIFICATION_SCHEMA_VERSION, '<' ) &&
			! CreateNotificationLogTable::up()
		) {
			return;
		}

		if (
			version_compare( $installed_version, self::DELIVERY_ATOMICITY_SCHEMA_VERSION, '<' ) &&
			! UpgradeDeliveryReservationAtomicity::up()
		) {
			return;
		}

		if ( version_compare( $installed_version, self::CURRENT_SCHEMA_VERSION, '<' ) ) {
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
		CreateNotificationLogTable::down();
		CreateRewardEventsTable::down();
		CreateRewardCampaignsTable::down();
		CreateOfferCampaignsTable::down();
		CreateDeliveryReservationsTable::down();
		CreateDeliveryCapacityTable::down();
		CreateDeliveryRulesTable::down();
		CreateFlowerDailyPricesTable::down();
		CreateActivityLogTable::down();
		delete_option( self::OPTION_NAME );
	}
}
