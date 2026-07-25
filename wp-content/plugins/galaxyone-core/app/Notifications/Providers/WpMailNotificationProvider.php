<?php
/**
 * WordPress mail notification provider.
 *
 * @package GalaxyOne\Core\Notifications\Providers
 */

namespace GalaxyOne\Core\Notifications\Providers;

use GalaxyOne\Core\Notifications\Contracts\NotificationProviderInterface;
use WC_Order;
use WP_Error;

final class WpMailNotificationProvider implements NotificationProviderInterface {

	/**
	 * Returns the provider key.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return 'wp_mail';
	}

	/**
	 * Sends an order confirmation or status notification.
	 *
	 * @param WC_Order $order     WooCommerce order.
	 * @param string   $event_key Notification event.
	 * @return true|WP_Error
	 */
	public function send( WC_Order $order, string $event_key ) {
		$recipient = sanitize_email( $order->get_billing_email() );

		if ( '' === $recipient || ! is_email( $recipient ) ) {
			return new WP_Error(
				'galaxyone_notification_missing_recipient',
				__( 'The order does not have a valid notification email address.', 'galaxyone-core' )
			);
		}

		$subject = $this->get_subject( $order, $event_key );
		$body    = $this->get_body( $order, $event_key );
		$sent    = wp_mail( $recipient, $subject, $body );

		if ( ! $sent ) {
			return new WP_Error(
				'galaxyone_notification_delivery_failed',
				__( 'WordPress could not send the order notification.', 'galaxyone-core' )
			);
		}

		return true;
	}

	/**
	 * Returns an email subject for an order event.
	 *
	 * @param WC_Order $order     WooCommerce order.
	 * @param string   $event_key Notification event.
	 * @return string
	 */
	private function get_subject( WC_Order $order, string $event_key ): string {
		$order_number = $order->get_order_number();
		$site_name    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		if ( 'order_confirmation' === $event_key ) {
			return sprintf(
				/* translators: 1: site name, 2: order number. */
				__( '%1$s: order #%2$s received', 'galaxyone-core' ),
				$site_name,
				$order_number
			);
		}

		return sprintf(
			/* translators: 1: site name, 2: order number. */
			__( '%1$s: order #%2$s status update', 'galaxyone-core' ),
			$site_name,
			$order_number
		);
	}

	/**
	 * Returns a plain-text email body for an order event.
	 *
	 * @param WC_Order $order     WooCommerce order.
	 * @param string   $event_key Notification event.
	 * @return string
	 */
	private function get_body( WC_Order $order, string $event_key ): string {
		$customer_name = sanitize_text_field( $order->get_formatted_billing_full_name() );
		$order_number  = $order->get_order_number();
		$status        = wc_get_order_status_name( $order->get_status() );
		$total         = wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) );

		if ( 'order_confirmation' === $event_key ) {
			return sprintf(
				/* translators: 1: customer name, 2: order number, 3: order total. */
				__(
					"Hello %1\$s,\n\nWe received your order #%2\$s.\nOrder total: %3\$s.\n\nThank you for shopping with us.",
					'galaxyone-core'
				),
				$customer_name,
				$order_number,
				$total
			);
		}

		return sprintf(
			/* translators: 1: customer name, 2: order number, 3: order status. */
			__(
				"Hello %1\$s,\n\nThe status of your order #%2\$s is now: %3\$s.\n\nThank you for shopping with us.",
				'galaxyone-core'
			),
			$customer_name,
			$order_number,
			$status
		);
	}
}
