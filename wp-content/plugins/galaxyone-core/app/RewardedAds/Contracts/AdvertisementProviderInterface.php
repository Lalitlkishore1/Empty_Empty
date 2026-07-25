<?php
/**
 * Advertisement provider contract.
 *
 * @package GalaxyOne\Core\RewardedAds\Contracts
 */

namespace GalaxyOne\Core\RewardedAds\Contracts;

interface AdvertisementProviderInterface {

	/**
	 * Returns the unique provider identifier.
	 *
	 * @return string
	 */
	public function get_key(): string;

	/**
	 * Starts a provider reward interaction.
	 *
	 * @param array<string, mixed> $campaign Reward campaign.
	 * @param array<string, mixed> $event    Reward event.
	 * @return array<string, mixed>
	 */
	public function begin( array $campaign, array $event ): array;

	/**
	 * Verifies provider-side completion for a reward event.
	 *
	 * @param array<string, mixed> $campaign Reward campaign.
	 * @param array<string, mixed> $event    Reward event.
	 * @param array<string, mixed> $payload  Provider completion payload.
	 * @return bool
	 */
	public function verify_completion( array $campaign, array $event, array $payload ): bool;
}
