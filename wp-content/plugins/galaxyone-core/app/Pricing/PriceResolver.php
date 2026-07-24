<?php
/**
 * Authoritative product-price resolver.
 *
 * @package GalaxyOne\Core\Pricing
 */

namespace GalaxyOne\Core\Pricing;

use GalaxyOne\Core\Offers\OfferEligibilityService;
use GalaxyOne\Core\Products\ProductCategoryResolver;
use WC_Product;

final class PriceResolver {

	/**
	 * Resolves the current server-side selling price for a product.
	 *
	 * A rewarded offer is accepted only as a server-created, verified context.
	 * No request handler in this phase accepts rewarded-offer data from a browser.
	 *
	 * @param int                       $product_id     WooCommerce product or variation ID.
	 * @param array<string, mixed>|null $rewarded_offer Trusted rewarded-offer context.
	 * @return array<string, int|string>|null
	 */
	public static function resolve( int $product_id, ?array $rewarded_offer = null ): ?array {
		$catalog_product_id = ProductCategoryResolver::get_catalog_product_id( $product_id );
		$normal_price       = self::get_normal_price( $catalog_product_id );

		if ( $catalog_product_id <= 0 || null === $normal_price ) {
			return null;
		}

		$resolved = array(
			'product_id'   => $catalog_product_id,
			'normal_price' => $normal_price,
			'price'        => $normal_price,
			'source'       => 'normal',
			'campaign_key' => '',
		);

		$scheduled_offer = OfferEligibilityService::get_scheduled_product_offer(
			$catalog_product_id,
			$normal_price
		);

		if ( is_array( $scheduled_offer ) ) {
			$resolved['price']        = $scheduled_offer['price'];
			$resolved['source']       = 'scheduled_offer';
			$resolved['campaign_key'] = $scheduled_offer['campaign_key'];
		}

		$rewarded_price = self::get_verified_rewarded_price(
			$catalog_product_id,
			$normal_price,
			$rewarded_offer
		);

		if ( is_array( $rewarded_price ) ) {
			$resolved['price']        = $rewarded_price['price'];
			$resolved['source']       = 'rewarded_offer';
			$resolved['campaign_key'] = $rewarded_price['campaign_key'];
		}

		return $resolved;
	}

	/**
	 * Returns the normal price for a GalaxyOne product.
	 *
	 * @param int $product_id Catalog product ID.
	 * @return string|null
	 */
	private static function get_normal_price( int $product_id ): ?string {
		if ( $product_id <= 0 ) {
			return null;
		}

		if ( ProductCategoryResolver::is_water( $product_id ) ) {
			return WaterPriceService::get_normal_price( $product_id );
		}

		if ( ProductCategoryResolver::is_bloom( $product_id ) ) {
			$daily_price = DailyFlowerPriceRepository::get_current_for_product( $product_id );

			return is_array( $daily_price ) ? (string) $daily_price['price'] : null;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product instanceof WC_Product ) {
			return null;
		}

		$regular_price = $product->get_regular_price();

		return '' === $regular_price
			? null
			: DailyFlowerPriceRepository::normalize_price( $regular_price );
	}

	/**
	 * Returns a valid server-verified rewarded price when one is supplied.
	 *
	 * @param int                       $product_id     Catalog product ID.
	 * @param string                    $normal_price   Product normal price.
	 * @param array<string, mixed>|null $rewarded_offer Trusted rewarded-offer context.
	 * @return array<string, string>|null
	 */
	private static function get_verified_rewarded_price(
		int $product_id,
		string $normal_price,
		?array $rewarded_offer
	): ?array {
		if (
			! is_array( $rewarded_offer ) ||
			empty( $rewarded_offer['is_verified'] ) ||
			! isset( $rewarded_offer['product_id'], $rewarded_offer['price'] ) ||
			! is_scalar( $rewarded_offer['product_id'] ) ||
			! is_scalar( $rewarded_offer['price'] )
		) {
			return null;
		}

		$rewarded_product_id = ProductCategoryResolver::get_catalog_product_id(
			absint( $rewarded_offer['product_id'] )
		);
		$rewarded_price      = DailyFlowerPriceRepository::normalize_price(
			$rewarded_offer['price']
		);

		if (
			$product_id !== $rewarded_product_id ||
			null === $rewarded_price ||
			! self::is_lower_price( $rewarded_price, $normal_price )
		) {
			return null;
		}

		$campaign_key = isset( $rewarded_offer['campaign_key'] ) && is_scalar( $rewarded_offer['campaign_key'] )
			? sanitize_title( (string) $rewarded_offer['campaign_key'] )
			: '';

		if ( '' === $campaign_key ) {
			return null;
		}

		return array(
			'price'        => $rewarded_price,
			'campaign_key' => $campaign_key,
		);
	}

	/**
	 * Determines whether a candidate selling price is lower than normal price.
	 *
	 * @param string $candidate_price Candidate price.
	 * @param string $normal_price    Normal price.
	 * @return bool
	 */
	private static function is_lower_price( string $candidate_price, string $normal_price ): bool {
		return (float) $candidate_price < (float) $normal_price;
	}
}
