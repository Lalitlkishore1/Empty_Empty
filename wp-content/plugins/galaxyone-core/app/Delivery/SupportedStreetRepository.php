<?php
/**
 * Supported street repository.
 *
 * @package GalaxyOne\Core\Delivery
 */

namespace GalaxyOne\Core\Delivery;

use GalaxyOne\Core\Database\Migrations\CreateSupportedStreetsTable;

final class SupportedStreetRepository {

	/**
	 * Creates one supported street.
	 *
	 * @param string $street_name  Street display name.
	 * @param string $locality     Locality display name.
	 * @param string $service_area Optional service-area postcode.
	 * @return int|false|null
	 */
	public static function create(
		string $street_name,
		string $locality,
		string $service_area = ''
	): int|false|null {
		global $wpdb;

		$prepared_street = StreetNormalizationService::prepare(
			$street_name,
			$locality,
			$service_area
		);

		if (
			! is_array( $prepared_street ) ||
			! self::is_valid_prepared_street( $prepared_street )
		) {
			return null;
		}

		$existing_id = self::get_id_by_identity_key(
			$prepared_street['street_identity_key']
		);

		if ( null !== $existing_id ) {
			return 0;
		}

		$table_name = CreateSupportedStreetsTable::get_table_name();
		$now        = current_time( 'mysql', true );
		$inserted   = $wpdb->insert(
			$table_name,
			array(
				'street_name'            => $prepared_street['street_name'],
				'normalized_street_name' => $prepared_street['normalized_street_name'],
				'locality'               => $prepared_street['locality'],
				'normalized_locality'    => $prepared_street['normalized_locality'],
				'service_area'           => $prepared_street['service_area'],
				'street_identity_key'    => $prepared_street['street_identity_key'],
				'is_active'              => 1,
				'created_at'             => $now,
				'updated_at'             => $now,
			),
			array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
			)
		);

		if ( false === $inserted ) {
			return null !== self::get_id_by_identity_key(
				$prepared_street['street_identity_key']
			) ? 0 : false;
		}

		$street_id = (int) $wpdb->insert_id;

		return $street_id > 0 ? $street_id : false;
	}

	/**
	 * Returns active supported streets.
	 *
	 * @return array<int, array<string, int|string>>
	 */
	public static function get_active(): array {
		global $wpdb;

		$table_name = CreateSupportedStreetsTable::get_table_name();
		$records    = $wpdb->get_results(
			"SELECT id, street_name, locality, service_area
			FROM {$table_name}
			WHERE is_active = 1
			ORDER BY normalized_locality ASC, normalized_street_name ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! is_array( $records ) ) {
			return array();
		}

		$streets = array();

		foreach ( $records as $record ) {
			if (
				! is_array( $record ) ||
				! isset(
					$record['id'],
					$record['street_name'],
					$record['locality'],
					$record['service_area']
				) ||
				! is_scalar( $record['street_name'] ) ||
				! is_scalar( $record['locality'] ) ||
				! is_scalar( $record['service_area'] )
			) {
				continue;
			}

			$street_id = absint( $record['id'] );

			if ( $street_id <= 0 ) {
				continue;
			}

			$streets[] = array(
				'id'           => $street_id,
				'street_name'  => (string) $record['street_name'],
				'locality'     => (string) $record['locality'],
				'service_area' => (string) $record['service_area'],
			);
		}

		return $streets;
	}

	/**
	 * Determines whether prepared street data is structurally valid.
	 *
	 * @param array<string, mixed> $prepared_street Prepared street data.
	 * @return bool
	 */
	private static function is_valid_prepared_street( array $prepared_street ): bool {
		$expected_keys = array(
			'street_name',
			'normalized_street_name',
			'locality',
			'normalized_locality',
			'service_area',
			'street_identity_key',
		);
		$actual_keys   = array_keys( $prepared_street );

		sort( $expected_keys );
		sort( $actual_keys );

		if ( $expected_keys !== $actual_keys ) {
			return false;
		}

		foreach ( $expected_keys as $key ) {
			if ( ! is_string( $prepared_street[ $key ] ) ) {
				return false;
			}
		}

		if (
			'' === $prepared_street['street_name'] ||
			'' === $prepared_street['normalized_street_name'] ||
			'' === $prepared_street['locality'] ||
			'' === $prepared_street['normalized_locality']
		) {
			return false;
		}

		return self::has_valid_identity_key(
			$prepared_street['street_identity_key']
		);
	}

	/**
	 * Returns an existing street ID for an identity key.
	 *
	 * @param string $identity_key Street identity key.
	 * @return int|null
	 */
	private static function get_id_by_identity_key( string $identity_key ): ?int {
		global $wpdb;

		if ( ! self::has_valid_identity_key( $identity_key ) ) {
			return null;
		}

		$table_name = CreateSupportedStreetsTable::get_table_name();
		$street_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id
				FROM {$table_name}
				WHERE street_identity_key = %s
				LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$identity_key
			)
		);
		$street_id = absint( $street_id );

		return $street_id > 0 ? $street_id : null;
	}

	/**
	 * Determines whether an identity key is structurally valid.
	 *
	 * @param string $identity_key Street identity key.
	 * @return bool
	 */
	private static function has_valid_identity_key( string $identity_key ): bool {
		$matches = preg_match( '/^[a-f0-9]{64}$/D', $identity_key );

		return false !== $matches && 1 === $matches;
	}
}
