<?php
/**
 * Offer eligibility service.
 *
 * @package GalaxyOne\Core\Offers
 */

namespace GalaxyOne\Core\Offers;

use GalaxyOne\Core\Products\ProductCategoryResolver;

final class OfferEligibilityService {

	/**
	 * Returns the eligible scheduled price offer for a product.
	 *
	 * @param int    $product_id   WooCommerce product or variation ID.
	 * @param string $normal_price Current server-resolved normal price.
	 * @return array<string, string>|null
	 */
	public static function get_scheduled_product_offer(
		int $product_id,
		string $normal_price
	): ?array {
		$product_id = ProductCategoryResolver::get_catalog_product_id( $product_id );

		if ( $product_id <= 0 || ! self::is_valid_price( $normal_price ) ) {
			return null;
		}

		$campaign = CampaignService::get_current_product_campaign( $product_id );

		if (
			! is_array( $campaign ) ||
			! self::is_product_campaign_eligible( $campaign, $product_id )
		) {
			return null;
		}

		$offer_price = (string) $campaign['offer_price'];

		if ( ! self::is_valid_price( $offer_price ) || (float) $offer_price >= (float) $normal_price ) {
			return null;
		}

		return array(
			'price'        => $offer_price,
			'campaign_key' => (string) $campaign['campaign_key'],
		);
	}

	/**
	 * Determines whether a campaign can apply to a product.
	 *
	 * @param array<string, int|string> $campaign   Campaign record.
	 * @param int                       $product_id Catalog product ID.
	 * @return bool
	 */
	public static function is_product_campaign_eligible(
		array $campaign,
		int $product_id
	): bool {
		return CampaignService::TYPE_PRODUCT_PRICE === $campaign['campaign_type'] &&
			CampaignService::is_currently_active( $campaign ) &&
			$product_id > 0 &&
			$product_id === (int) $campaign['product_id'];
	}

	/**
	 * Determines whether a price is a valid non-negative decimal.
	 *
	 * @param string $price Price value.
	 * @return bool
	 */
	private static function is_valid_price( string $price ): bool {
		return null !== \GalaxyOne\Core\Pricing\DailyFlowerPriceRepository::normalize_price( $price );
	}
}
