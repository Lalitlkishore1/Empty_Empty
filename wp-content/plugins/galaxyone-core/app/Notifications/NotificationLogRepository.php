<?php
/**
 * Notification-log repository.
 *
 * @package GalaxyOne\Core\Notifications
 */

namespace GalaxyOne\Core\Notifications;

use GalaxyOne\Core\Database\Migrations\CreateNotificationLogTable;

final class NotificationLogRepository {

	/**
	 * Queued notification status.
	 *
	 * @var string
	 */
	public const STATUS_QUEUED = 'queued';

	/**
	 * Sent notification status.
	 *
	 * @var string
	 */
	public const STATUS_SENT = 'sent';

	/**
	 * Failed notification status.
	 *
	 * @var string
	 */
	public const STATUS_FAILED = 'failed';

	/**
	 * Skipped notification status.
	 *
	 * @var string
	 */
	public const STATUS_SKIPPED = 'skipped';

	/**
	 * Creates one queued delivery record unless the order event is already logged.
	 *
	 * @param int    $order_id     WooCommerce order ID.
	 * @param string $event_key    Notification event key.
	 * @param string $provider_key Provider key.
	 * @param string $recipient    Recipient email address.
	 * @param string $subject      Email subject.
	 * @return int|null
	 */
	public static function queue(
		int $order_id,
		string $event_key,
		string $provider_key,
		string $recipient,
		string $subject
	): ?int {
		global $wpdb;

		if ( ! self::table_exists() || $order_id <= 0 ) {
			return null;
		}

		$event_key    = sanitize_key( $event_key );
		$provider_key = sanitize_key( $provider_key );
		$recipient    = sanitize_email( $recipient );
		$subject      = sanitize_text_field( $subject );

		if ( '' === $event_key || '' === $provider_key ) {
			return null;
		}

		$table_name = CreateNotificationLogTable::get_table_name();
		$existing   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE order_id = %d AND event_key = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order_id,
				$event_key
			)
		);

		if ( null !== $existing ) {
			return null;
		}

		$now      = current_time( 'mysql', true );
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'order_id'     => $order_id,
				'event_key'    => $event_key,
				'provider_key' => $provider_key,
				'recipient'    => $recipient,
				'status'       => self::STATUS_QUEUED,
				'subject'      => $subject,
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return false === $inserted ? null : (int) $wpdb->insert_id;
	}

	/**
	 * Updates a queued record with its final delivery result.
	 *
	 * @param int    $log_id        Notification-log ID.
	 * @param string $status        Final status.
	 * @param string $error_message Optional failure reason.
	 * @return bool
	 */
	public static function mark_result( int $log_id, string $status, string $error_message = '' ): bool {
		global $wpdb;

		if (
			$log_id <= 0 ||
			! in_array(
				$status,
				array( self::STATUS_SENT, self::STATUS_FAILED, self::STATUS_SKIPPED ),
				true
			)
		) {
			return false;
		}

		$updated = $wpdb->update(
			CreateNotificationLogTable::get_table_name(),
			array(
				'status'        => $status,
				'error_message' => '' === $error_message ? null : sanitize_text_field( $error_message ),
				'sent_at'       => self::STATUS_SENT === $status ? current_time( 'mysql', true ) : null,
				'updated_at'    => current_time( 'mysql', true ),
			),
			array( 'id' => $log_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return 1 === $updated;
	}

	/**
	 * Returns delivery records for one order.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array<int, object>
	 */
	public static function get_for_order( int $order_id ): array {
		global $wpdb;

		if ( ! self::table_exists() || $order_id <= 0 ) {
			return array();
		}

		$table_name = CreateNotificationLogTable::get_table_name();
		$records    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_key, provider_key, recipient, status, subject, error_message, sent_at, created_at
				FROM %i
				WHERE order_id = %d
				ORDER BY id DESC",
				$table_name,
				$order_id
			)
		);

		return is_array( $records ) ? $records : array();
	}

	/**
	 * Determines whether the notification-log table exists.
	 *
	 * @return bool
	 */
	private static function table_exists(): bool {
		global $wpdb;

		$table_name = CreateNotificationLogTable::get_table_name();
		$found_name = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return $table_name === $found_name;
	}
}
