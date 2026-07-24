<?php
/**
 * Product-category resolver.
 *
 * @package GalaxyOne\Core\Products
 */

namespace GalaxyOne\Core\Products;

use WC_Product;

final class ProductCategoryResolver {

	/**
	 * Water product-category slug.
	 *
	 * @var string
	 */
	public const WATER_CATEGORY = 'water';

	/**
	 * Blooms product-category slug.
	 *
	 * @var string
	 */
	public const BLOOMS_CATEGORY = 'blooms';

	/**
	 * Resolves a product's GalaxyOne category.
	 *
	 * @param int $product_id WooCommerce product or variation ID.
	 * @return string|null
	 */
	public static function get_category( int $product_id ): ?string {
		$catalog_product_id = self::get_catalog_product_id( $product_id );

		if ( $catalog_product_id <= 0 ) {
			return null;
		}

		if ( has_term( self::WATER_CATEGORY, 'product_cat', $catalog_product_id ) ) {
			return self::WATER_CATEGORY;
		}

		if ( has_term( self::BLOOMS_CATEGORY, 'product_cat', $catalog_product_id ) ) {
			return self::BLOOMS_CATEGORY;
		}

		return null;
	}

	/**
	 * Determines whether a product belongs to Water.
	 *
	 * @param int $product_id WooCommerce product or variation ID.
	 * @return bool
	 */
	public static function is_water( int $product_id ): bool {
		return self::WATER_CATEGORY === self::get_category( $product_id );
	}

	/**
	 * Determines whether a product belongs to Blooms.
	 *
	 * @param int $product_id WooCommerce product or variation ID.
	 * @return bool
	 */
	public static function is_bloom( int $product_id ): bool {
		return self::BLOOMS_CATEGORY === self::get_category( $product_id );
	}

	/**
	 * Returns the parent catalog product ID for a product or variation.
	 *
	 * @param int $product_id WooCommerce product or variation ID.
	 * @return int
	 */
	public static function get_catalog_product_id( int $product_id ): int {
		$product = wc_get_product( $product_id );

		if ( ! $product instanceof WC_Product ) {
			return 0;
		}

		if ( $product->is_type( 'variation' ) ) {
			return (int) $product->get_parent_id();
		}

		return (int) $product->get_id();
	}

	/**
	 * Returns published products in a GalaxyOne category.
	 *
	 * @param string $category Category slug.
	 * @return array<int, WC_Product>
	 */
	public static function get_products_for_category( string $category ): array {
		$products = wc_get_products(
			array(
				'category' => array( $category ),
				'limit'    => -1,
				'status'   => 'publish',
				'return'   => 'objects',
				'orderby'  => 'title',
				'order'    => 'ASC',
			)
		);

		return is_array( $products ) ? $products : array();
	}
}
