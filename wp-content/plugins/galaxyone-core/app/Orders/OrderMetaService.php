<?php
/**
 * Order metadata service.
 *
 * @package GalaxyOne\Core\Orders
 */

namespace GalaxyOne\Core\Orders;

use GalaxyOne\Core\Cart\CartRecalculationService;
use GalaxyOne\Core\Delivery\DeliveryReservationService;
use GalaxyOne\Core\Delivery\DeliverySlotService;
use GalaxyOne\Core\Delivery\ServiceAreaService;
use WC_Order;
use WC_Order_Item_Product;

final class OrderMetaService {

	/**
	 * Stores one authoritative price snapshot on an order line item.
	 *
	 * @param WC_Order_Item_Product $item       Order line item.
	 * @param array<string, mixed>  $cart_values Cart item data.
	 * @return void
	 */
	public static function store_line_item_snapshot(
		WC_Order_Item_Product $item,
		array $cart_values
	): void {
		if (
			! isset( $cart_values['galaxyone_price_snapshot'] ) ||
			! is_array( $cart_values['galaxyone_price_snapshot'] )
		) {
			return;
		}

		$snapshot = $cart_values['galaxyone_price_snapshot'];

		$item->add_meta_data(
			'_galaxyone_price_snapshot',
			wp_json_encode(
				array(
					'product_id'   => isset( $snapshot['product_id'] ) ? absint( $snapshot['product_id'] ) : 0,
					'normal_price' => isset( $snapshot['normal_price'] ) ? (string) $snapshot['normal_price'] : '',
					'price'        => isset( $snapshot['price'] ) ? (string) $snapshot['price'] : '',
					'source'       => isset( $snapshot['source'] ) ? sanitize_key( (string) $snapshot['source'] ) : '',
					'campaign_key' => isset( $snapshot['campaign_key'] )
						? sanitize_title( (string) $snapshot['campaign_key'] )
						: '',
					'currency'     => isset( $snapshot['currency'] ) ? sanitize_text_field( (string) $snapshot['currency'] ) : '',
					'resolved_at'  => isset( $snapshot['resolved_at'] ) ? sanitize_text_field( (string) $snapshot['resolved_at'] ) : '',
				)
			),
			true
		);
	}

	/**
	 * Stores final checkout delivery and customer metadata.
	 *
	 * @param WC_Order             $order Checkout order.
	 * @param array<string, mixed> $data  Checkout values.
	 * @return void
	 */
	public static function store_checkout_metadata( WC_Order $order, array $data ): void {
		$selection = CartRecalculationService::get_delivery_selection();
		$fee       = CartRecalculationService::get_delivery_fee_snapshot();
		$slot      = DeliverySlotService::get_slot( (string) $selection['slot_key'] );
		$area      = ServiceAreaService::get_service_area( (string) $selection['postcode'] );
		$reservation = CartRecalculationService::get_delivery_reservation();
		$landmark    = isset( $data['galaxyone_landmark'] ) && is_scalar( $data['galaxyone_landmark'] )
			? sanitize_text_field( (string) $data['galaxyone_landmark'] )
			: '';

		$order->update_meta_data(
			'_galaxyone_delivery_date',
			sanitize_text_field( (string) $selection['delivery_date'] )
		);
		$order->update_meta_data(
			'_galaxyone_delivery_slot_key',
			sanitize_title( (string) $selection['slot_key'] )
		);
		$order->update_meta_data(
			'_galaxyone_delivery_slot_label',
			is_array( $slot ) ? sanitize_text_field( (string) $slot['label'] ) : ''
		);
		$order->update_meta_data(
			'_galaxyone_delivery_postcode',
			sanitize_text_field( (string) $selection['postcode'] )
		);
		$order->update_meta_data(
			'_galaxyone_delivery_service_area',
			is_array( $area ) ? sanitize_text_field( (string) $area['label'] ) : ''
		);
		$order->update_meta_data(
			'_galaxyone_delivery_fee',
			(string) $fee['applied_fee']
		);
		$order->update_meta_data(
			'_galaxyone_delivery_normal_fee',
			(string) $fee['normal_fee']
		);
		$order->update_meta_data(
			'_galaxyone_free_delivery_campaign_key',
			sanitize_title( (string) $fee['campaign_key'] )
		);
		$order->update_meta_data(
			'_galaxyone_delivery_reservation_token',
			isset( $reservation['token'] ) && is_scalar( $reservation['token'] )
				? sanitize_text_field( (string) $reservation['token'] )
				: ''
		);
		$order->update_meta_data( '_galaxyone_landmark', $landmark );
	}

	/**
	 * Confirms the provisional delivery reservation for a created order.
	 *
	 * @param WC_Order $order Created order.
	 * @return bool
	 */
	public static function confirm_delivery_reservation( WC_Order $order ): bool {
		$reservation_token = (string) $order->get_meta(
			'_galaxyone_delivery_reservation_token',
			true
		);

		if ( '' === $reservation_token ) {
			return false;
		}

		$assigned = DeliveryReservationService::assign_to_order(
			$reservation_token,
			$order->get_id()
		);

		if ( $assigned ) {
			CartRecalculationService::clear_cart_reservation();
		}

		return $assigned;
	}

	/**
	 * Releases an order's delivery reservation.
	 *
	 * @param WC_Order $order  WooCommerce order.
	 * @param string   $status Final reservation status.
	 * @return bool
	 */
	public static function release_delivery_reservation( WC_Order $order, string $status ): bool {
		$reservation_token = (string) $order->get_meta(
			'_galaxyone_delivery_reservation_token',
			true
		);

		if ( '' === $reservation_token ) {
			return false;
		}

		return DeliveryReservationService::release( $reservation_token, $status );
	}
}
