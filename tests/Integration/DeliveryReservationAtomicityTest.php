<?php
/**
 * Delivery reservation atomicity integration tests.
 *
 * @package GalaxyOne\Tests\Integration
 */

declare(strict_types=1);

namespace GalaxyOne\Tests\Integration;

use GalaxyOne\Core\Delivery\DeliveryCapacityService;
use GalaxyOne\Core\Delivery\DeliveryReservationService;
use GalaxyOne\Core\Delivery\DeliverySlotService;
use GalaxyOne\Core\Delivery\DeliveryValidationService;
use GalaxyOne\Core\Delivery\ServiceAreaService;
use GalaxyOne\Tests\Support\IntegrationTestCase;
use WP_Error;

final class DeliveryReservationAtomicityTest extends IntegrationTestCase {

	/**
	 * Test schema option value before the test.
	 *
	 * @var mixed
	 */
	private $previous_schema_version;

	/**
	 * Whether the schema option existed before the test.
	 *
	 * @var bool
	 */
	private bool $schema_option_existed = false;

	/**
	 * Unique delivery date.
	 *
	 * @var string
	 */
	private string $delivery_date;

	/**
	 * Unique normalized delivery slot key.
	 *
	 * @var string
	 */
	private string $slot_key;

	/**
	 * Unique postcode.
	 *
	 * @var string
	 */
	private string $postcode;

	/**
	 * Runs before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->schema_option_existed   = false !== get_option( 'galaxyone_core_schema_version', false );
		$this->previous_schema_version = get_option( 'galaxyone_core_schema_version', false );
		$this->delivery_date           = gmdate( 'Y-m-d', time() + DAY_IN_SECONDS );
		$this->slot_key                = sanitize_title(
			'atomicity-' . wp_generate_password( 10, false, false )
		);
		$this->postcode                = 'AT' . wp_rand( 100000, 999999 );

		update_option( 'galaxyone_core_schema_version', '0.9.0', false );
		$this->create_slot();
	}

	/**
	 * Runs after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->cleanup_fixtures();

		if ( $this->schema_option_existed ) {
			update_option(
				'galaxyone_core_schema_version',
				(string) $this->previous_schema_version,
				false
			);
		} else {
			delete_option( 'galaxyone_core_schema_version' );
		}

		parent::tearDown();
	}

	/**
	 * Verifies creation persists the capacity claim and active reservation together.
	 *
	 * @return void
	 */
	public function test_atomic_creation_persists_matching_capacity_and_reservation(): void {
		$this->create_capacity( 1 );

		$reservation = DeliveryReservationService::create(
			$this->delivery_date,
			$this->slot_key,
			1,
			0,
			wp_generate_uuid4()
		);

		self::assertIsArray( $reservation );
		self::assertSame( 1, $this->get_capacity()['reserved_count'] );
		self::assertSame( 1, $this->get_reservation_count( 'active' ) );
		self::assertSame( 1, $this->get_derived_reserved_quantity() );
	}

	/**
	 * Verifies DeliveryValidationService uses the production reservation path.
	 *
	 * @return void
	 */
	public function test_validation_service_creates_reservation_using_server_operation_key(): void {
		$this->create_capacity( 1 );

		self::assertTrue(
			ServiceAreaService::save_service_area(
				$this->postcode,
				'Atomicity Test Area',
				'0'
			)
		);

		$result = DeliveryValidationService::reserve(
			array(
				'postcode' => $this->postcode,
			),
			$this->delivery_date,
			$this->slot_key,
			1,
			0,
			wp_generate_uuid4()
		);

		self::assertNotInstanceOf( WP_Error::class, $result );
		self::assertIsArray( $result );
		self::assertArrayHasKey( 'reservation', $result );
		self::assertSame( 1, $this->get_capacity()['reserved_count'] );
		self::assertSame( 1, $this->get_reservation_count( 'active' ) );
	}

	/**
	 * Verifies an insert failure rolls back the preceding capacity claim.
	 *
	 * @return void
	 */
	public function test_reservation_insert_failure_rolls_back_capacity_claim(): void {
		global $wpdb;

		$this->create_capacity( 1 );

		$trigger_name       = 'g1_res_insert_' . wp_rand( 100000, 999999 );
		$reservations_table = $wpdb->prefix . 'galaxy_delivery_reservations';

		try {
			$this->create_failure_trigger(
				$trigger_name,
				"BEFORE INSERT ON {$reservations_table}
				FOR EACH ROW
				SIGNAL SQLSTATE '45000'
				SET MESSAGE_TEXT = 'test reservation insert failure'"
			);

			self::assertNull(
				DeliveryReservationService::create(
					$this->delivery_date,
					$this->slot_key,
					1,
					0,
					wp_generate_uuid4()
				)
			);
			self::assertSame( 0, $this->get_capacity()['reserved_count'] );
			self::assertSame( 0, $this->get_reservation_count() );
		} finally {
			$wpdb->query( "DROP TRIGGER IF EXISTS {$trigger_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	/**
	 * Verifies a capacity-write failure creates no reservation.
	 *
	 * @return void
	 */
	public function test_capacity_update_failure_creates_no_reservation(): void {
		global $wpdb;

		$this->create_capacity( 1 );

		$trigger_name   = 'g1_cap_update_' . wp_rand( 100000, 999999 );
		$capacity_table = $wpdb->prefix . 'galaxy_delivery_capacities';

		try {
			$this->create_failure_trigger(
				$trigger_name,
				"BEFORE UPDATE ON {$capacity_table}
				FOR EACH ROW
				SIGNAL SQLSTATE '45000'
				SET MESSAGE_TEXT = 'test capacity update failure'"
			);

			self::assertNull(
				DeliveryReservationService::create(
					$this->delivery_date,
					$this->slot_key,
					1,
					0,
					wp_generate_uuid4()
				)
			);
			self::assertSame( 0, $this->get_capacity()['reserved_count'] );
			self::assertSame( 0, $this->get_reservation_count() );
		} finally {
			$wpdb->query( "DROP TRIGGER IF EXISTS {$trigger_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	/**
	 * Verifies matching idempotency retries reuse the existing active reservation.
	 *
	 * @return void
	 */
	public function test_matching_idempotency_key_reuses_existing_reservation(): void {
		$this->create_capacity( 1 );

		$key   = wp_generate_uuid4();
		$first = DeliveryReservationService::create(
			$this->delivery_date,
			$this->slot_key,
			1,
			0,
			$key
		);
		$retry = DeliveryReservationService::create(
			$this->delivery_date,
			$this->slot_key,
			1,
			0,
			$key
		);

		self::assertIsArray( $first );
		self::assertSame( $first, $retry );
		self::assertSame( 1, $this->get_reservation_count( 'active' ) );
		self::assertSame( 1, $this->get_capacity()['reserved_count'] );
	}

	/**
	 * Verifies conflicting and terminal idempotency reuse fails safely.
	 *
	 * @return void
	 */
	public function test_conflicting_confirmed_and_terminal_idempotency_reuse_fail_safely(): void {
		$this->create_capacity( 2 );

		$confirmed_key = wp_generate_uuid4();
		$confirmed     = DeliveryReservationService::create(
			$this->delivery_date,
			$this->slot_key,
			1,
			0,
			$confirmed_key
		);

		self::assertIsArray( $confirmed );
		self::assertNull(
			DeliveryReservationService::create(
				$this->delivery_date,
				$this->slot_key,
				2,
				0,
				$confirmed_key
			)
		);
		self::assertTrue(
			DeliveryReservationService::assign_to_order(
				(string) $confirmed['token'],
				101
			)
		);
		self::assertNull(
			DeliveryReservationService::create(
				$this->delivery_date,
				$this->slot_key,
				1,
				0,
				$confirmed_key
			)
		);

		$terminal_key = wp_generate_uuid4();
		$terminal     = DeliveryReservationService::create(
			$this->delivery_date,
			$this->slot_key,
			1,
			0,
			$terminal_key
		);

		self::assertIsArray( $terminal );
		self::assertTrue( DeliveryReservationService::release( (string) $terminal['token'] ) );
		self::assertNull(
			DeliveryReservationService::create(
				$this->delivery_date,
				$this->slot_key,
				1,
				0,
				$terminal_key
			)
		);
		self::assertSame( 1, $this->get_reservation_count( 'confirmed' ) );
		self::assertSame( 1, $this->get_reservation_count( 'released' ) );
		self::assertSame( 1, $this->get_capacity()['reserved_count'] );
	}

	/**
	 * Verifies release is exact, idempotent, and cannot underflow capacity.
	 *
	 * @return void
	 */
	public function test_release_decrements_once_and_never_makes_reserved_count_negative(): void {
		$this->create_capacity( 1 );

		$reservation = DeliveryReservationService::create(
			$this->delivery_date,
			$this->slot_key,
			1,
			0,
			wp_generate_uuid4()
		);

		self::assertIsArray( $reservation );
		self::assertTrue( DeliveryReservationService::release( (string) $reservation['token'] ) );
		self::assertFalse( DeliveryReservationService::release( (string) $reservation['token'] ) );
		self::assertSame( 0, $this->get_capacity()['reserved_count'] );
		self::assertSame( 1, $this->get_reservation_count( 'released' ) );
		self::assertSame( 0, $this->get_derived_reserved_quantity() );
	}

	/**
	 * Verifies expiry decrements capacity once and cannot overwrite confirmation.
	 *
	 * @return void
	 */
	public function test_expiry_is_idempotent_and_cannot_overwrite_confirmation(): void {
		global $wpdb;

		$this->create_capacity( 2 );

		$expired = DeliveryReservationService::create(
			$this->delivery_date,
			$this->slot_key,
			1,
			0,
			wp_generate_uuid4()
		);
		$confirmed = DeliveryReservationService::create(
			$this->delivery_date,
			$this->slot_key,
			1,
			0,
			wp_generate_uuid4()
		);

		self::assertIsArray( $expired );
		self::assertIsArray( $confirmed );
		self::assertTrue(
			DeliveryReservationService::assign_to_order(
				(string) $confirmed['token'],
				102
			)
		);

		$reservations_table = $wpdb->prefix . 'galaxy_delivery_reservations';
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$reservations_table}
				SET expires_at = %s
				WHERE reservation_token = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				gmdate( 'Y-m-d H:i:s', time() - MINUTE_IN_SECONDS ),
				(string) $expired['token']
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		self::assertSame( 1, DeliveryReservationService::release_expired() );
		self::assertSame( 0, DeliveryReservationService::release_expired() );
		self::assertSame( 'expired', $this->get_reservation_status( (string) $expired['token'] ) );
		self::assertSame( 'confirmed', $this->get_reservation_status( (string) $confirmed['token'] ) );
		self::assertSame( 1, $this->get_capacity()['reserved_count'] );
		self::assertSame( 1, $this->get_derived_reserved_quantity() );
	}

	/**
	 * Verifies configured capacity cannot be reduced below current reservations.
	 *
	 * @return void
	 */
	public function test_capacity_configuration_cannot_fall_below_reserved_usage(): void {
		$this->create_capacity( 2 );

		$reservation = DeliveryReservationService::create(
			$this->delivery_date,
			$this->slot_key,
			1,
			0,
			wp_generate_uuid4()
		);

		self::assertIsArray( $reservation );
		self::assertFalse(
			DeliveryCapacityService::save_capacity(
				$this->delivery_date,
				$this->slot_key,
				0
			)
		);
		self::assertSame( 2, $this->get_capacity()['capacity'] );
		self::assertSame( 1, $this->get_capacity()['reserved_count'] );
	}

	/**
	 * Verifies capacity state matches active and confirmed reservation quantities.
	 *
	 * @return void
	 */
	public function test_reserved_count_matches_active_and_confirmed_reservations(): void {
		$this->create_capacity( 3 );

		$active = DeliveryReservationService::create(
			$this->delivery_date,
			$this->slot_key,
			1,
			0,
			wp_generate_uuid4()
		);
		$confirmed = DeliveryReservationService::create(
			$this->delivery_date,
			$this->slot_key,
			1,
			0,
			wp_generate_uuid4()
		);

		self::assertIsArray( $active );
		self::assertIsArray( $confirmed );
		self::assertTrue(
			DeliveryReservationService::assign_to_order(
				(string) $confirmed['token'],
				103
			)
		);
		self::assertSame(
			$this->get_derived_reserved_quantity(),
			$this->get_capacity()['reserved_count']
		);
	}

	/**
	 * Verifies independent creates with distinct keys cannot both claim final capacity.
	 *
	 * @return void
	 */
	public function test_concurrent_distinct_keys_cannot_overbook_final_capacity(): void {
		$this->create_capacity( 1 );

		$results = $this->run_workers(
			array(
				$this->create_worker_payload( wp_generate_uuid4(), 'create-one' ),
				$this->create_worker_payload( wp_generate_uuid4(), 'create-two' ),
			)
		);

		$successful = array_filter(
			$results,
			static fn( array $result ): bool => true === $result['result']['success']
		);

		self::assertCount( 1, $successful );
		self::assertSame( 1, $this->get_reservation_count( 'active' ) );
		self::assertSame( 1, $this->get_capacity()['reserved_count'] );
	}

	/**
	 * Verifies independent retries with one key persist exactly one reservation.
	 *
	 * @return void
	 */
	public function test_concurrent_same_key_reuses_one_reservation(): void {
		$this->create_capacity( 1 );

		$key     = wp_generate_uuid4();
		$results = $this->run_workers(
			array(
				$this->create_worker_payload( $key, 'same-one' ),
				$this->create_worker_payload( $key, 'same-two' ),
			)
		);

		self::assertCount( 2, $results );
		self::assertTrue( $results[0]['result']['success'] );
		self::assertTrue( $results[1]['result']['success'] );
		self::assertSame(
			$results[0]['result']['reservation']['token'],
			$results[1]['result']['reservation']['token']
		);
		self::assertSame( 1, $this->get_reservation_count( 'active' ) );
		self::assertSame( 1, $this->get_capacity()['reserved_count'] );
	}

	/**
	 * Verifies concurrent releases decrement capacity exactly once.
	 *
	 * @return void
	 */
	public function test_concurrent_release_decrements_capacity_once(): void {
		$this->create_capacity( 1 );

		$reservation = DeliveryReservationService::create(
			$this->delivery_date,
			$this->slot_key,
			1,
			0,
			wp_generate_uuid4()
		);

		self::assertIsArray( $reservation );

		$results = $this->run_workers(
			array(
				array(
					'action'            => 'release',
					'worker_id'         => 'release-one',
					'reservation_token' => (string) $reservation['token'],
				),
				array(
					'action'            => 'release',
					'worker_id'         => 'release-two',
					'reservation_token' => (string) $reservation['token'],
				),
			)
		);

		$successful = array_filter(
			$results,
			static fn( array $result ): bool => true === $result['result']['success']
		);

		self::assertCount( 1, $successful );
		self::assertSame( 0, $this->get_capacity()['reserved_count'] );
		self::assertSame( 1, $this->get_reservation_count( 'released' ) );
	}

	/**
	 * Verifies concurrent confirmation and expiry leave one coherent terminal state.
	 *
	 * @return void
	 */
	public function test_concurrent_confirmation_and_expiry_do_not_conflict(): void {
		global $wpdb;

		$this->create_capacity( 1 );

		$reservation = DeliveryReservationService::create(
			$this->delivery_date,
			$this->slot_key,
			1,
			0,
			wp_generate_uuid4()
		);

		self::assertIsArray( $reservation );

		$reservations_table = $wpdb->prefix . 'galaxy_delivery_reservations';
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$reservations_table}
				SET expires_at = %s
				WHERE reservation_token = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				gmdate( 'Y-m-d H:i:s', time() - MINUTE_IN_SECONDS ),
				(string) $reservation['token']
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$this->run_workers(
			array(
				array(
					'action'            => 'confirm',
					'worker_id'         => 'confirm',
					'reservation_token' => (string) $reservation['token'],
					'order_id'          => 104,
				),
				array(
					'action'    => 'expire',
					'worker_id' => 'expire',
				),
			)
		);

		self::assertSame( 'expired', $this->get_reservation_status( (string) $reservation['token'] ) );
		self::assertSame( 0, $this->get_capacity()['reserved_count'] );
		self::assertSame( 0, $this->get_derived_reserved_quantity() );
	}

	/**
	 * Creates an active every-day delivery slot.
	 *
	 * @return void
	 */
	private function create_slot(): void {
		self::assertTrue(
			DeliverySlotService::save_slot(
				$this->slot_key,
				'Atomicity Slot',
				-1,
				'00:00',
				'23:59',
				''
			)
		);
	}

	/**
	 * Creates a capacity configuration through the production service.
	 *
	 * @param int $capacity Maximum delivery capacity.
	 * @return void
	 */
	private function create_capacity( int $capacity ): void {
		self::assertTrue(
			DeliveryCapacityService::save_capacity(
				$this->delivery_date,
				$this->slot_key,
				$capacity
			)
		);
	}

	/**
	 * Creates a deterministic test-only database trigger.
	 *
	 * @param string $trigger_name Trigger name.
	 * @param string $definition   Trigger definition after the trigger name.
	 * @return void
	 */
	private function create_failure_trigger( string $trigger_name, string $definition ): void {
		global $wpdb;

		$created = $wpdb->query(
			"CREATE TRIGGER {$trigger_name} {$definition}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		self::assertSame( 0, $created );
	}

	/**
	 * Returns the configured capacity row.
	 *
	 * @return array<string, int>
	 */
	private function get_capacity(): array {
		$capacity = DeliveryCapacityService::get_capacity(
			$this->delivery_date,
			$this->slot_key
		);

		self::assertIsArray( $capacity );

		return $capacity;
	}

	/**
	 * Returns the count of reservations in an optional lifecycle state.
	 *
	 * @param string $status Optional reservation state.
	 * @return int
	 */
	private function get_reservation_count( string $status = '' ): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'galaxy_delivery_reservations';

		if ( '' === $status ) {
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

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$table_name}
				WHERE delivery_date = %s
					AND slot_key = %s
					AND status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->delivery_date,
				$this->slot_key,
				$status
			)
		);
	}

	/**
	 * Returns the derived quantity of active and confirmed reservations.
	 *
	 * @return int
	 */
	private function get_derived_reserved_quantity(): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'galaxy_delivery_reservations';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(quantity), 0)
				FROM {$table_name}
				WHERE delivery_date = %s
					AND slot_key = %s
					AND status IN (%s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->delivery_date,
				$this->slot_key,
				'active',
				'confirmed'
			)
		);
	}

	/**
	 * Returns a reservation lifecycle status.
	 *
	 * @param string $reservation_token Reservation token.
	 * @return string
	 */
	private function get_reservation_status( string $reservation_token ): string {
		global $wpdb;

		$table_name = $wpdb->prefix . 'galaxy_delivery_reservations';

		return (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status
				FROM {$table_name}
				WHERE reservation_token = %s
				LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$reservation_token
			)
		);
	}

	/**
	 * Creates a worker create-operation payload.
	 *
	 * @param string $idempotency_key Stable operation key.
	 * @param string $worker_id      Worker identifier.
	 * @return array<string, mixed>
	 */
	private function create_worker_payload( string $idempotency_key, string $worker_id ): array {
		return array(
			'action'          => 'create',
			'worker_id'       => $worker_id,
			'delivery_date'   => $this->delivery_date,
			'slot_key'        => $this->slot_key,
			'quantity'        => 1,
			'idempotency_key' => $idempotency_key,
		);
	}

	/**
	 * Runs independent worker processes from one coordinated start barrier.
	 *
	 * @param array<int, array<string, mixed>> $payloads Worker payloads.
	 * @return array<int, array<string, mixed>>
	 */
	private function run_workers( array $payloads ): array {
		$worker_file = __DIR__ . '/Support/DeliveryReservationConcurrencyWorker.php';
		$directory   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'galaxyone-workers-' . wp_generate_uuid4();
		$workers     = array();

		self::assertTrue( mkdir( $directory, 0700 ) );

		try {
			foreach ( $payloads as $payload ) {
				$payload['barrier_directory'] = $directory;
				$encoded_payload               = base64_encode( (string) wp_json_encode( $payload ) );
				$descriptor_spec               = array(
					0 => array( 'pipe', 'r' ),
					1 => array( 'pipe', 'w' ),
					2 => array( 'pipe', 'w' ),
				);
				$pipes                         = array();
				$process                       = proc_open(
					array( PHP_BINARY, $worker_file, $encoded_payload ),
					$descriptor_spec,
					$pipes,
					null,
					null,
					array(
						'bypass_shell' => true,
					)
				);

				self::assertIsResource( $process );

				fclose( $pipes[0] );
				stream_set_blocking( $pipes[1], false );
				stream_set_blocking( $pipes[2], false );

				$workers[] = array(
					'process' => $process,
					'stdout'  => $pipes[1],
					'stderr'  => $pipes[2],
					'closed'  => false,
				);
			}

			$deadline = microtime( true ) + 20;

			while ( microtime( true ) < $deadline ) {
				$all_ready = true;

				foreach ( $payloads as $payload ) {
					if (
						! file_exists(
							$directory . DIRECTORY_SEPARATOR . (string) $payload['worker_id'] . '.ready'
						)
					) {
						$all_ready = false;
						break;
					}
				}

				if ( $all_ready ) {
					break;
				}

				usleep( 10000 );
			}

			foreach ( $payloads as $payload ) {
				self::assertFileExists(
					$directory . DIRECTORY_SEPARATOR . (string) $payload['worker_id'] . '.ready'
				);
			}

			self::assertNotFalse(
				file_put_contents(
					$directory . DIRECTORY_SEPARATOR . 'start',
					'start'
				)
			);

			$deadline = microtime( true ) + 30;

			while ( microtime( true ) < $deadline ) {
				$running = false;

				foreach ( $workers as $worker ) {
					if (
						! $worker['closed'] &&
						is_resource( $worker['process'] ) &&
						true === proc_get_status( $worker['process'] )['running']
					) {
						$running = true;
						break;
					}
				}

				if ( ! $running ) {
					break;
				}

				usleep( 10000 );
			}

			$results = array();

			foreach ( $workers as $index => $worker ) {
				$results[] = $this->collect_worker_result( $workers[ $index ] );
			}

			return $results;
		} finally {
			foreach ( $workers as $index => $worker ) {
				$this->finalize_worker( $workers[ $index ], true );
			}

			$files = glob( $directory . DIRECTORY_SEPARATOR . '*' );

			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					if ( is_file( $file ) ) {
						unlink( $file );
					}
				}
			}

			if ( is_dir( $directory ) ) {
				rmdir( $directory );
			}
		}
	}

	/**
	 * Collects one completed worker result and closes its resources.
	 *
	 * @param array<string, mixed> $worker Worker process state.
	 * @return array<string, mixed>
	 */
	private function collect_worker_result( array &$worker ): array {
		if (
			! isset( $worker['process'] ) ||
			! is_resource( $worker['process'] ) ||
			true === $worker['closed']
		) {
			self::fail( 'A delivery concurrency worker was unavailable.' );
		}

		$status = proc_get_status( $worker['process'] );

		if ( true === $status['running'] ) {
			$this->finalize_worker( $worker, true );
			self::fail( 'A delivery concurrency worker exceeded the test timeout.' );
		}

		$stdout = is_resource( $worker['stdout'] ) ? stream_get_contents( $worker['stdout'] ) : false;
		$stderr = is_resource( $worker['stderr'] ) ? stream_get_contents( $worker['stderr'] ) : false;

		$exit_code = $this->finalize_worker( $worker, false );
		$result    = is_string( $stdout ) ? json_decode( $stdout, true ) : null;

		self::assertIsArray( $result, is_string( $stderr ) ? $stderr : '' );

		return array(
			'exit_code' => $exit_code,
			'result'    => $result,
		);
	}

	/**
	 * Terminates a worker when needed and finalizes every resource once.
	 *
	 * @param array<string, mixed> $worker    Worker process state.
	 * @param bool                 $terminate Whether a running worker must be terminated.
	 * @return int
	 */
	private function finalize_worker( array &$worker, bool $terminate ): int {
		if ( true === $worker['closed'] ) {
			return -1;
		}

		$exit_code = -1;

		if ( isset( $worker['process'] ) && is_resource( $worker['process'] ) ) {
			$status = proc_get_status( $worker['process'] );

			if ( $terminate && true === $status['running'] ) {
				proc_terminate( $worker['process'] );
			}
		}

		foreach ( array( 'stdout', 'stderr' ) as $pipe_name ) {
			if ( isset( $worker[ $pipe_name ] ) && is_resource( $worker[ $pipe_name ] ) ) {
				fclose( $worker[ $pipe_name ] );
			}
		}

		if ( isset( $worker['process'] ) && is_resource( $worker['process'] ) ) {
			$exit_code = proc_close( $worker['process'] );
		}

		$worker['closed'] = true;

		return $exit_code;
	}

	/**
	 * Removes test-owned custom-table fixtures.
	 *
	 * @return void
	 */
	private function cleanup_fixtures(): void {
		global $wpdb;

		$rules_table        = $wpdb->prefix . 'galaxy_delivery_rules';
		$capacity_table     = $wpdb->prefix . 'galaxy_delivery_capacities';
		$reservations_table = $wpdb->prefix . 'galaxy_delivery_reservations';

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

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$rules_table}
				WHERE rule_key IN (%s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->slot_key,
				$this->postcode
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
