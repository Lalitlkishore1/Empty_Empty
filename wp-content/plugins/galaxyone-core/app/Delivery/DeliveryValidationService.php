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
	 * @param array<string, mixed> $address        WooCommerce-style address data.
	 * @param string               $delivery_date  Selected date in Y-m-d format.
	 * @param string               $slot_key       Selected slot key.
	 * @param int                  $quantity       Required capacity.
	 * @param bool                 $check_capacity Whether to perform the non-authoritative capacity pre-check.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function validate(
		array $address,
		string $delivery_date,
		string $slot_key,
		int $quantity = 1,
		bool $check_capacity = true
	): array|WP_Error {
		$delivery_date = sanitize_text_field( $delivery_date );
		$slot_key      = sanitize_title( $slot_key );
		$postcode      = isset( $address['postcode'] ) && is_scalar( $address['postcode'] )
			? (string) $address['postcode']
			: '';
		$street_name   = isset( $address['address_1'] ) && is_scalar( $address['address_1'] )
			? (string) $address['address_1']
			: '';
		$locality      = isset( $address['city'] ) && is_scalar( $address['city'] )
			? (string) $address['city']
			: '';
		$area          = ServiceAreaService::get_service_area( $postcode );

		if ( ! is_array( $area ) ) {
			return new WP_Error(
				'galaxyone_delivery_out_of_area',
				__( 'Delivery is not available for the supplied postcode.', 'galaxyone-core' )
			);
		}

		if (
			'' === trim( $street_name ) ||
			'' === trim( $locality ) ||
			'' === trim( $postcode ) ||
			! SupportedStreetRepository::is_active_supported_street(
				$street_name,
				$locality,
				$postcode
			)
		) {
			return new WP_Error(
				'galaxyone_delivery_unsupported_street',
				__( 'Delivery is not available for the supplied address.', 'galaxyone-core' )
			);
		}

		if ( ! DeliverySlotService::is_slot_available( $delivery_date, $slot_key ) ) {
			return new WP_Error(
				'galaxyone_delivery_slot_unavailable',
				__( 'The selected delivery date or slot is unavailable.', 'galaxyone-core' )
			);
		}

		if (
			$check_capacity &&
			! DeliveryCapacityService::has_capacity( $delivery_date, $slot_key, $quantity )
		) {
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
	 * @param array<string, mixed> $address         WooCommerce-style address data.
	 * @param string               $delivery_date   Selected date in Y-m-d format.
	 * @param string               $slot_key        Selected slot key.
	 * @param int                  $quantity        Required capacity.
	 * @param int                  $order_id        WooCommerce order ID when available.
	 * @param string               $idempotency_key Stable server-side operation UUID.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function reserve(
		array $address,
		string $delivery_date,
		string $slot_key,
		int $quantity = 1,
		int $order_id = 0,
		string $idempotency_key = ''
	): array|WP_Error {
		$delivery_date   = sanitize_text_field( $delivery_date );
		$slot_key        = sanitize_title( $slot_key );
		$idempotency_key = trim( $idempotency_key );
		$validation      = self::validate(
			$address,
			$delivery_date,
			$slot_key,
			$quantity,
			false
		);

		if ( $validation instanceof WP_Error ) {
			return $validation;
		}

		if (
			! self::is_valid_delivery_operation(
				$delivery_date,
				$slot_key,
				$quantity
			) ||
			! wp_is_uuid( $idempotency_key )
		) {
			return new WP_Error(
				'galaxyone_delivery_reservation_failed',
				__( 'The selected delivery slot could not be reserved. Please choose another slot.', 'galaxyone-core' )
			);
		}

		$reservation = DeliveryReservationService::create(
			$delivery_date,
			$slot_key,
			$quantity,
			$order_id,
			$idempotency_key
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

	/**
	 * Determines whether normalized operation attributes are valid.
	 *
	 * @param string $delivery_date Normalized delivery date.
	 * @param string $slot_key      Normalized delivery slot key.
	 * @param int    $quantity      Reserved capacity.
	 * @return bool
	 */
	public static function is_valid_delivery_operation(
		string $delivery_date,
		string $slot_key,
		int $quantity
	): bool {
		return DeliverySlotService::is_valid_date( $delivery_date ) &&
			'' !== sanitize_title( $slot_key ) &&
			$quantity > 0;
	}
}
