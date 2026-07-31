<?php
/**
 * Supported street repository integration tests.
 *
 * @package GalaxyOne\Tests\Integration
 */

declare(strict_types=1);

namespace GalaxyOne\Tests\Integration;

use GalaxyOne\Core\Database\Migrations\CreateSupportedStreetsTable;
use GalaxyOne\Core\Delivery\StreetNormalizationService;
use GalaxyOne\Core\Delivery\SupportedStreetRepository;
use GalaxyOne\Tests\Support\IntegrationTestCase;

final class SupportedStreetRepositoryTest extends IntegrationTestCase {

	/**
	 * @var array<int, int>
	 */
	private array $street_ids = array();

	public function setUp(): void {
		parent::setUp();

		self::assertTrue( CreateSupportedStreetsTable::up() );
	}

	public function tearDown(): void {
		$this->delete_test_streets();

		parent::tearDown();
	}

	public function test_create_normalizes_and_persists_a_supported_street(): void {
		global $wpdb;

		$street_name  = '  North   Main Street  ';
		$locality     = '  Town   Centre ';
		$service_area = ' ab 12 3cd ';
		$prepared     = StreetNormalizationService::prepare(
			$street_name,
			$locality,
			$service_area
		);

		self::assertIsArray( $prepared );

		$street_id = SupportedStreetRepository::create(
			$street_name,
			$locality,
			$service_area
		);

		if ( is_int( $street_id ) && $street_id > 0 ) {
			$this->street_ids[] = $street_id;
		}

		self::assertIsInt( $street_id );
		self::assertGreaterThan( 0, $street_id );

		$table_name = CreateSupportedStreetsTable::get_table_name();
		$record     = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, street_name, normalized_street_name, locality, normalized_locality,
					service_area, street_identity_key, is_active, created_at, updated_at
				FROM {$table_name}
				WHERE id = %d
				LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$street_id
			),
			ARRAY_A
		);

		self::assertIsArray( $record );
		self::assertSame( (string) $street_id, $record['id'] );
		self::assertSame( $prepared['street_name'], $record['street_name'] );
		self::assertSame(
			$prepared['normalized_street_name'],
			$record['normalized_street_name']
		);
		self::assertSame( $prepared['locality'], $record['locality'] );
		self::assertSame(
			$prepared['normalized_locality'],
			$record['normalized_locality']
		);
		self::assertSame( $prepared['service_area'], $record['service_area'] );
		self::assertSame(
			$prepared['street_identity_key'],
			$record['street_identity_key']
		);
		self::assertSame( '1', $record['is_active'] );
		self::assertNotSame( '', $record['created_at'] );
		self::assertNotSame( '', $record['updated_at'] );
	}

	public function test_duplicate_normalized_identity_is_not_persisted_twice(): void {
		$first_street_id = SupportedStreetRepository::create(
			'North Main Street',
			'Town Centre',
			'AB12 3CD'
		);

		if ( is_int( $first_street_id ) && $first_street_id > 0 ) {
			$this->street_ids[] = $first_street_id;
		}

		self::assertIsInt( $first_street_id );
		self::assertGreaterThan( 0, $first_street_id );

		$duplicate_street_id = SupportedStreetRepository::create(
			'  north  main street ',
			' town   centre ',
			'ab123cd'
		);

		if ( is_int( $duplicate_street_id ) && $duplicate_street_id > 0 ) {
			$this->street_ids[] = $duplicate_street_id;
		}

		self::assertSame( 0, $duplicate_street_id );
		self::assertSame(
			1,
			$this->get_street_count_by_identity_key(
				StreetNormalizationService::prepare(
					'North Main Street',
					'Town Centre',
					'AB12 3CD'
				)['street_identity_key']
			)
		);
	}

	public function test_get_active_returns_only_active_supported_streets_with_public_row_shape(): void {
		global $wpdb;

		$active_street_id = SupportedStreetRepository::create(
			'Active Street',
			'Repository Locality',
			'AB12 3CD'
		);

		if ( is_int( $active_street_id ) && $active_street_id > 0 ) {
			$this->street_ids[] = $active_street_id;
		}

		$inactive_street_id = SupportedStreetRepository::create(
			'Inactive Street',
			'Repository Locality',
			'AB12 3CD'
		);

		if ( is_int( $inactive_street_id ) && $inactive_street_id > 0 ) {
			$this->street_ids[] = $inactive_street_id;
		}

		self::assertIsInt( $active_street_id );
		self::assertIsInt( $inactive_street_id );
		self::assertGreaterThan( 0, $active_street_id );
		self::assertGreaterThan( 0, $inactive_street_id );

		$table_name = CreateSupportedStreetsTable::get_table_name();

		self::assertSame(
			1,
			$wpdb->update(
				$table_name,
				array( 'is_active' => 0 ),
				array( 'id' => $inactive_street_id ),
				array( '%d' ),
				array( '%d' )
			)
		);

		$streets = SupportedStreetRepository::get_active();
		$records = array_values(
			array_filter(
				$streets,
				static function ( array $street ) use ( $active_street_id, $inactive_street_id ): bool {
					return $active_street_id === $street['id'] || $inactive_street_id === $street['id'];
				}
			)
		);

		self::assertCount( 1, $records );
		self::assertSame(
			array(
				'id'           => $active_street_id,
				'street_name'  => 'Active Street',
				'locality'     => 'Repository Locality',
				'service_area' => 'AB123CD',
			),
			$records[0]
		);
	}

	private function get_street_count_by_identity_key( string $identity_key ): int {
		global $wpdb;

		$table_name = CreateSupportedStreetsTable::get_table_name();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$table_name}
				WHERE street_identity_key = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$identity_key
			)
		);
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
