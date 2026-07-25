<?php
/**
 * Frontend module.
 *
 * @package GalaxyOne\Core\Frontend
 */

namespace GalaxyOne\Core\Frontend;

use GalaxyOne\Core\Contracts\ModuleInterface;
use GalaxyOne\Core\Products\ProductCategoryResolver;

final class FrontendModule implements ModuleInterface {

	/**
	 * Registers frontend rendering shortcodes.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode(
			'galaxyone_homepage',
			array( $this, 'render_homepage' )
		);

		add_shortcode(
			'galaxyone_category_cards',
			array( $this, 'render_category_cards' )
		);

		add_shortcode(
			'galaxyone_product_cards',
			array( $this, 'render_product_cards' )
		);
	}

	/**
	 * Renders the GalaxyOne homepage content for an Elementor shortcode widget.
	 *
	 * @return string
	 */
	public function render_homepage(): string {
		$water_products  = ProductCategoryResolver::get_products_for_category(
			ProductCategoryResolver::WATER_CATEGORY
		);
		$bloom_products  = ProductCategoryResolver::get_products_for_category(
			ProductCategoryResolver::BLOOMS_CATEGORY
		);
		$category_cards  = CategoryRenderer::render_active_category_cards();
		$future_cards    = CategoryRenderer::render_future_category_cards();
		$water_cards     = ProductCardRenderer::render_product_cards( $water_products );
		$bloom_cards     = ProductCardRenderer::render_product_cards( $bloom_products );
		$water_is_empty  = empty( $water_products );
		$bloom_is_empty  = empty( $bloom_products );

		ob_start();

		require GALAXYONE_CORE_PATH . 'templates/frontend/homepage/homepage.php';

		return (string) ob_get_clean();
	}

	/**
	 * Renders active and future category cards.
	 *
	 * @return string
	 */
	public function render_category_cards(): string {
		$category_cards = CategoryRenderer::render_active_category_cards();
		$future_cards   = CategoryRenderer::render_future_category_cards();

		return '<div class="galaxyone-category-grid">' .
			$category_cards .
			$future_cards .
			'</div>';
	}

	/**
	 * Renders product cards for one active GalaxyOne category.
	 *
	 * @param array<string, mixed> $attributes Shortcode attributes.
	 * @return string
	 */
	public function render_product_cards( array $attributes = array() ): string {
		$attributes = shortcode_atts(
			array(
				'category' => ProductCategoryResolver::WATER_CATEGORY,
			),
			$attributes,
			'galaxyone_product_cards'
		);
		$category   = sanitize_key( (string) $attributes['category'] );

		if (
			! in_array(
				$category,
				array(
					ProductCategoryResolver::WATER_CATEGORY,
					ProductCategoryResolver::BLOOMS_CATEGORY,
				),
				true
			)
		) {
			return ProductCardRenderer::render_empty_state(
				__( 'This product category is not available.', 'galaxyone-core' )
			);
		}

		$products = ProductCategoryResolver::get_products_for_category( $category );

		if ( empty( $products ) ) {
			return ProductCardRenderer::render_empty_state(
				__( 'No products are available in this category right now.', 'galaxyone-core' )
			);
		}

		return ProductCardRenderer::render_product_cards( $products );
	}
}
