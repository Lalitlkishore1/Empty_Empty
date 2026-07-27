<?php
/**
 * Schema-manager upgrade integration tests.
 *
 * @package GalaxyOne\Tests\Integration
 */

declare(strict_types=1);

namespace GalaxyOne\Tests\Integration;

use GalaxyOne\Core\Database\Migrations\CreateDeliveryCapacityTable;
use GalaxyOne\Core\Database\Migrations\CreateDeliveryReservationsTable;
use GalaxyOne\Core\Database\Migrations\CreateNotificationLogTable;
use GalaxyOne\Core\Database\SchemaManager;
use GalaxyOne\Tests\Support\IntegrationTestCase;

final class SchemaManagerUpgradeTest extends IntegrationTestCase {

	/**
	 * Test delivery date.
	 *
	 * @var string
	 */
	private string $delivery_date;

	/**
	 * Test delivery slot key.
	 *
	 * @var string
	 */
	private string $slot_key;

	/**
	 * Runs before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->delivery_date = gmdate( 'Y-m-d', time() + DAY_IN_SECONDS );
		$this->slot_key      = 'schema-atomicity-' . wp_generate_password( 10, false, false );

		$this->delete_test_rows();
		update_option( 'galaxyone_core_schema_version', '0.9.0', false );
	}

	/**
	 * Restores the approved atomicity schema after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->delete_test_rows();
		update_option( 'galaxyone_core_schema_version', '0.8.0', false );
		SchemaManager::maybe_upgrade();

		parent::tearDown();
	}

	/**
	 * Verifies a 0.7.0 installation receives notification logs and the complete approved upgrade path.
	 *
	 * @return void
	 */
	public function test_notification_log_schema_migrates_from_version_zero_point_seven(): void {
		global $wpdb;

		CreateNotificationLogTable::down();
		update_option( 'galaxyone_core_schema_version', '0.7.0', false );

		SchemaManager::maybe_upgrade();

		$table_name = CreateNotificationLogTable::get_table_name();
		$found_name = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		self::assertSame( $table_name, $found_name );
		self::assertSame( '0.9.0', get_option( 'galaxyone_core_schema_version' ) );
		self::assertSame( 'innodb', $this->get_engine( CreateDeliveryCapacityTable::get_table_name() ) );
		self::assertSame( 'innodb', $this->get_engine( CreateDeliveryReservationsTable::get_table_name() ) );
		self::assertSame( 'char(36)', $this->get_idempotency_column_type() );
		self::assertSame( 'YES', $this->get_idempotency_column_nullability() );
		self::assertTrue( $this->has_exact_idempotency_index() );
	}

	/**
	 * Verifies an installation at 0.8.0 reaches 0.9.0 with a ready atomic schema.
	 *
	 * @return void
	 */
	public function test_upgrade_from_zero_point_eight_creates_repeatable_atomicity_schema(): void {
		$this->prepare_legacy_delivery_schema();

		$this->insert_capacity( 5, 0 );
		$this->insert_reservation( wp_generate_uuid4(), 'active', 2, null );
		$this->insert_reservation( wp_generate_uuid4(), 'confirmed', 1, null );

		SchemaManager::maybe_upgrade();

		$reservations_table = CreateDeliveryReservationsTable::get_table_name();

		self::assertSame( '0.9.0', get_option( 'galaxyone_core_schema_version' ) );
		self::assertSame( 'innodb', $this->get_engine( CreateDeliveryCapacityTable::get_table_name() ) );
		self::assertSame( 'innodb', $this->get_engine( $reservations_table ) );
		self::assertSame( 3, $this->get_reserved_count() );
		self::assertSame( 2, $this->get_historical_null_idempotency_count() );
		self::assertTrue( $this->has_exact_idempotency_index() );
		self::assertSame( 'char(36)', $this->get_idempotency_column_type() );
		self::assertSame( 'YES', $this->get_idempotency_column_nullability() );

		SchemaManager::maybe_upgrade();

		self::assertSame( '0.9.0', get_option( 'galaxyone_core_schema_version' ) );
		self::assertSame( 3, $this->get_reserved_count() );
		self::assertTrue( $this->has_exact_idempotency_index() );
	}

	/**
	 * Verifies duplicate historical non-null idempotency keys block the migration.
	 *
	 * @return void
	 */
	public function test_duplicate_non_null_idempotency_keys_fail_closed(): void {
		$this->prepare_legacy_delivery_schema();
		$this->add_nullable_idempotency_column();

		$this->insert_capacity( 5, 0 );
		$duplicate_key = wp_generate_uuid4();

		$this->insert_reservation( wp_generate_uuid4(), 'active', 1, $duplicate_key );
		$this->insert_reservation( wp_generate_uuid4(), 'active', 1, $duplicate_key );

		SchemaManager::maybe_upgrade();

		self::assertSame( '0.8.0', get_option( 'galaxyone_core_schema_version' ) );
		self::assertSame( 2, $this->get_reservation_count() );
		self::assertSame( 0, $this->get_reserved_count() );
		self::assertFalse( $this->has_exact_idempotency_index() );
	}

	/**
	 * Verifies unmatched active reservations block readiness and keep the version unchanged.
	 *
	 * @return void
	 */
	public function test_unmatched_active_reservation_blocks_atomicity_upgrade(): void {
		$this->prepare_legacy_delivery_schema();

		$this->insert_reservation( wp_generate_uuid4(), 'active', 1, null );

		SchemaManager::maybe_upgrade();

		self::assertSame( '0.8.0', get_option( 'galaxyone_core_schema_version' ) );
		self::assertSame( 1, $this->get_reservation_count() );
		self::assertFalse( $this->has_exact_idempotency_index() );
	}

	/**
	 * Converts the test tables into the pre-0.9.0 delivery schema state.
	 *
	 * @return void
	 */
	private function prepare_legacy_delivery_schema(): void {
		global $wpdb;

		$reservations_table = CreateDeliveryReservationsTable::get_table_name();
		$capacity_table     = CreateDeliveryCapacityTable::get_table_name();

		$this->delete_test_rows();
		$this->drop_idempotency_index_if_present();
		$this->drop_idempotency_column_if_present();

		self::assertNotFalse(
			$wpdb->query(
				"ALTER TABLE {$reservations_table} ENGINE=MyISAM" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			)
		);
		self::assertNotFalse(
			$wpdb->query(
				"ALTER TABLE {$capacity_table} ENGINE=MyISAM" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			)
		);

		update_option( 'galaxyone_core_schema_version', '0.8.0', false );
	}

	/**
	 * Adds the nullable migration column without the unique index.
	 *
	 * @return void
	 */
	private function add_nullable_idempotency_column(): void {
		global $wpdb;

		$reservations_table = CreateDeliveryReservationsTable::get_table_name();

		self::assertSame(
			0,
			$wpdb->query(
				"ALTER TABLE {$reservations_table}
				ADD COLUMN idempotency_key CHAR(36) NULL
				AFTER reservation_token" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			)
		);
	}

	/**
	 * Inserts a test capacity fixture.
	 *
	 * @param int $capacity       Configured capacity.
	 * @param int $reserved_count Stored reserved count.
	 * @return void
	 */
	private function insert_capacity( int $capacity, int $reserved_count ): void {
		global $wpdb;

		$table_name = CreateDeliveryCapacityTable::get_table_name();

		self::assertSame(
			1,
			$wpdb->insert(
				$table_name,
				array(
					'delivery_date' => $this->delivery_date,
					'slot_key'      => $this->slot_key,
					'capacity'      => $capacity,
					'reserved_count' => $reserved_count,
					'updated_at'    => gmdate( 'Y-m-d H:i:s' ),
				),
				array( '%s', '%s', '%d', '%d', '%s' )
			)
		);
	}

	/**
	 * Inserts a historical reservation fixture.
	 *
	 * @param string      $token           Reservation token.
	 * @param string      $status          Reservation status.
	 * @param int         $quantity        Reserved quantity.
	 * @param string|null $idempotency_key Historical idempotency key.
	 * @return void
	 */
	private function insert_reservation(
		string $token,
		string $status,
		int $quantity,
		?string $idempotency_key
	): void {
		global $wpdb;

		$table_name = CreateDeliveryReservationsTable::get_table_name();
		$data       = array(
			'reservation_token' => $token,
			'order_id'          => 'confirmed' === $status ? 999 : 0,
			'delivery_date'     => $this->delivery_date,
			'slot_key'          => $this->slot_key,
			'quantity'          => $quantity,
			'status'            => $status,
			'expires_at'        => gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ),
			'created_at'        => gmdate( 'Y-m-d H:i:s' ),
		);
		$format     = array( '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' );

		if ( null !== $idempotency_key ) {
			$data['idempotency_key'] = $idempotency_key;
			$format[]                = '%s';
		}

		self::assertSame( 1, $wpdb->insert( $table_name, $data, $format ) );
	}

	/**
	 * Removes test-owned delivery fixtures.
	 *
	 * @return void
	 */
	private function delete_test_rows(): void {
		global $wpdb;

		$reservations_table = CreateDeliveryReservationsTable::get_table_name();
		$capacity_table     = CreateDeliveryCapacityTable::get_table_name();

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$reservations_table}
				WHERE delivery_date = %s
					AND slot_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->delivery_date,
				$this->slot_key
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$capacity_table}
				WHERE delivery_date = %s
					AND slot_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->delivery_date,
				$this->slot_key
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Drops the atomicity index when present.
	 *
	 * @return void
	 */
	private function drop_idempotency_index_if_present(): void {
		global $wpdb;

		if ( ! $this->has_exact_idempotency_index() ) {
			return;
		}

		$table_name = CreateDeliveryReservationsTable::get_table_name();

		self::assertSame(
			0,
			$wpdb->query(
				"ALTER TABLE {$table_name} DROP INDEX idempotency_key" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			)
		);
	}

	/**
	 * Drops the atomicity column when present.
	 *
	 * @return void
	 */
	private function drop_idempotency_column_if_present(): void {
		global $wpdb;

		if ( '' === $this->get_idempotency_column_type() ) {
			return;
		}

		$table_name = CreateDeliveryReservationsTable::get_table_name();

		self::assertSame(
			0,
			$wpdb->query(
				"ALTER TABLE {$table_name} DROP COLUMN idempotency_key" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			)
		);
	}

	/**
	 * Returns the storage engine for a table.
	 *
	 * @param string $table_name Database table name.
	 * @return string
	 */
	private function get_engine( string $table_name ): string {
		global $wpdb;

		$status = $wpdb->get_row(
			$wpdb->prepare(
				'SHOW TABLE STATUS LIKE %s',
				$table_name
			),
			ARRAY_A
		);

		return is_array( $status ) && isset( $status['Engine'] )
			? strtolower( (string) $status['Engine'] )
			: '';
	}

	/**
	 * Returns the idempotency-column type.
	 *
	 * @return string
	 */
	private function get_idempotency_column_type(): string {
		global $wpdb;

		$table_name = CreateDeliveryReservationsTable::get_table_name();
		$column     = $wpdb->get_row(
			"SHOW COLUMNS FROM {$table_name} LIKE 'idempotency_key'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return is_array( $column ) && isset( $column['Type'] )
			? strtolower( (string) $column['Type'] )
			: '';
	}

	/**
	 * Returns idempotency-column nullability.
	 *
	 * @return string
	 */
	private function get_idempotency_column_nullability(): string {
		global $wpdb;

		$table_name = CreateDeliveryReservationsTable::get_table_name();
		$column     = $wpdb->get_row(
			"SHOW COLUMNS FROM {$table_name} LIKE 'idempotency_key'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return is_array( $column ) && isset( $column['Null'] )
			? strtoupper( (string) $column['Null'] )
			: '';
	}

	/**
	 * Determines whether the exact required unique index exists.
	 *
	 * @return bool
	 */
	private function has_exact_idempotency_index(): bool {
		global $wpdb;

		$table_name = CreateDeliveryReservationsTable::get_table_name();
		$indexes    = $wpdb->get_results(
			"SHOW INDEX FROM {$table_name}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		$grouped    = array();

		if ( ! is_array( $indexes ) ) {
			return false;
		}

		foreach ( $indexes as $index ) {
			if (
				! is_array( $index ) ||
				! isset( $index['Key_name'] ) ||
				! is_scalar( $index['Key_name'] )
			) {
				return false;
			}

			$key_name = (string) $index['Key_name'];

			if ( ! isset( $grouped[ $key_name ] ) ) {
				$grouped[ $key_name ] = array();
			}

			$grouped[ $key_name ][] = $index;
		}

		foreach ( $grouped as $rows ) {
			if ( 1 !== count( $rows ) ) {
				continue;
			}

			$index = $rows[0];

			if (
				isset(
					$index['Non_unique'],
					$index['Column_name'],
					$index['Seq_in_index']
				) &&
				'0' === (string) $index['Non_unique'] &&
				'idempotency_key' === (string) $index['Column_name'] &&
				'1' === (string) $index['Seq_in_index'] &&
				array_key_exists( 'Sub_part', $index ) &&
				null === $index['Sub_part']
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns test reservation rows with NULL idempotency keys.
	 *
	 * @return int
	 */
	private function get_historical_null_idempotency_count(): int {
		global $wpdb;

		$table_name = CreateDeliveryReservationsTable::get_table_name();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$table_name}
				WHERE delivery_date = %s
					AND slot_key = %s
					AND idempotency_key IS NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->delivery_date,
				$this->slot_key
			)
		);
	}

	/**
	 * Returns the number of test reservations.
	 *
	 * @return int
	 */
	private function get_reservation_count(): int {
		global $wpdb;

		$table_name = CreateDeliveryReservationsTable::get_table_name();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$table_name}
				WHERE delivery_date = %s
					AND slot_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->delivery_date,
				$this->slot_key
			)
		);
	}

	/**
	 * Returns the test capacity reserved count.
	 *
	 * @return int
	 */
	private function get_reserved_count(): int {
		global $wpdb;

		$table_name = CreateDeliveryCapacityTable::get_table_name();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT reserved_count
				FROM {$table_name}
				WHERE delivery_date = %s
					AND slot_key = %s
				LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->delivery_date,
				$this->slot_key
			)
		);
	}
}
