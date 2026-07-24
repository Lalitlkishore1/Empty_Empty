<?php
/**
 * Daily flower-price repository.
 *
 * @package GalaxyOne\Core\Pricing
 */

namespace GalaxyOne\Core\Pricing;

use GalaxyOne\Core\Database\Migrations\CreateFlowerDailyPricesTable;

final class DailyFlowerPriceRepository {

	/**
	 * Returns a daily price record for a product and effective date.
	 *
	 * @param int    $product_id     WooCommerce product ID.
	 * @param string $effective_date Effective date in Y-m-d format.
	 * @return array<string, int|string|bool>|null
	 */
	public static function get_for_product_date( int $product_id, string $effective_date ): ?array {
		global $wpdb;

		if ( $product_id <= 0 || ! self::is_valid_effective_date( $effective_date ) ) {
			return null;
		}

		$table_name = CreateFlowerDailyPricesTable::get_table_name();
		$record     = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT product_id, effective_date, price, is_available
				FROM {$table_name}
				WHERE product_id = %d
					AND effective_date = %s
				LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$product_id,
				$effective_date
			),
			ARRAY_A
		);

		if ( ! is_array( $record ) ) {
			return null;
		}

		return array(
			'product_id'     => (int) $record['product_id'],
			'effective_date' => (string) $record['effective_date'],
			'price'          => (string) $record['price'],
			'is_available'   => 1 === (int) $record['is_available'],
		);
	}

	/**
	 * Returns the daily price record for the current WordPress-local date.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array<string, int|string|bool>|null
	 */
	public static function get_current_for_product( int $product_id ): ?array {
		return self::get_for_product_date( $product_id, wp_date( 'Y-m-d' ) );
	}

	/**
	 * Saves a daily price and availability record.
	 *
	 * @param int    $product_id     WooCommerce product ID.
	 * @param string $effective_date Effective date in Y-m-d format.
	 * @param string $price          Non-negative decimal price.
	 * @param bool   $is_available   Whether the product can be purchased.
	 * @return bool
	 */
	public static function save(
		int $product_id,
		string $effective_date,
		string $price,
		bool $is_available
	): bool {
		global $wpdb;

		$normalized_price = self::normalize_price( $price );

		if (
			$product_id <= 0 ||
			! self::is_valid_effective_date( $effective_date ) ||
			null === $normalized_price
		) {
			return false;
		}

		$table_name = CreateFlowerDailyPricesTable::get_table_name();
		$now        = current_time( 'mysql', true );
		$query      = $wpdb->prepare(
			"INSERT INTO {$table_name}
				(product_id, effective_date, price, is_available, created_at, updated_at)
			VALUES (%d, %s, %s, %d, %s, %s)
			ON DUPLICATE KEY UPDATE
				price = %s,
				is_available = %d,
				updated_at = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$product_id,
			$effective_date,
			$normalized_price,
			$is_available ? 1 : 0,
			$now,
			$now,
			$normalized_price,
			$is_available ? 1 : 0,
			$now
		);

		$result = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return false !== $result;
	}

	/**
	 * Normalizes a valid non-negative decimal price.
	 *
	 * @param mixed $price Price to normalize.
	 * @return string|null
	 */
	public static function normalize_price( mixed $price ): ?string {
		if ( ! is_scalar( $price ) ) {
			return null;
		}

		$price = trim( (string) $price );

		if ( ! preg_match( '/^(?:0|[1-9]\d*)(?:\.\d{1,4})?$/', $price ) ) {
			return null;
		}

		return wc_format_decimal( $price, 4 );
	}

	/**
	 * Determines whether a value is a valid effective date.
	 *
	 * @param string $effective_date Effective date in Y-m-d format.
	 * @return bool
	 */
	public static function is_valid_effective_date( string $effective_date ): bool {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $effective_date ) ) {
			return false;
		}

		$date_parts = explode( '-', $effective_date );

		return checkdate(
			(int) $date_parts[1],
			(int) $date_parts[2],
			(int) $date_parts[0]
		);
	}
}
