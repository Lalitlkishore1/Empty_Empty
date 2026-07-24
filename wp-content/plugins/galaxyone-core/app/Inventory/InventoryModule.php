<?php
/**
 * Inventory module.
 *
 * @package GalaxyOne\Core\Inventory
 */

namespace GalaxyOne\Core\Inventory;

use GalaxyOne\Core\Contracts\ModuleInterface;

final class InventoryModule implements ModuleInterface {

	/**
	 * Registers the module with WordPress and WooCommerce.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter(
			'woocommerce_add_to_cart_validation',
			array( $this, 'validate_stock' ),
			10,
			3
		);
	}

	/**
	 * Rejects cart additions for products that are out of stock.
	 *
	 * @param bool $passed     Whether prior cart validation passed.
	 * @param int  $product_id WooCommerce product ID.
	 * @param int  $quantity   Requested quantity.
	 * @return bool
	 */
	public function validate_stock( bool $passed, int $product_id, int $quantity ): bool {
		if ( ! $passed ) {
			return false;
		}

		if ( InventoryService::is_product_in_stock( $product_id ) ) {
			return true;
		}

		wc_add_notice(
			__( 'This product is currently unavailable.', 'galaxyone-core' ),
			'error'
		);

		return false;
	}
}
