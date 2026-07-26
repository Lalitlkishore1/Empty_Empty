<?php
/**
 * Reward completion service.
 *
 * @package GalaxyOne\Core\RewardedAds
 */

namespace GalaxyOne\Core\RewardedAds;

use GalaxyOne\Core\ActivityLog\ActivityLogRepository;
use GalaxyOne\Core\RewardedAds\Contracts\AdvertisementProviderInterface;
use GalaxyOne\Core\RewardedAds\Providers\StagingAdvertisementProvider;
use WP_Error;

final class RewardCompletionService {

	/**
	 * Starts a reward interaction for an eligible product.
	 *
	 * @param int $product_id WooCommerce product or variation ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function begin( int $product_id ): array|WP_Error {
		if ( ! self::is_staging_environment() ) {
			return new WP_Error(
				'galaxyone_reward_unavailable',
				__( 'This optional reward is unavailable.', 'galaxyone-core' )
			);
		}

		$campaign = RewardEligibilityService::get_campaign( $product_id );

		if ( ! is_array( $campaign ) ) {
			return new WP_Error(
				'galaxyone_reward_unavailable',
				__( 'No optional rewarded offer is currently available for this product.', 'galaxyone-core' )
			);
		}

		$customer_hash = RewardEligibilityService::get_customer_hash();
		$event         = RewardEventRepository::create_pending( $campaign, $customer_hash );

		if ( ! is_array( $event ) ) {
			return new WP_Error(
				'galaxyone_reward_start_failed',
				__( 'The rewarded offer could not be started. Please try again.', 'galaxyone-core' )
			);
		}

		$provider = self::get_provider( (string) $campaign['provider_key'] );

		if ( ! $provider instanceof AdvertisementProviderInterface ) {
			return new WP_Error(
				'galaxyone_reward_provider_unavailable',
				__( 'The rewarded offer provider is currently unavailable.', 'galaxyone-core' )
			);
		}

		$response = $provider->begin( $campaign, $event );

		ActivityLogRepository::record(
			'reward_event_started',
			array(),
			array(
				'event_token'  => $event['event_token'],
				'campaign_key' => $event['campaign_key'],
				'product_id'   => $event['product_id'],
			),
			array(
				'source' => 'rewarded_ads',
			)
		);

		return $response;
	}

	/**
	 * Completes a reward after provider verification.
	 *
	 * @param string               $event_token Event token.
	 * @param array<string, mixed> $payload     Provider completion payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function complete( string $event_token, array $payload = array() ): array|WP_Error {
		if ( ! self::is_staging_environment() ) {
			return new WP_Error(
				'galaxyone_reward_unavailable',
				__( 'This optional reward is unavailable.', 'galaxyone-core' )
			);
		}

		$customer_hash = RewardEligibilityService::get_customer_hash();
		$event         = RewardEventRepository::get_by_token( $event_token, $customer_hash );

		if ( ! is_array( $event ) || 'pending' !== $event['status'] ) {
			return new WP_Error(
				'galaxyone_reward_event_invalid',
				__( 'This reward interaction is no longer available.', 'galaxyone-core' )
			);
		}

		$campaign = RewardCampaignRepository::get_campaign( (string) $event['campaign_key'] );

		if ( ! is_array( $campaign ) || ! RewardCampaignRepository::is_current( $campaign ) ) {
			return new WP_Error(
				'galaxyone_reward_campaign_inactive',
				__( 'This rewarded offer is no longer active.', 'galaxyone-core' )
			);
		}

		$provider = self::get_provider( (string) $event['provider_key'] );

		if (
			! $provider instanceof AdvertisementProviderInterface ||
			! $provider->verify_completion( $campaign, $event, $payload ) ||
			! RewardEventRepository::mark_completed( $event_token, $customer_hash )
		) {
			return new WP_Error(
				'galaxyone_reward_completion_failed',
				__( 'The reward was not verified. You can retry or continue at the normal price.', 'galaxyone-core' )
			);
		}

		ActivityLogRepository::record(
			'reward_event_completed',
			array(
				'status' => 'pending',
			),
			array(
				'event_token'  => $event['event_token'],
				'campaign_key' => $event['campaign_key'],
				'product_id'   => $event['product_id'],
			),
			array(
				'source' => 'rewarded_ads',
			)
		);

		return array(
			'event_token'  => $event['event_token'],
			'campaign_key' => $event['campaign_key'],
			'product_id'   => $event['product_id'],
			'message'      => __( 'Your optional rewarded offer is unlocked.', 'galaxyone-core' ),
		);
	}

	/**
	 * Returns a supported provider.
	 *
	 * @param string $provider_key Provider key.
	 * @return AdvertisementProviderInterface|null
	 */
	private static function get_provider( string $provider_key ): ?AdvertisementProviderInterface {
		if ( 'staging' === $provider_key && self::is_staging_environment() ) {
			return new StagingAdvertisementProvider();
		}

		return null;
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
