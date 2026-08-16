<?php
/**
 * Checkout-validation service.
 *
 * @package GalaxyOne\Core\Checkout
 */

namespace GalaxyOne\Core\Checkout;

use GalaxyOne\Core\Cart\CartRecalculationService;
use GalaxyOne\Core\Delivery\DeliveryValidationService;
use GalaxyOne\Core\Pricing\WaterDeliveryHandlingService;
use WC_Cart;
use WP_Error;

final class CheckoutValidationService {

	/**
	 * Validates GalaxyOne checkout requirements.
	 *
	 * @param array<string, mixed> $data WooCommerce checkout values.
	 * @return array<int, WP_Error>
	 */
	public static function validate( array $data ): array {
		$errors = array();

		$phone = isset( $data['billing_phone'] ) && is_scalar( $data['billing_phone'] )
			? preg_replace( '/\D+/', '', (string) $data['billing_phone'] )
			: '';

		if ( strlen( $phone ) < 7 || strlen( $phone ) > 15 ) {
			$errors[] = new WP_Error(
				'galaxyone_invalid_mobile_number',
				__( 'Enter a valid mobile number.', 'galaxyone-core' )
			);
		}

		$selection = CartRecalculationService::get_delivery_selection();
		$postcode  = (string) $selection['postcode'];
		$date      = (string) $selection['delivery_date'];
		$slot_key  = (string) $selection['slot_key'];

		if ( '' === trim( $date ) || '' === trim( $slot_key ) ) {
			$errors[] = new WP_Error(
				'galaxyone_delivery_selection_required',
				__( 'Choose a delivery date and time slot before placing your order.', 'galaxyone-core' )
			);

			return $errors;
		}

		$delivery_validation = DeliveryValidationService::validate(
			array(
				'address_1' => (string) $selection['address_1'],
				'city'      => (string) $selection['city'],
				'postcode'  => $postcode,
			),
			$date,
			$slot_key
		);

		if ( $delivery_validation instanceof WP_Error ) {
			$errors[] = $delivery_validation;

			return $errors;
		}

		$cart = WC()->cart;

		if ( $cart instanceof WC_Cart && WaterDeliveryHandlingService::cart_contains_water( $cart ) ) {
			$water_handling = WaterDeliveryHandlingService::resolve(
				$cart,
				(string) $selection['water_access'],
				(string) $selection['water_floor']
			);

			if ( $water_handling instanceof WP_Error ) {
				$errors[] = $water_handling;
			}
		}

		return $errors;
	}
}
