<?php
/**
 * Product-card renderer.
 *
 * @package GalaxyOne\Core\Frontend
 */

namespace GalaxyOne\Core\Frontend;

use GalaxyOne\Core\Delivery\DeliverySlotService;
use GalaxyOne\Core\Delivery\ServiceAreaService;
use GalaxyOne\Core\Pricing\PriceResolver;
use GalaxyOne\Core\Products\ProductAvailabilityService;
use GalaxyOne\Core\Products\ProductCategoryResolver;
use WC_Product;

final class ProductCardRenderer {

	/**
	 * Renders a set of product cards.
	 *
	 * @param array<int, WC_Product> $products WooCommerce products.
	 * @return string
	 */
	public static function render_product_cards( array $products ): string {
		if ( empty( $products ) ) {
			return self::render_empty_state(
				__( 'No products are available in this category right now.', 'galaxyone-core' )
			);
		}

		$cards = '';

		foreach ( $products as $product ) {
			if ( $product instanceof WC_Product ) {
				$cards .= self::render_product_card( $product );
			}
		}

		if ( '' === $cards ) {
			return self::render_empty_state(
				__( 'No products are available in this category right now.', 'galaxyone-core' )
			);
		}

		return '<div class="galaxyone-product-grid">' . $cards . '</div>';
	}

	/**
	 * Renders one product card.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return string
	 */
	public static function render_product_card( WC_Product $product ): string {
		$card = self::get_card_data( $product );

		ob_start();

		require GALAXYONE_CORE_PATH . 'templates/frontend/product-card.php';

		return (string) ob_get_clean();
	}

	/**
	 * Renders a clear empty-state message.
	 *
	 * @param string $message Customer-facing message.
	 * @return string
	 */
	public static function render_empty_state( string $message ): string {
		return sprintf(
			'<p class="galaxyone-storefront-state galaxyone-storefront-state--empty" role="status">%s</p>',
			esc_html( $message )
		);
	}

	/**
	 * Builds trusted presentation data for one product.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return array<string, bool|int|string>
	 */
	private static function get_card_data( WC_Product $product ): array {
		$product_id         = (int) $product->get_id();
		$catalog_product_id = ProductCategoryResolver::get_catalog_product_id( $product_id );
		$category           = ProductCategoryResolver::get_category( $product_id );
		$price              = PriceResolver::resolve( $product_id );
		$is_available       = ProductAvailabilityService::is_available( $product_id );
		$is_purchasable     = $is_available &&
			$product->is_purchasable() &&
			$product->is_in_stock();
		$is_simple          = $product->is_type( 'simple' );
		$has_add_to_cart    = $is_purchasable && $is_simple;
		$has_offer          = is_array( $price ) &&
			'scheduled_offer' === $price['source'];
		$has_price          = is_array( $price );
		$price_html         = $has_price
			? wc_price( (string) $price['price'] )
			: '';
		$normal_price_html  = $has_offer
			? wc_price( (string) $price['normal_price'] )
			: '';
		$action_url         = $has_add_to_cart
			? $product->add_to_cart_url()
			: $product->get_permalink();
		$action_label       = $has_add_to_cart
			? __( 'Add to cart', 'galaxyone-core' )
			: __( 'View product', 'galaxyone-core' );

		return array(
			'product_id'            => $product_id,
			'catalog_product_id'    => $catalog_product_id,
			'name'                  => $product->get_name(),
			'product_url'           => $product->get_permalink(),
			'image_html'            => $product->get_image( 'woocommerce_thumbnail' ),
			'category_label'        => self::get_category_label( $category ),
			'delivery_information'  => self::get_delivery_information(),
			'is_available'          => $is_available,
			'has_price'             => $has_price,
			'price_html'            => $price_html,
			'normal_price_html'     => $normal_price_html,
			'has_offer'             => $has_offer,
			'offer_label'           => __( 'Scheduled offer', 'galaxyone-core' ),
			'has_add_to_cart'       => $has_add_to_cart,
			'action_url'            => $action_url,
			'action_label'          => $action_label,
			'add_to_cart_url'       => $has_add_to_cart ? $product->add_to_cart_url() : '',
			'add_to_cart_aria'      => sprintf(
				/* translators: %s: product name. */
				__( 'Add %s to cart', 'galaxyone-core' ),
				$product->get_name()
			),
		);
	}

	/**
	 * Returns a customer-facing category label.
	 *
	 * @param string|null $category Category slug.
	 * @return string
	 */
	private static function get_category_label( ?string $category ): string {
		if ( ProductCategoryResolver::WATER_CATEGORY === $category ) {
			return __( 'Water', 'galaxyone-core' );
		}

		if ( ProductCategoryResolver::BLOOMS_CATEGORY === $category ) {
			return __( 'Blooms', 'galaxyone-core' );
		}

		return __( 'GalaxyOne product', 'galaxyone-core' );
	}

	/**
	 * Returns current delivery information without calculating a customer fee.
	 *
	 * @return string
	 */
	private static function get_delivery_information(): string {
		$service_areas = ServiceAreaService::get_service_areas();
		$slots         = DeliverySlotService::get_slots();

		if ( empty( $service_areas ) || empty( $slots ) ) {
			return __( 'Delivery availability is currently being configured.', 'galaxyone-core' );
		}

		return __( 'Delivery availability and charges depend on your postcode.', 'galaxyone-core' );
	}
}
