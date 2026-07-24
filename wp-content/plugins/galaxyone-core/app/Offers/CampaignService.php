<?php
/**
 * Offer campaign service.
 *
 * @package GalaxyOne\Core\Offers
 */

namespace GalaxyOne\Core\Offers;

use DateTimeImmutable;
use DateTimeZone;
use GalaxyOne\Core\Database\Migrations\CreateOfferCampaignsTable;
use GalaxyOne\Core\Pricing\DailyFlowerPriceRepository;
use WC_Product;

final class CampaignService {

	/**
	 * Scheduled product-price campaign type.
	 *
	 * @var string
	 */
	public const TYPE_PRODUCT_PRICE = 'product_price';

	/**
	 * Free-delivery campaign type.
	 *
	 * @var string
	 */
	public const TYPE_FREE_DELIVERY = 'free_delivery';

	/**
	 * Active campaign status.
	 *
	 * @var string
	 */
	public const STATUS_ACTIVE = 'active';

	/**
	 * Paused campaign status.
	 *
	 * @var string
	 */
	public const STATUS_PAUSED = 'paused';

	/**
	 * Saves a campaign after validating its configuration.
	 *
	 * @param array<string, mixed> $campaign Submitted campaign values.
	 * @return bool
	 */
	public static function save_campaign( array $campaign ): bool {
		global $wpdb;

		$campaign = self::normalize_campaign( $campaign );

		if ( ! is_array( $campaign ) ) {
			return false;
		}

		$table_name = CreateOfferCampaignsTable::get_table_name();
		$now        = current_time( 'mysql', true );
		$query      = $wpdb->prepare(
			"INSERT INTO {$table_name}
				(campaign_key, name, campaign_type, product_id, offer_price, status, starts_at, ends_at, created_at, updated_at)
			VALUES (%s, %s, %s, %d, NULLIF(%s, ''), %s, NULLIF(%s, ''), NULLIF(%s, ''), %s, %s)
			ON DUPLICATE KEY UPDATE
				name = %s,
				campaign_type = %s,
				product_id = %d,
				offer_price = NULLIF(%s, ''),
				status = %s,
				starts_at = NULLIF(%s, ''),
				ends_at = NULLIF(%s, ''),
				updated_at = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$campaign['campaign_key'],
			$campaign['name'],
			$campaign['campaign_type'],
			$campaign['product_id'],
			$campaign['offer_price'],
			$campaign['status'],
			$campaign['starts_at'],
			$campaign['ends_at'],
			$now,
			$now,
			$campaign['name'],
			$campaign['campaign_type'],
			$campaign['product_id'],
			$campaign['offer_price'],
			$campaign['status'],
			$campaign['starts_at'],
			$campaign['ends_at'],
			$now
		);

		return false !== $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Deletes one campaign.
	 *
	 * @param string $campaign_key Campaign identifier.
	 * @return bool
	 */
	public static function delete_campaign( string $campaign_key ): bool {
		global $wpdb;

		$campaign_key = sanitize_title( $campaign_key );

		if ( '' === $campaign_key ) {
			return false;
		}

		$table_name = CreateOfferCampaignsTable::get_table_name();
		$deleted    = $wpdb->delete(
			$table_name,
			array(
				'campaign_key' => $campaign_key,
			),
			array( '%s' )
		);

		return 1 === $deleted;
	}

	/**
	 * Returns one campaign, including paused and scheduled campaigns.
	 *
	 * @param string $campaign_key Campaign identifier.
	 * @return array<string, int|string>|null
	 */
	public static function get_campaign( string $campaign_key ): ?array {
		global $wpdb;

		$campaign_key = sanitize_title( $campaign_key );

		if ( '' === $campaign_key ) {
			return null;
		}

		$table_name = CreateOfferCampaignsTable::get_table_name();
		$campaign   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT campaign_key, name, campaign_type, product_id, offer_price, status, starts_at, ends_at
				FROM {$table_name}
				WHERE campaign_key = %s
				LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$campaign_key
			),
			ARRAY_A
		);

		return is_array( $campaign ) ? self::format_campaign( $campaign ) : null;
	}

	/**
	 * Returns all campaigns for administration.
	 *
	 * @return array<int, array<string, int|string>>
	 */
	public static function get_campaigns(): array {
		global $wpdb;

		$table_name = CreateOfferCampaignsTable::get_table_name();
		$campaigns  = $wpdb->get_results(
			"SELECT campaign_key, name, campaign_type, product_id, offer_price, status, starts_at, ends_at
			FROM {$table_name}
			ORDER BY updated_at DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! is_array( $campaigns ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static fn( array $campaign ): array => self::format_campaign( $campaign ),
					$campaigns
				)
			)
		);
	}

	/**
	 * Returns the currently applicable scheduled product-price campaign.
	 *
	 * @param int $product_id Catalog product ID.
	 * @return array<string, int|string>|null
	 */
	public static function get_current_product_campaign( int $product_id ): ?array {
		global $wpdb;

		if ( $product_id <= 0 ) {
			return null;
		}

		$table_name = CreateOfferCampaignsTable::get_table_name();
		$now        = current_time( 'mysql', true );
		$campaign   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT campaign_key, name, campaign_type, product_id, offer_price, status, starts_at, ends_at
				FROM {$table_name}
				WHERE campaign_type = %s
					AND product_id = %d
					AND status = %s
					AND (starts_at IS NULL OR starts_at <= %s)
					AND (ends_at IS NULL OR ends_at > %s)
				ORDER BY offer_price ASC, id ASC
				LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::TYPE_PRODUCT_PRICE,
				$product_id,
				self::STATUS_ACTIVE,
				$now,
				$now
			),
			ARRAY_A
		);

		return is_array( $campaign ) ? self::format_campaign( $campaign ) : null;
	}

	/**
	 * Returns currently applicable free-delivery campaigns.
	 *
	 * @return array<int, array<string, int|string>>
	 */
	public static function get_current_free_delivery_campaigns(): array {
		global $wpdb;

		$table_name = CreateOfferCampaignsTable::get_table_name();
		$now        = current_time( 'mysql', true );
		$campaigns  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT campaign_key, name, campaign_type, product_id, offer_price, status, starts_at, ends_at
				FROM {$table_name}
				WHERE campaign_type = %s
					AND status = %s
					AND (starts_at IS NULL OR starts_at <= %s)
					AND (ends_at IS NULL OR ends_at > %s)
				ORDER BY product_id ASC, id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::TYPE_FREE_DELIVERY,
				self::STATUS_ACTIVE,
				$now,
				$now
			),
			ARRAY_A
		);

		if ( ! is_array( $campaigns ) ) {
			return array();
		}

		return array_values(
			array_map(
				static fn( array $campaign ): array => self::format_campaign( $campaign ),
				$campaigns
			)
		);
	}

	/**
	 * Determines whether a campaign is active at the current time.
	 *
	 * @param array<string, int|string> $campaign Campaign record.
	 * @return bool
	 */
	public static function is_currently_active( array $campaign ): bool {
		if ( self::STATUS_ACTIVE !== $campaign['status'] ) {
			return false;
		}

		$now       = current_time( 'mysql', true );
		$starts_at = (string) $campaign['starts_at'];
		$ends_at   = (string) $campaign['ends_at'];

		return ( '' === $starts_at || $starts_at <= $now ) &&
			( '' === $ends_at || $ends_at > $now );
	}

	/**
	 * Formats a stored UTC datetime for an administration datetime-local input.
	 *
	 * @param string $datetime Stored UTC datetime.
	 * @return string
	 */
	public static function format_datetime_for_input( string $datetime ): string {
		if ( '' === $datetime ) {
			return '';
		}

		$date = DateTimeImmutable::createFromFormat(
			'Y-m-d H:i:s',
			$datetime,
			new DateTimeZone( 'UTC' )
		);

		if ( false === $date ) {
			return '';
		}

		return wp_date( 'Y-m-d\TH:i', $date->getTimestamp(), wp_timezone() );
	}

	/**
	 * Validates and normalizes campaign values.
	 *
	 * @param array<string, mixed> $campaign Campaign values.
	 * @return array<string, int|string>|null
	 */
	private static function normalize_campaign( array $campaign ): ?array {
		$campaign_key = isset( $campaign['campaign_key'] ) && is_scalar( $campaign['campaign_key'] )
			? sanitize_title( (string) $campaign['campaign_key'] )
			: '';
		$name         = isset( $campaign['name'] ) && is_scalar( $campaign['name'] )
			? sanitize_text_field( (string) $campaign['name'] )
			: '';
		$campaign_type = isset( $campaign['campaign_type'] ) && is_scalar( $campaign['campaign_type'] )
			? sanitize_key( (string) $campaign['campaign_type'] )
			: '';
		$status        = isset( $campaign['status'] ) && is_scalar( $campaign['status'] )
			? sanitize_key( (string) $campaign['status'] )
			: '';
		$product_id    = isset( $campaign['product_id'] ) ? absint( $campaign['product_id'] ) : 0;
		$raw_price     = isset( $campaign['offer_price'] ) && is_scalar( $campaign['offer_price'] )
			? (string) $campaign['offer_price']
			: '';
		$raw_starts_at = isset( $campaign['starts_at'] ) && is_scalar( $campaign['starts_at'] )
			? trim( (string) $campaign['starts_at'] )
			: '';
		$raw_ends_at   = isset( $campaign['ends_at'] ) && is_scalar( $campaign['ends_at'] )
			? trim( (string) $campaign['ends_at'] )
			: '';

		if (
			'' === $campaign_key ||
			'' === $name ||
			! in_array(
				$campaign_type,
				array( self::TYPE_PRODUCT_PRICE, self::TYPE_FREE_DELIVERY ),
				true
			) ||
			! in_array( $status, array( self::STATUS_ACTIVE, self::STATUS_PAUSED ), true )
		) {
			return null;
		}

		$starts_at = self::normalize_datetime( $raw_starts_at );
		$ends_at   = self::normalize_datetime( $raw_ends_at );

		if (
			( '' !== $raw_starts_at && null === $starts_at ) ||
			( '' !== $raw_ends_at && null === $ends_at ) ||
			( null !== $starts_at && null !== $ends_at && $starts_at >= $ends_at )
		) {
			return null;
		}

		if ( self::TYPE_PRODUCT_PRICE === $campaign_type ) {
			$product     = wc_get_product( $product_id );
			$offer_price = DailyFlowerPriceRepository::normalize_price( $raw_price );

			if ( ! $product instanceof WC_Product || null === $offer_price ) {
				return null;
			}
		} else {
			if ( $product_id > 0 && ! wc_get_product( $product_id ) instanceof WC_Product ) {
				return null;
			}

			$offer_price = '';
		}

		return array(
			'campaign_key'  => $campaign_key,
			'name'          => $name,
			'campaign_type' => $campaign_type,
			'product_id'    => $product_id,
			'offer_price'   => $offer_price,
			'status'        => $status,
			'starts_at'     => $starts_at ?? '',
			'ends_at'       => $ends_at ?? '',
		);
	}

	/**
	 * Normalizes a site-local datetime-local value to UTC.
	 *
	 * @param string $datetime Datetime-local value.
	 * @return string|null
	 */
	private static function normalize_datetime( string $datetime ): ?string {
		if ( '' === $datetime ) {
			return null;
		}

		$date   = DateTimeImmutable::createFromFormat(
			'!Y-m-d\TH:i',
			$datetime,
			wp_timezone()
		);
		$errors = DateTimeImmutable::getLastErrors();

		if (
			false === $date ||
			( is_array( $errors ) && ( 0 < $errors['warning_count'] || 0 < $errors['error_count'] ) )
		) {
			return null;
		}

		return $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Formats a database campaign record.
	 *
	 * @param array<string, mixed> $campaign Database campaign record.
	 * @return array<string, int|string>
	 */
	private static function format_campaign( array $campaign ): array {
		return array(
			'campaign_key'  => (string) $campaign['campaign_key'],
			'name'          => (string) $campaign['name'],
			'campaign_type' => (string) $campaign['campaign_type'],
			'product_id'    => (int) $campaign['product_id'],
			'offer_price'   => null === $campaign['offer_price'] ? '' : (string) $campaign['offer_price'],
			'status'        => (string) $campaign['status'],
			'starts_at'     => null === $campaign['starts_at'] ? '' : (string) $campaign['starts_at'],
			'ends_at'       => null === $campaign['ends_at'] ? '' : (string) $campaign['ends_at'],
		);
	}
}
