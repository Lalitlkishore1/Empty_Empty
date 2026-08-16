<?php
/**
 * Orders module.
 *
 * @package GalaxyOne\Core\Orders
 */

namespace GalaxyOne\Core\Orders;

use GalaxyOne\Core\Notifications\NotificationLogRepository;
use GalaxyOne\Core\Contracts\ModuleInterface;
use GalaxyOne\Core\Security\Capabilities;
use WC_Order;

final class OrdersModule implements ModuleInterface {

	/**
	 * Administration page slug.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'galaxyone-orders';

	/**
	 * Administrative action handler.
	 *
	 * @var AdminOrderActions
	 */
	private AdminOrderActions $actions;

	/**
	 * Creates the orders module.
	 */
	public function __construct() {
		$this->actions = new AdminOrderActions();
	}

	/**
	 * Registers order operations.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action(
			'woocommerce_admin_order_data_after_shipping_address',
			array( OrderMetaService::class, 'render_water_delivery_handling_snapshot' )
		);
		$this->actions->register();
	}

	/**
	 * Registers the order operations page.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'galaxyone-core',
			__( 'Orders', 'galaxyone-core' ),
			__( 'Orders', 'galaxyone-core' ),
			Capabilities::get_manage_capability(),
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Renders order operations.
	 *
	 * @return void
	 */
	public function render_page(): void {
		$this->assert_access();

		$filters = array(
			'search'      => $this->get_query_string( 'order_search' ),
			'status'      => $this->get_query_string( 'order_status' ),
			'page_number' => max( 1, absint( $this->get_query_string( 'paged' ) ) ),
		);
		$result  = OrderQueryService::get_orders( $filters );
		$order   = $this->get_selected_order();
		$logs    = $order instanceof WC_Order
			? NotificationLogRepository::get_for_order( $order->get_id() )
			: array();
		$notice  = $this->get_query_string( 'galaxyone_notice' );

		require GALAXYONE_CORE_PATH . 'templates/admin/orders/orders-page.php';
	}

	/**
	 * Returns one selected order for the current administrator.
	 *
	 * @return WC_Order|null
	 */
	private function get_selected_order(): ?WC_Order {
		$order_id = absint( $this->get_query_string( 'order_id' ) );
		$order    = wc_get_order( $order_id );

		return $order instanceof WC_Order ? $order : null;
	}

	/**
	 * Returns a sanitized query-string value.
	 *
	 * @param string $key Query argument key.
	 * @return string
	 */
	private function get_query_string( string $key ): string {
		return isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';
	}

	/**
	 * Stops unauthorized access before reading customer or order data.
	 *
	 * @return void
	 */
	private function assert_access(): void {
		if ( Capabilities::can_manage_galaxyone() ) {
			return;
		}

		wp_die(
			esc_html__( 'You do not have permission to access orders.', 'galaxyone-core' ),
			esc_html__( 'Orders', 'galaxyone-core' ),
			array( 'response' => 403 )
		);
	}
}
