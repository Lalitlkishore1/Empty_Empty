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
			! DeliverySlotService::is_valid_date( $delivery_date ) ||
			'' === $slot_key ||
			$capacity < 0
		) {
			return false;
		}

		$table_name = CreateDeliveryCapacityTable::get_table_name();
		$current    = self::get_capacity( $delivery_date, $slot_key );

		if ( is_array( $current ) && $capacity < $current['reserved_count'] ) {
			return false;
		}

		$now    = current_time( 'mysql', true );
		$query  = $wpdb->prepare(
			"INSERT INTO {$table_name}
				(delivery_date, slot_key, capacity, reserved_count, updated_at)
			VALUES (%s, %s, %d, %d, %s)
			ON DUPLICATE KEY UPDATE
				capacity = %d,
				updated_at = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$delivery_date,
			$slot_key,
			$capacity,
			0,
			$now,
			$capacity,
			$now
		);

		return false !== $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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

		if ( $quantity <= 0 ) {
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
				sanitize_title( $slot_key ),
				$quantity
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return 1 === $result;
	}

	/**
	 * Releases claimed delivery capacity.
	 *
	 * @param string $delivery_date Delivery date in Y-m-d format.
	 * @param string $slot_key      Delivery slot identifier.
	 * @param int    $quantity      Capacity to release.
	 * @return bool
	 */
	public static function release_capacity( string $delivery_date, string $slot_key, int $quantity ): bool {
		global $wpdb;

		if ( $quantity <= 0 ) {
			return false;
		}

		$table_name = CreateDeliveryCapacityTable::get_table_name();
		$result     = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name}
				SET reserved_count = GREATEST(reserved_count - %d, 0),
					updated_at = %s
				WHERE delivery_date = %s
					AND slot_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$quantity,
				current_time( 'mysql', true ),
				$delivery_date,
				sanitize_title( $slot_key )
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return 1 === $result;
	}
}
