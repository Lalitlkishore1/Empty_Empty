<?php
/**
 * Inventory service.
 *
 * @package GalaxyOne\Core\Inventory
 */

namespace GalaxyOne\Core\Inventory;

use GalaxyOne\Core\Products\ProductCategoryResolver;
use WC_Product;

final class InventoryService {

	/**
	 * Water availability product-meta key.
	 *
	 * @var string
	 */
	public const WATER_AVAILABILITY_META_KEY = '_galaxyone_water_available';

	/**
	 * Determines whether a WooCommerce product is in stock.
	 *
	 * @param int $product_id WooCommerce product or variation ID.
	 * @return bool
	 */
	public static function is_product_in_stock( int $product_id ): bool {
		$product = wc_get_product( $product_id );

		return $product instanceof WC_Product && $product->is_in_stock();
	}

	/**
	 * Determines whether a Water product is available for purchase.
	 *
	 * @param int $product_id WooCommerce product or variation ID.
	 * @return bool
	 */
	public static function is_water_available( int $product_id ): bool {
		$catalog_product_id = ProductCategoryResolver::get_catalog_product_id( $product_id );

		if ( $catalog_product_id <= 0 || ! ProductCategoryResolver::is_water( $catalog_product_id ) ) {
			return false;
		}

		$is_enabled = 'yes' === get_post_meta(
			$catalog_product_id,
			self::WATER_AVAILABILITY_META_KEY,
			true
		);

		return $is_enabled && self::is_product_in_stock( $product_id );
	}
}
