<?php
/**
 * Offer campaigns table migration.
 *
 * @package GalaxyOne\Core\Database\Migrations
 */

namespace GalaxyOne\Core\Database\Migrations;

use GalaxyOne\Core\Contracts\MigrationInterface;

final class CreateOfferCampaignsTable implements MigrationInterface {

	/**
	 * Creates the offer campaigns table.
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
			campaign_key varchar(191) NOT NULL,
			name varchar(191) NOT NULL,
			campaign_type varchar(30) NOT NULL,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			offer_price decimal(19,4) NULL,
			status varchar(20) NOT NULL DEFAULT 'paused',
			starts_at datetime NULL,
			ends_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY campaign_key (campaign_key),
			KEY campaign_type_status (campaign_type, status),
			KEY product_status_dates (product_id, status, starts_at, ends_at)
		) {$charset_collate};";

		dbDelta( $sql );

		return self::table_exists();
	}

	/**
	 * Removes the offer campaigns table.
	 *
	 * @return void
	 */
	public static function down(): void {
		global $wpdb;

		$table_name = self::get_table_name();

		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Returns the prefixed offer campaigns table name.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'galaxy_offer_campaigns';
	}

	/**
	 * Determines whether the offer campaigns table exists.
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
