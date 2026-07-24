<?php
/**
 * Products module.
 *
 * @package GalaxyOne\Core\Products
 */

namespace GalaxyOne\Core\Products;

use GalaxyOne\Core\ActivityLog\ActivityLogRepository;
use GalaxyOne\Core\Contracts\ModuleInterface;
use GalaxyOne\Core\Inventory\InventoryService;
use WC_Product;

final class ProductsModule implements ModuleInterface {

	/**
	 * Daily flower-price administration page.
	 *
	 * @var DailyFlowerPricePage
	 */
	private DailyFlowerPricePage $daily_flower_price_page;

	/**
	 * Creates the products module.
	 */
	public function __construct() {
		$this->daily_flower_price_page = new DailyFlowerPricePage();
	}

	/**
	 * Registers the module with WordPress and WooCommerce.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->daily_flower_price_page->register();

		add_action(
			'woocommerce_product_options_inventory_product_data',
			array( $this, 'render_water_availability_field' )
		);

		add_action(
			'woocommerce_admin_process_product_object',
			array( $this, 'save_water_availability' )
		);

		add_filter(
			'woocommerce_is_purchasable',
			array( $this, 'filter_purchasable_status' ),
			10,
			2
		);

		add_filter(
			'woocommerce_add_to_cart_validation',
			array( $this, 'validate_product_availability' ),
			20,
			3
		);

		add_action(
			'woocommerce_single_product_summary',
			array( $this, 'render_unavailable_message' ),
			29
		);
	}

	/**
	 * Renders Water availability controls in the WooCommerce product editor.
	 *
	 * @return void
	 */
	public function render_water_availability_field(): void {
		global $product_object;

		if ( ! $product_object instanceof WC_Product ) {
			return;
		}

		$product_id = $product_object->get_id();

		if ( ! ProductCategoryResolver::is_water( $product_id ) ) {
			return;
		}

		$is_available = InventoryService::is_water_available( $product_id );

		require GALAXYONE_CORE_PATH . 'templates/admin/products/water-availability-field.php';
	}

	/**
	 * Saves Water availability metadata from the WooCommerce product editor.
	 *
	 * @param WC_Product $product Product being saved.
	 * @return void
	 */
	public function save_water_availability( WC_Product $product ): void {
		$product_id = $product->get_id();

		if (
			! current_user_can( 'edit_post', $product_id ) ||
			! ProductCategoryResolver::is_water( $product_id )
		) {
			return;
		}

		$old_value = 'yes' === $product->get_meta(
			InventoryService::WATER_AVAILABILITY_META_KEY,
			true
		);
		$new_value = isset( $_POST[ InventoryService::WATER_AVAILABILITY_META_KEY ] ) && // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'yes' === sanitize_key( wp_unslash( $_POST[ InventoryService::WATER_AVAILABILITY_META_KEY ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$product->update_meta_data(
			InventoryService::WATER_AVAILABILITY_META_KEY,
			$new_value ? 'yes' : 'no'
		);

		if ( $old_value === $new_value ) {
			return;
		}

		ActivityLogRepository::record(
			'water_availability_updated',
			array(
				'product_id' => $product_id,
				'is_available' => $old_value,
			),
			array(
				'product_id' => $product_id,
				'is_available' => $new_value,
			),
			array(
				'source' => 'woocommerce_product_editor',
			)
		);
	}

	/**
	 * Prevents unavailable GalaxyOne products from being purchased.
	 *
	 * @param bool       $purchasable Current WooCommerce purchasable status.
	 * @param WC_Product $product     WooCommerce product.
	 * @return bool
	 */
	public function filter_purchasable_status( bool $purchasable, WC_Product $product ): bool {
		if ( ! $purchasable ) {
			return false;
		}

		return ProductAvailabilityService::is_available( $product->get_id() );
	}

	/**
	 * Applies server-side GalaxyOne availability validation to cart additions.
	 *
	 * @param bool $passed     Whether prior cart validation passed.
	 * @param int  $product_id WooCommerce product ID.
	 * @param int  $quantity   Requested quantity.
	 * @return bool
	 */
	public function validate_product_availability( bool $passed, int $product_id, int $quantity ): bool {
		if ( ! $passed ) {
			return false;
		}

		if ( ProductAvailabilityService::is_available( $product_id ) ) {
			return true;
		}

		wc_add_notice(
			__( 'This product is currently unavailable and cannot be added to the cart.', 'galaxyone-core' ),
			'error'
		);

		return false;
	}

	/**
	 * Renders a visible product-page unavailable state.
	 *
	 * @return void
	 */
	public function render_unavailable_message(): void {
		global $product;

		if (
			! $product instanceof WC_Product ||
			ProductAvailabilityService::is_available( $product->get_id() )
		) {
			return;
		}

		require GALAXYONE_CORE_PATH . 'templates/frontend/product/unavailable-message.php';
	}
}
