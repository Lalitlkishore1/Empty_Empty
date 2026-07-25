<?php
/**
 * Category-card renderer.
 *
 * @package GalaxyOne\Core\Frontend
 */

namespace GalaxyOne\Core\Frontend;

use GalaxyOne\Core\Products\ProductCategoryResolver;
use WP_Term;

final class CategoryRenderer {

	/**
	 * Renders cards for active GalaxyOne categories.
	 *
	 * @return string
	 */
	public static function render_active_category_cards(): string {
		$cards = '';

		foreach ( self::get_active_categories() as $category ) {
			$cards .= self::render_category_card( $category );
		}

		return $cards;
	}

	/**
	 * Renders non-purchasable future-category cards.
	 *
	 * @return string
	 */
	public static function render_future_category_cards(): string {
		$cards = '';

		foreach ( self::get_future_categories() as $category ) {
			$cards .= self::render_category_card( $category );
		}

		return $cards;
	}

	/**
	 * Returns active category presentation data.
	 *
	 * @return array<int, array<string, bool|int|string>>
	 */
	private static function get_active_categories(): array {
		$definitions = array(
			ProductCategoryResolver::WATER_CATEGORY => array(
				'title'       => __( 'Water', 'galaxyone-core' ),
				'description' => __( 'Reliable essentials for everyday delivery.', 'galaxyone-core' ),
			),
			ProductCategoryResolver::BLOOMS_CATEGORY => array(
				'title'       => __( 'Blooms', 'galaxyone-core' ),
				'description' => __( 'Fresh flowers and fragrances for every occasion.', 'galaxyone-core' ),
			),
		);
		$categories  = array();

		foreach ( $definitions as $slug => $definition ) {
			$term = get_term_by( 'slug', $slug, 'product_cat' );

			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$url = get_term_link( $term );

			if ( is_wp_error( $url ) ) {
				continue;
			}

			$categories[] = array(
				'slug'         => $slug,
				'title'        => $definition['title'],
				'description'  => $definition['description'],
				'url'          => $url,
				'product_count'=> (int) $term->count,
				'is_available' => true,
				'status_label' => __( 'Shop now', 'galaxyone-core' ),
			);
		}

		return $categories;
	}

	/**
	 * Returns future-category placeholder presentation data.
	 *
	 * @return array<int, array<string, bool|int|string>>
	 */
	private static function get_future_categories(): array {
		return array(
			array(
				'slug'          => 'vegetables',
				'title'         => __( 'Vegetables', 'galaxyone-core' ),
				'description'   => __( 'Fresh everyday essentials are planned for a future release.', 'galaxyone-core' ),
				'url'           => '',
				'product_count' => 0,
				'is_available'  => false,
				'status_label'  => __( 'Coming soon', 'galaxyone-core' ),
			),
			array(
				'slug'          => 'fruits',
				'title'         => __( 'Fruits', 'galaxyone-core' ),
				'description'   => __( 'Fresh everyday essentials are planned for a future release.', 'galaxyone-core' ),
				'url'           => '',
				'product_count' => 0,
				'is_available'  => false,
				'status_label'  => __( 'Coming soon', 'galaxyone-core' ),
			),
			array(
				'slug'          => 'grocery',
				'title'         => __( 'Grocery', 'galaxyone-core' ),
				'description'   => __( 'Fresh everyday essentials are planned for a future release.', 'galaxyone-core' ),
				'url'           => '',
				'product_count' => 0,
				'is_available'  => false,
				'status_label'  => __( 'Coming soon', 'galaxyone-core' ),
			),
		);
	}

	/**
	 * Renders one category card.
	 *
	 * @param array<string, bool|int|string> $category Category presentation data.
	 * @return string
	 */
	private static function render_category_card( array $category ): string {
		ob_start();

		require GALAXYONE_CORE_PATH . 'templates/frontend/category-card.php';

		return (string) ob_get_clean();
	}
}
