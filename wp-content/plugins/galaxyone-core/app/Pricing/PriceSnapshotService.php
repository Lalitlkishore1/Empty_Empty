<?php
/**
 * Price snapshot service.
 *
 * @package GalaxyOne\Core\Pricing
 */

namespace GalaxyOne\Core\Pricing;

final class PriceSnapshotService {

	/**
	 * Creates an immutable snapshot from the authoritative price resolver.
	 *
	 * @param int                       $product_id     WooCommerce product or variation ID.
	 * @param array<string, mixed>|null $rewarded_offer Trusted rewarded-offer context.
	 * @return array<string, int|string>|null
	 */
	public static function create(
		int $product_id,
		?array $rewarded_offer = null
	): ?array {
		$resolved_price = PriceResolver::resolve( $product_id, $rewarded_offer );

		if ( ! is_array( $resolved_price ) ) {
			return null;
		}

		return array(
			'product_id'   => (int) $resolved_price['product_id'],
			'normal_price' => (string) $resolved_price['normal_price'],
			'price'        => (string) $resolved_price['price'],
			'source'       => (string) $resolved_price['source'],
			'campaign_key' => (string) $resolved_price['campaign_key'],
			'currency'     => get_woocommerce_currency(),
			'resolved_at'  => current_time( 'mysql', true ),
		);
	}
}
