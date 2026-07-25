<?php
/**
 * Notification provider contract.
 *
 * @package GalaxyOne\Core\Notifications\Contracts
 */

namespace GalaxyOne\Core\Notifications\Contracts;

use WC_Order;
use WP_Error;

interface NotificationProviderInterface {

	/**
	 * Returns the stable provider key.
	 *
	 * @return string
	 */
	public function get_key(): string;

	/**
	 * Delivers a notification for an order event.
	 *
	 * @param WC_Order $order     WooCommerce order.
	 * @param string   $event_key Notification event.
	 * @return true|WP_Error
	 */
	public function send( WC_Order $order, string $event_key );
}
