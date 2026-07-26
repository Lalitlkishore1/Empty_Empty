<?php
/**
 * Staging advertisement provider.
 *
 * @package GalaxyOne\Core\RewardedAds\Providers
 */

namespace GalaxyOne\Core\RewardedAds\Providers;

use GalaxyOne\Core\RewardedAds\Contracts\AdvertisementProviderInterface;

final class StagingAdvertisementProvider implements AdvertisementProviderInterface {

	/**
	 * Returns the provider key.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'staging';
	}

	/**
	 * Starts a staging reward interaction.
	 *
	 * @param array<string, mixed> $campaign Reward campaign.
	 * @param array<string, mixed> $event    Reward event.
	 * @return array<string, mixed>
	 */
	public function begin( array $campaign, array $event ): array {
		if ( ! $this->is_staging_environment() ) {
			return array(
				'provider_key' => $this->get_key(),
				'message'      => __( 'This optional reward is unavailable.', 'galaxyone-core' ),
				'event_token'  => '',
				'campaign_key' => '',
			);
		}

		return array(
			'provider_key' => $this->get_key(),
			'message'      => __(
				'The staging reward is ready for verified test completion.',
				'galaxyone-core'
			),
			'event_token'  => (string) $event['event_token'],
			'campaign_key' => (string) $campaign['campaign_key'],
		);
	}

	/**
	 * Verifies a staging completion request.
	 *
	 * The staging endpoint itself represents the test-provider callback. It
	 * accepts only the authenticated reward event handled by GalaxyOne.
	 *
	 * @param array<string, mixed> $campaign Reward campaign.
	 * @param array<string, mixed> $event    Reward event.
	 * @param array<string, mixed> $payload  Provider completion payload.
	 * @return bool
	 */
	public function verify_completion( array $campaign, array $event, array $payload ): bool {
		unset( $campaign, $payload );

		return $this->is_staging_environment() &&
			isset( $event['event_token'] ) &&
			is_string( $event['event_token'] ) &&
			wp_is_uuid( $event['event_token'] );
	}

	/**
	 * Determines whether staging reward behavior is permitted.
	 *
	 * @return bool
	 */
	private function is_staging_environment(): bool {
		return in_array(
			wp_get_environment_type(),
			array( 'local', 'development', 'staging' ),
			true
		);
	}
}
