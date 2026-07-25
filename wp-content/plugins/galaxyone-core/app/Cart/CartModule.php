<?php
/**
 * Cart module.
 *
 * @package GalaxyOne\Core\Cart
 */

namespace GalaxyOne\Core\Cart;

use GalaxyOne\Core\Contracts\ModuleInterface;

final class CartModule implements ModuleInterface {

	/**
	 * Registers cart integrations.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter(
			'woocommerce_add_to_cart_validation',
			array( $this, 'validate_add_to_cart' ),
			10,
			5
		);

		add_action(
			'woocommerce_check_cart_items',
			array( $this, 'validate_cart_items' )
		);

		add_action(
			'woocommerce_before_calculate_totals',
			array( CartRecalculationService::class, 'recalculate_prices' ),
			20
		);

		add_action(
			'woocommerce_cart_calculate_fees',
			array( CartRecalculationService::class, 'apply_delivery_fee' ),
			20
		);

		add_action(
			'woocommerce_cart_updated',
			array( CartRecalculationService::class, 'acknowledge_price_changes' )
		);

		add_action(
			'woocommerce_after_cart_totals',
			array( $this, 'render_delivery_summary' )
		);
	}

	/**
	 * Rejects unavailable or unpriceable products before cart insertion.
	 *
	 * @param bool   $passed       Whether WooCommerce has accepted the request.
	 * @param int    $product_id   Product ID.
	 * @param int    $quantity     Requested quantity.
	 * @param int    $variation_id Variation ID.
	 * @param array  $variations   Selected variation data.
	 * @return bool
	 */
	public function validate_add_to_cart(
		bool $passed,
		int $product_id,
		int $quantity,
		int $variation_id,
		array $variations
	): bool {
		unset( $quantity, $variations );

		if ( ! $passed ) {
			return false;
		}

		$validation_product_id = $variation_id > 0 ? $variation_id : $product_id;
		$error                 = CartValidationService::validate_product( $validation_product_id );

		if ( null === $error ) {
			return true;
		}

		wc_add_notice( $error->get_error_message(), 'error' );

		return false;
	}

	/**
	 * Adds cart validation errors to the WooCommerce notice queue.
	 *
	 * @return void
	 */
	public function validate_cart_items(): void {
		foreach ( CartValidationService::validate_cart() as $error ) {
			wc_add_notice( $error->get_error_message(), 'error' );
		}
	}

	/**
	 * Renders the cart delivery-selection summary.
	 *
	 * @return void
	 */
	public function render_delivery_summary(): void {
		$selection = CartRecalculationService::get_delivery_selection();
		$fee       = CartRecalculationService::get_delivery_fee_snapshot();

		require GALAXYONE_CORE_PATH . 'templates/frontend/cart/delivery-summary.php';
	}
}
