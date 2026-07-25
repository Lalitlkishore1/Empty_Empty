<?php
/**
 * Cart-validation service.
 *
 * @package GalaxyOne\Core\Cart
 */

namespace GalaxyOne\Core\Cart;

use GalaxyOne\Core\Pricing\PriceResolver;
use GalaxyOne\Core\Products\ProductAvailabilityService;
use WC_Cart;
use WP_Error;

final class CartValidationService {

	/**
	 * Validates one product for cart eligibility.
	 *
	 * @param int $product_id WooCommerce product or variation ID.
	 * @return WP_Error|null
	 */
	public static function validate_product( int $product_id ): ?WP_Error {
		if ( $product_id <= 0 || ! ProductAvailabilityService::is_available( $product_id ) ) {
			return new WP_Error(
				'galaxyone_product_unavailable',
				__( 'This product is currently unavailable and cannot be purchased.', 'galaxyone-core' )
			);
		}

		if ( ! is_array( PriceResolver::resolve( $product_id ) ) ) {
			return new WP_Error(
				'galaxyone_product_price_unavailable',
				__( 'The current price for this product is unavailable. Please try again later.', 'galaxyone-core' )
			);
		}

		return null;
	}

	/**
	 * Validates all current cart items.
	 *
	 * @return array<int, WP_Error>
	 */
	public static function validate_cart(): array {
		$cart = WC()->cart;

		if ( ! $cart instanceof WC_Cart ) {
			return array();
		}

		$errors = array();

		foreach ( $cart->get_cart() as $cart_item ) {
			$product_id = self::get_cart_item_product_id( $cart_item );
			$error      = self::validate_product( $product_id );

			if ( $error instanceof WP_Error ) {
				$errors[] = $error;

				continue;
			}

			if ( ! empty( $cart_item['galaxyone_price_change_detected'] ) ) {
				$errors[] = new WP_Error(
					'galaxyone_cart_price_changed',
					__( 'A product price or offer changed. Review and update your cart before checking out.', 'galaxyone-core' )
				);
			}
		}

		return self::unique_errors( $errors );
	}

	/**
	 * Returns the effective product or variation ID for one cart item.
	 *
	 * @param array<string, mixed> $cart_item WooCommerce cart item.
	 * @return int
	 */
	public static function get_cart_item_product_id( array $cart_item ): int {
		$variation_id = isset( $cart_item['variation_id'] )
			? absint( $cart_item['variation_id'] )
			: 0;

		if ( $variation_id > 0 ) {
			return $variation_id;
		}

		return isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
	}

	/**
	 * Removes duplicate errors with the same code.
	 *
	 * @param array<int, WP_Error> $errors Candidate errors.
	 * @return array<int, WP_Error>
	 */
	private static function unique_errors( array $errors ): array {
		$unique_errors = array();
		$seen_codes    = array();

		foreach ( $errors as $error ) {
			$code = $error->get_error_code();

			if ( isset( $seen_codes[ $code ] ) ) {
				continue;
			}

			$seen_codes[ $code ] = true;
			$unique_errors[]     = $error;
		}

		return $unique_errors;
	}
}
