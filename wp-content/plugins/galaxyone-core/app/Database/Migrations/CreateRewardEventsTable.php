<?php
/**
 * Reward events table migration.
 *
 * @package GalaxyOne\Core\Database\Migrations
 */

namespace GalaxyOne\Core\Database\Migrations;

use GalaxyOne\Core\Contracts\MigrationInterface;

final class CreateRewardEventsTable implements MigrationInterface {

	/**
	 * Creates the reward events table.
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
			event_token char(36) NOT NULL,
			campaign_key varchar(191) NOT NULL,
			product_id bigint(20) unsigned NOT NULL,
			customer_hash char(64) NOT NULL,
			provider_key varchar(50) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			completion_count smallint(5) unsigned NOT NULL DEFAULT 0,
			required_completions smallint(5) unsigned NOT NULL DEFAULT 1,
			offer_price decimal(19,4) NOT NULL,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			expires_at datetime NOT NULL,
			completed_at datetime NULL,
			redeemed_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_token (event_token),
			KEY customer_product_status (customer_hash, product_id, status, expires_at),
			KEY campaign_status (campaign_key, status),
			KEY order_id (order_id)
		) {$charset_collate};";

		dbDelta( $sql );

		return self::table_exists();
	}

	/**
	 * Removes the reward events table.
	 *
	 * @return void
	 */
	public static function down(): void {
		global $wpdb;

		$table_name = self::get_table_name();

		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Returns the prefixed reward events table name.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'galaxy_reward_events';
	}

	/**
	 * Determines whether the reward events table exists.
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
