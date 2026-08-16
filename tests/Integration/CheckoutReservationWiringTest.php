<?php
/**
 * Checkout delivery-reservation wiring integration tests.
 *
 * @package GalaxyOne\Tests\Integration
 */

declare(strict_types=1);

namespace GalaxyOne\Tests\Integration;

use GalaxyOne\Core\Cart\CartRecalculationService;
use GalaxyOne\Core\Checkout\CheckoutModule;
use GalaxyOne\Core\Database\Migrations\CreateDeliveryCapacityTable;
use GalaxyOne\Core\Database\Migrations\CreateDeliveryReservationsTable;
use GalaxyOne\Core\Database\Migrations\CreateDeliveryRulesTable;
use GalaxyOne\Core\Database\Migrations\CreateSupportedStreetsTable;
use GalaxyOne\Core\Delivery\DeliveryCapacityService;
use GalaxyOne\Core\Delivery\DeliveryReservationService;
use GalaxyOne\Core\Delivery\DeliverySlotService;
use GalaxyOne\Core\Delivery\ServiceAreaService;
use GalaxyOne\Core\Delivery\SupportedStreetRepository;
use GalaxyOne\Tests\Support\IntegrationTestCase;
use WC_Cart;
use WC_Order;
use WC_Session;
use WP_Error;

final class CheckoutReservationWiringTest extends IntegrationTestCase {

	private CheckoutModule $checkout_module;

	private string $delivery_date;

	private string $replacement_delivery_date;

	private string $slot_key;

	private string $postcode;

	private string $street_name;

	private string $locality;

	private int $street_id = 0;

	/**
	 * @var array<string, mixed>
	 */
	private array $session_values = array();

	/**
	 * @var array<int, int>
	 */
	private array $order_ids = array();

	/**
	 * @var mixed
	 */
	private $previous_cart;

	/**
	 * @var mixed
	 */
	private $previous_session;

	/**
	 * @var array<string, mixed>
	 */
	private array $previous_post;

	public function setUp(): void {
		parent::setUp();

		global $wpdb;

		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( 'SET autocommit = 1' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		$this->delivery_date             = wp_date( 'Y-m-d', time() + DAY_IN_SECONDS );
		$this->replacement_delivery_date = wp_date( 'Y-m-d', time() + ( 2 * DAY_IN_SECONDS ) );
		$this->slot_key                  = sanitize_title(
			'checkout-reservation-' . wp_generate_password( 10, false, false )
		);
		$this->postcode                  = 'CR' . wp_rand( 100000, 999999 );
		$this->street_name               = 'Checkout Reservation Street ' . $this->slot_key;
		$this->locality                  = 'Checkout Reservation Locality';

		self::assertTrue( CreateSupportedStreetsTable::up() );
		self::assertTrue(
			DeliverySlotService::save_slot(
				$this->slot_key,
				'Checkout Reservation Slot',
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
				1
			)
		);
		self::assertTrue(
			ServiceAreaService::save_service_area(
				$this->postcode,
				'Checkout Reservation Area',
				'0'
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

		$this->configure_checkout_runtime();

		$this->checkout_module = new CheckoutModule();
		$this->checkout_module->register();
	}

	public function tearDown(): void {
		$this->remove_checkout_hooks();
		$this->cleanup_fixtures();
		$this->restore_checkout_runtime();

		parent::tearDown();
	}

	public function test_valid_final_checkout_reserves_delivery_capacity_once(): void {
		$errors = $this->run_final_checkout_validation();

		self::assertFalse( $errors->has_errors(), implode( ', ', $errors->get_error_codes() ) );

		$reservation = CartRecalculationService::get_delivery_reservation();

		self::assertArrayHasKey( 'token', $reservation );
		self::assertTrue( wp_is_uuid( (string) $reservation['token'] ) );
		self::assertSame( $this->delivery_date, $reservation['delivery_date'] );
		self::assertSame( $this->slot_key, $reservation['slot_key'] );
		self::assertSame( 1, (int) $reservation['quantity'] );
		self::assertSame( 1, $this->get_active_reservation_count( $this->delivery_date ) );
		self::assertSame( 1, $this->get_capacity( $this->delivery_date )['reserved_count'] );
	}

	public function test_repeated_final_checkout_validation_reuses_the_existing_reservation(): void {
		$first_errors      = $this->run_final_checkout_validation();
		$first_reservation = CartRecalculationService::get_delivery_reservation();
		$second_errors     = $this->run_final_checkout_validation();
		$second_reservation = CartRecalculationService::get_delivery_reservation();

		self::assertFalse( $first_errors->has_errors(), implode( ', ', $first_errors->get_error_codes() ) );
		self::assertFalse( $second_errors->has_errors(), implode( ', ', $second_errors->get_error_codes() ) );
		self::assertSame( $first_reservation['token'], $second_reservation['token'] );
		self::assertSame( 1, $this->get_active_reservation_count( $this->delivery_date ) );
		self::assertSame( 1, $this->get_capacity( $this->delivery_date )['reserved_count'] );
	}

	public function test_full_capacity_blocks_final_checkout_without_creating_another_reservation(): void {
		$existing_reservation = DeliveryReservationService::create(
			$this->delivery_date,
			$this->slot_key,
			1,
			0,
			wp_generate_uuid4()
		);

		self::assertIsArray( $existing_reservation );
		self::assertSame( 1, $this->get_capacity( $this->delivery_date )['reserved_count'] );

		$errors = $this->run_final_checkout_validation();

		self::assertTrue( $errors->has_errors() );
		self::assertSame( 'galaxyone_delivery_capacity_changed', $errors->get_error_code() );
		self::assertSame( 1, $this->get_active_reservation_count( $this->delivery_date ) );
		self::assertSame( 1, $this->get_capacity( $this->delivery_date )['reserved_count'] );
		self::assertSame( array(), CartRecalculationService::get_delivery_reservation() );
	}

	public function test_invalid_checkout_does_not_reserve_delivery_capacity(): void {
		$errors = $this->run_final_checkout_validation( 'invalid' );

		self::assertTrue( $errors->has_errors() );
		self::assertSame( 'galaxyone_invalid_mobile_number', $errors->get_error_code() );
		self::assertSame( 0, $this->get_active_reservation_count( $this->delivery_date ) );
		self::assertSame( 0, $this->get_capacity( $this->delivery_date )['reserved_count'] );
		self::assertSame( array(), CartRecalculationService::get_delivery_reservation() );
	}

	public function test_changed_delivery_selection_releases_and_replaces_the_reservation(): void {
		$first_errors = $this->run_final_checkout_validation();

		self::assertFalse( $first_errors->has_errors(), implode( ', ', $first_errors->get_error_codes() ) );

		$first_reservation = CartRecalculationService::get_delivery_reservation();

		self::assertTrue(
			DeliveryCapacityService::save_capacity(
				$this->replacement_delivery_date,
				$this->slot_key,
				1
			)
		);

		$second_errors = $this->run_final_checkout_validation(
			'9876543210',
			$this->replacement_delivery_date
		);

		self::assertFalse( $second_errors->has_errors(), implode( ', ', $second_errors->get_error_codes() ) );
		self::assertSame( 'released', $this->get_reservation_status( (string) $first_reservation['token'] ) );
		self::assertSame( 0, $this->get_capacity( $this->delivery_date )['reserved_count'] );
		self::assertSame( 1, $this->get_active_reservation_count( $this->replacement_delivery_date ) );
		self::assertSame( 1, $this->get_capacity( $this->replacement_delivery_date )['reserved_count'] );
		self::assertNotSame(
			$first_reservation['token'],
			CartRecalculationService::get_delivery_reservation()['token']
		);
	}

	public function test_order_confirmation_assigns_the_existing_reservation_without_another_capacity_claim(): void {
		$errors = $this->run_final_checkout_validation();

		self::assertFalse( $errors->has_errors(), implode( ', ', $errors->get_error_codes() ) );

		$reservation = CartRecalculationService::get_delivery_reservation();
		$order       = wc_create_order();

		self::assertInstanceOf( WC_Order::class, $order );

		$order_id           = $order->get_id();
		$this->order_ids[] = $order_id;

		$data = $this->get_checkout_data();

		do_action( 'woocommerce_checkout_create_order', $order, $data );
		$order->save();
		do_action( 'woocommerce_checkout_order_processed', $order_id, $data, $order );

		self::assertSame( 'confirmed', $this->get_reservation_status( (string) $reservation['token'] ) );
		self::assertSame( $order_id, $this->get_reservation_order_id( (string) $reservation['token'] ) );
		self::assertSame( 1, $this->get_capacity( $this->delivery_date )['reserved_count'] );
		self::assertSame( array(), CartRecalculationService::get_delivery_reservation() );
	}

	public function test_checkout_order_exception_releases_the_unconfirmed_reservation(): void {
		$errors = $this->run_final_checkout_validation();

		self::assertFalse( $errors->has_errors(), implode( ', ', $errors->get_error_codes() ) );

		$reservation = CartRecalculationService::get_delivery_reservation();

		do_action( 'woocommerce_checkout_order_exception', new WC_Order() );

		self::assertSame( 'released', $this->get_reservation_status( (string) $reservation['token'] ) );
		self::assertSame( 0, $this->get_capacity( $this->delivery_date )['reserved_count'] );
		self::assertSame( array(), CartRecalculationService::get_delivery_reservation() );
	}

	private function configure_checkout_runtime(): void {
		$session_values = &$this->session_values;
		$session        = $this->createMock( WC_Session::class );
		$cart           = $this->createMock( WC_Cart::class );

		$session->method( 'get' )->willReturnCallback(
			static function ( string $key, mixed $default = null ) use ( &$session_values ): mixed {
				return array_key_exists( $key, $session_values )
					? $session_values[ $key ]
					: $default;
			}
		);
		$session->method( 'set' )->willReturnCallback(
			static function ( string $key, mixed $value ) use ( &$session_values ): void {
				$session_values[ $key ] = $value;
			}
		);
		$cart->method( 'get_cart' )->willReturn( array() );

		$woocommerce            = WC();
		$this->previous_cart    = $woocommerce->cart;
		$this->previous_session = $woocommerce->session;
		$this->previous_post    = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The integration test controls checkout request state.
		$woocommerce->cart      = $cart;
		$woocommerce->session   = $session;
	}

	private function restore_checkout_runtime(): void {
		$woocommerce            = WC();
		$woocommerce->cart      = $this->previous_cart;
		$woocommerce->session   = $this->previous_session;
		$_POST                  = $this->previous_post; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The integration test restores checkout request state.
	}

	private function remove_checkout_hooks(): void {
		remove_filter(
			'woocommerce_payment_gateways',
			array( $this->checkout_module, 'register_payment_gateways' )
		);
		remove_filter(
			'woocommerce_checkout_fields',
			array( $this->checkout_module, 'configure_checkout_fields' )
		);
		remove_action(
			'woocommerce_after_order_notes',
			array( $this->checkout_module, 'render_delivery_selection' )
		);
		remove_action(
			'woocommerce_checkout_update_order_review',
			array( $this->checkout_module, 'capture_delivery_selection' )
		);
		remove_action(
			'woocommerce_after_checkout_validation',
			array( $this->checkout_module, 'validate_checkout' ),
			10
		);
		remove_action(
			'woocommerce_after_checkout_validation',
			array( $this->checkout_module, 'reserve_delivery_selection' ),
			PHP_INT_MAX
		);
		remove_action(
			'woocommerce_checkout_create_order_line_item',
			array( $this->checkout_module, 'store_order_item_snapshot' )
		);
		remove_action(
			'woocommerce_checkout_create_order',
			array( $this->checkout_module, 'store_order_metadata' )
		);
		remove_action(
			'woocommerce_checkout_order_processed',
			array( $this->checkout_module, 'confirm_delivery_reservation' )
		);
		remove_action(
			'woocommerce_checkout_order_exception',
			array( $this->checkout_module, 'release_delivery_reservation_after_checkout_exception' )
		);
	}

	private function run_final_checkout_validation(
		string $phone = '9876543210',
		?string $delivery_date = null
	): WP_Error {
		$_POST['galaxyone_delivery_date']         = $delivery_date ?? $this->delivery_date;
		$_POST['galaxyone_delivery_slot']         = $this->slot_key;
		$_POST['galaxyone_water_delivery_access'] = '';
		$_POST['galaxyone_water_delivery_floor']  = '';

		$errors = new WP_Error();

		do_action(
			'woocommerce_after_checkout_validation',
			$this->get_checkout_data( $phone ),
			$errors
		);

		return $errors;
	}

	/**
	 * @return array<string, string>
	 */
	private function get_checkout_data( string $phone = '9876543210' ): array {
		return array(
			'billing_phone'     => $phone,
			'billing_address_1' => $this->street_name,
			'billing_city'      => $this->locality,
			'billing_postcode'  => $this->postcode,
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

	private function get_active_reservation_count( string $delivery_date ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM %i
				WHERE delivery_date = %s
					AND slot_key = %s
					AND status = %s",
				CreateDeliveryReservationsTable::get_table_name(),
				$delivery_date,
				$this->slot_key,
				'active'
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function get_reservation_status( string $reservation_token ): string {
		global $wpdb;

		return (string) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT status FROM %i WHERE reservation_token = %s',
				CreateDeliveryReservationsTable::get_table_name(),
				$reservation_token
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function get_reservation_order_id( string $reservation_token ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT order_id FROM %i WHERE reservation_token = %s',
				CreateDeliveryReservationsTable::get_table_name(),
				$reservation_token
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function cleanup_fixtures(): void {
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

		foreach ( $this->order_ids as $order_id ) {
			wp_delete_post( $order_id, true );
		}

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
	}
}
