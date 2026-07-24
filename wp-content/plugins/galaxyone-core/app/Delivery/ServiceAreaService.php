<?php
/**
 * Service-area service.
 *
 * @package GalaxyOne\Core\Delivery
 */

namespace GalaxyOne\Core\Delivery;

use GalaxyOne\Core\Database\Migrations\CreateDeliveryRulesTable;

final class ServiceAreaService {

	/**
	 * Service-area rule type.
	 *
	 * @var string
	 */
	private const RULE_TYPE = 'service_area';

	/**
	 * Returns a configured service area for a postcode.
	 *
	 * @param string $postcode Customer delivery postcode.
	 * @return array<string, string>|null
	 */
	public static function get_service_area( string $postcode ): ?array {
		global $wpdb;

		$postcode = self::normalize_postcode( $postcode );

		if ( '' === $postcode ) {
			return null;
		}

		$table_name = CreateDeliveryRulesTable::get_table_name();
		$record     = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT service_area, label, fee
				FROM {$table_name}
				WHERE rule_type = %s
					AND service_area = %s
					AND is_active = 1
				LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::RULE_TYPE,
				$postcode
			),
			ARRAY_A
		);

		if ( ! is_array( $record ) ) {
			return null;
		}

		return array(
			'postcode' => (string) $record['service_area'],
			'label'    => (string) $record['label'],
			'fee'      => (string) $record['fee'],
		);
	}

	/**
	 * Saves a service area and its normal delivery fee.
	 *
	 * @param string $postcode Postcode to serve.
	 * @param string $label    Administrator-facing label.
	 * @param string $fee      Non-negative decimal delivery fee.
	 * @return bool
	 */
	public static function save_service_area( string $postcode, string $label, string $fee ): bool {
		global $wpdb;

		$postcode = self::normalize_postcode( $postcode );
		$label    = sanitize_text_field( $label );
		$fee      = self::normalize_fee( $fee );

		if ( '' === $postcode || '' === $label || null === $fee ) {
			return false;
		}

		$table_name = CreateDeliveryRulesTable::get_table_name();
		$now        = current_time( 'mysql', true );
		$query      = $wpdb->prepare(
			"INSERT INTO {$table_name}
				(rule_type, rule_key, label, service_area, fee, is_active, created_at, updated_at)
			VALUES (%s, %s, %s, %s, %s, %d, %s, %s)
			ON DUPLICATE KEY UPDATE
				label = %s,
				service_area = %s,
				fee = %s,
				is_active = %d,
				updated_at = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			self::RULE_TYPE,
			$postcode,
			$label,
			$postcode,
			$fee,
			1,
			$now,
			$now,
			$label,
			$postcode,
			$fee,
			1,
			$now
		);

		return false !== $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Returns all configured service areas.
	 *
	 * @return array<int, object>
	 */
	public static function get_service_areas(): array {
		global $wpdb;

		$table_name = CreateDeliveryRulesTable::get_table_name();
		$areas      = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT service_area, label, fee
				FROM {$table_name}
				WHERE rule_type = %s
					AND is_active = 1
				ORDER BY service_area ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::RULE_TYPE
			)
		);

		return is_array( $areas ) ? $areas : array();
	}

	/**
	 * Normalizes a configured postcode.
	 *
	 * @param string $postcode Postcode value.
	 * @return string
	 */
	public static function normalize_postcode( string $postcode ): string {
		$postcode = strtoupper( preg_replace( '/\s+/', '', $postcode ) );

		if ( ! preg_match( '/^[A-Z0-9-]{3,12}$/', $postcode ) ) {
			return '';
		}

		return $postcode;
	}

	/**
	 * Normalizes a non-negative delivery fee.
	 *
	 * @param string $fee Fee value.
	 * @return string|null
	 */
	private static function normalize_fee( string $fee ): ?string {
		$fee = trim( $fee );

		if ( ! preg_match( '/^(?:0|[1-9]\d*)(?:\.\d{1,4})?$/', $fee ) ) {
			return null;
		}

		return wc_format_decimal( $fee, 4 );
	}
}
