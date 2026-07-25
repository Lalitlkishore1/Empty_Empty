<?php
/**
 * Reward campaign repository.
 *
 * @package GalaxyOne\Core\RewardedAds
 */

namespace GalaxyOne\Core\RewardedAds;

use DateTimeImmutable;
use DateTimeZone;
use GalaxyOne\Core\Database\Migrations\CreateRewardCampaignsTable;
use GalaxyOne\Core\Pricing\PriceResolver;
use GalaxyOne\Core\Products\ProductCategoryResolver;
use WC_Product;

final class RewardCampaignRepository {

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
	 * Saves a reward campaign.
	 *
	 * @param array<string, mixed> $campaign Submitted campaign values.
	 * @return bool
	 */
	public static function save( array $campaign ): bool {
		global $wpdb;

		$campaign = self::normalize_campaign( $campaign );

		if ( ! is_array( $campaign ) ) {
			return false;
		}

		$table_name = CreateRewardCampaignsTable::get_table_name();
		$now        = current_time( 'mysql', true );
		$query      = $wpdb->prepare(
			"INSERT INTO {$table_name}
				(campaign_key, name, product_id, offer_price, provider_key, required_completions, reward_ttl_minutes, status, starts_at, ends_at, created_at, updated_at)
			VALUES (%s, %s, %d, %s, %s, %d, %d, %s, NULLIF(%s, ''), NULLIF(%s, ''), %s, %s)
			ON DUPLICATE KEY UPDATE
				name = %s,
				product_id = %d,
				offer_price = %s,
				provider_key = %s,
				required_completions = %d,
				reward_ttl_minutes = %d,
				status = %s,
				starts_at = NULLIF(%s, ''),
				ends_at = NULLIF(%s, ''),
				updated_at = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$campaign['campaign_key'],
			$campaign['name'],
			$campaign['product_id'],
			$campaign['offer_price'],
			$campaign['provider_key'],
			$campaign['required_completions'],
			$campaign['reward_ttl_minutes'],
			$campaign['status'],
			$campaign['starts_at'],
			$campaign['ends_at'],
			$now,
			$now,
			$campaign['name'],
			$campaign['product_id'],
			$campaign['offer_price'],
			$campaign['provider_key'],
			$campaign['required_completions'],
			$campaign['reward_ttl_minutes'],
			$campaign['status'],
			$campaign['starts_at'],
			$campaign['ends_at'],
			$now
		);

		return false !== $wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Returns all reward campaigns for administration.
	 *
	 * @return array<int, array<string, int|string>>
	 */
	public static function get_campaigns(): array {
		global $wpdb;

		$table_name = CreateRewardCampaignsTable::get_table_name();
		$campaigns  = $wpdb->get_results(
			"SELECT campaign_key, name, product_id, offer_price, provider_key, required_completions, reward_ttl_minutes, status, starts_at, ends_at
			FROM {$table_name}
			ORDER BY updated_at DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! is_array( $campaigns ) ) {
			return array();
		}

		return array_map(
			static fn( array $campaign ): array => self::format_campaign( $campaign ),
			$campaigns
		);
	}

	/**
	 * Returns the currently active campaign for a catalog product.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return array<string, int|string>|null
	 */
	public static function get_current_for_product( int $product_id ): ?array {
		global $wpdb;

		$product_id = ProductCategoryResolver::get_catalog_product_id( $product_id );

		if ( $product_id <= 0 ) {
			return null;
		}

		$table_name = CreateRewardCampaignsTable::get_table_name();
		$now        = current_time( 'mysql', true );
		$campaign   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT campaign_key, name, product_id, offer_price, provider_key, required_completions, reward_ttl_minutes, status, starts_at, ends_at
				FROM {$table_name}
				WHERE product_id = %d
					AND status = %s
					AND (starts_at IS NULL OR starts_at <= %s)
					AND (ends_at IS NULL OR ends_at > %s)
				ORDER BY offer_price ASC, id ASC
				LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
	 * Returns a campaign by its key.
	 *
	 * @param string $campaign_key Campaign key.
	 * @return array<string, int|string>|null
	 */
	public static function get_campaign( string $campaign_key ): ?array {
		global $wpdb;

		$campaign_key = sanitize_title( $campaign_key );

		if ( '' === $campaign_key ) {
			return null;
		}

		$table_name = CreateRewardCampaignsTable::get_table_name();
		$campaign   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT campaign_key, name, product_id, offer_price, provider_key, required_completions, reward_ttl_minutes, status, starts_at, ends_at
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
	 * Determines whether a campaign remains active.
	 *
	 * @param array<string, int|string> $campaign Campaign.
	 * @return bool
	 */
	public static function is_current( array $campaign ): bool {
		$now       = current_time( 'mysql', true );
		$starts_at = (string) $campaign['starts_at'];
		$ends_at   = (string) $campaign['ends_at'];

		return self::STATUS_ACTIVE === $campaign['status'] &&
			( '' === $starts_at || $starts_at <= $now ) &&
			( '' === $ends_at || $ends_at > $now );
	}

	/**
	 * Validates and normalizes a campaign.
	 *
	 * @param array<string, mixed> $campaign Submitted values.
	 * @return array<string, int|string>|null
	 */
	private static function normalize_campaign( array $campaign ): ?array {
		$campaign_key = isset( $campaign['campaign_key'] ) && is_scalar( $campaign['campaign_key'] )
			? sanitize_title( (string) $campaign['campaign_key'] )
			: '';
		$name         = isset( $campaign['name'] ) && is_scalar( $campaign['name'] )
			? sanitize_text_field( (string) $campaign['name'] )
			: '';
		$product_id   = isset( $campaign['product_id'] )
			? ProductCategoryResolver::get_catalog_product_id( absint( $campaign['product_id'] ) )
			: 0;
		$offer_price  = isset( $campaign['offer_price'] ) && is_scalar( $campaign['offer_price'] )
			? wc_format_decimal( (string) $campaign['offer_price'], 4 )
			: '';
		$provider_key = isset( $campaign['provider_key'] ) && is_scalar( $campaign['provider_key'] )
			? sanitize_key( (string) $campaign['provider_key'] )
			: '';
		$status       = isset( $campaign['status'] ) && is_scalar( $campaign['status'] )
			? sanitize_key( (string) $campaign['status'] )
			: '';
		$completions  = isset( $campaign['required_completions'] )
			? absint( $campaign['required_completions'] )
			: 0;
		$ttl          = isset( $campaign['reward_ttl_minutes'] )
			? absint( $campaign['reward_ttl_minutes'] )
			: 0;
		$starts_at    = self::normalize_datetime( $campaign['starts_at'] ?? '' );
		$ends_at      = self::normalize_datetime( $campaign['ends_at'] ?? '' );
		$product      = wc_get_product( $product_id );
		$current      = PriceResolver::resolve( $product_id );

		if (
			'' === $campaign_key ||
			'' === $name ||
			! $product instanceof WC_Product ||
			! is_array( $current ) ||
			'' === $offer_price ||
			(float) $offer_price >= (float) $current['price'] ||
			'staging' !== $provider_key ||
			$completions < 1 ||
			$completions > 10 ||
			$ttl < 1 ||
			$ttl > 1440 ||
			! in_array( $status, array( self::STATUS_ACTIVE, self::STATUS_PAUSED ), true ) ||
			( null !== $starts_at && null !== $ends_at && $starts_at >= $ends_at )
		) {
			return null;
		}

		return array(
			'campaign_key'          => $campaign_key,
			'name'                  => $name,
			'product_id'            => $product_id,
			'offer_price'           => $offer_price,
			'provider_key'          => $provider_key,
			'required_completions'  => $completions,
			'reward_ttl_minutes'    => $ttl,
			'status'                => $status,
			'starts_at'             => $starts_at ?? '',
			'ends_at'               => $ends_at ?? '',
		);
	}

	/**
	 * Normalizes an optional local datetime input to UTC.
	 *
	 * @param mixed $datetime Datetime value.
	 * @return string|null
	 */
	private static function normalize_datetime( $datetime ): ?string {
		if ( ! is_scalar( $datetime ) || '' === trim( (string) $datetime ) ) {
			return null;
		}

		$date = DateTimeImmutable::createFromFormat(
			'!Y-m-d\TH:i',
			(string) $datetime,
			wp_timezone()
		);

		if ( false === $date ) {
			return null;
		}

		return $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Formats one database campaign.
	 *
	 * @param array<string, mixed> $campaign Database campaign.
	 * @return array<string, int|string>
	 */
	private static function format_campaign( array $campaign ): array {
		return array(
			'campaign_key'         => (string) $campaign['campaign_key'],
			'name'                 => (string) $campaign['name'],
			'product_id'           => (int) $campaign['product_id'],
			'offer_price'          => (string) $campaign['offer_price'],
			'provider_key'         => (string) $campaign['provider_key'],
			'required_completions' => (int) $campaign['required_completions'],
			'reward_ttl_minutes'   => (int) $campaign['reward_ttl_minutes'],
			'status'               => (string) $campaign['status'],
			'starts_at'            => null === $campaign['starts_at'] ? '' : (string) $campaign['starts_at'],
			'ends_at'              => null === $campaign['ends_at'] ? '' : (string) $campaign['ends_at'],
		);
	}
}
