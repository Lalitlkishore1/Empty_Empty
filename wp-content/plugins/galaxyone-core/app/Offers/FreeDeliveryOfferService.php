<?php
/**
 * Free-delivery offer service.
 *
 * @package GalaxyOne\Core\Offers
 */

namespace GalaxyOne\Core\Offers;

use GalaxyOne\Core\Products\ProductCategoryResolver;

final class FreeDeliveryOfferService {

	/**
	 * Returns the first eligible free-delivery campaign for supplied products.
	 *
	 * An empty product list can receive only a global campaign. Cart and
	 * checkout integration are intentionally deferred to Phase 9.
	 *
	 * @param array<int, int> $product_ids WooCommerce product or variation IDs.
	 * @return array<string, int|string>|null
	 */
	public static function get_eligible_campaign( array $product_ids = array() ): ?array {
		$catalog_product_ids = self::normalize_product_ids( $product_ids );
		$campaigns           = CampaignService::get_current_free_delivery_campaigns();

		foreach ( $campaigns as $campaign ) {
			$campaign_product_id = (int) $campaign['product_id'];

			if (
				0 === $campaign_product_id ||
				in_array( $campaign_product_id, $catalog_product_ids, true )
			) {
				return $campaign;
			}
		}

		return null;
	}

	/**
	 * Determines whether a free-delivery campaign applies to supplied products.
	 *
	 * @param array<int, int> $product_ids WooCommerce product or variation IDs.
	 * @return bool
	 */
	public static function is_eligible( array $product_ids = array() ): bool {
		return is_array( self::get_eligible_campaign( $product_ids ) );
	}

	/**
	 * Resolves supplied products to their catalog product IDs.
	 *
	 * @param array<int, int> $product_ids Product IDs.
	 * @return array<int, int>
	 */
	private static function normalize_product_ids( array $product_ids ): array {
		$catalog_product_ids = array();

		foreach ( $product_ids as $product_id ) {
			$catalog_product_id = ProductCategoryResolver::get_catalog_product_id(
				absint( $product_id )
			);

			if ( $catalog_product_id > 0 ) {
				$catalog_product_ids[] = $catalog_product_id;
			}
		}

		return array_values( array_unique( $catalog_product_ids ) );
	}
}
