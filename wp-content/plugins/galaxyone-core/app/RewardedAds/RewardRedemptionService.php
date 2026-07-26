<?php
/**
 * Reward redemption service.
 *
 * @package GalaxyOne\Core\RewardedAds
 */

namespace GalaxyOne\Core\RewardedAds;

use GalaxyOne\Core\ActivityLog\ActivityLogRepository;
use GalaxyOne\Core\Products\ProductCategoryResolver;
use WC_Order;

final class RewardRedemptionService {

	/**
	 * Returns a trusted reward-price context for the current customer.
	 *
	 * @param int $product_id WooCommerce product or variation ID.
	 * @return array<string, int|string|bool>|null
	 */
	public static function get_rewarded_offer( int $product_id ): ?array {
		if ( ! self::is_staging_environment() ) {
			return null;
		}

		$product_id = ProductCategoryResolver::get_catalog_product_id( $product_id );

		if ( $product_id <= 0 ) {
			return null;
		}

		$event = RewardEventRepository::get_unlocked_for_product(
			$product_id,
			RewardEligibilityService::get_customer_hash()
		);

		if ( ! is_array( $event ) ) {
			return null;
		}

		$campaign = RewardCampaignRepository::get_campaign( (string) $event['campaign_key'] );

		if (
			! is_array( $campaign ) ||
			! RewardCampaignRepository::is_current( $campaign ) ||
			(string) $campaign['offer_price'] !== (string) $event['offer_price']
		) {
			return null;
		}

		return array(
			'is_verified' => true,
			'product_id'  => $product_id,
			'price'       => (string) $event['offer_price'],
			'campaign_key' => (string) $event['campaign_key'],
			'event_token' => (string) $event['event_token'],
		);
	}

	/**
	 * Redeems each reward actually used by a created order.
	 *
	 * @param WC_Order $order Created WooCommerce order.
	 * @return void
	 */
	public static function redeem_order_rewards( WC_Order $order ): void {
		if ( ! self::is_staging_environment() ) {
			return;
		}

		$customer_hash = RewardEligibilityService::get_customer_hash();

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$snapshot_json = (string) $item->get_meta( '_galaxyone_price_snapshot', true );
			$snapshot      = json_decode( $snapshot_json, true );

			if (
				! is_array( $snapshot ) ||
				'rewarded_offer' !== ( $snapshot['source'] ?? '' ) ||
				empty( $snapshot['reward_event_token'] ) ||
				! is_string( $snapshot['reward_event_token'] )
			) {
				continue;
			}

			if (
				RewardEventRepository::redeem(
					$snapshot['reward_event_token'],
					$customer_hash,
					$order->get_id()
				)
			) {
				ActivityLogRepository::record(
					'reward_event_redeemed',
					array(
						'event_token' => $snapshot['reward_event_token'],
					),
					array(
						'order_id' => $order->get_id(),
					),
					array(
						'source' => 'woocommerce_order',
					)
				);
			}
		}
	}

	/**
	 * Determines whether staging reward behavior is permitted.
	 *
	 * @return bool
	 */
	private static function is_staging_environment(): bool {
		return in_array(
			wp_get_environment_type(),
			array( 'local', 'development', 'staging' ),
			true
		);
	}
}
