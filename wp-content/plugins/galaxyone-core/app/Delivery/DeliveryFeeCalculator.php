<?php
/**
 * Delivery-fee calculator.
 *
 * @package GalaxyOne\Core\Delivery
 */

namespace GalaxyOne\Core\Delivery;

final class DeliveryFeeCalculator {

	/**
	 * Returns the configured delivery fee for an address.
	 *
	 * @param array<string, mixed> $address WooCommerce-style address data.
	 * @return string|null
	 */
	public static function calculate( array $address ): ?string {
		$postcode = isset( $address['postcode'] ) && is_scalar( $address['postcode'] )
			? (string) $address['postcode']
			: '';
		$area     = ServiceAreaService::get_service_area( $postcode );

		return is_array( $area ) ? $area['fee'] : null;
	}
}
