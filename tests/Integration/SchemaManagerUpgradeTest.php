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
use GalaxyOne\Core\Database\Migrations\CreateSupportedStreetsTable;
use GalaxyOne\Core\Database\SchemaManager;
use GalaxyOne\Tests\Support\IntegrationTestCase;

final class SchemaManagerUpgradeTest extends IntegrationTestCase {

	private string $delivery_date;

	private string $slot_key;

	private string $supported_street_identity_key;

	public function setUp(): void {
		parent::setUp();

		$this->delivery_date                  = gmdate( 'Y-m-d', time() + DAY_IN_SECONDS );
		$this->slot_key                       = 'schema-atomicity-' . wp_generate_password( 10, false, false );
		$this->supported_street_identity_key = hash( 'sha256', wp_generate_uuid4() );

		$this->delete_test_rows();
		$this->delete_supported_street_fixture();
		update_option( 'galaxyone_core_schema_version', '0.10.0', false );
	}

	public function tearDown(): void {
		$this->delete_test_rows();
		$this->delete_supported_street_fixture();
		update_option( 'galaxyone_core_schema_version', '0.8.0', false );
		SchemaManager::maybe_upgrade();

		parent::tearDown();
	}

	public function test_notification_log_schema_migrates_from_version_zero_point_seven(): void {
		global $wpdb;

		CreateNotificationLogTable::down();
		CreateSupportedStreetsTable::down();
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
		self::assertSame( '0.10.0', get_option( 'galaxyone_core_schema_version' ) );
		self::assertSame( 'innodb', $this->get_engine( CreateDeliveryCapacityTable::get_table_name() ) );
		self::assertSame( 'innodb', $this->get_engine( CreateDeliveryReservationsTable::get_table_name() ) );
		self::assertSame( 'char(36)', $this->get_idempotency_column_type() );
		self::assertSame( 'YES', $this->get_idempotency_column_nullability() );
		self::assertTrue( $this->has_exact_idempotency_index() );
		$this->assert_supported_streets_schema();
	}

	public function test_upgrade_from_zero_point_eight_creates_repeatable_atomicity_schema(): void {
		$this->prepare_legacy_delivery_schema();
		CreateSupportedStreetsTable::down();

		$this->insert_capacity( 5, 0 );
		$this->insert_reservation( wp_generate_uuid4(), 'active', 2, null );
		$this->insert_reservation( wp_generate_uuid4(), 'confirmed', 1, null );

		SchemaManager::maybe_upgrade();

		$reservations_table = CreateDeliveryReservationsTable::get_table_name();

		self::assertSame( '0.10.0', get_option( 'galaxyone_core_schema_version' ) );
		self::assertSame( 'innodb', $this->get_engine( CreateDeliveryCapacityTable::get_table_name() ) );
		self::assertSame( 'innodb', $this->get_engine( $reservations_table ) );
		self::assertSame( 3, $this->get_reserved_count() );
		self::assertSame( 2, $this->get_historical_null_idempotency_count() );
		self::assertTrue( $this->has_exact_idempotency_index() );
		self::assertSame( 'char(36)', $this->get_idempotency_column_type() );
		self::assertSame( 'YES', $this->get_idempotency_column_nullability() );
		$this->assert_supported_streets_schema();

		SchemaManager::maybe_upgrade();

		self::assertSame( '0.10.0', get_option( 'galaxyone_core_schema_version' ) );
		self::assertSame( 3, $this->get_reserved_count() );
		self::assertTrue( $this->has_exact_idempotency_index() );
		$this->assert_supported_streets_schema();
	}

	public function test_supported_streets_schema_migrates_from_version_zero_point_nine(): void {
		CreateSupportedStreetsTable::down();
		update_option( 'galaxyone_core_schema_version', '0.9.0', false );

		self::assertFalse( $this->supported_streets_table_exists() );

		SchemaManager::maybe_upgrade();

		self::assertSame( '0.10.0', get_option( 'galaxyone_core_schema_version' ) );
		$this->assert_supported_streets_schema();

		$this->insert_supported_street_fixture();

		self::assertSame( 1, $this->get_supported_street_fixture_count() );

		SchemaManager::maybe_upgrade();

		self::assertSame( '0.10.0', get_option( 'galaxyone_core_schema_version' ) );
		self::assertSame( 1, $this->get_supported_street_fixture_count() );
		$this->assert_supported_streets_schema();

		self::assertTrue( CreateSupportedStreetsTable::up() );

		self::assertSame( '0.10.0', get_option( 'galaxyone_core_schema_version' ) );
		self::assertSame( 1, $this->get_supported_street_fixture_count() );
		$this->assert_supported_streets_schema();
	}

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

	public function test_unmatched_active_reservation_blocks_atomicity_upgrade(): void {
		$this->prepare_legacy_delivery_schema();

		$this->insert_reservation( wp_generate_uuid4(), 'active', 1, null );

		SchemaManager::maybe_upgrade();

		self::assertSame( '0.8.0', get_option( 'galaxyone_core_schema_version' ) );
		self::assertSame( 1, $this->get_reservation_count() );
		self::assertFalse( $this->has_exact_idempotency_index() );
	}

	public function test_unmatched_confirmed_reservation_blocks_atomicity_upgrade(): void {
		$this->prepare_legacy_delivery_schema();

		$pre_upgrade_version = (string) get_option( 'galaxyone_core_schema_version' );
		$token               = wp_generate_uuid4();

		$this->insert_reservation( $token, 'confirmed', 1, null );

		SchemaManager::maybe_upgrade();

		self::assertSame(
			$pre_upgrade_version,
			(string) get_option( 'galaxyone_core_schema_version' )
		);
		self::assertSame( 1, $this->get_reservation_count() );
		self::assertSame( 'confirmed', $this->get_reservation_status( $token ) );
		self::assertSame( 0, $this->get_capacity_row_count() );
	}

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

	private function insert_supported_street_fixture(): void {
		global $wpdb;

		$table_name = CreateSupportedStreetsTable::get_table_name();

		self::assertSame(
			1,
			$wpdb->insert(
				$table_name,
				array(
					'street_name'              => 'Migration Test Street',
					'normalized_street_name'   => 'migration test street',
					'locality'                 => 'Migration Test Locality',
					'normalized_locality'      => 'migration test locality',
					'service_area'             => '560001',
					'street_identity_key'      => $this->supported_street_identity_key,
					'is_active'                => 1,
					'created_at'               => gmdate( 'Y-m-d H:i:s' ),
					'updated_at'               => gmdate( 'Y-m-d H:i:s' ),
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
			)
		);
	}

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

	private function delete_supported_street_fixture(): void {
		global $wpdb;

		if ( ! $this->supported_streets_table_exists() ) {
			return;
		}

		$wpdb->delete(
			CreateSupportedStreetsTable::get_table_name(),
			array( 'street_identity_key' => $this->supported_street_identity_key ),
			array( '%s' )
		);
	}

	private function drop_idempotency_index_if_present(): void {
		global $wpdb;

		if ( ! $this->has_exact_idempotency_index() ) {
			return;
		}

		$table_name = CreateDeliveryReservationsTable::get_table_name();

		self::assertNotFalse(
	        $wpdb->query(
				"ALTER TABLE {$table_name} DROP INDEX idempotency_key" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			)
		);
	}

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

	private function assert_supported_streets_schema(): void {
		$table_name = CreateSupportedStreetsTable::get_table_name();

		self::assertTrue( $this->supported_streets_table_exists() );
		self::assertSame( 'innodb', $this->get_engine( $table_name ) );

		$columns = $this->get_supported_streets_columns();

		self::assertSame(
			array(
				'created_at',
				'id',
				'is_active',
				'locality',
				'normalized_locality',
				'normalized_street_name',
				'service_area',
				'street_identity_key',
				'street_name',
				'updated_at',
			),
			array_keys( $columns )
		);

		$this->assert_supported_street_column(
			$columns,
			'id',
			'/^bigint(?:\(20\))? unsigned$/',
			'NO',
			null,
			'auto_increment'
		);
		$this->assert_supported_street_column(
			$columns,
			'street_name',
			'/^varchar\(191\)$/',
			'NO',
			null,
			''
		);
		$this->assert_supported_street_column(
			$columns,
			'normalized_street_name',
			'/^varchar\(191\)$/',
			'NO',
			null,
			''
		);
		$this->assert_supported_street_column(
			$columns,
			'locality',
			'/^varchar\(191\)$/',
			'NO',
			null,
			''
		);
		$this->assert_supported_street_column(
			$columns,
			'normalized_locality',
			'/^varchar\(191\)$/',
			'NO',
			null,
			''
		);
		$this->assert_supported_street_column(
			$columns,
			'service_area',
			'/^varchar\(32\)$/',
			'NO',
			'',
			''
		);
		$this->assert_supported_street_column(
			$columns,
			'street_identity_key',
			'/^char\(64\)$/',
			'NO',
			null,
			''
		);
		$this->assert_supported_street_column(
			$columns,
			'is_active',
			'/^tinyint(?:\(1\))?$/',
			'NO',
			'1',
			''
		);
		$this->assert_supported_street_column(
			$columns,
			'created_at',
			'/^datetime$/',
			'NO',
			null,
			''
		);
		$this->assert_supported_street_column(
			$columns,
			'updated_at',
			'/^datetime$/',
			'NO',
			null,
			''
		);

		$indexes = $this->get_supported_streets_indexes();

		self::assertSame(
			array(
				'PRIMARY',
				'is_active',
				'locality_active',
				'service_area',
				'street_identity_key',
			),
			array_keys( $indexes )
		);

		$this->assert_supported_street_index( $indexes, 'PRIMARY', true, array( 'id' ) );
		$this->assert_supported_street_index(
			$indexes,
			'street_identity_key',
			true,
			array( 'street_identity_key' )
		);
		$this->assert_supported_street_index(
			$indexes,
			'locality_active',
			false,
			array( 'normalized_locality', 'is_active' )
		);
		$this->assert_supported_street_index( $indexes, 'is_active', false, array( 'is_active' ) );
		$this->assert_supported_street_index(
			$indexes,
			'service_area',
			false,
			array( 'service_area' )
		);
	}

	private function assert_supported_street_column(
		array $columns,
		string $column_name,
		string $type_pattern,
		string $nullability,
		?string $default,
		string $extra
	): void {
		self::assertArrayHasKey( $column_name, $columns );
		self::assertIsArray( $columns[ $column_name ] );

		$column = $columns[ $column_name ];

		self::assertArrayHasKey( 'Type', $column );
		self::assertArrayHasKey( 'Null', $column );
		self::assertArrayHasKey( 'Default', $column );
		self::assertArrayHasKey( 'Extra', $column );
		self::assertMatchesRegularExpression(
			$type_pattern,
			strtolower( (string) $column['Type'] )
		);
		self::assertSame( $nullability, strtoupper( (string) $column['Null'] ) );

		if ( null === $default ) {
			self::assertNull( $column['Default'] );
		} else {
			self::assertSame( $default, (string) $column['Default'] );
		}

		self::assertSame( $extra, strtolower( (string) $column['Extra'] ) );
	}

	private function assert_supported_street_index(
		array $indexes,
		string $index_name,
		bool $is_unique,
		array $columns
	): void {
		self::assertArrayHasKey( $index_name, $indexes );
		self::assertCount( count( $columns ), $indexes[ $index_name ] );

		foreach ( $indexes[ $index_name ] as $position => $index ) {
			self::assertIsArray( $index );
			self::assertArrayHasKey( 'Key_name', $index );
			self::assertArrayHasKey( 'Non_unique', $index );
			self::assertArrayHasKey( 'Seq_in_index', $index );
			self::assertArrayHasKey( 'Column_name', $index );
			self::assertArrayHasKey( 'Sub_part', $index );
			self::assertSame( $index_name, (string) $index['Key_name'] );
			self::assertSame( $is_unique ? '0' : '1', (string) $index['Non_unique'] );
			self::assertSame( (string) ( $position + 1 ), (string) $index['Seq_in_index'] );
			self::assertSame( $columns[ $position ], (string) $index['Column_name'] );
			self::assertNull( $index['Sub_part'] );
		}
	}

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

	private function get_supported_street_fixture_count(): int {
		global $wpdb;

		$table_name = CreateSupportedStreetsTable::get_table_name();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$table_name}
				WHERE street_identity_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->supported_street_identity_key
			)
		);
	}

	private function get_supported_streets_columns(): array {
		global $wpdb;

		$table_name = CreateSupportedStreetsTable::get_table_name();
		$rows       = $wpdb->get_results(
			"SHOW COLUMNS FROM {$table_name}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		$columns    = array();

		if ( ! is_array( $rows ) ) {
			return $columns;
		}

		foreach ( $rows as $row ) {
			if (
				! is_array( $row ) ||
				! isset( $row['Field'] ) ||
				! is_scalar( $row['Field'] )
			) {
				return array();
			}

			$columns[ (string) $row['Field'] ] = $row;
		}

		ksort( $columns );

		return $columns;
	}

	private function get_supported_streets_indexes(): array {
		global $wpdb;

		$table_name = CreateSupportedStreetsTable::get_table_name();
		$rows       = $wpdb->get_results(
			"SHOW INDEX FROM {$table_name}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		$indexes    = array();

		if ( ! is_array( $rows ) ) {
			return $indexes;
		}

		foreach ( $rows as $row ) {
			if (
				! is_array( $row ) ||
				! isset( $row['Key_name'] ) ||
				! is_scalar( $row['Key_name'] )
			) {
				return array();
			}

			$index_name = (string) $row['Key_name'];

			if ( ! isset( $indexes[ $index_name ] ) ) {
				$indexes[ $index_name ] = array();
			}

			$indexes[ $index_name ][] = $row;
		}

		foreach ( $indexes as &$index_rows ) {
			usort(
				$index_rows,
				static function ( array $left, array $right ): int {
					return (int) ( $left['Seq_in_index'] ?? 0 )
						<=> (int) ( $right['Seq_in_index'] ?? 0 );
				}
			);
		}
		unset( $index_rows );

		ksort( $indexes );

		return $indexes;
	}

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

	private function supported_streets_table_exists(): bool {
		global $wpdb;

		$table_name = CreateSupportedStreetsTable::get_table_name();
		$found_name = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return $table_name === $found_name;
	}

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

	private function get_reservation_status( string $token ): string {
		global $wpdb;

		$table_name = CreateDeliveryReservationsTable::get_table_name();

		return (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status
				FROM {$table_name}
				WHERE reservation_token = %s
				LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$token
			)
		);
	}

	private function get_capacity_row_count(): int {
		global $wpdb;

		$table_name = CreateDeliveryCapacityTable::get_table_name();

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
