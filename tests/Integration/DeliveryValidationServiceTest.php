<?php
/**
 * Delivery validation service integration tests.
 *
 * @package GalaxyOne\Tests\Integration
 */

declare(strict_types=1);

namespace GalaxyOne\Tests\Integration;

use GalaxyOne\Core\Database\Migrations\CreateSupportedStreetsTable;
use GalaxyOne\Core\Delivery\DeliveryCapacityService;
use GalaxyOne\Core\Delivery\DeliverySlotService;
use GalaxyOne\Core\Delivery\DeliveryValidationService;
use GalaxyOne\Core\Delivery\ServiceAreaService;
use GalaxyOne\Core\Delivery\SupportedStreetRepository;
use GalaxyOne\Tests\Support\IntegrationTestCase;
use WP_Error;

final class DeliveryValidationServiceTest extends IntegrationTestCase {

	/**
	 * @var array<int, int>
	 */
	private array $street_ids = array();

	private $previous_schema_version;

	private bool $schema_option_existed = false;

	private string $delivery_date;

	private string $slot_key;

	private string $postcode;

	private string $alternate_postcode;

	private string $street_name;

	private string $locality;

	public function setUp(): void {
		parent::setUp();

		$this->schema_option_existed = false !== get_option( 'galaxyone_core_schema_version', false );
		$this->previous_schema_version = get_option( 'galaxyone_core_schema_version', false );
		$this->delivery_date = gmdate( 'Y-m-d', time() + DAY_IN_SECONDS );
		$this->slot_key = sanitize_title(
			'delivery-validation-' . wp_generate_password( 10, false, false )
		);
		$this->postcode = 'DV' . wp_rand( 100000, 999999 );
		$this->alternate_postcode = 'DW' . wp_rand( 100000, 999999 );
		$this->street_name = 'Validation Street ' . $this->slot_key;
		$this->locality = 'Validation Locality';

		update_option( 'galaxyone_core_schema_version', '0.9.0', false );

		self::assertTrue( CreateSupportedStreetsTable::up() );
		self::assertTrue(
			DeliverySlotService::save_slot(
				$this->slot_key,
				'Delivery Validation Slot',
				-1,
				'00:00',
				'23:59',
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
			ServiceAreaService::save_service_area(
				$this->postcode,
				'Delivery Validation Area',
				'25'
			)
		);
		self::assertTrue(
			ServiceAreaService::save_service_area(
				$this->alternate_postcode,
				'Alternate Delivery Validation Area',
				'25'
			)
		);

		$street_id = SupportedStreetRepository::create(
			$this->street_name,
			$this->locality,
			$this->postcode
		);

		if ( is_int( $street_id ) && $street_id > 0 ) {
			$this->street_ids[] = $street_id;
		}

		self::assertIsInt( $street_id );
		self::assertGreaterThan( 0, $street_id );
	}

	public function tearDown(): void {
		$this->cleanup_delivery_fixtures();
		$this->delete_test_streets();

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

	public function test_exact_active_supported_street_is_accepted(): void {
		$validation = DeliveryValidationService::validate(
			array(
				'address_1' => $this->street_name,
				'city'      => $this->locality,
				'postcode'  => $this->postcode,
			),
			$this->delivery_date,
			$this->slot_key
		);

		self::assertNotInstanceOf( WP_Error::class, $validation );
		self::assertIsArray( $validation );
		self::assertSame( $this->postcode, $validation['service_area']['postcode'] );
		self::assertSame( '25.0000', $validation['delivery_fee'] );
		self::assertSame( $this->slot_key, $validation['slot']['rule_key'] );
	}

	public function test_unsupported_street_in_valid_postcode_is_rejected(): void {
		$validation = DeliveryValidationService::validate(
			array(
				'address_1' => 'Unsupported Validation Street',
				'city'      => $this->locality,
				'postcode'  => $this->postcode,
			),
			$this->delivery_date,
			$this->slot_key
		);

		self::assertInstanceOf( WP_Error::class, $validation );
		self::assertSame(
			'galaxyone_delivery_unsupported_street',
			$validation->get_error_code()
		);
	}

	public function test_matching_street_in_wrong_locality_is_rejected(): void {
		$validation = DeliveryValidationService::validate(
			array(
				'address_1' => $this->street_name,
				'city'      => 'Different Validation Locality',
				'postcode'  => $this->postcode,
			),
			$this->delivery_date,
			$this->slot_key
		);

		self::assertInstanceOf( WP_Error::class, $validation );
		self::assertSame(
			'galaxyone_delivery_unsupported_street',
			$validation->get_error_code()
		);
	}

	public function test_matching_street_and_locality_in_wrong_service_area_is_rejected(): void {
		$validation = DeliveryValidationService::validate(
			array(
				'address_1' => $this->street_name,
				'city'      => $this->locality,
				'postcode'  => $this->alternate_postcode,
			),
			$this->delivery_date,
			$this->slot_key
		);

		self::assertInstanceOf( WP_Error::class, $validation );
		self::assertSame(
			'galaxyone_delivery_unsupported_street',
			$validation->get_error_code()
		);
	}

	public function test_inactive_supported_street_is_rejected(): void {
		global $wpdb;

		$inactive_street_name = 'Inactive Validation Street ' . $this->slot_key;
		$inactive_street_id   = SupportedStreetRepository::create(
			$inactive_street_name,
			$this->locality,
			$this->postcode
		);

		if ( is_int( $inactive_street_id ) && $inactive_street_id > 0 ) {
			$this->street_ids[] = $inactive_street_id;
		}

		self::assertIsInt( $inactive_street_id );
		self::assertGreaterThan( 0, $inactive_street_id );

		self::assertSame(
			1,
			$wpdb->update(
				CreateSupportedStreetsTable::get_table_name(),
				array( 'is_active' => 0 ),
				array( 'id' => $inactive_street_id ),
				array( '%d' ),
				array( '%d' )
			)
		);

		$validation = DeliveryValidationService::validate(
			array(
				'address_1' => $inactive_street_name,
				'city'      => $this->locality,
				'postcode'  => $this->postcode,
			),
			$this->delivery_date,
			$this->slot_key
		);

		self::assertInstanceOf( WP_Error::class, $validation );
		self::assertSame(
			'galaxyone_delivery_unsupported_street',
			$validation->get_error_code()
		);
	}

	public function test_empty_street_is_rejected(): void {
		$validation = DeliveryValidationService::validate(
			array(
				'address_1' => '',
				'city'      => $this->locality,
				'postcode'  => $this->postcode,
			),
			$this->delivery_date,
			$this->slot_key
		);

		self::assertInstanceOf( WP_Error::class, $validation );
		self::assertSame(
			'galaxyone_delivery_unsupported_street',
			$validation->get_error_code()
		);
	}

	public function test_empty_locality_is_rejected(): void {
		$validation = DeliveryValidationService::validate(
			array(
				'address_1' => $this->street_name,
				'city'      => '',
				'postcode'  => $this->postcode,
			),
			$this->delivery_date,
			$this->slot_key
		);

		self::assertInstanceOf( WP_Error::class, $validation );
		self::assertSame(
			'galaxyone_delivery_unsupported_street',
			$validation->get_error_code()
		);
	}

	public function test_reservation_is_rejected_for_an_unsupported_street(): void {
		$reservation = DeliveryValidationService::reserve(
			array(
				'address_1' => 'Unsupported Reservation Street',
				'city'      => $this->locality,
				'postcode'  => $this->postcode,
			),
			$this->delivery_date,
			$this->slot_key,
			1,
			0,
			wp_generate_uuid4()
		);

		self::assertInstanceOf( WP_Error::class, $reservation );
		self::assertSame(
			'galaxyone_delivery_unsupported_street',
			$reservation->get_error_code()
		);

		$capacity = DeliveryCapacityService::get_capacity(
			$this->delivery_date,
			$this->slot_key
		);

		self::assertIsArray( $capacity );
		self::assertSame( 0, $capacity['reserved_count'] );
	}

	private function cleanup_delivery_fixtures(): void {
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
				WHERE rule_key IN (%s, %s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->slot_key,
				$this->postcode,
				$this->alternate_postcode
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function delete_test_streets(): void {
		global $wpdb;

		if ( array() === $this->street_ids ) {
			return;
		}

		$table_name   = CreateSupportedStreetsTable::get_table_name();
		$placeholders = implode( ', ', array_fill( 0, count( $this->street_ids ), '%d' ) );
		$delete_query = "DELETE FROM {$table_name} WHERE id IN ({$placeholders})"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$prepared_sql = $wpdb->prepare( $delete_query, $this->street_ids );

		$wpdb->query( $prepared_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$this->street_ids = array();
	}
}
