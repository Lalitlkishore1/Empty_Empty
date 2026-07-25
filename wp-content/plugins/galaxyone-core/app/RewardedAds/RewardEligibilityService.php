<?php
/**
 * Reward eligibility service.
 *
 * @package GalaxyOne\Core\RewardedAds
 */

namespace GalaxyOne\Core\RewardedAds;

use GalaxyOne\Core\Products\ProductCategoryResolver;

final class RewardEligibilityService {

	/**
	 * Returns the active reward campaign for a product.
	 *
	 * @param int $product_id WooCommerce product or variation ID.
	 * @return array<string, int|string>|null
	 */
	public static function get_campaign( int $product_id ): ?array {
		$product_id = ProductCategoryResolver::get_catalog_product_id( $product_id );

		return $product_id > 0
			? RewardCampaignRepository::get_current_for_product( $product_id )
			: null;
	}

	/**
	 * Returns a server-side customer identity hash.
	 *
	 * @return string
	 */
	public static function get_customer_hash(): string {
		$user_id = get_current_user_id();

		if ( $user_id > 0 ) {
			return hash_hmac( 'sha256', 'user:' . $user_id, wp_salt( 'auth' ) );
		}

		$session_id = WC()->session ? (string) WC()->session->get_customer_id() : '';

		if ( '' === $session_id ) {
			$session_id = wp_generate_uuid4();

			if ( WC()->session ) {
				WC()->session->set( 'galaxyone_reward_guest_id', $session_id );
			}
		}

		return hash_hmac( 'sha256', 'guest:' . $session_id, wp_salt( 'auth' ) );
	}
}
