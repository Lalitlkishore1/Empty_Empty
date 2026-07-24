<?php
/**
 * Delivery reservations table migration.
 *
 * @package GalaxyOne\Core\Database\Migrations
 */

namespace GalaxyOne\Core\Database\Migrations;

use GalaxyOne\Core\Contracts\MigrationInterface;

final class CreateDeliveryReservationsTable implements MigrationInterface {

	/**
	 * Creates the delivery reservations table.
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
			reservation_token char(36) NOT NULL,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			delivery_date date NOT NULL,
			slot_key varchar(191) NOT NULL,
			quantity int(10) unsigned NOT NULL DEFAULT 1,
			status varchar(20) NOT NULL DEFAULT 'active',
			expires_at datetime NOT NULL,
			released_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY reservation_token (reservation_token),
			KEY status_expires_at (status, expires_at),
			KEY delivery_date_slot (delivery_date, slot_key),
			KEY order_id (order_id)
		) {$charset_collate};";

		dbDelta( $sql );

		return self::table_exists();
	}

	/**
	 * Removes the delivery reservations table.
	 *
	 * @return void
	 */
	public static function down(): void {
		global $wpdb;

		$table_name = self::get_table_name();

		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Returns the prefixed delivery reservations table name.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'galaxy_delivery_reservations';
	}

	/**
	 * Determines whether the delivery reservations table exists.
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
