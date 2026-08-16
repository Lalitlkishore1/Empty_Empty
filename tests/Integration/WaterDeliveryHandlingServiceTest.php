<?php
/**
 * Water delivery-handling integration tests.
 *
 * @package GalaxyOne\Tests\Integration
 */

declare(strict_types=1);

namespace GalaxyOne\Tests\Integration;

use GalaxyOne\Core\Cart\CartRecalculationService;
use GalaxyOne\Core\Checkout\CheckoutModule;
use GalaxyOne\Core\Database\Migrations\CreateSupportedStreetsTable;
use GalaxyOne\Core\Delivery\DeliveryCapacityService;
use GalaxyOne\Core\Delivery\DeliverySlotService;
use GalaxyOne\Core\Delivery\ServiceAreaService;
use GalaxyOne\Core\Delivery\SupportedStreetRepository;
use GalaxyOne\Core\Orders\OrderMetaService;
use GalaxyOne\Core\Pricing\WaterDeliveryHandlingService;
use GalaxyOne\Core\Products\ProductCategoryResolver;
use GalaxyOne\Core\Settings\SettingsRepository;
use GalaxyOne\Tests\Support\IntegrationTestCase;
use WC_Cart;
use WC_Order;
use WC_Product_Simple;
use WC_Session;
use WP_Error;

final class WaterDeliveryHandlingServiceTest extends IntegrationTestCase {

	/**
	 * @var array<int, int>
	 */
	private array $product_ids = array();

	/**
	 * @var mixed
	 */
	private $previous_settings;

	private bool $settings_option_existed = false;

	private int $water_product_id;

	private int $second_water_product_id;

	private int $bloom_product_id;

	private string $delivery_date;

	private string $slot_key;

	private string $postcode;

	private string $street_name;

	private string $locality;

	private int $street_id;

	public function setUp(): void {
		parent::setUp();

		$this->settings_option_existed = false !== get_option( SettingsRepository::OPTION_NAME, false );
		$this->previous_settings       = get_option( SettingsRepository::OPTION_NAME, false );
		$this->water_product_id        = $this->create_product_in_category(
			'Water Handling Test Can',
			ProductCategoryResolver::WATER_CATEGORY
		);
		$this->second_water_product_id = $this->create_product_in_category(
			'Water Handling Test Bottle',
			ProductCategoryResolver::WATER_CATEGORY
		);
		$this->bloom_product_id        = $this->create_product_in_category(
			'Water Handling Test Bloom',
			ProductCategoryResolver::BLOOMS_CATEGORY
		);
		$this->delivery_date            = gmdate( 'Y-m-d', time() + DAY_IN_SECONDS );
		$this->slot_key                 = sanitize_title(
			'water-handling-' . wp_generate_password( 10, false, false )
		);
		$this->postcode                 = 'WH' . wp_rand( 100000, 999999 );
		$this->street_name              = 'Water Handling Street ' . $this->slot_key;
		$this->locality                 = 'Water Handling Locality';

		self::assertTrue( CreateSupportedStreetsTable::up() );
		self::assertTrue(
			DeliverySlotService::save_slot(
				$this->slot_key,
				'Water Handling Slot',
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
				20
			)
		);
		self::assertTrue(
			ServiceAreaService::save_service_area(
				$this->postcode,
				'Water Handling Area',
				'25'
			)
		);

		$street_id = SupportedStreetRepository::create(
			$this->street_name,
			$this->locality,
			$this->postcode
		);

		self::assertIsInt( $street_id );
		self::assertGreaterThan( 0, $street_id );
		$this->street_id = $street_id;
	}

	public function tearDown(): void {
		$this->cleanup_delivery_fixtures();

		if ( $this->street_id > 0 ) {
			global $wpdb;

			$wpdb->delete(
				CreateSupportedStreetsTable::get_table_name(),
				array( 'id' => $this->street_id ),
				array( '%d' )
			);
		}

		foreach ( $this->product_ids as $product_id ) {
			wp_delete_post( $product_id, true );
		}

		if ( $this->settings_option_existed ) {
			update_option( SettingsRepository::OPTION_NAME, $this->previous_settings, false );
		} else {
			delete_option( SettingsRepository::OPTION_NAME );
		}

		parent::tearDown();
	}

	public function test_non_water_cart_does_not_require_water_delivery_handling(): void {
		$cart = $this->create_cart(
			array(
				$this->bloom_product_id => 3,
			)
		);

		self::assertFalse( WaterDeliveryHandlingService::cart_contains_water( $cart ) );
		self::assertSame( 0, WaterDeliveryHandlingService::get_water_quantity( $cart ) );
	}

	public function test_ground_floor_and_working_lift_resolve_zero_handling(): void {
		$cart = $this->create_cart(
			array(
				$this->water_product_id => 2,
			)
		);

		$ground_floor = WaterDeliveryHandlingService::resolve(
			$cart,
			WaterDeliveryHandlingService::ACCESS_GROUND_FLOOR
		);
		$working_lift = WaterDeliveryHandlingService::resolve(
			$cart,
			WaterDeliveryHandlingService::ACCESS_WORKING_LIFT
		);

		self::assertIsArray( $ground_floor );
		self::assertSame( 2, $ground_floor['water_quantity'] );
		self::assertSame( '0.0000', $ground_floor['rate'] );
		self::assertSame( '0.00', $ground_floor['charge'] );
		self::assertIsArray( $working_lift );
		self::assertSame( '0.0000', $working_lift['rate'] );
		self::assertSame( '0.00', $working_lift['charge'] );
	}

	public function test_stairs_rates_are_resolved_independently_from_configuration(): void {
		$cart = $this->create_cart(
			array(
				$this->water_product_id => 3,
			)
		);

		foreach (
			array(
				1 => array( '5.0000', '15.00' ),
				2 => array( '10.0000', '30.00' ),
				3 => array( '15.0000', '45.00' ),
				4 => array( '20.0000', '60.00' ),
			) as $floor => $expected
		) {
			$handling = WaterDeliveryHandlingService::resolve(
				$cart,
				WaterDeliveryHandlingService::ACCESS_STAIRS,
				(string) $floor
			);

			self::assertIsArray( $handling );
			self::assertSame( $floor, $handling['floor'] );
			self::assertSame( $expected[0], $handling['rate'] );
			self::assertSame( $expected[1], $handling['charge'] );
		}
	}

	public function test_mixed_cart_counts_only_all_qualifying_water_quantities(): void {
		$cart = $this->create_cart(
			array(
				$this->water_product_id        => 2,
				$this->second_water_product_id => 1,
				$this->bloom_product_id        => 5,
			)
		);

		$handling = WaterDeliveryHandlingService::resolve(
			$cart,
			WaterDeliveryHandlingService::ACCESS_STAIRS,
			'3'
		);

		self::assertIsArray( $handling );
		self::assertSame( 3, $handling['water_quantity'] );
		self::assertSame( '45.00', $handling['charge'] );
	}

	public function test_invalid_access_and_stairs_floors_are_rejected_server_side(): void {
		$cart = $this->create_cart(
			array(
				$this->water_product_id => 1,
			)
		);

		foreach (
			array(
				array( '', '', 'galaxyone_water_delivery_access_required' ),
				array( 'invalid_access', '', 'galaxyone_water_delivery_access_required' ),
				array( WaterDeliveryHandlingService::ACCESS_STAIRS, '', 'galaxyone_water_delivery_floor_required' ),
				array( WaterDeliveryHandlingService::ACCESS_STAIRS, 'second', 'galaxyone_water_delivery_floor_invalid' ),
				array( WaterDeliveryHandlingService::ACCESS_STAIRS, '0', 'galaxyone_water_delivery_floor_invalid' ),
				array( WaterDeliveryHandlingService::ACCESS_STAIRS, '5', 'galaxyone_water_delivery_manual_confirmation_required' ),
				array( WaterDeliveryHandlingService::ACCESS_STAIRS, '8', 'galaxyone_water_delivery_manual_confirmation_required' ),
			) as $case
		) {
			$result = WaterDeliveryHandlingService::resolve( $cart, $case[0], $case[1] );

			self::assertInstanceOf( WP_Error::class, $result );
			self::assertSame( $case[2], $result->get_error_code() );
		}
	}

	public function test_updated_floor_rate_changes_new_calculations_without_changing_prior_snapshot(): void {
		$cart             = $this->create_cart(
			array(
				$this->water_product_id => 2,
			)
		);
		$original_handling = WaterDeliveryHandlingService::resolve(
			$cart,
			WaterDeliveryHandlingService::ACCESS_STAIRS,
			'2'
		);

		self::assertIsArray( $original_handling );
		self::assertSame( '10.0000', $original_handling['rate'] );
		self::assertSame( '20.00', $original_handling['charge'] );

		$settings                                = SettingsRepository::get_settings();
		$settings['water_stairs_floor_2_rate'] = '12.0000';
		update_option( SettingsRepository::OPTION_NAME, $settings, false );

		$new_handling = WaterDeliveryHandlingService::resolve(
			$cart,
			WaterDeliveryHandlingService::ACCESS_STAIRS,
			'2'
		);

		self::assertIsArray( $new_handling );
		self::assertSame( '12.0000', $new_handling['rate'] );
		self::assertSame( '24.00', $new_handling['charge'] );
		self::assertSame( '10.0000', $original_handling['rate'] );
		self::assertSame( '20.00', $original_handling['charge'] );
	}

	public function test_final_checkout_recalculation_uses_and_persists_the_current_water_handling(): void {
		$session_values = array();
		$fees           = array();
		$cart_items     = array(
			'water-handling-cart-item' => array(
				'product_id'   => $this->water_product_id,
				'variation_id' => 0,
				'quantity'     => 2,
			),
		);
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
		$cart->method( 'get_cart' )->willReturnCallback(
			static function () use ( &$cart_items ): array {
				return $cart_items;
			}
		);
		$cart->method( 'add_fee' )->willReturnCallback(
			static function ( mixed $name, mixed $amount ) use ( &$fees ): void {
				$fees[] = array(
					'name'   => (string) $name,
					'amount' => (string) $amount,
				);
			}
		);
		$cart->method( 'calculate_totals' )->willReturnCallback(
			static function () use ( &$fees, $cart ): void {
				$fees = array();
				CartRecalculationService::apply_delivery_fee( $cart );
			}
		);

		$woocommerce      = WC();
		$previous_cart    = $woocommerce->cart;
		$previous_session = $woocommerce->session;
		$previous_post    = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The integration test controls checkout request state.
		$woocommerce->cart    = $cart;
		$woocommerce->session = $session;
		$checkout             = new CheckoutModule();

		try {
			$this->submit_final_checkout_choice(
				$checkout,
				WaterDeliveryHandlingService::ACCESS_GROUND_FLOOR,
				''
			);
			self::assertSame( array(), $this->get_water_handling_fees( $fees ) );
			self::assertSame(
				array(
					'access_type'   => WaterDeliveryHandlingService::ACCESS_GROUND_FLOOR,
					'floor'         => 0,
					'rate'          => '0.0000',
					'water_quantity' => 2,
					'charge'        => '0.00',
				),
				CartRecalculationService::get_water_delivery_handling_snapshot()
			);

			$this->submit_final_checkout_choice(
				$checkout,
				WaterDeliveryHandlingService::ACCESS_STAIRS,
				'2'
			);
			self::assertSame(
				array(
					array(
						'name'   => 'Water delivery handling',
						'amount' => '20',
					),
				),
				$this->get_water_handling_fees( $fees )
			);

			$this->submit_final_checkout_choice(
				$checkout,
				WaterDeliveryHandlingService::ACCESS_STAIRS,
				'3'
			);
			self::assertSame( '30.00', CartRecalculationService::get_water_delivery_handling_snapshot()['charge'] );
			self::assertCount( 1, $this->get_water_handling_fees( $fees ) );

			$this->submit_final_checkout_choice(
				$checkout,
				WaterDeliveryHandlingService::ACCESS_WORKING_LIFT,
				''
			);
			self::assertSame( array(), $this->get_water_handling_fees( $fees ) );
			self::assertSame( '0.00', CartRecalculationService::get_water_delivery_handling_snapshot()['charge'] );

			$cart_items['water-handling-cart-item']['quantity'] = 3;
			$this->submit_final_checkout_choice(
				$checkout,
				WaterDeliveryHandlingService::ACCESS_STAIRS,
				'3'
			);
			self::assertSame( 3, CartRecalculationService::get_water_delivery_handling_snapshot()['water_quantity'] );
			self::assertSame( '45.00', CartRecalculationService::get_water_delivery_handling_snapshot()['charge'] );

			$settings                                = SettingsRepository::get_settings();
			$settings['water_stairs_floor_3_rate'] = '17.0000';
			update_option( SettingsRepository::OPTION_NAME, $settings, false );
			$this->submit_final_checkout_choice(
				$checkout,
				WaterDeliveryHandlingService::ACCESS_STAIRS,
				'3'
			);
			$handling = CartRecalculationService::get_water_delivery_handling_snapshot();
			self::assertSame( '17.0000', $handling['rate'] );
			self::assertSame( '51.00', $handling['charge'] );
			self::assertCount( 1, $this->get_water_handling_fees( $fees ) );

			$this->assert_order_snapshot_matches_handling( $handling );

			$cart_items = array();
			$this->submit_final_checkout_choice(
				$checkout,
				WaterDeliveryHandlingService::ACCESS_STAIRS,
				'3'
			);
			self::assertSame(
				array(
					'access_type'   => '',
					'floor'         => 0,
					'rate'          => '',
					'water_quantity' => 0,
					'charge'        => '',
				),
				CartRecalculationService::get_water_delivery_handling_snapshot()
			);
			self::assertSame( array(), $this->get_water_handling_fees( $fees ) );
			$this->assert_order_snapshot_is_absent();
		} finally {
			$woocommerce->cart    = $previous_cart;
			$woocommerce->session = $previous_session;
			$_POST                = $previous_post;
		}
	}

	private function submit_final_checkout_choice(
		CheckoutModule $checkout,
		string $access_type,
		string $floor
	): void {
		$_POST['galaxyone_delivery_date']         = $this->delivery_date;
		$_POST['galaxyone_delivery_slot']         = $this->slot_key;
		$_POST['galaxyone_water_delivery_access'] = $access_type;
		$_POST['galaxyone_water_delivery_floor']  = $floor;

		$errors = new WP_Error();
		$checkout->validate_checkout(
			array(
				'billing_phone'     => '9876543210',
				'billing_address_1' => $this->street_name,
				'billing_city'      => $this->locality,
				'billing_postcode'  => $this->postcode,
			),
			$errors
		);

		self::assertFalse( $errors->has_errors() );
	}

	/**
	 * @param array<int, array{name: string, amount: string}> $fees Cart fees.
	 * @return array<int, array{name: string, amount: string}>
	 */
	private function get_water_handling_fees( array $fees ): array {
		return array_values(
			array_filter(
				$fees,
				static function ( array $fee ): bool {
					return 'Water delivery handling' === $fee['name'];
				}
			)
		);
	}

	/**
	 * @param array<string, int|string> $handling Authoritative Water handling snapshot.
	 * @return void
	 */
	private function assert_order_snapshot_matches_handling( array $handling ): void {
		$metadata = array();
		$order    = $this->createMock( WC_Order::class );
		$order->method( 'update_meta_data' )->willReturnCallback(
			static function ( string $key, mixed $value ) use ( &$metadata ): void {
				$metadata[ $key ] = $value;
			}
		);

		OrderMetaService::store_checkout_metadata( $order, array() );

		self::assertArrayHasKey( '_galaxyone_water_delivery_handling_snapshot', $metadata );
		self::assertSame(
			$handling,
			json_decode(
				(string) $metadata['_galaxyone_water_delivery_handling_snapshot'],
				true
			)
		);
	}

	private function assert_order_snapshot_is_absent(): void {
		$metadata = array();
		$order    = $this->createMock( WC_Order::class );
		$order->method( 'update_meta_data' )->willReturnCallback(
			static function ( string $key, mixed $value ) use ( &$metadata ): void {
				$metadata[ $key ] = $value;
			}
		);

		OrderMetaService::store_checkout_metadata( $order, array() );

		self::assertArrayNotHasKey( '_galaxyone_water_delivery_handling_snapshot', $metadata );
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
				WHERE rule_key IN (%s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->slot_key,
				$this->postcode
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Creates a persisted simple product in one GalaxyOne category.
	 *
	 * @param string $name     Product name.
	 * @param string $category Product category slug.
	 * @return int
	 */
	private function create_product_in_category( string $name, string $category ): int {
		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_regular_price( '25.00' );
		$product->set_price( '25.00' );
		$product->set_status( 'publish' );
		$product->save();

		$product_id = $product->get_id();

		self::assertGreaterThan( 0, $product_id );
		self::assertFalse(
			is_wp_error( wp_set_object_terms( $product_id, $category, 'product_cat' ) )
		);

		$this->product_ids[] = $product_id;

		return $product_id;
	}

	/**
	 * Creates a cart double with authoritative WooCommerce cart-item quantities.
	 *
	 * @param array<int, int> $quantities Product IDs mapped to quantities.
	 * @return WC_Cart
	 */
	private function create_cart( array $quantities ): WC_Cart {
		$items = array();

		foreach ( $quantities as $product_id => $quantity ) {
			$items[ 'water-handling-' . $product_id ] = array(
				'product_id'   => $product_id,
				'variation_id' => 0,
				'quantity'     => $quantity,
			);
		}

		$cart = $this->createMock( WC_Cart::class );
		$cart->method( 'get_cart' )->willReturn( $items );

		return $cart;
	}
}
