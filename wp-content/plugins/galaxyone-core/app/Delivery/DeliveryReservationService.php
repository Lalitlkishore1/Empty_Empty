<?php
/**
 * Delivery-reservation service.
 *
 * @package GalaxyOne\Core\Delivery
 */

namespace GalaxyOne\Core\Delivery;

use GalaxyOne\Core\Database\Migrations\CreateDeliveryReservationsTable;

final class DeliveryReservationService {

	/**
	 * Active reservation status.
	 *
	 * @var string
	 */
	private const ACTIVE_STATUS = 'active';

	/**
	 * Reservation lifetime in seconds.
	 *
	 * @var int
	 */
	private const RESERVATION_TTL = 1800;

	/**
	 * Creates a delivery capacity reservation.
	 *
	 * @param string $delivery_date Delivery date in Y-m-d format.
	 * @param string $slot_key      Delivery slot identifier.
	 * @param int    $quantity      Reserved capacity.
	 * @param int    $order_id      WooCommerce order ID when available.
	 * @return array<string, int|string>|null
	 */
	public static function create(
		string $delivery_date,
		string $slot_key,
		int $quantity = 1,
		int $order_id = 0
	): ?array {
		global $wpdb;

		self::release_expired();

		if (
			$quantity <= 0 ||
			! DeliverySlotService::is_slot_available( $delivery_date, $slot_key ) ||
			! DeliveryCapacityService::claim_capacity( $delivery_date, $slot_key, $quantity )
		) {
			return null;
		}

		$token      = wp_generate_uuid4();
		$table_name = CreateDeliveryReservationsTable::get_table_name();
		$now        = current_time( 'mysql', true );
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + self::RESERVATION_TTL );
		$inserted   = $wpdb->insert(
			$table_name,
			array(
				'reservation_token' => $token,
				'order_id'          => max( 0, $order_id ),
				'delivery_date'     => $delivery_date,
				'slot_key'          => sanitize_title( $slot_key ),
				'quantity'          => $quantity,
				'status'            => self::ACTIVE_STATUS,
				'expires_at'        => $expires_at,
				'created_at'        => $now,
			),
			array(
				'%s',
				'%d',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
			)
		);

		if ( false === $inserted ) {
			DeliveryCapacityService::release_capacity( $delivery_date, $slot_key, $quantity );

			return null;
		}

		return array(
			'token'         => $token,
			'delivery_date' => $delivery_date,
			'slot_key'      => sanitize_title( $slot_key ),
			'quantity'      => $quantity,
			'expires_at'    => $expires_at,
		);
	}

	/**
	 * Releases an active reservation exactly once.
	 *
	 * @param string $reservation_token Reservation token.
	 * @param string $status            Final status.
	 * @return bool
	 */
	public static function release( string $reservation_token, string $status = 'released' ): bool {
		global $wpdb;

		if (
			! in_array( $status, array( 'released', 'cancelled', 'expired' ), true ) ||
			! wp_is_uuid( $reservation_token )
		) {
			return false;
		}

		$table_name  = CreateDeliveryReservationsTable::get_table_name();
		$reservation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT delivery_date, slot_key, quantity
				FROM {$table_name}
				WHERE reservation_token = %s
					AND status = %s
				LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$reservation_token,
				self::ACTIVE_STATUS
			),
			ARRAY_A
		);

		if ( ! is_array( $reservation ) ) {
			return false;
		}

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name}
				SET status = %s,
					released_at = %s
				WHERE reservation_token = %s
					AND status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$status,
				current_time( 'mysql', true ),
				$reservation_token,
				self::ACTIVE_STATUS
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( 1 !== $updated ) {
			return false;
		}

		return DeliveryCapacityService::release_capacity(
			(string) $reservation['delivery_date'],
			(string) $reservation['slot_key'],
			(int) $reservation['quantity']
		);
	}

	/**
	 * Releases all expired active reservations.
	 *
	 * @return int
	 */
	public static function release_expired(): int {
		global $wpdb;

		$table_name = CreateDeliveryReservationsTable::get_table_name();
		$tokens     = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT reservation_token
				FROM {$table_name}
				WHERE status = %s
					AND expires_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::ACTIVE_STATUS,
				current_time( 'mysql', true )
			)
		);

		if ( ! is_array( $tokens ) ) {
			return 0;
		}

		$released = 0;

		foreach ( $tokens as $token ) {
			if ( self::release( (string) $token, 'expired' ) ) {
				++$released;
			}
		}

		return $released;
	}
}
