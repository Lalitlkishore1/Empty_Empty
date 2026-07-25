<?php
/**
 * Order query service.
 *
 * @package GalaxyOne\Core\Orders
 */

namespace GalaxyOne\Core\Orders;

use WC_Order;

final class OrderQueryService {

	/**
	 * Returns a paginated set of orders for operations screens.
	 *
	 * @param array<string, mixed> $filters Search and filtering values.
	 * @return array{orders: array<int, WC_Order>, total: int, pages: int}
	 */
	public static function get_orders( array $filters = array() ): array {
		$page      = isset( $filters['page_number'] ) ? max( 1, absint( $filters['page_number'] ) ) : 1;
		$limit     = isset( $filters['limit'] ) ? max( 1, min( 50, absint( $filters['limit'] ) ) ) : 20;
		$status    = isset( $filters['status'] ) ? sanitize_key( (string) $filters['status'] ) : '';
		$search    = isset( $filters['search'] ) ? sanitize_text_field( (string) $filters['search'] ) : '';
		$date_from = isset( $filters['date_from'] ) ? self::sanitize_date( (string) $filters['date_from'] ) : '';
		$date_to   = isset( $filters['date_to'] ) ? self::sanitize_date( (string) $filters['date_to'] ) : '';

		$args = array(
			'type'     => 'shop_order',
			'limit'    => $limit,
			'page'     => $page,
			'paginate' => true,
			'orderby'  => 'date',
			'order'    => 'DESC',
		);

		if ( '' !== $status && array_key_exists( 'wc-' . $status, wc_get_order_statuses() ) ) {
			$args['status'] = 'wc-' . $status;
		}

		if ( '' !== $search ) {
			$order_ids = wc_order_search( $search );

			if ( empty( $order_ids ) ) {
				return array(
					'orders' => array(),
					'total'  => 0,
					'pages'  => 0,
				);
			}

			$args['include'] = array_map( 'absint', $order_ids );
		}

		if ( '' !== $date_from && '' !== $date_to ) {
			$args['date_created'] = $date_from . '...' . $date_to . ' 23:59:59';
		} elseif ( '' !== $date_from ) {
			$args['date_created'] = '>=' . $date_from;
		} elseif ( '' !== $date_to ) {
			$args['date_created'] = '<=' . $date_to . ' 23:59:59';
		}

		$result = wc_get_orders( $args );

		if ( ! is_object( $result ) || ! isset( $result->orders, $result->total, $result->max_num_pages ) ) {
			return array(
				'orders' => array(),
				'total'  => 0,
				'pages'  => 0,
			);
		}

		$orders = array_values(
			array_filter(
				$result->orders,
				static fn( $order ): bool => $order instanceof WC_Order
			)
		);

		return array(
			'orders' => $orders,
			'total'  => (int) $result->total,
			'pages'  => (int) $result->max_num_pages,
		);
	}

	/**
	 * Returns a customer's recent orders.
	 *
	 * @param int $customer_id WordPress customer ID.
	 * @param int $limit       Maximum orders to return.
	 * @return array<int, WC_Order>
	 */
	public static function get_customer_orders( int $customer_id, int $limit = 20 ): array {
		if ( $customer_id <= 0 ) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'type'     => 'shop_order',
				'customer' => $customer_id,
				'limit'    => max( 1, min( 50, $limit ) ),
				'orderby'  => 'date',
				'order'    => 'DESC',
			)
		);

		return is_array( $orders )
			? array_values(
				array_filter(
					$orders,
					static fn( $order ): bool => $order instanceof WC_Order
				)
			)
			: array();
	}

	/**
	 * Returns dashboard figures calculated from stored order data.
	 *
	 * @return array<string, int|float>
	 */
	public static function get_dashboard_metrics(): array {
		$today  = wp_date( 'Y-m-d' );
		$orders = wc_get_orders(
			array(
				'type'         => 'shop_order',
				'limit'        => -1,
				'date_created' => '>=' . $today,
				'orderby'      => 'date',
				'order'        => 'DESC',
			)
		);

		$today_orders   = 0;
		$today_revenue  = 0.0;
		$open_orders    = 0;
		$completed_count = 0;

		if ( is_array( $orders ) ) {
			foreach ( $orders as $order ) {
				if ( ! $order instanceof WC_Order ) {
					continue;
				}

				++$today_orders;

				if ( in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
					$today_revenue += (float) $order->get_total();
				}

				if ( in_array( $order->get_status(), array( 'pending', 'on-hold', 'processing' ), true ) ) {
					++$open_orders;
				}

				if ( 'completed' === $order->get_status() ) {
					++$completed_count;
				}
			}
		}

		return array(
			'today_orders'    => $today_orders,
			'today_revenue'   => $today_revenue,
			'open_orders'     => $open_orders,
			'completed_orders' => $completed_count,
		);
	}

	/**
	 * Returns allowed destination statuses for one order.
	 *
	 * @param string $current_status Current WooCommerce status without the wc- prefix.
	 * @return array<int, string>
	 */
	public static function get_allowed_transitions( string $current_status ): array {
		$transitions = array(
			'pending'    => array( 'on-hold', 'processing', 'cancelled', 'failed' ),
			'failed'     => array( 'pending', 'on-hold', 'cancelled' ),
			'on-hold'    => array( 'processing', 'cancelled', 'failed' ),
			'processing' => array( 'completed', 'on-hold', 'cancelled', 'refunded' ),
			'completed'  => array( 'refunded' ),
			'cancelled'  => array(),
			'refunded'   => array(),
		);

		return $transitions[ $current_status ] ?? array();
	}

	/**
	 * Validates one order-status transition.
	 *
	 * @param string $from_status Current status.
	 * @param string $to_status   Proposed status.
	 * @return bool
	 */
	public static function can_transition( string $from_status, string $to_status ): bool {
		return in_array(
			sanitize_key( $to_status ),
			self::get_allowed_transitions( sanitize_key( $from_status ) ),
			true
		);
	}

	/**
	 * Validates a date in Y-m-d format.
	 *
	 * @param string $date Date to validate.
	 * @return string
	 */
	private static function sanitize_date( string $date ): string {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return '';
		}

		$parts = explode( '-', $date );

		return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ? $date : '';
	}
}
