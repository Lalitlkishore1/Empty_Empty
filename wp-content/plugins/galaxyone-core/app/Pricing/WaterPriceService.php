<?php
/**
 * Water price service.
 *
 * @package GalaxyOne\Core\Pricing
 */

namespace GalaxyOne\Core\Pricing;

use GalaxyOne\Core\Products\ProductCategoryResolver;
use WC_Product;

final class WaterPriceService {

	/**
	 * Returns a Water product's WooCommerce normal price.
	 *
	 * @param int $product_id WooCommerce product or variation ID.
	 * @return string|null
	 */
	public static function get_normal_price( int $product_id ): ?string {
		$catalog_product_id = ProductCategoryResolver::get_catalog_product_id( $product_id );

		if ( $catalog_product_id <= 0 || ! ProductCategoryResolver::is_water( $catalog_product_id ) ) {
			return null;
		}

		$product = wc_get_product( $catalog_product_id );

		if ( ! $product instanceof WC_Product ) {
			return null;
		}

		$price = $product->get_regular_price();

		return '' === $price ? null : $price;
	}
}
