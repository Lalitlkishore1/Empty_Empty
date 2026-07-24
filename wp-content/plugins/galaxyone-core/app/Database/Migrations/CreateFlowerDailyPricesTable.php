<?php
/**
 * Flower daily-prices table migration.
 *
 * @package GalaxyOne\Core\Database\Migrations
 */

namespace GalaxyOne\Core\Database\Migrations;

use GalaxyOne\Core\Contracts\MigrationInterface;

final class CreateFlowerDailyPricesTable implements MigrationInterface {

	/**
	 * Creates the GalaxyOne flower daily-prices table.
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
			product_id bigint(20) unsigned NOT NULL,
			effective_date date NOT NULL,
			price decimal(19,4) unsigned NOT NULL,
			is_available tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY product_date (product_id, effective_date),
			KEY effective_date (effective_date),
			KEY product_id (product_id)
		) {$charset_collate};";

		dbDelta( $sql );

		return self::table_exists();
	}

	/**
	 * Removes the GalaxyOne flower daily-prices table.
	 *
	 * @return void
	 */
	public static function down(): void {
		global $wpdb;

		$table_name = self::get_table_name();

		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Returns the prefixed flower daily-prices table name.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'galaxy_flower_daily_prices';
	}

	/**
	 * Determines whether the flower daily-prices table exists.
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
