<?php
/**
 * Delivery-reservation atomicity schema upgrade.
 *
 * @package GalaxyOne\Core\Database\Migrations
 */

namespace GalaxyOne\Core\Database\Migrations;

use GalaxyOne\Core\Contracts\MigrationInterface;

final class UpgradeDeliveryReservationAtomicity implements MigrationInterface {

	/**
	 * Applies the delivery-reservation atomicity upgrade.
	 *
	 * @return bool
	 */
	public static function up(): bool {
		global $wpdb;

		$capacity_table     = CreateDeliveryCapacityTable::get_table_name();
		$reservations_table = CreateDeliveryReservationsTable::get_table_name();

		if (
			! self::table_exists( $capacity_table ) ||
			! self::table_exists( $reservations_table ) ||
			! self::ensure_innodb( $capacity_table ) ||
			! self::ensure_innodb( $reservations_table ) ||
			! self::ensure_idempotency_column( $reservations_table ) ||
			! self::reconcile_capacity( $capacity_table, $reservations_table ) ||
			! self::ensure_unique_idempotency_index( $reservations_table )
		) {
			return false;
		}

		return self::is_ready( $capacity_table, $reservations_table );
	}

	/**
	 * Does not reverse this forward-only schema migration.
	 *
	 * @return void
	 */
	public static function down(): void {
	}

	/**
	 * Determines whether a table exists.
	 *
	 * @param string $table_name Database table name.
	 * @return bool
	 */
	private static function table_exists( string $table_name ): bool {
		global $wpdb;

		$found_name = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return $table_name === $found_name;
	}

	/**
	 * Ensures a table uses InnoDB.
	 *
	 * @param string $table_name Database table name.
	 * @return bool
	 */
	private static function ensure_innodb( string $table_name ): bool {
		global $wpdb;

		$engine = self::get_table_engine( $table_name );

		if ( 'innodb' === $engine ) {
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

	/**
	 * Adds the nullable durable idempotency key when absent.
	 *
	 * @param string $table_name Reservation table name.
	 * @return bool
	 */
	private static function ensure_idempotency_column( string $table_name ): bool {
		global $wpdb;

		$column = $wpdb->get_row(
			"SHOW COLUMNS FROM {$table_name} LIKE 'idempotency_key'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( is_array( $column ) ) {
			return 'char(36)' === strtolower( (string) $column['Type'] ) &&
				'YES' === strtoupper( (string) $column['Null'] );
		}

		if (
			false === $wpdb->query(
				"ALTER TABLE {$table_name}
				ADD COLUMN idempotency_key CHAR(36) NULL AFTER reservation_token" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			)
		) {
			return false;
		}

		return self::ensure_idempotency_column( $table_name );
	}

	/**
	 * Reconciles derived capacity counts without changing reservation history.
	 *
	 * @param string $capacity_table     Capacity table name.
	 * @param string $reservations_table Reservation table name.
	 * @return bool
	 */
	private static function reconcile_capacity(
		string $capacity_table,
		string $reservations_table
	): bool {
		global $wpdb;

		if (
			false === $wpdb->query(
				"LOCK TABLES {$capacity_table} WRITE, {$reservations_table} READ" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			)
		) {
			return false;
		}

		try {
			$unmatched_reservation = $wpdb->get_var(
				"SELECT r.id
				FROM {$reservations_table} r
				LEFT JOIN {$capacity_table} c
					ON c.delivery_date = r.delivery_date
					AND c.slot_key = r.slot_key
				WHERE r.status IN ('active', 'confirmed')
					AND c.id IS NULL
				LIMIT 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);

			if ( null !== $unmatched_reservation ) {
				return false;
			}

			$rows = $wpdb->get_results(
				"SELECT c.id, c.capacity, c.reserved_count,
					COALESCE(
						SUM(
							CASE
								WHEN r.status IN ('active', 'confirmed') THEN r.quantity
								ELSE 0
							END
						),
						0
					) AS derived_reserved_count
				FROM {$capacity_table} c
				LEFT JOIN {$reservations_table} r
					ON r.delivery_date = c.delivery_date
					AND r.slot_key = c.slot_key
				GROUP BY c.id, c.capacity, c.reserved_count", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_A
			);

			if ( ! is_array( $rows ) ) {
				return false;
			}

			foreach ( $rows as $row ) {
				$capacity = (int) $row['capacity'];
				$derived  = (int) $row['derived_reserved_count'];

				if ( $derived < 0 || $derived > $capacity ) {
					return false;
				}
			}

			$now = current_time( 'mysql', true );

			foreach ( $rows as $row ) {
				$stored  = (int) $row['reserved_count'];
				$derived = (int) $row['derived_reserved_count'];

				if ( $stored === $derived ) {
					continue;
				}

				$updated = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$capacity_table}
						SET reserved_count = %d,
							updated_at = %s
						WHERE id = %d
							AND capacity >= %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$derived,
						$now,
						(int) $row['id'],
						$derived
					)
				); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				if ( 1 !== $updated ) {
					return false;
				}
			}

			return self::has_safe_capacity_counts( $capacity_table, $reservations_table );
		} finally {
			$wpdb->query( 'UNLOCK TABLES' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Adds the unique idempotency index after duplicate detection.
	 *
	 * @param string $table_name Reservation table name.
	 * @return bool
	 */
	private static function ensure_unique_idempotency_index( string $table_name ): bool {
		global $wpdb;

		if ( self::has_unique_idempotency_index( $table_name ) ) {
			return true;
		}

		$duplicate_key = $wpdb->get_var(
			"SELECT idempotency_key
			FROM {$table_name}
			WHERE idempotency_key IS NOT NULL
			GROUP BY idempotency_key
			HAVING COUNT(*) > 1
			LIMIT 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( null !== $duplicate_key ) {
			return false;
		}

		if (
			false === $wpdb->query(
				"ALTER TABLE {$table_name}
				ADD UNIQUE KEY idempotency_key (idempotency_key)" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			)
		) {
			return false;
		}

		return self::has_unique_idempotency_index( $table_name );
	}

	/**
	 * Determines whether a full, single-column unique idempotency index exists.
	 *
	 * @param string $table_name Reservation table name.
	 * @return bool
	 */
	private static function has_unique_idempotency_index( string $table_name ): bool {
		global $wpdb;

		$indexes = $wpdb->get_results(
			"SHOW INDEX FROM {$table_name}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! is_array( $indexes ) || empty( $indexes ) ) {
			return false;
		}

		$grouped_indexes = array();

		foreach ( $indexes as $index ) {
			if (
				! is_array( $index ) ||
				! array_key_exists( 'Key_name', $index ) ||
				! array_key_exists( 'Non_unique', $index ) ||
				! array_key_exists( 'Column_name', $index ) ||
				! array_key_exists( 'Seq_in_index', $index ) ||
				! array_key_exists( 'Sub_part', $index ) ||
				! is_scalar( $index['Key_name'] )
			) {
				return false;
			}

			$index_name = (string) $index['Key_name'];

			if ( '' === $index_name ) {
				return false;
			}

			if ( ! isset( $grouped_indexes[ $index_name ] ) ) {
				$grouped_indexes[ $index_name ] = array();
			}

			$grouped_indexes[ $index_name ][] = $index;
		}

		foreach ( $grouped_indexes as $index_rows ) {
			if ( 1 !== count( $index_rows ) ) {
				continue;
			}

			$index = $index_rows[0];

			if (
				! is_scalar( $index['Non_unique'] ) ||
				! is_scalar( $index['Column_name'] ) ||
				! is_scalar( $index['Seq_in_index'] ) ||
				'0' !== (string) $index['Non_unique'] ||
				'idempotency_key' !== (string) $index['Column_name'] ||
				'1' !== (string) $index['Seq_in_index'] ||
				null !== $index['Sub_part']
			) {
				continue;
			}

			return true;
		}

		return false;
	}

	/**
	 * Determines whether capacity rows reflect active and confirmed reservations.
	 *
	 * @param string $capacity_table     Capacity table name.
	 * @param string $reservations_table Reservation table name.
	 * @return bool
	 */
	private static function has_safe_capacity_counts(
		string $capacity_table,
		string $reservations_table
	): bool {
		global $wpdb;

		$invalid_row = $wpdb->get_var(
			"SELECT c.id
			FROM {$capacity_table} c
			LEFT JOIN {$reservations_table} r
				ON r.delivery_date = c.delivery_date
				AND r.slot_key = c.slot_key
			GROUP BY c.id, c.capacity, c.reserved_count
			HAVING c.reserved_count <> COALESCE(
				SUM(
					CASE
						WHEN r.status IN ('active', 'confirmed') THEN r.quantity
						ELSE 0
					END
				),
				0
			)
			OR c.reserved_count > c.capacity
			LIMIT 1" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return null === $invalid_row;
	}

	/**
	 * Determines whether the complete atomic schema is ready.
	 *
	 * @param string $capacity_table     Capacity table name.
	 * @param string $reservations_table Reservation table name.
	 * @return bool
	 */
	private static function is_ready(
		string $capacity_table,
		string $reservations_table
	): bool {
		return self::table_exists( $capacity_table ) &&
			self::table_exists( $reservations_table ) &&
			'innodb' === self::get_table_engine( $capacity_table ) &&
			'innodb' === self::get_table_engine( $reservations_table ) &&
			self::ensure_idempotency_column( $reservations_table ) &&
			self::has_unique_idempotency_index( $reservations_table ) &&
			self::has_safe_capacity_counts( $capacity_table, $reservations_table );
	}
}
