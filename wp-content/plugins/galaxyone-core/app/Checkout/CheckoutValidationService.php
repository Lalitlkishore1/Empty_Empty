<?php
/**
 * Checkout-validation service.
 *
 * @package GalaxyOne\Core\Checkout
 */

namespace GalaxyOne\Core\Checkout;

use GalaxyOne\Core\Delivery\DeliveryValidationService;
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

		$postcode = isset( $data['billing_postcode'] ) && is_scalar( $data['billing_postcode'] )
			? (string) $data['billing_postcode']
			: '';
		$date     = isset( $data['galaxyone_delivery_date'] ) && is_scalar( $data['galaxyone_delivery_date'] )
			? (string) $data['galaxyone_delivery_date']
			: '';
		$slot_key = isset( $data['galaxyone_delivery_slot'] ) && is_scalar( $data['galaxyone_delivery_slot'] )
			? (string) $data['galaxyone_delivery_slot']
			: '';

		if ( '' === trim( $date ) || '' === trim( $slot_key ) ) {
			$errors[] = new WP_Error(
				'galaxyone_delivery_selection_required',
				__( 'Choose a delivery date and time slot before placing your order.', 'galaxyone-core' )
			);

			return $errors;
		}

		$delivery_validation = DeliveryValidationService::validate(
			array(
				'postcode' => $postcode,
			),
			$date,
			$slot_key
		);

		if ( $delivery_validation instanceof WP_Error ) {
			$errors[] = $delivery_validation;
		}

		return $errors;
	}
}
