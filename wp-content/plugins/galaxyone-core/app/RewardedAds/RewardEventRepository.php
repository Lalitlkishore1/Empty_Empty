<?php
/**
 * Reward event repository.
 *
 * @package GalaxyOne\Core\RewardedAds
 */

namespace GalaxyOne\Core\RewardedAds;

use GalaxyOne\Core\Database\Migrations\CreateRewardEventsTable;

final class RewardEventRepository {

	/**
	 * Creates a pending reward event.
	 *
	 * @param array<string, int|string> $campaign     Reward campaign.
	 * @param string                    $customer_hash Customer identity hash.
	 * @return array<string, int|string>|null
	 */
	public static function create_pending( array $campaign, string $customer_hash ): ?array {
		global $wpdb;

		$event_token = wp_generate_uuid4();
		$expires_at  = gmdate(
			'Y-m-d H:i:s',
			time() + ( (int) $campaign['reward_ttl_minutes'] * MINUTE_IN_SECONDS )
		);
		$now         = current_time( 'mysql', true );
		$inserted    = $wpdb->insert(
			CreateRewardEventsTable::get_table_name(),
			array(
				'event_token'           => $event_token,
				'campaign_key'          => (string) $campaign['campaign_key'],
				'product_id'            => (int) $campaign['product_id'],
				'customer_hash'         => $customer_hash,
				'provider_key'          => (string) $campaign['provider_key'],
				'status'                => 'pending',
				'completion_count'      => 0,
				'required_completions'  => (int) $campaign['required_completions'],
				'offer_price'           => (string) $campaign['offer_price'],
				'expires_at'            => $expires_at,
				'created_at'            => $now,
				'updated_at'            => $now,
			),
			array(
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		if ( false === $inserted ) {
			return null;
		}

		return self::get_by_token( $event_token, $customer_hash );
	}

	/**
	 * Returns a customer-owned reward event.
	 *
	 * @param string $event_token   Event token.
	 * @param string $customer_hash Customer identity hash.
	 * @return array<string, int|string>|null
	 */
	public static function get_by_token( string $event_token, string $customer_hash ): ?array {
		global $wpdb;

		if ( ! wp_is_uuid( $event_token ) ) {
			return null;
		}

		$table_name = CreateRewardEventsTable::get_table_name();
		$event      = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT event_token, campaign_key, product_id, customer_hash, provider_key, status, completion_count, required_completions, offer_price, order_id, expires_at
				FROM {$table_name}
				WHERE event_token = %s
					AND customer_hash = %s
				LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$event_token,
				$customer_hash
			),
			ARRAY_A
		);

		return is_array( $event ) ? self::format_event( $event ) : null;
	}

	/**
	 * Returns an unlocked event for a product and customer.
	 *
	 * @param int    $product_id    Catalog product ID.
	 * @param string $customer_hash Customer identity hash.
	 * @return array<string, int|string>|null
	 */
	public static function get_unlocked_for_product( int $product_id, string $customer_hash ): ?array {
		global $wpdb;

		$table_name = CreateRewardEventsTable::get_table_name();
		$event      = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT event_token, campaign_key, product_id, customer_hash, provider_key, status, completion_count, required_completions, offer_price, order_id, expires_at
				FROM {$table_name}
				WHERE product_id = %d
					AND customer_hash = %s
					AND status = %s
					AND expires_at > %s
				ORDER BY completed_at DESC, id DESC
				LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$product_id,
				$customer_hash,
				'completed',
				current_time( 'mysql', true )
			),
			ARRAY_A
		);

		return is_array( $event ) ? self::format_event( $event ) : null;
	}

	/**
	 * Marks a pending event as completed after provider verification.
	 *
	 * @param string $event_token   Event token.
	 * @param string $customer_hash Customer identity hash.
	 * @return bool
	 */
	public static function mark_completed( string $event_token, string $customer_hash ): bool {
		global $wpdb;

		$table_name = CreateRewardEventsTable::get_table_name();
		$updated    = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name}
				SET completion_count = required_completions,
					status = %s,
					completed_at = %s,
					updated_at = %s
				WHERE event_token = %s
					AND customer_hash = %s
					AND status = %s
					AND expires_at > %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'completed',
				current_time( 'mysql', true ),
				current_time( 'mysql', true ),
				$event_token,
				$customer_hash,
				'pending',
				current_time( 'mysql', true )
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return 1 === $updated;
	}

	/**
	 * Redeems a completed reward event exactly once.
	 *
	 * @param string $event_token   Event token.
	 * @param string $customer_hash Customer identity hash.
	 * @param int    $order_id      WooCommerce order ID.
	 * @return bool
	 */
	public static function redeem( string $event_token, string $customer_hash, int $order_id ): bool {
		global $wpdb;

		if ( ! wp_is_uuid( $event_token ) || $order_id <= 0 ) {
			return false;
		}

		$table_name = CreateRewardEventsTable::get_table_name();
		$updated    = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name}
				SET status = %s,
					order_id = %d,
					redeemed_at = %s,
					updated_at = %s
				WHERE event_token = %s
					AND customer_hash = %s
					AND status = %s
					AND expires_at > %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'redeemed',
				$order_id,
				current_time( 'mysql', true ),
				current_time( 'mysql', true ),
				$event_token,
				$customer_hash,
				'completed',
				current_time( 'mysql', true )
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return 1 === $updated;
	}

	/**
	 * Expires pending and completed events whose reward validity elapsed.
	 *
	 * @return int
	 */
	public static function expire_events(): int {
		global $wpdb;

		$table_name = CreateRewardEventsTable::get_table_name();

		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name}
				SET status = %s,
					updated_at = %s
				WHERE status IN (%s, %s)
					AND expires_at <= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'expired',
				current_time( 'mysql', true ),
				'pending',
				'completed',
				current_time( 'mysql', true )
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Formats a database reward event.
	 *
	 * @param array<string, mixed> $event Database event.
	 * @return array<string, int|string>
	 */
	private static function format_event( array $event ): array {
		return array(
			'event_token'          => (string) $event['event_token'],
			'campaign_key'         => (string) $event['campaign_key'],
			'product_id'           => (int) $event['product_id'],
			'customer_hash'        => (string) $event['customer_hash'],
			'provider_key'         => (string) $event['provider_key'],
			'status'               => (string) $event['status'],
			'completion_count'     => (int) $event['completion_count'],
			'required_completions' => (int) $event['required_completions'],
			'offer_price'          => (string) $event['offer_price'],
			'order_id'             => (int) $event['order_id'],
			'expires_at'           => (string) $event['expires_at'],
		);
	}
}
