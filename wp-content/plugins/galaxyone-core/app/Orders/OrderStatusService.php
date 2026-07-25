<?php
/**
 * Order status service.
 *
 * @package GalaxyOne\Core\Orders
 */

namespace GalaxyOne\Core\Orders;

use GalaxyOne\Core\ActivityLog\ActivityLogRepository;
use WC_Order;

final class OrderStatusService {

	/**
	 * Registers order-status lifecycle hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action(
			'woocommerce_order_status_changed',
			array( self::class, 'handle_status_change' ),
			20,
			4
		);
	}

	/**
	 * Releases delivery capacity for cancelled or failed orders.
	 *
	 * Order-management user interfaces and broader order workflows remain
	 * deferred to Phase 11.
	 *
	 * @param int      $order_id   WooCommerce order ID.
	 * @param string   $from_status Previous WooCommerce status.
	 * @param string   $to_status   New WooCommerce status.
	 * @param WC_Order $order      WooCommerce order.
	 * @return void
	 */
	public static function handle_status_change(
		int $order_id,
		string $from_status,
		string $to_status,
		WC_Order $order
	): void {
		if ( ! in_array( $to_status, array( 'cancelled', 'failed' ), true ) ) {
			return;
		}

		$reservation_status = 'cancelled' === $to_status ? 'cancelled' : 'failed';
		$released           = OrderMetaService::release_delivery_reservation(
			$order,
			$reservation_status
		);

		if ( ! $released ) {
			return;
		}

		ActivityLogRepository::record(
			'order_delivery_reservation_released',
			array(
				'order_id' => $order_id,
				'status'   => $from_status,
			),
			array(
				'order_id' => $order_id,
				'status'   => $to_status,
			),
			array(
				'source' => 'woocommerce_order_status',
			)
		);
	}
}
