<?php
/**
 * Delivery-capacity service.
 *
 * @package GalaxyOne\Core\Delivery
 */

namespace GalaxyOne\Core\Delivery;

use GalaxyOne\Core\Database\Migrations\CreateDeliveryCapacityTable;

final class DeliveryCapacityService {

	/**
	 * Schema option used to store the installed version.
	 *
	 * @var string
	 */
	private const SCHEMA_VERSION_OPTION = 'galaxyone_core_schema_version';

	/**
	 * Minimum schema version required for atomic capacity mutations.
	 *
	 * @var string
	 */
	private const ATOMICITY_SCHEMA_VERSION = '0.9.0';

	/**
	 * Saves a capacity limit for a delivery date and slot.
	 *
	 * @param string $delivery_date Delivery date in Y-m-d format.
	 * @param string $slot_key      Delivery slot identifier.
	 * @param int    $capacity      Maximum deliveries.
	 * @return bool
	 */
	public static function save_capacity( string $delivery_date, string $slot_key, int $capacity ): bool {
		global $wpdb;

		$slot_key = sanitize_title( $slot_key );

		if (
			! self::is_atomicity_schema_ready() ||
			! DeliverySlotService::is_valid_date( $delivery_date ) ||
			'' === $slot_key ||
			$capacity < 0
		) {
			return false;
		}

		$table_name = CreateDeliveryCapacityTable::get_table_name();
		$now        = current_time( 'mysql', true );
		$query      = $wpdb->prepare(
			"INSERT INTO {$table_name}
				(delivery_date, slot_key, capacity, reserved_count, updated_at)
			VALUES (%s, %s, %d, %d, %s)
			ON DUPLICATE KEY UPDATE
				capacity = IF(reserved_count <= %d, %d, capacity),
				updated_at = IF(reserved_count <= %d, %s, updated_at)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$delivery_date,
			$slot_key,
			$capacity,
			0,
			$now,
			$capacity,
			$capacity,
			$capacity,
			$now
		);

		if ( false === $wpdb->query( $query ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return false;
		}

		$current = self::get_capacity( $delivery_date, $slot_key );

		return is_array( $current ) &&
			$capacity === $current['capacity'] &&
			$current['reserved_count'] <= $current['capacity'];
	}

	/**
	 * Returns capacity information for a delivery date and slot.
	 *
	 * @param string $delivery_date Delivery date in Y-m-d format.
	 * @param string $slot_key      Delivery slot identifier.
	 * @return array<string, int>|null
	 */
	public static function get_capacity( string $delivery_date, string $slot_key ): ?array {
		global $wpdb;

		$table_name = CreateDeliveryCapacityTable::get_table_name();
		$capacity   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT capacity, reserved_count
				FROM {$table_name}
				WHERE delivery_date = %s
					AND slot_key = %s
				LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$delivery_date,
				sanitize_title( $slot_key )
			),
			ARRAY_A
		);

		if ( ! is_array( $capacity ) ) {
			return null;
		}

		return array(
			'capacity'       => (int) $capacity['capacity'],
			'reserved_count' => (int) $capacity['reserved_count'],
		);
	}

	/**
	 * Determines whether capacity is available.
	 *
	 * @param string $delivery_date Delivery date in Y-m-d format.
	 * @param string $slot_key      Delivery slot identifier.
	 * @param int    $quantity      Required capacity.
	 * @return bool
	 */
	public static function has_capacity( string $delivery_date, string $slot_key, int $quantity = 1 ): bool {
		$capacity = self::get_capacity( $delivery_date, $slot_key );

		return is_array( $capacity ) &&
			$quantity > 0 &&
			$capacity['capacity'] - $capacity['reserved_count'] >= $quantity;
	}

	/**
	 * Atomically claims available delivery capacity.
	 *
	 * @param string $delivery_date Delivery date in Y-m-d format.
	 * @param string $slot_key      Delivery slot identifier.
	 * @param int    $quantity      Capacity to claim.
	 * @return bool
	 */
	public static function claim_capacity( string $delivery_date, string $slot_key, int $quantity ): bool {
		global $wpdb;

		$slot_key = sanitize_title( $slot_key );

		if (
			! self::is_atomicity_schema_ready() ||
			! DeliverySlotService::is_valid_date( $delivery_date ) ||
			'' === $slot_key ||
			$quantity <= 0
		) {
			return false;
		}

		$table_name = CreateDeliveryCapacityTable::get_table_name();
		$result     = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name}
				SET reserved_count = reserved_count + %d,
					updated_at = %s
				WHERE delivery_date = %s
					AND slot_key = %s
					AND capacity - reserved_count >= %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$quantity,
				current_time( 'mysql', true ),
				$delivery_date,
				$slot_key,
				$quantity
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return 1 === $result;
	}

	/**
	 * Releases claimed delivery capacity exactly.
	 *
	 * @param string $delivery_date Delivery date in Y-m-d format.
	 * @param string $slot_key      Delivery slot identifier.
	 * @param int    $quantity      Capacity to release.
	 * @return bool
	 */
	public static function release_capacity( string $delivery_date, string $slot_key, int $quantity ): bool {
		global $wpdb;

		$slot_key = sanitize_title( $slot_key );

		if (
			! self::is_atomicity_schema_ready() ||
			! DeliverySlotService::is_valid_date( $delivery_date ) ||
			'' === $slot_key ||
			$quantity <= 0
		) {
			return false;
		}

		$table_name = CreateDeliveryCapacityTable::get_table_name();
		$result     = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name}
				SET reserved_count = reserved_count - %d,
					updated_at = %s
				WHERE delivery_date = %s
					AND slot_key = %s
					AND reserved_count >= %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$quantity,
				current_time( 'mysql', true ),
				$delivery_date,
				$slot_key,
				$quantity
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return 1 === $result;
	}

	/**
	 * Determines whether the atomic delivery-reservation schema is ready.
	 *
	 * @return bool
	 */
	private static function is_atomicity_schema_ready(): bool {
		$installed_version = get_option( self::SCHEMA_VERSION_OPTION, false );

		if ( ! is_scalar( $installed_version ) ) {
			return false;
		}

		$installed_version = trim( (string) $installed_version );

		if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $installed_version ) ) {
			return false;
		}

		return version_compare( $installed_version, self::ATOMICITY_SCHEMA_VERSION, '>=' );
	}
}
