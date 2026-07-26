<?php
/**
 * Schema-manager upgrade integration tests.
 *
 * @package GalaxyOne\Tests\Integration
 */

declare(strict_types=1);

namespace GalaxyOne\Tests\Integration;

use GalaxyOne\Core\Database\Migrations\CreateNotificationLogTable;
use GalaxyOne\Core\Database\SchemaManager;
use GalaxyOne\Tests\Support\IntegrationTestCase;

final class SchemaManagerUpgradeTest extends IntegrationTestCase {

	/**
	 * Verifies a 0.7.0 installation receives the notification-log table.
	 *
	 * @return void
	 */
	public function test_notification_log_schema_migrates_from_version_zero_point_seven(): void {
		global $wpdb;

		CreateNotificationLogTable::down();
		update_option( 'galaxyone_core_schema_version', '0.7.0', false );

		SchemaManager::maybe_upgrade();

		$table_name = CreateNotificationLogTable::get_table_name();
		$found_name = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		self::assertSame( $table_name, $found_name );
		self::assertSame( '0.8.0', get_option( 'galaxyone_core_schema_version' ) );
	}
}
