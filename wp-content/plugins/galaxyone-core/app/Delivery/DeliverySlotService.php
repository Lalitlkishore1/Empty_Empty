<?php
/**
 * Delivery-slot service.
 *
 * @package GalaxyOne\Core\Delivery
 */

namespace GalaxyOne\Core\Delivery;

use DateTimeImmutable;
use DateTimeInterface;
use GalaxyOne\Core\Database\Migrations\CreateDeliveryRulesTable;

final class DeliverySlotService {

	/**
	 * Delivery-slot rule type.
	 *
	 * @var string
	 */
	private const SLOT_RULE_TYPE = 'delivery_slot';

	/**
	 * Closed-date rule type.
	 *
	 * @var string
	 */
	private const CLOSED_DATE_RULE_TYPE = 'closed_date';

	/**
	 * Saves a recurring delivery slot.
	 *
	 * @param string $slot_key    Stable slot identifier.
	 * @param string $label       Customer-facing slot label.
	 * @param int    $weekday     Day of week from 0 through 6, or -1 for every day.
	 * @param string $start_time  Start time in H:i format.
	 * @param string $end_time    End time in H:i format.
	 * @param string $cutoff_time Same-day cutoff time in H:i format.
	 * @return bool
	 */
	public static function save_slot(
		string $slot_key,
		string $label,
		int $weekday,
		string $start_time,
		string $end_time,
		string $cutoff_time
	): bool {
		global $wpdb;

		$slot_key          = sanitize_title( $slot_key );
		$label             = sanitize_text_field( $label );
		$start_time        = self::normalize_time( $start_time );
		$end_time          = self::normalize_time( $end_time );
		$raw_cutoff_time   = trim( $cutoff_time );
		$normalized_cutoff = '';

		if ( '' !== $raw_cutoff_time ) {
			$normalized_cutoff = self::normalize_time( $raw_cutoff_time );
		}

		if (
			'' === $slot_key ||
			'' === $label ||
			$weekday < -1 ||
			$weekday > 6 ||
			'' === $start_time ||
			'' === $end_time ||
			( '' !== $raw_cutoff_time && '' === $normalized_cutoff )
		) {
			return false;
		}

		if ( $start_time >= $end_time ) {
			return false;
		}

		$table_name = CreateDeliveryRulesTable::get_table_name();
		$now        = current_time( 'mysql', true );
		$query      = $wpdb->prepare(
			"INSERT INTO %i
				(rule_type, rule_key, label, weekday, start_time, end_time, cutoff_time, is_active, created_at, updated_at)
			VALUES (%s, %s, %s, %d, %s, %s, NULLIF(%s, ''), %d, %s, %s)
			ON DUPLICATE KEY UPDATE
				label = %s,
				weekday = %d,
				start_time = %s,
				end_time = %s,
				cutoff_time = NULLIF(%s, ''),
				is_active = %d,
				updated_at = %s",
			$table_name,
			self::SLOT_RULE_TYPE,
			$slot_key,
			$label,
			$weekday,
			$start_time,
			$end_time,
			$normalized_cutoff,
			1,
			$now,
			$now,
			$label,
			$weekday,
			$start_time,
			$end_time,
			$normalized_cutoff,
			1,
			$now
		);

		return false !== $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Saves a closed delivery date.
	 *
	 * @param string $delivery_date Date in Y-m-d format.
	 * @return bool
	 */
	public static function close_date( string $delivery_date ): bool {
		global $wpdb;

		if ( ! self::is_valid_date( $delivery_date ) ) {
			return false;
		}

		$table_name = CreateDeliveryRulesTable::get_table_name();
		$now        = current_time( 'mysql', true );
		$query      = $wpdb->prepare(
			"INSERT INTO %i
				(rule_type, rule_key, label, closed_date, is_active, created_at, updated_at)
			VALUES (%s, %s, %s, %s, %d, %s, %s)
			ON DUPLICATE KEY UPDATE
				is_active = %d,
				updated_at = %s",
			$table_name,
			self::CLOSED_DATE_RULE_TYPE,
			$delivery_date,
			$delivery_date,
			$delivery_date,
			1,
			$now,
			$now,
			1,
			$now
		);

		return false !== $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Returns active delivery slots.
	 *
	 * @return array<int, object>
	 */
	public static function get_slots(): array {
		global $wpdb;

		$table_name = CreateDeliveryRulesTable::get_table_name();
		$slots      = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT rule_key, label, weekday, start_time, end_time, cutoff_time
				FROM %i
				WHERE rule_type = %s
					AND is_active = 1
				ORDER BY start_time ASC",
				$table_name,
				self::SLOT_RULE_TYPE
			)
		);

		return is_array( $slots ) ? $slots : array();
	}

	/**
	 * Returns active delivery slots available on a date.
	 *
	 * @param string $delivery_date Date in Y-m-d format.
	 * @return array<int, object>
	 */
	public static function get_available_slots( string $delivery_date ): array {
		$available_slots = array();

		foreach ( self::get_slots() as $slot ) {
			if ( ! is_object( $slot ) || ! isset( $slot->rule_key ) || ! is_scalar( $slot->rule_key ) ) {
				continue;
			}

			if (
				self::is_slot_available( $delivery_date, (string) $slot->rule_key ) &&
				DeliveryCapacityService::has_capacity( $delivery_date, (string) $slot->rule_key )
			) {
				$available_slots[] = $slot;
			}
		}

		return $available_slots;
	}

	/**
	 * Returns the current delivery date in the WordPress timezone.
	 *
	 * @return string
	 */
	public static function get_current_delivery_date(): string {
		return self::get_current_datetime()->format( 'Y-m-d' );
	}

	/**
	 * Returns one active delivery slot.
	 *
	 * @param string $slot_key Slot identifier.
	 * @return array<string, int|string>|null
	 */
	public static function get_slot( string $slot_key ): ?array {
		global $wpdb;

		$slot_key = sanitize_title( $slot_key );

		if ( '' === $slot_key ) {
			return null;
		}

		$table_name = CreateDeliveryRulesTable::get_table_name();
		$slot       = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT rule_key, label, weekday, start_time, end_time, cutoff_time
				FROM %i
				WHERE rule_type = %s
					AND rule_key = %s
					AND is_active = 1
				LIMIT 1",
				$table_name,
				self::SLOT_RULE_TYPE,
				$slot_key
			),
			ARRAY_A
		);

		if ( ! is_array( $slot ) ) {
			return null;
		}

		return array(
			'rule_key'    => (string) $slot['rule_key'],
			'label'       => (string) $slot['label'],
			'weekday'     => (int) $slot['weekday'],
			'start_time'  => (string) $slot['start_time'],
			'end_time'    => (string) $slot['end_time'],
			'cutoff_time' => (string) $slot['cutoff_time'],
		);
	}

	/**
	 * Determines whether a delivery slot can be selected on a date.
	 *
	 * @param string $delivery_date Date in Y-m-d format.
	 * @param string $slot_key      Slot identifier.
	 * @return bool
	 */
	public static function is_slot_available( string $delivery_date, string $slot_key ): bool {
		if ( ! self::is_valid_date( $delivery_date ) || self::is_closed_date( $delivery_date ) ) {
			return false;
		}

		$slot = self::get_slot( $slot_key );
		$now  = self::get_current_datetime();

		if ( ! is_array( $slot ) || $delivery_date < $now->format( 'Y-m-d' ) ) {
			return false;
		}

		$delivery_datetime = DateTimeImmutable::createFromFormat(
			'!Y-m-d',
			$delivery_date,
			wp_timezone()
		);

		if ( false === $delivery_datetime ) {
			return false;
		}

		$weekday = (int) $delivery_datetime->format( 'w' );

		if ( -1 !== $slot['weekday'] && $weekday !== $slot['weekday'] ) {
			return false;
		}

		if (
			$delivery_date === $now->format( 'Y-m-d' ) &&
			'' !== $slot['cutoff_time'] &&
			$now->format( 'H:i' ) >= substr( $slot['cutoff_time'], 0, 5 )
		) {
			return false;
		}

		if ( $delivery_date === $now->format( 'Y-m-d' ) ) {
			$slot_end = DateTimeImmutable::createFromFormat(
				'!Y-m-d H:i:s',
				$delivery_date . ' ' . $slot['end_time'],
				wp_timezone()
			);

			if ( false === $slot_end || $now >= $slot_end ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns the current application datetime in the WordPress timezone.
	 *
	 * @return DateTimeImmutable
	 */
	private static function get_current_datetime(): DateTimeImmutable {
		$timezone = wp_timezone();
		$now      = apply_filters(
			'galaxyone_delivery_current_datetime',
			new DateTimeImmutable( 'now', $timezone )
		);

		if ( $now instanceof DateTimeInterface ) {
			return DateTimeImmutable::createFromInterface( $now )->setTimezone( $timezone );
		}

		return new DateTimeImmutable( 'now', $timezone );
	}

	/**
	 * Determines whether a date is closed for delivery.
	 *
	 * @param string $delivery_date Date in Y-m-d format.
	 * @return bool
	 */
	public static function is_closed_date( string $delivery_date ): bool {
		global $wpdb;

		if ( ! self::is_valid_date( $delivery_date ) ) {
			return true;
		}

		$table_name = CreateDeliveryRulesTable::get_table_name();
		$rule_id    = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id
				FROM %i
				WHERE rule_type = %s
					AND closed_date = %s
					AND is_active = 1
				LIMIT 1",
				$table_name,
				self::CLOSED_DATE_RULE_TYPE,
				$delivery_date
			)
		);

		return null !== $rule_id;
	}

	/**
	 * Determines whether a date is valid.
	 *
	 * @param string $delivery_date Date in Y-m-d format.
	 * @return bool
	 */
	public static function is_valid_date( string $delivery_date ): bool {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $delivery_date ) ) {
			return false;
		}

		$date_parts = explode( '-', $delivery_date );

		return checkdate(
			(int) $date_parts[1],
			(int) $date_parts[2],
			(int) $date_parts[0]
		);
	}

	/**
	 * Normalizes a time in H:i format.
	 *
	 * @param string $time Time value.
	 * @return string
	 */
	private static function normalize_time( string $time ): string {
		$time = trim( $time );

		if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
			return '';
		}

		return $time . ':00';
	}
}
