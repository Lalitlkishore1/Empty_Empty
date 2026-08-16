<?php
/**
 * Water delivery-handling service.
 *
 * @package GalaxyOne\Core\Pricing
 */

declare(strict_types=1);

namespace GalaxyOne\Core\Pricing;

use GalaxyOne\Core\Cart\CartValidationService;
use GalaxyOne\Core\Products\ProductCategoryResolver;
use GalaxyOne\Core\Settings\SettingsRepository;
use WC_Cart;
use WP_Error;

/**
 * Resolves authoritative Water delivery-condition handling charges.
 */
final class WaterDeliveryHandlingService {

	/**
	 * Ground-floor delivery access key.
	 *
	 * @var string
	 */
	public const ACCESS_GROUND_FLOOR = 'ground_floor';

	/**
	 * Working-lift delivery access key.
	 *
	 * @var string
	 */
	public const ACCESS_WORKING_LIFT = 'working_lift';

	/**
	 * Stairs delivery access key.
	 *
	 * @var string
	 */
	public const ACCESS_STAIRS = 'stairs';

	/**
	 * Returns the supported delivery-access options.
	 *
	 * @return array<string, string>
	 */
	public static function get_access_options(): array {
		return array(
			self::ACCESS_GROUND_FLOOR => __( 'Ground floor', 'galaxyone-core' ),
			self::ACCESS_WORKING_LIFT => __( 'Working lift available', 'galaxyone-core' ),
			self::ACCESS_STAIRS       => __( 'Stairs', 'galaxyone-core' ),
		);
	}

	/**
	 * Returns the customer-facing label for a stored access key.
	 *
	 * @param string $access_type Stored delivery-access key.
	 * @return string
	 */
	public static function get_access_label( string $access_type ): string {
		$access_type = sanitize_key( $access_type );
		$options     = self::get_access_options();

		return isset( $options[ $access_type ] ) ? $options[ $access_type ] : '';
	}

	/**
	 * Determines whether a cart contains qualifying Water products.
	 *
	 * @param WC_Cart $cart WooCommerce cart.
	 * @return bool
	 */
	public static function cart_contains_water( WC_Cart $cart ): bool {
		return self::get_water_quantity( $cart ) > 0;
	}

	/**
	 * Returns the authoritative quantity of qualifying Water products in a cart.
	 *
	 * @param WC_Cart $cart WooCommerce cart.
	 * @return int
	 */
	public static function get_water_quantity( WC_Cart $cart ): int {
		$quantity = 0;

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! is_array( $cart_item ) ) {
				continue;
			}

			$product_id = CartValidationService::get_cart_item_product_id( $cart_item );

			if ( $product_id <= 0 || ! ProductCategoryResolver::is_water( $product_id ) ) {
				continue;
			}

			$item_quantity = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 0;

			if ( $item_quantity > 0 ) {
				$quantity += $item_quantity;
			}
		}

		return $quantity;
	}

	/**
	 * Resolves a validated Water handling snapshot for a cart and delivery choice.
	 *
	 * @param WC_Cart $cart        WooCommerce cart.
	 * @param string  $access_type Customer-selected delivery access key.
	 * @param string  $floor       Customer-selected stairs floor.
	 * @return array<string, int|string>|WP_Error
	 */
	public static function resolve(
		WC_Cart $cart,
		string $access_type,
		string $floor = ''
	): array|WP_Error {
		$water_quantity = self::get_water_quantity( $cart );

		if ( $water_quantity <= 0 ) {
			return new WP_Error(
				'galaxyone_water_delivery_not_required',
				__( 'Water delivery handling is not required for this cart.', 'galaxyone-core' )
			);
		}

		$access_type = sanitize_key( $access_type );
		$floor       = trim( sanitize_text_field( $floor ) );
		$options     = self::get_access_options();

		if ( ! isset( $options[ $access_type ] ) ) {
			return new WP_Error(
				'galaxyone_water_delivery_access_required',
				__( 'Choose the delivery access for your Water order.', 'galaxyone-core' )
			);
		}

		$resolved_floor = 0;
		$rate           = '0.0000';

		if ( self::ACCESS_STAIRS === $access_type ) {
			if ( '' === $floor ) {
				return new WP_Error(
					'galaxyone_water_delivery_floor_required',
					__( 'Enter the floor for stairs delivery.', 'galaxyone-core' )
				);
			}

			if ( ! preg_match( '/^[0-9]+$/', $floor ) ) {
				return new WP_Error(
					'galaxyone_water_delivery_floor_invalid',
					__( 'Enter a valid whole-number floor for stairs delivery.', 'galaxyone-core' )
				);
			}

			$resolved_floor = (int) $floor;

			if ( $resolved_floor < 1 ) {
				return new WP_Error(
					'galaxyone_water_delivery_floor_invalid',
					__( 'Enter a valid whole-number floor for stairs delivery.', 'galaxyone-core' )
				);
			}

			if ( $resolved_floor >= 5 ) {
				return new WP_Error(
					'galaxyone_water_delivery_manual_confirmation_required',
					__( 'Stairs delivery to floor 5 or above requires manual delivery confirmation.', 'galaxyone-core' )
				);
			}

			$rate = SettingsRepository::get_water_stairs_rate( $resolved_floor );

			if ( ! is_string( $rate ) ) {
				return new WP_Error(
					'galaxyone_water_delivery_configuration_unavailable',
					__( 'Water delivery handling is currently unavailable. Please contact us before placing your order.', 'galaxyone-core' )
				);
			}
		}

		$charge = wc_format_decimal( (float) $rate * $water_quantity, wc_get_price_decimals() );

		if ( '' === $charge || (float) $charge < 0 ) {
			return new WP_Error(
				'galaxyone_water_delivery_configuration_unavailable',
				__( 'Water delivery handling is currently unavailable. Please contact us before placing your order.', 'galaxyone-core' )
			);
		}

		return array(
			'access_type'   => $access_type,
			'floor'         => $resolved_floor,
			'rate'          => $rate,
			'water_quantity' => $water_quantity,
			'charge'        => $charge,
		);
	}
}
