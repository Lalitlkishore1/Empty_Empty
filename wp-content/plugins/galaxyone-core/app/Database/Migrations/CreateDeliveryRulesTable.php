<?php
/**
 * Delivery rules table migration.
 *
 * @package GalaxyOne\Core\Database\Migrations
 */

namespace GalaxyOne\Core\Database\Migrations;

use GalaxyOne\Core\Contracts\MigrationInterface;

final class CreateDeliveryRulesTable implements MigrationInterface {

	/**
	 * Creates the delivery rules table.
	 *
	 * @return bool
	 */
	public static function up(): bool {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			rule_type varchar(30) NOT NULL,
			rule_key varchar(191) NOT NULL,
			label varchar(191) NOT NULL DEFAULT '',
			service_area varchar(32) NOT NULL DEFAULT '',
			fee decimal(19,4) unsigned NOT NULL DEFAULT 0,
			weekday tinyint(2) NOT NULL DEFAULT -1,
			start_time time NULL,
			end_time time NULL,
			cutoff_time time NULL,
			closed_date date NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY rule_type_key (rule_type, rule_key),
			KEY service_area (service_area),
			KEY closed_date (closed_date),
			KEY rule_type_active (rule_type, is_active)
		) {$charset_collate};";

		dbDelta( $sql );

		return self::table_exists();
	}

	/**
	 * Removes the delivery rules table.
	 *
	 * @return void
	 */
	public static function down(): void {
		global $wpdb;

		$table_name = self::get_table_name();

		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Returns the prefixed delivery rules table name.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'galaxy_delivery_rules';
	}

	/**
	 * Determines whether the delivery rules table exists.
	 *
	 * @return bool
	 */
	private static function table_exists(): bool {
		global $wpdb;

		$table_name = self::get_table_name();
		$found_name = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return $table_name === $found_name;
	}
}
