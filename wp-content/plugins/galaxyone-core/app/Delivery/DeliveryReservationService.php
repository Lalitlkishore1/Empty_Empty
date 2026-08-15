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
	 * Schema option used to store the installed version.
	 *
	 * @var string
	 */
	private const SCHEMA_VERSION_OPTION = 'galaxyone_core_schema_version';

	/**
	 * Minimum schema version required for atomic reservation mutations.
	 *
	 * @var string
	 */
	private const ATOMICITY_SCHEMA_VERSION = '0.9.0';

	/**
	 * Active reservation status.
	 *
	 * @var string
	 */
	private const ACTIVE_STATUS = 'active';

	/**
	 * Confirmed order reservation status.
	 *
	 * @var string
	 */
	private const CONFIRMED_STATUS = 'confirmed';

	/**
	 * Reservation lifetime in seconds.
	 *
	 * @var int
	 */
	private const RESERVATION_TTL = 1800;

	/**
	 * Maximum attempts for retryable database lock failures.
	 *
	 * @var int
	 */
	private const MAX_TRANSACTION_ATTEMPTS = 3;

	/**
	 * Creates a delivery capacity reservation.
	 *
	 * @param string $delivery_date   Delivery date in Y-m-d format.
	 * @param string $slot_key        Delivery slot identifier.
	 * @param int    $quantity        Reserved capacity.
	 * @param int    $order_id        WooCommerce order ID when available.
	 * @param string $idempotency_key Stable server-generated operation UUID.
	 * @return array<string, int|string>|null
	 */
	public static function create(
		string $delivery_date,
		string $slot_key,
		int $quantity = 1,
		int $order_id = 0,
		string $idempotency_key = ''
	): ?array {
		$delivery_date   = sanitize_text_field( $delivery_date );
		$slot_key        = sanitize_title( $slot_key );
		$idempotency_key = trim( $idempotency_key );

		if (
			! self::is_atomicity_schema_ready() ||
			! DeliverySlotService::is_valid_date( $delivery_date ) ||
			'' === $slot_key ||
			$quantity <= 0 ||
			! wp_is_uuid( $idempotency_key ) ||
			! DeliverySlotService::is_slot_available( $delivery_date, $slot_key )
		) {
			return null;
		}

		self::release_expired();

		for ( $attempt = 0; $attempt < self::MAX_TRANSACTION_ATTEMPTS; ++$attempt ) {
			$result = self::create_once(
				$delivery_date,
				$slot_key,
				$quantity,
				max( 0, $order_id ),
				$idempotency_key
			);

			if ( 'success' === $result['state'] && isset( $result['reservation'] ) && is_array( $result['reservation'] ) ) {
				return $result['reservation'];
			}

			if ( 'retry' !== $result['state'] || self::MAX_TRANSACTION_ATTEMPTS - 1 === $attempt ) {
				return null;
			}

			usleep( 50000 * ( $attempt + 1 ) );
		}

		return null;
	}

	/**
	 * Binds an active reservation to a successfully created order.
	 *
	 * Confirmed reservations remain counted against delivery capacity until
	 * the associated order is cancelled or fails.
	 *
	 * @param string $reservation_token Reservation token.
	 * @param int    $order_id          WooCommerce order ID.
	 * @return bool
	 */
	public static function assign_to_order( string $reservation_token, int $order_id ): bool {
		if (
			! self::is_atomicity_schema_ready() ||
			! wp_is_uuid( $reservation_token ) ||
			$order_id <= 0
		) {
			return false;
		}

		for ( $attempt = 0; $attempt < self::MAX_TRANSACTION_ATTEMPTS; ++$attempt ) {
			$result = self::assign_to_order_once( $reservation_token, $order_id );

			if ( 'success' === $result ) {
				return true;
			}

			if ( 'retry' !== $result || self::MAX_TRANSACTION_ATTEMPTS - 1 === $attempt ) {
				return false;
			}

			usleep( 50000 * ( $attempt + 1 ) );
		}

		return false;
	}

	/**
	 * Releases an active or confirmed reservation exactly once.
	 *
	 * @param string $reservation_token Reservation token.
	 * @param string $status            Final status.
	 * @return bool
	 */
	public static function release( string $reservation_token, string $status = 'released' ): bool {
		if (
			! self::is_atomicity_schema_ready() ||
			! in_array( $status, array( 'released', 'cancelled', 'expired', 'failed' ), true ) ||
			! wp_is_uuid( $reservation_token )
		) {
			return false;
		}

		for ( $attempt = 0; $attempt < self::MAX_TRANSACTION_ATTEMPTS; ++$attempt ) {
			$result = self::release_once( $reservation_token, $status );

			if ( 'success' === $result ) {
				return true;
			}

			if ( 'retry' !== $result || self::MAX_TRANSACTION_ATTEMPTS - 1 === $attempt ) {
				return false;
			}

			usleep( 50000 * ( $attempt + 1 ) );
		}

		return false;
	}

	/**
	 * Releases all expired active reservations.
	 *
	 * @return int
	 */
	public static function release_expired(): int {
		global $wpdb;

		if ( ! self::is_atomicity_schema_ready() ) {
			return 0;
		}

		$table_name = CreateDeliveryReservationsTable::get_table_name();

		self::clear_database_error();

		$tokens = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT reservation_token
				FROM %i
				WHERE status = %s
					AND expires_at < %s",
				$table_name,
				self::ACTIVE_STATUS,
				current_time( 'mysql', true )
			)
		);

		if ( self::database_has_error() || ! is_array( $tokens ) ) {
			return 0;
		}

		$released = 0;

		foreach ( $tokens as $token ) {
			if ( self::expire_reservation( (string) $token ) ) {
				++$released;
			}
		}

		return $released;
	}

	/**
	 * Creates one reservation transaction attempt.
	 *
	 * @param string $delivery_date   Normalized delivery date.
	 * @param string $slot_key        Normalized delivery slot key.
	 * @param int    $quantity        Requested quantity.
	 * @param int    $order_id        WooCommerce order ID.
	 * @param string $idempotency_key Stable operation UUID.
	 * @return array<string, mixed>
	 */
	private static function create_once(
		string $delivery_date,
		string $slot_key,
		int $quantity,
		int $order_id,
		string $idempotency_key
	): array {
		global $wpdb;

		if ( ! self::begin_transaction() ) {
			return self::database_error_is_retryable()
				? array( 'state' => 'retry' )
				: array( 'state' => 'failed' );
		}

		$table_name = CreateDeliveryReservationsTable::get_table_name();
		$existing   = self::lock_reservation_by_idempotency_key( $table_name, $idempotency_key );

		if ( false === $existing ) {
			return self::failed_transaction_result();
		}

		if ( is_array( $existing ) ) {
			if (
				self::ACTIVE_STATUS === (string) $existing['status'] &&
				$delivery_date === (string) $existing['delivery_date'] &&
				$slot_key === (string) $existing['slot_key'] &&
				$quantity === (int) $existing['quantity']
			) {
				if ( ! self::commit_transaction() ) {
					self::rollback_transaction();

					return array( 'state' => 'failed' );
				}

				return array(
					'state'       => 'success',
					'reservation' => self::format_reservation( $existing ),
				);
			}

			self::rollback_transaction();

			return array( 'state' => 'failed' );
		}

		if ( ! DeliveryCapacityService::claim_capacity( $delivery_date, $slot_key, $quantity ) ) {
			return self::failed_transaction_result();
		}

		$token      = wp_generate_uuid4();
		$now        = current_time( 'mysql', true );
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + self::RESERVATION_TTL );
		$inserted   = $wpdb->insert(
			$table_name,
			array(
				'reservation_token' => $token,
				'idempotency_key'   => $idempotency_key,
				'order_id'          => $order_id,
				'delivery_date'     => $delivery_date,
				'slot_key'          => $slot_key,
				'quantity'          => $quantity,
				'status'            => self::ACTIVE_STATUS,
				'expires_at'        => $expires_at,
				'created_at'        => $now,
			),
			array(
				'%s',
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
			return self::failed_transaction_result();
		}

		if ( ! self::commit_transaction() ) {
			self::rollback_transaction();

			return array( 'state' => 'failed' );
		}

		return array(
			'state'       => 'success',
			'reservation' => array(
				'token'         => $token,
				'delivery_date' => $delivery_date,
				'slot_key'      => $slot_key,
				'quantity'      => $quantity,
				'expires_at'    => $expires_at,
			),
		);
	}

	/**
	 * Performs one transactional confirmation attempt.
	 *
	 * @param string $reservation_token Reservation token.
	 * @param int    $order_id          WooCommerce order ID.
	 * @return string
	 */
	private static function assign_to_order_once( string $reservation_token, int $order_id ): string {
		global $wpdb;

		if ( ! self::begin_transaction() ) {
			return self::database_error_is_retryable() ? 'retry' : 'failed';
		}

		$table_name  = CreateDeliveryReservationsTable::get_table_name();
		$reservation = self::lock_reservation_by_token( $table_name, $reservation_token );

		if ( false === $reservation ) {
			return self::failed_transaction_status();
		}

		if ( ! is_array( $reservation ) ) {
			self::rollback_transaction();

			return 'failed';
		}

		if ( self::CONFIRMED_STATUS === (string) $reservation['status'] ) {
			$confirmed_for_order = $order_id === (int) $reservation['order_id'];

			if ( ! self::commit_transaction() ) {
				self::rollback_transaction();

				return 'failed';
			}

			return $confirmed_for_order ? 'success' : 'failed';
		}

		if (
			self::ACTIVE_STATUS !== (string) $reservation['status'] ||
			(string) $reservation['expires_at'] <= current_time( 'mysql', true )
		) {
			self::rollback_transaction();

			return 'failed';
		}

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i
				SET order_id = %d,
					status = %s,
					expires_at = %s
				WHERE id = %d
					AND status = %s
					AND expires_at > %s",
				$table_name,
				$order_id,
				self::CONFIRMED_STATUS,
				current_time( 'mysql', true ),
				(int) $reservation['id'],
				self::ACTIVE_STATUS,
				current_time( 'mysql', true )
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( 1 !== $updated ) {
			return self::failed_transaction_status();
		}

		if ( ! self::commit_transaction() ) {
			self::rollback_transaction();

			return 'failed';
		}

		return 'success';
	}

	/**
	 * Performs one transactional release attempt.
	 *
	 * @param string $reservation_token Reservation token.
	 * @param string $status            Final status.
	 * @return string
	 */
	private static function release_once( string $reservation_token, string $status ): string {
		global $wpdb;

		if ( ! self::begin_transaction() ) {
			return self::database_error_is_retryable() ? 'retry' : 'failed';
		}

		$table_name  = CreateDeliveryReservationsTable::get_table_name();
		$reservation = self::lock_reservation_by_token( $table_name, $reservation_token );

		if ( false === $reservation ) {
			return self::failed_transaction_status();
		}

		if ( ! is_array( $reservation ) ) {
			self::rollback_transaction();

			return 'failed';
		}

		if (
			self::ACTIVE_STATUS !== (string) $reservation['status'] &&
			self::CONFIRMED_STATUS !== (string) $reservation['status']
		) {
			self::rollback_transaction();

			return 'failed';
		}

		if (
			! DeliveryCapacityService::release_capacity(
				(string) $reservation['delivery_date'],
				(string) $reservation['slot_key'],
				(int) $reservation['quantity']
			)
		) {
			return self::failed_transaction_status();
		}

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i
				SET status = %s,
					released_at = %s
				WHERE id = %d
					AND status IN (%s, %s)",
				$table_name,
				$status,
				current_time( 'mysql', true ),
				(int) $reservation['id'],
				self::ACTIVE_STATUS,
				self::CONFIRMED_STATUS
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( 1 !== $updated ) {
			return self::failed_transaction_status();
		}

		if ( ! self::commit_transaction() ) {
			self::rollback_transaction();

			return 'failed';
		}

		return 'success';
	}

	/**
	 * Expires one candidate reservation in its own transaction.
	 *
	 * @param string $reservation_token Reservation token.
	 * @return bool
	 */
	private static function expire_reservation( string $reservation_token ): bool {
		if ( ! wp_is_uuid( $reservation_token ) ) {
			return false;
		}

		for ( $attempt = 0; $attempt < self::MAX_TRANSACTION_ATTEMPTS; ++$attempt ) {
			$result = self::expire_reservation_once( $reservation_token );

			if ( 'success' === $result ) {
				return true;
			}

			if ( 'retry' !== $result || self::MAX_TRANSACTION_ATTEMPTS - 1 === $attempt ) {
				return false;
			}

			usleep( 50000 * ( $attempt + 1 ) );
		}

		return false;
	}

	/**
	 * Performs one transactional expiry attempt.
	 *
	 * @param string $reservation_token Reservation token.
	 * @return string
	 */
	private static function expire_reservation_once( string $reservation_token ): string {
		global $wpdb;

		if ( ! self::begin_transaction() ) {
			return self::database_error_is_retryable() ? 'retry' : 'failed';
		}

		$table_name  = CreateDeliveryReservationsTable::get_table_name();
		$reservation = self::lock_reservation_by_token( $table_name, $reservation_token );

		if ( false === $reservation ) {
			return self::failed_transaction_status();
		}

		if (
			! is_array( $reservation ) ||
			self::ACTIVE_STATUS !== (string) $reservation['status'] ||
			(string) $reservation['expires_at'] >= current_time( 'mysql', true )
		) {
			self::rollback_transaction();

			return 'failed';
		}

		if (
			! DeliveryCapacityService::release_capacity(
				(string) $reservation['delivery_date'],
				(string) $reservation['slot_key'],
				(int) $reservation['quantity']
			)
		) {
			return self::failed_transaction_status();
		}

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i
				SET status = %s,
					released_at = %s
				WHERE id = %d
					AND status = %s
					AND expires_at < %s",
				$table_name,
				'expired',
				current_time( 'mysql', true ),
				(int) $reservation['id'],
				self::ACTIVE_STATUS,
				current_time( 'mysql', true )
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( 1 !== $updated ) {
			return self::failed_transaction_status();
		}

		if ( ! self::commit_transaction() ) {
			self::rollback_transaction();

			return 'failed';
		}

		return 'success';
	}

	/**
	 * Locks an existing reservation by its idempotency key.
	 *
	 * @param string $table_name      Reservation table name.
	 * @param string $idempotency_key Stable operation UUID.
	 * @return array<string, mixed>|false|null
	 */
	private static function lock_reservation_by_idempotency_key(
		string $table_name,
		string $idempotency_key
	): array|false|null {
		global $wpdb;

		self::clear_database_error();

		$reservation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, reservation_token, idempotency_key, order_id, delivery_date,
					slot_key, quantity, status, expires_at
				FROM %i
				WHERE idempotency_key = %s
				LIMIT 1
				FOR UPDATE",
				$table_name,
				$idempotency_key
			),
			ARRAY_A
		);

		if ( self::database_has_error() ) {
			return false;
		}

		return is_array( $reservation ) ? $reservation : null;
	}

	/**
	 * Locks an existing reservation by token.
	 *
	 * @param string $table_name        Reservation table name.
	 * @param string $reservation_token Reservation token.
	 * @return array<string, mixed>|false|null
	 */
	private static function lock_reservation_by_token(
		string $table_name,
		string $reservation_token
	): array|false|null {
		global $wpdb;

		self::clear_database_error();

		$reservation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, reservation_token, order_id, delivery_date, slot_key,
					quantity, status, expires_at
				FROM %i
				WHERE reservation_token = %s
				LIMIT 1
				FOR UPDATE",
				$table_name,
				$reservation_token
			),
			ARRAY_A
		);

		if ( self::database_has_error() ) {
			return false;
		}

		return is_array( $reservation ) ? $reservation : null;
	}

	/**
	 * Formats a locked database row as a reservation payload.
	 *
	 * @param array<string, mixed> $reservation Reservation database row.
	 * @return array<string, int|string>
	 */
	private static function format_reservation( array $reservation ): array {
		return array(
			'token'         => (string) $reservation['reservation_token'],
			'delivery_date' => (string) $reservation['delivery_date'],
			'slot_key'      => (string) $reservation['slot_key'],
			'quantity'      => (int) $reservation['quantity'],
			'expires_at'    => (string) $reservation['expires_at'],
		);
	}

	/**
	 * Begins a new transaction only when the connection is not already in one.
	 *
	 * @return bool
	 */
	private static function begin_transaction(): bool {
		global $wpdb;

		if ( self::transaction_is_open() ) {
			return false;
		}

		self::clear_database_error();

		return false !== $wpdb->query( 'START TRANSACTION' ) && ! self::database_has_error(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Commits the current transaction.
	 *
	 * @return bool
	 */
	private static function commit_transaction(): bool {
		global $wpdb;

		self::clear_database_error();

		return false !== $wpdb->query( 'COMMIT' ) && ! self::database_has_error(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Attempts to roll back the current transaction.
	 *
	 * @return bool
	 */
	private static function rollback_transaction(): bool {
		global $wpdb;

		self::clear_database_error();

		return false !== $wpdb->query( 'ROLLBACK' ) && ! self::database_has_error(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Determines whether the database connection is already in a transaction.
	 *
	 * @return bool
	 */
	private static function transaction_is_open(): bool {
	    global $wpdb;

	    self::clear_database_error();

	    $in_transaction = $wpdb->get_var(
		    "SELECT COUNT(*)
		    FROM information_schema.innodb_trx
		    WHERE trx_mysql_thread_id = CONNECTION_ID()"
	    ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	    if ( self::database_has_error() || null === $in_transaction ) {
		    return true;
	    }

	    return 0 < (int) $in_transaction;
    }

	/**
	 * Returns the safe outcome after a failed coordinated write.
	 *
	 * @return array<string, string>
	 */
	private static function failed_transaction_result(): array {
		$retryable = self::database_error_is_retryable();
		$rolled    = self::rollback_transaction();

		return $retryable && $rolled
			? array( 'state' => 'retry' )
			: array( 'state' => 'failed' );
	}

	/**
	 * Returns the safe status after a failed coordinated write.
	 *
	 * @return string
	 */
	private static function failed_transaction_status(): string {
		$retryable = self::database_error_is_retryable();
		$rolled    = self::rollback_transaction();

		return $retryable && $rolled ? 'retry' : 'failed';
	}

	/**
	 * Determines whether the most recent database failure is retryable.
	 *
	 * @return bool
	 */
	private static function database_error_is_retryable(): bool {
		global $wpdb;

		$error = strtolower( (string) $wpdb->last_error );

		return '' !== $error &&
			(
				false !== strpos( $error, 'deadlock' ) ||
				false !== strpos( $error, 'lock wait timeout' ) ||
				false !== strpos( $error, 'error 1205' ) ||
				false !== strpos( $error, 'error 1213' )
			);
	}

	/**
	 * Clears a previously recorded database error before a correctness query.
	 *
	 * @return void
	 */
	private static function clear_database_error(): void {
		global $wpdb;

		$wpdb->last_error = '';
	}

	/**
	 * Determines whether the most recent database query failed.
	 *
	 * @return bool
	 */
	private static function database_has_error(): bool {
		global $wpdb;

		return '' !== (string) $wpdb->last_error;
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
