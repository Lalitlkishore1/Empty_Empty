<?php
/**
 * Administrative order actions.
 *
 * @package GalaxyOne\Core\Orders
 */

namespace GalaxyOne\Core\Orders;

use GalaxyOne\Core\ActivityLog\ActivityLogRepository;
use GalaxyOne\Core\Security\Capabilities;
use GalaxyOne\Core\Security\NonceVerifier;
use WC_Order;

final class AdminOrderActions {

	/**
	 * Administrative order-status action.
	 *
	 * @var string
	 */
	private const ACTION = 'galaxyone_update_order_status';

	/**
	 * Registers administrative order actions.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'update_order_status' ) );
	}

	/**
	 * Updates one order through an allowed state transition.
	 *
	 * @return void
	 */
	public function update_order_status(): void {
		if ( ! Capabilities::can_manage_galaxyone() ) {
			wp_die(
				esc_html__( 'You do not have permission to manage orders.', 'galaxyone-core' ),
				esc_html__( 'Orders', 'galaxyone-core' ),
				array( 'response' => 403 )
			);
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$order    = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			$this->redirect( $order_id, 'not-found' );
		}

		if (
			! NonceVerifier::verify_request_nonce(
				self::ACTION . '_' . $order_id,
				'galaxyone_order_status_nonce'
			)
		) {
			wp_die(
				esc_html__( 'The security check failed. No order was changed.', 'galaxyone-core' ),
				esc_html__( 'Orders', 'galaxyone-core' ),
				array( 'response' => 403 )
			);
		}

		$new_status = isset( $_POST['new_status'] ) && is_string( $_POST['new_status'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? sanitize_key( wp_unslash( $_POST['new_status'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			: '';
		$old_status = $order->get_status();

		if ( ! OrderQueryService::can_transition( $old_status, $new_status ) ) {
			$this->redirect( $order_id, 'invalid-transition' );
		}

		$order->update_status(
			$new_status,
			__( 'Order status updated through GalaxyOne operations.', 'galaxyone-core' ),
			true
		);

		ActivityLogRepository::record(
			'order_status_updated',
			array(
				'order_id' => $order_id,
				'status'   => $old_status,
			),
			array(
				'order_id' => $order_id,
				'status'   => $new_status,
			),
			array( 'source' => 'galaxyone_orders_admin' )
		);

		$this->redirect( $order_id, 'updated' );
	}

	/**
	 * Redirects to the selected order operations page.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $notice   Notice identifier.
	 * @return void
	 */
	private function redirect( int $order_id, string $notice ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => OrdersModule::PAGE_SLUG,
					'order_id'         => max( 0, $order_id ),
					'galaxyone_notice' => sanitize_key( $notice ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
