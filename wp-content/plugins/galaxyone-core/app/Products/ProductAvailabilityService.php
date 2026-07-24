<?php
/**
 * Product availability service.
 *
 * @package GalaxyOne\Core\Products
 */

namespace GalaxyOne\Core\Products;

use GalaxyOne\Core\Inventory\InventoryService;
use GalaxyOne\Core\Pricing\DailyFlowerPriceRepository;

final class ProductAvailabilityService {

	/**
	 * Determines whether a product is currently available for purchase.
	 *
	 * @param int         $product_id     WooCommerce product or variation ID.
	 * @param string|null $effective_date Optional effective date in Y-m-d format.
	 * @return bool
	 */
	public static function is_available( int $product_id, ?string $effective_date = null ): bool {
		$category = ProductCategoryResolver::get_category( $product_id );

		if ( ProductCategoryResolver::WATER_CATEGORY === $category ) {
			return InventoryService::is_water_available( $product_id );
		}

		if ( ProductCategoryResolver::BLOOMS_CATEGORY !== $category ) {
			return true;
		}

		$catalog_product_id = ProductCategoryResolver::get_catalog_product_id( $product_id );

		if ( $catalog_product_id <= 0 ) {
			return false;
		}

		$daily_record = null === $effective_date
			? DailyFlowerPriceRepository::get_current_for_product( $catalog_product_id )
			: DailyFlowerPriceRepository::get_for_product_date( $catalog_product_id, $effective_date );

		return is_array( $daily_record ) &&
			! empty( $daily_record['is_available'] ) &&
			InventoryService::is_product_in_stock( $product_id );
	}
}
