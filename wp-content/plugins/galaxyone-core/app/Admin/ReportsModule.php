<?php
/**
 * Reports module.
 *
 * @package GalaxyOne\Core\Admin
 */

namespace GalaxyOne\Core\Admin;

use GalaxyOne\Core\Contracts\ModuleInterface;
use GalaxyOne\Core\Orders\OrderQueryService;
use GalaxyOne\Core\Security\Capabilities;
use WC_Order;

final class ReportsModule implements ModuleInterface {

	/**
	 * Reports page slug.
	 *
	 * @var string
	 */
	private const PAGE_SLUG = 'galaxyone-reports';

	/**
	 * Registers reporting administration.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
	}

	/**
	 * Registers the reports submenu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'galaxyone-core',
			__( 'Reports', 'galaxyone-core' ),
			__( 'Reports', 'galaxyone-core' ),
			Capabilities::get_manage_capability(),
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Renders the initial operational report.
	 *
	 * @return void
	 */
	public function render_page(): void {
		$this->assert_access();

		$date_from = $this->get_date_query_value( 'date_from' );
		$date_to   = $this->get_date_query_value( 'date_to' );
		$result    = OrderQueryService::get_orders(
			array(
				'date_from' => $date_from,
				'date_to'   => $date_to,
				'limit'     => 50,
			)
		);
		$summary   = $this->summarize_orders( $result['orders'] );

		require GALAXYONE_CORE_PATH . 'templates/admin/reports/reports-page.php';
	}

	/**
	 * Produces report figures from immutable stored order totals.
	 *
	 * @param array<int, WC_Order> $orders Orders in the report.
	 * @return array<string, int|float>
	 */
	private function summarize_orders( array $orders ): array {
		$revenue        = 0.0;
		$completed      = 0;
		$cancelled      = 0;
		$delivery_fees  = 0.0;

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			if ( in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
				$revenue += (float) $order->get_total();
			}

			if ( 'completed' === $order->get_status() ) {
				++$completed;
			}

			if ( in_array( $order->get_status(), array( 'cancelled', 'failed', 'refunded' ), true ) ) {
				++$cancelled;
			}

			$delivery_fees += (float) $order->get_meta( '_galaxyone_delivery_fee', true );
		}

		return array(
			'order_count'   => count( $orders ),
			'revenue'       => $revenue,
			'completed'     => $completed,
			'cancelled'     => $cancelled,
			'delivery_fees' => $delivery_fees,
		);
	}

	/**
	 * Returns a validated date query value.
	 *
	 * @param string $key Query key.
	 * @return string
	 */
	private function get_date_query_value( string $key ): string {
		$value = isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}

		$parts = explode( '-', $value );

		return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ? $value : '';
	}

	/**
	 * Stops unauthorized access before report data is read.
	 *
	 * @return void
	 */
	private function assert_access(): void {
		if ( Capabilities::can_manage_galaxyone() ) {
			return;
		}

		wp_die(
			esc_html__( 'You do not have permission to access reports.', 'galaxyone-core' ),
			esc_html__( 'Reports', 'galaxyone-core' ),
			array( 'response' => 403 )
		);
	}
}
