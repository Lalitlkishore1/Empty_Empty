<?php
/**
 * Delivery-slot availability integration tests.
 *
 * @package GalaxyOne\Tests\Integration
 */

declare(strict_types=1);

namespace GalaxyOne\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use GalaxyOne\Core\Checkout\CheckoutModule;
use GalaxyOne\Core\Database\Migrations\CreateDeliveryCapacityTable;
use GalaxyOne\Core\Database\Migrations\CreateDeliveryReservationsTable;
use GalaxyOne\Core\Database\Migrations\CreateDeliveryRulesTable;
use GalaxyOne\Core\Database\Migrations\CreateSupportedStreetsTable;
use GalaxyOne\Core\Delivery\DeliveryCapacityService;
use GalaxyOne\Core\Delivery\DeliveryReservationService;
use GalaxyOne\Core\Delivery\DeliverySlotService;
use GalaxyOne\Core\Delivery\DeliveryValidationService;
use GalaxyOne\Core\Delivery\ServiceAreaService;
use GalaxyOne\Core\Delivery\SupportedStreetRepository;
use GalaxyOne\Tests\Support\IntegrationTestCase;
use ReflectionMethod;
use WP_Error;

final class DeliverySlotAvailabilityTest extends IntegrationTestCase {

	private string $slot_key;

	private string $delivery_date = '2026-08-17';

	private string $future_delivery_date = '2026-08-18';

	private string $postcode;

	private string $street_name;

	private string $locality = 'Delivery Slot Availability Locality';

	private int $street_id = 0;

	private ?DateTimeImmutable $current_datetime = null;

	private $previous_schema_version;

	private bool $schema_option_existed = false;

	public function setUp(): void {
		parent::setUp();

		global $wpdb;

		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( 'SET autocommit = 1' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		$this->schema_option_existed   = false !== get_option( 'galaxyone_core_schema_version', false );
		$this->previous_schema_version = get_option( 'galaxyone_core_schema_version', false );
		$this->slot_key = sanitize_title(
			'delivery-slot-availability-' . wp_generate_password( 10, false, false )
		);
		$this->postcode = 'DS' . wp_rand( 100000, 999999 );
		$this->street_name = 'Delivery Slot Availability Street ' . $this->slot_key;

		add_filter( 'pre_option_timezone_string', array( $this, 'use_test_timezone' ) );
		add_filter( 'galaxyone_delivery_current_datetime', array( $this, 'use_test_datetime' ) );

		update_option( 'galaxyone_core_schema_version', '0.9.0', false );

		self::assertTrue( CreateSupportedStreetsTable::up() );

		self::assertTrue(
			DeliverySlotService::save_slot(
				$this->slot_key,
				'Delivery Slot Availability',
				-1,
				'10:00',
				'12:00',
				''
			)
		);
		self::assertTrue(
			DeliveryCapacityService::save_capacity(
				$this->delivery_date,
				$this->slot_key,
				2
			)
		);
		self::assertTrue(
			DeliveryCapacityService::save_capacity(
				$this->future_delivery_date,
				$this->slot_key,
				2
			)
		);
		self::assertTrue(
			ServiceAreaService::save_service_area(
				$this->postcode,
				'Delivery Slot Availability Area',
				'25'
			)
		);

		$street_id = SupportedStreetRepository::create(
			$this->street_name,
			$this->locality,
			$this->postcode
		);

		if ( is_int( $street_id ) && $street_id > 0 ) {
			$this->street_id = $street_id;
		}

		self::assertIsInt( $street_id );
		self::assertGreaterThan( 0, $street_id );
	}

	public function tearDown(): void {
		global $wpdb;

		remove_filter( 'pre_option_timezone_string', array( $this, 'use_test_timezone' ) );
		remove_filter( 'galaxyone_delivery_current_datetime', array( $this, 'use_test_datetime' ) );

		$this->release_reservations();
		$wpdb->delete(
			CreateDeliveryReservationsTable::get_table_name(),
			array( 'slot_key' => $this->slot_key ),
			array( '%s' )
		);
		$wpdb->delete(
			CreateDeliveryCapacityTable::get_table_name(),
			array( 'slot_key' => $this->slot_key ),
			array( '%s' )
		);
		$wpdb->delete(
			CreateDeliveryRulesTable::get_table_name(),
			array( 'rule_key' => $this->slot_key ),
			array( '%s' )
		);
		$wpdb->delete(
			CreateDeliveryRulesTable::get_table_name(),
			array(
				'rule_type'    => 'service_area',
				'service_area' => $this->postcode,
			),
			array( '%s', '%s' )
		);

		if ( $this->street_id > 0 ) {
			$wpdb->delete(
				CreateSupportedStreetsTable::get_table_name(),
				array( 'id' => $this->street_id ),
				array( '%d' )
			);
		}

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

	public function test_ended_same_day_slot_is_not_available(): void {
		$this->set_current_datetime( '2026-08-17 12:23:00' );

		self::assertFalse(
			DeliverySlotService::is_slot_available( '2026-08-17', $this->slot_key )
		);
		self::assertSame(
			array(),
			DeliverySlotService::get_available_slots( $this->delivery_date )
		);
	}

	public function test_ended_same_day_slot_is_rejected_by_delivery_validation(): void {
		$this->set_current_datetime( '2026-08-17 12:23:00' );

		$validation = DeliveryValidationService::validate(
			$this->get_address(),
			$this->delivery_date,
			$this->slot_key
		);

		self::assertInstanceOf( WP_Error::class, $validation );
		self::assertSame( 'galaxyone_delivery_slot_unavailable', $validation->get_error_code() );
	}

	public function test_same_day_slot_is_available_before_its_end_without_a_cutoff(): void {
		$this->set_current_datetime( '2026-08-17 11:30:00' );

		self::assertTrue(
			DeliverySlotService::is_slot_available( $this->delivery_date, $this->slot_key )
		);
	}

	public function test_same_day_slot_is_unavailable_at_its_exact_end(): void {
		$this->set_current_datetime( '2026-08-17 12:00:00' );

		self::assertFalse(
			DeliverySlotService::is_slot_available( $this->delivery_date, $this->slot_key )
		);
	}

	public function test_future_date_reuses_the_same_recurring_slot(): void {
		$this->set_current_datetime( '2026-08-17 12:23:00' );

		self::assertTrue(
			DeliverySlotService::is_slot_available( $this->future_delivery_date, $this->slot_key )
		);
		self::assertContains( $this->slot_key, $this->get_available_slot_keys( $this->future_delivery_date ) );
	}

	public function test_missing_capacity_is_not_offered_or_exposed_by_checkout_options(): void {
		$this->set_current_datetime( '2026-08-17 12:23:00' );
		$this->delete_capacity( $this->future_delivery_date );

		$capacity_row_count = $this->get_capacity_row_count( $this->future_delivery_date );
		$reservation_count  = $this->get_reservation_count( $this->future_delivery_date );

		self::assertFalse(
			DeliveryCapacityService::has_capacity(
				$this->future_delivery_date,
				$this->slot_key
			)
		);
		self::assertNotContains( $this->slot_key, $this->get_available_slot_keys( $this->future_delivery_date ) );
		self::assertNotContains( $this->slot_key, $this->get_checkout_slot_keys( $this->future_delivery_date ) );

		$validation = DeliveryValidationService::validate(
			$this->get_address(),
			$this->future_delivery_date,
			$this->slot_key
		);

		self::assertInstanceOf( WP_Error::class, $validation );
		self::assertSame( 'galaxyone_delivery_capacity_full', $validation->get_error_code() );
		self::assertSame( $capacity_row_count, $this->get_capacity_row_count( $this->future_delivery_date ) );
		self::assertSame( $reservation_count, $this->get_reservation_count( $this->future_delivery_date ) );
	}

	public function test_full_capacity_is_not_offered_or_exposed_by_checkout_options(): void {
		$this->set_current_datetime( '2026-08-17 12:23:00' );
		$this->set_capacity( $this->future_delivery_date, 5 );
		$this->set_reserved_count( $this->future_delivery_date, 5 );

		self::assertFalse(
			DeliveryCapacityService::has_capacity(
				$this->future_delivery_date,
				$this->slot_key
			)
		);
		self::assertNotContains( $this->slot_key, $this->get_available_slot_keys( $this->future_delivery_date ) );
		self::assertNotContains( $this->slot_key, $this->get_checkout_slot_keys( $this->future_delivery_date ) );
	}

	public function test_usable_capacity_is_offered_without_availability_mutation(): void {
		$this->set_current_datetime( '2026-08-17 12:23:00' );
		$this->set_capacity( $this->future_delivery_date, 5 );

		$initial_capacity    = $this->get_capacity( $this->future_delivery_date );
		$initial_reservations = $this->get_reservation_count( $this->future_delivery_date );

		self::assertTrue(
			DeliveryCapacityService::has_capacity(
				$this->future_delivery_date,
				$this->slot_key
			)
		);
		self::assertContains( $this->slot_key, $this->get_available_slot_keys( $this->future_delivery_date ) );
		self::assertContains( $this->slot_key, $this->get_checkout_slot_keys( $this->future_delivery_date ) );

		$validation = DeliveryValidationService::validate(
			$this->get_address(),
			$this->future_delivery_date,
			$this->slot_key
		);

		self::assertNotInstanceOf( WP_Error::class, $validation );
		self::assertSame( $initial_capacity, $this->get_capacity( $this->future_delivery_date ) );
		self::assertSame( $initial_reservations, $this->get_reservation_count( $this->future_delivery_date ) );

		$this->set_reserved_count( $this->future_delivery_date, 4 );

		self::assertTrue(
			DeliveryCapacityService::has_capacity(
				$this->future_delivery_date,
				$this->slot_key
			)
		);
		self::assertContains( $this->slot_key, $this->get_available_slot_keys( $this->future_delivery_date ) );
		self::assertContains( $this->slot_key, $this->get_checkout_slot_keys( $this->future_delivery_date ) );
	}

	public function test_capacity_aware_availability_does_not_weaken_cutoff_rules(): void {
		$this->set_current_datetime( '2026-08-17 11:30:00' );

		self::assertTrue(
			DeliverySlotService::save_slot(
				$this->slot_key,
				'Delivery Slot Availability',
				-1,
				'10:00',
				'12:00',
				'11:00'
			)
		);
		self::assertNotContains( $this->slot_key, $this->get_available_slot_keys( $this->delivery_date ) );
	}

	public function test_availability_snapshot_does_not_replace_atomic_reservation_protection(): void {
		$this->set_current_datetime( '2026-08-17 12:23:00' );
		$this->set_capacity( $this->future_delivery_date, 5 );
		$this->set_reserved_count( $this->future_delivery_date, 4 );

		self::assertContains( $this->slot_key, $this->get_available_slot_keys( $this->future_delivery_date ) );
		self::assertContains( $this->slot_key, $this->get_checkout_slot_keys( $this->future_delivery_date ) );

		$this->create_reservation( $this->future_delivery_date );

		$failed_reservation = DeliveryValidationService::reserve(
			$this->get_address(),
			$this->future_delivery_date,
			$this->slot_key,
			1,
			0,
			wp_generate_uuid4()
		);

		self::assertInstanceOf( WP_Error::class, $failed_reservation );
		self::assertSame( 'galaxyone_delivery_capacity_changed', $failed_reservation->get_error_code() );
		self::assertSame( 5, $this->get_capacity( $this->future_delivery_date )['reserved_count'] );
		self::assertSame( 1, $this->get_reservation_count( $this->future_delivery_date ) );
		self::assertNotContains( $this->slot_key, $this->get_available_slot_keys( $this->future_delivery_date ) );
		self::assertNotContains( $this->slot_key, $this->get_checkout_slot_keys( $this->future_delivery_date ) );
	}

	public function test_past_delivery_date_remains_unavailable(): void {
		$this->set_current_datetime( '2026-08-17 12:23:00' );

		self::assertFalse(
			DeliverySlotService::is_slot_available( '2026-08-16', $this->slot_key )
		);
	}

	public function test_expired_slot_cannot_reserve_capacity_and_future_slot_can(): void {
		$this->set_current_datetime( '2026-08-17 12:23:00' );

		$expired_reservation = DeliveryValidationService::reserve(
			$this->get_address(),
			$this->delivery_date,
			$this->slot_key,
			1,
			0,
			wp_generate_uuid4()
		);

		self::assertInstanceOf( WP_Error::class, $expired_reservation );
		self::assertSame( 'galaxyone_delivery_slot_unavailable', $expired_reservation->get_error_code() );
		self::assertSame( 0, $this->get_capacity( $this->delivery_date )['reserved_count'] );
		self::assertTrue(
			DeliveryCapacityService::has_capacity(
				$this->future_delivery_date,
				$this->slot_key
			)
		);

		$future_reservation = DeliveryValidationService::reserve(
			$this->get_address(),
			$this->future_delivery_date,
			$this->slot_key,
			1,
			0,
			wp_generate_uuid4()
		);

		self::assertIsArray( $future_reservation );
		self::assertSame( 1, $this->get_capacity( $this->future_delivery_date )['reserved_count'] );
	}

	public function test_same_day_date_remains_visible_when_its_only_slot_has_ended(): void {
		$this->set_current_datetime( '2026-08-17 12:23:00' );

		$method = new ReflectionMethod( CheckoutModule::class, 'get_delivery_options' );
		$options = $method->invoke( new CheckoutModule() );

		self::assertContains( $this->delivery_date, $options['dates'] );
		self::assertArrayHasKey( $this->delivery_date, $options['slots_by_date'] );
		self::assertSame( array(), $options['slots_by_date'][ $this->delivery_date ] );
	}

	public function use_test_timezone(): string {
		return 'Asia/Kolkata';
	}

	public function use_test_datetime( DateTimeImmutable $current_datetime ): DateTimeImmutable {
		return $this->current_datetime ?? $current_datetime;
	}

	private function set_current_datetime( string $datetime ): void {
		$this->current_datetime = new DateTimeImmutable(
			$datetime,
			new DateTimeZone( 'Asia/Kolkata' )
		);
	}

	/**
	 * @return array{address_1: string, city: string, postcode: string}
	 */
	private function get_address(): array {
		return array(
			'address_1' => $this->street_name,
			'city'      => $this->locality,
			'postcode'  => $this->postcode,
		);
	}

	/**
	 * @return array{capacity: int, reserved_count: int}
	 */
	private function get_capacity( string $delivery_date ): array {
		$capacity = DeliveryCapacityService::get_capacity( $delivery_date, $this->slot_key );

		self::assertIsArray( $capacity );

		return $capacity;
	}

	private function set_capacity( string $delivery_date, int $capacity ): void {
		self::assertTrue(
			DeliveryCapacityService::save_capacity(
				$delivery_date,
				$this->slot_key,
				$capacity
			)
		);
	}

	private function delete_capacity( string $delivery_date ): void {
		global $wpdb;

		self::assertSame(
			1,
			$wpdb->delete(
				CreateDeliveryCapacityTable::get_table_name(),
				array(
					'delivery_date' => $delivery_date,
					'slot_key'      => $this->slot_key,
				),
				array( '%s', '%s' )
			)
		);
	}

	private function set_reserved_count( string $delivery_date, int $reserved_count ): void {
		global $wpdb;

		self::assertSame(
			1,
			$wpdb->update(
				CreateDeliveryCapacityTable::get_table_name(),
				array( 'reserved_count' => $reserved_count ),
				array(
					'delivery_date' => $delivery_date,
					'slot_key'      => $this->slot_key,
				),
				array( '%d' ),
				array( '%s', '%s' )
			)
		);
	}

	private function create_reservation( string $delivery_date ): void {
		$reservation = DeliveryReservationService::create(
			$delivery_date,
			$this->slot_key,
			1,
			0,
			wp_generate_uuid4()
		);

		self::assertIsArray( $reservation );
	}

	/**
	 * @return array<int, string>
	 */
	private function get_available_slot_keys( string $delivery_date ): array {
		$slot_keys = array();

		foreach ( DeliverySlotService::get_available_slots( $delivery_date ) as $slot ) {
			if ( is_object( $slot ) && isset( $slot->rule_key ) && is_scalar( $slot->rule_key ) ) {
				$slot_keys[] = (string) $slot->rule_key;
			}
		}

		return $slot_keys;
	}

	/**
	 * @return array<int, string>
	 */
	private function get_checkout_slot_keys( string $delivery_date ): array {
		$method  = new ReflectionMethod( CheckoutModule::class, 'get_delivery_options' );
		$options = $method->invoke( new CheckoutModule() );
		$slots   = isset( $options['slots_by_date'][ $delivery_date ] ) && is_array( $options['slots_by_date'][ $delivery_date ] )
			? $options['slots_by_date'][ $delivery_date ]
			: array();
		$slot_keys = array();

		foreach ( $slots as $slot ) {
			if ( is_object( $slot ) && isset( $slot->rule_key ) && is_scalar( $slot->rule_key ) ) {
				$slot_keys[] = (string) $slot->rule_key;
			}
		}

		return $slot_keys;
	}

	private function get_capacity_row_count( string $delivery_date ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM %i
				WHERE delivery_date = %s
					AND slot_key = %s",
				CreateDeliveryCapacityTable::get_table_name(),
				$delivery_date,
				$this->slot_key
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	private function get_reservation_count( string $delivery_date ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM %i
				WHERE delivery_date = %s
					AND slot_key = %s",
				CreateDeliveryReservationsTable::get_table_name(),
				$delivery_date,
				$this->slot_key
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	private function release_reservations(): void {
		global $wpdb;

		$reservation_tokens = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT reservation_token
				FROM %i
				WHERE slot_key = %s
					AND status IN ( %s, %s )",
				CreateDeliveryReservationsTable::get_table_name(),
				$this->slot_key,
				'active',
				'confirmed'
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		foreach ( $reservation_tokens as $reservation_token ) {
			DeliveryReservationService::release( (string) $reservation_token );
		}
	}
}
