<?php
/**
 * Notifications module.
 *
 * @package GalaxyOne\Core\Notifications
 */

namespace GalaxyOne\Core\Notifications;

use GalaxyOne\Core\ActivityLog\ActivityLogRepository;
use GalaxyOne\Core\Contracts\ModuleInterface;
use GalaxyOne\Core\Notifications\Providers\WpMailNotificationProvider;
use WC_Order;
use WP_Error;

final class NotificationsModule implements ModuleInterface {

	/**
	 * Notification provider.
	 *
	 * @var WpMailNotificationProvider
	 */
	private WpMailNotificationProvider $provider;

	/**
	 * Creates the notification module.
	 */
	public function __construct() {
		$this->provider = new WpMailNotificationProvider();
	}

	/**
	 * Registers order notification events.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'woocommerce_checkout_order_created',
			array( $this, 'send_order_confirmation' ),
			30
		);

		add_action(
			'woocommerce_order_status_changed',
			array( $this, 'send_status_notification' ),
			30,
			4
		);
	}

	/**
	 * Sends one confirmation notification for a newly created order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return void
	 */
	public function send_order_confirmation( WC_Order $order ): void {
		$this->dispatch( $order, 'order_confirmation' );
	}

	/**
	 * Sends one notification for each resulting order status.
	 *
	 * @param int      $order_id   WooCommerce order ID.
	 * @param string   $from_status Previous status.
	 * @param string   $to_status   New status.
	 * @param WC_Order $order      WooCommerce order.
	 * @return void
	 */
	public function send_status_notification(
		int $order_id,
		string $from_status,
		string $to_status,
		WC_Order $order
	): void {
		unset( $order_id, $from_status );

		$this->dispatch( $order, 'order_status_' . sanitize_key( $to_status ) );
	}

	/**
	 * Creates and finalizes a single delivery log for an order event.
	 *
	 * @param WC_Order $order     WooCommerce order.
	 * @param string   $event_key Notification event key.
	 * @return void
	 */
	private function dispatch( WC_Order $order, string $event_key ): void {
		$recipient = sanitize_email( $order->get_billing_email() );
		$subject   = $this->get_log_subject( $order, $event_key );
		$log_id    = NotificationLogRepository::queue(
			$order->get_id(),
			$event_key,
			$this->provider->get_key(),
			$recipient,
			$subject
		);

		if ( null === $log_id ) {
			return;
		}

		if ( '' === $recipient || ! is_email( $recipient ) ) {
			NotificationLogRepository::mark_result(
				$log_id,
				NotificationLogRepository::STATUS_SKIPPED,
				__( 'No valid customer email address is available.', 'galaxyone-core' )
			);
			$this->record_activity( $order, $event_key, NotificationLogRepository::STATUS_SKIPPED );

			return;
		}

		$result = $this->provider->send( $order, $event_key );

		if ( $result instanceof WP_Error ) {
			NotificationLogRepository::mark_result(
				$log_id,
				NotificationLogRepository::STATUS_FAILED,
				$result->get_error_message()
			);
			$this->record_activity( $order, $event_key, NotificationLogRepository::STATUS_FAILED );

			return;
		}

		NotificationLogRepository::mark_result( $log_id, NotificationLogRepository::STATUS_SENT );
		$this->record_activity( $order, $event_key, NotificationLogRepository::STATUS_SENT );
	}

	/**
	 * Returns a safe delivery-log subject.
	 *
	 * @param WC_Order $order     WooCommerce order.
	 * @param string   $event_key Notification event.
	 * @return string
	 */
	private function get_log_subject( WC_Order $order, string $event_key ): string {
		if ( 'order_confirmation' === $event_key ) {
			return sprintf(
				/* translators: %s: order number. */
				__( 'Order #%s received', 'galaxyone-core' ),
				$order->get_order_number()
			);
		}

		return sprintf(
			/* translators: %s: order number. */
			__( 'Order #%s status update', 'galaxyone-core' ),
			$order->get_order_number()
		);
	}

	/**
	 * Records notification delivery without storing customer contact data.
	 *
	 * @param WC_Order $order     WooCommerce order.
	 * @param string   $event_key Notification event.
	 * @param string   $status    Delivery status.
	 * @return void
	 */
	private function record_activity( WC_Order $order, string $event_key, string $status ): void {
		ActivityLogRepository::record(
			'order_notification_' . sanitize_key( $status ),
			array(),
			array(
				'order_id'  => $order->get_id(),
				'event_key' => sanitize_key( $event_key ),
				'status'    => sanitize_key( $status ),
			),
			array( 'source' => 'galaxyone_notifications' )
		);
	}
}
