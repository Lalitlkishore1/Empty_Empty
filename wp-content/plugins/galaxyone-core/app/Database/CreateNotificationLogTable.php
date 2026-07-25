<?php
/**
 * Notification-log table migration.
 *
 * @package GalaxyOne\Core\Database\Migrations
 */

namespace GalaxyOne\Core\Database\Migrations;

use GalaxyOne\Core\Contracts\MigrationInterface;

final class CreateNotificationLogTable implements MigrationInterface {

	/**
	 * Creates the notification-log table.
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
			order_id bigint(20) unsigned NOT NULL,
			event_key varchar(50) NOT NULL,
			provider_key varchar(50) NOT NULL,
			recipient varchar(320) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'queued',
			subject varchar(255) NOT NULL DEFAULT '',
			error_message text NULL,
			sent_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_event (order_id, event_key),
			KEY status_created_at (status, created_at),
			KEY order_id (order_id)
		) {$charset_collate};";

		dbDelta( $sql );

		return self::table_exists();
	}

	/**
	 * Removes the notification-log table.
	 *
	 * @return void
	 */
	public static function down(): void {
		global $wpdb;

		$table_name = self::get_table_name();

		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Returns the prefixed notification-log table name.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'galaxy_notification_logs';
	}

	/**
	 * Determines whether the notification-log table exists.
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
