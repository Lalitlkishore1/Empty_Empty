<?php
/**
 * Supported streets table migration.
 *
 * @package GalaxyOne\Core\Database\Migrations
 */

namespace GalaxyOne\Core\Database\Migrations;

use GalaxyOne\Core\Contracts\MigrationInterface;

final class CreateSupportedStreetsTable implements MigrationInterface {

	/**
	 * Creates the supported streets table.
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
			street_name varchar(191) NOT NULL,
			normalized_street_name varchar(191) NOT NULL,
			locality varchar(191) NOT NULL,
			normalized_locality varchar(191) NOT NULL,
			service_area varchar(32) NOT NULL DEFAULT '',
			street_identity_key char(64) NOT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY street_identity_key (street_identity_key),
			KEY locality_active (normalized_locality, is_active),
			KEY is_active (is_active),
			KEY service_area (service_area)
		) {$charset_collate};";

		dbDelta( $sql );

		return self::table_exists() && self::ensure_innodb( $table_name );
	}

	/**
	 * Removes the supported streets table.
	 *
	 * @return void
	 */
	public static function down(): void {
		global $wpdb;

		$table_name = self::get_table_name();

		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Returns the prefixed supported streets table name.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'galaxy_supported_streets';
	}

	/**
	 * Determines whether the supported streets table exists.
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

	/**
	 * Ensures the table uses InnoDB.
	 *
	 * @param string $table_name Database table name.
	 * @return bool
	 */
	private static function ensure_innodb( string $table_name ): bool {
		global $wpdb;

		if ( 'innodb' === self::get_table_engine( $table_name ) ) {
			return true;
		}

		if ( false === $wpdb->query( "ALTER TABLE {$table_name} ENGINE=InnoDB" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return false;
		}

		return 'innodb' === self::get_table_engine( $table_name );
	}

	/**
	 * Returns the normalized table storage engine.
	 *
	 * @param string $table_name Database table name.
	 * @return string
	 */
	private static function get_table_engine( string $table_name ): string {
		global $wpdb;

		$table_status = $wpdb->get_row(
			$wpdb->prepare(
				'SHOW TABLE STATUS LIKE %s',
				$table_name
			),
			ARRAY_A
		);

		if ( ! is_array( $table_status ) || ! isset( $table_status['Engine'] ) ) {
			return '';
		}

		return strtolower( (string) $table_status['Engine'] );
	}
}
