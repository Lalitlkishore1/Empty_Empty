<?php
/**
 * Delivery-validation service.
 *
 * @package GalaxyOne\Core\Delivery
 */

namespace GalaxyOne\Core\Delivery;

use WP_Error;

final class DeliveryValidationService {

	/**
	 * Validates a delivery selection without creating a reservation.
	 *
	 * @param array<string, mixed> $address       WooCommerce-style address data.
	 * @param string               $delivery_date Selected date in Y-m-d format.
	 * @param string               $slot_key      Selected slot key.
	 * @param int                  $quantity      Required capacity.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function validate(
		array $address,
		string $delivery_date,
		string $slot_key,
		int $quantity = 1
	): array|WP_Error {
		$postcode = isset( $address['postcode'] ) && is_scalar( $address['postcode'] )
			? (string) $address['postcode']
			: '';
		$area     = ServiceAreaService::get_service_area( $postcode );

		if ( ! is_array( $area ) ) {
			return new WP_Error(
				'galaxyone_delivery_out_of_area',
				__( 'Delivery is not available for the supplied postcode.', 'galaxyone-core' )
			);
		}

		if ( ! DeliverySlotService::is_slot_available( $delivery_date, $slot_key ) ) {
			return new WP_Error(
				'galaxyone_delivery_slot_unavailable',
				__( 'The selected delivery date or slot is unavailable.', 'galaxyone-core' )
			);
		}

		if ( ! DeliveryCapacityService::has_capacity( $delivery_date, $slot_key, $quantity ) ) {
			return new WP_Error(
				'galaxyone_delivery_capacity_full',
				__( 'The selected delivery slot has reached capacity.', 'galaxyone-core' )
			);
		}

		$slot = DeliverySlotService::get_slot( $slot_key );

		return array(
			'service_area' => $area,
			'slot'         => $slot,
			'delivery_fee' => $area['fee'],
		);
	}

	/**
	 * Validates and reserves a delivery selection.
	 *
	 * @param array<string, mixed> $address       WooCommerce-style address data.
	 * @param string               $delivery_date Selected date in Y-m-d format.
	 * @param string               $slot_key      Selected slot key.
	 * @param int                  $quantity      Required capacity.
	 * @param int                  $order_id      WooCommerce order ID when available.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function reserve(
		array $address,
		string $delivery_date,
		string $slot_key,
		int $quantity = 1,
		int $order_id = 0
	): array|WP_Error {
		$validation = self::validate( $address, $delivery_date, $slot_key, $quantity );

		if ( $validation instanceof WP_Error ) {
			return $validation;
		}

		$reservation = DeliveryReservationService::create(
			$delivery_date,
			$slot_key,
			$quantity,
			$order_id
		);

		if ( ! is_array( $reservation ) ) {
			return new WP_Error(
				'galaxyone_delivery_capacity_changed',
				__( 'The selected delivery slot is no longer available.', 'galaxyone-core' )
			);
		}

		$validation['reservation'] = $reservation;

		return $validation;
	}
}
