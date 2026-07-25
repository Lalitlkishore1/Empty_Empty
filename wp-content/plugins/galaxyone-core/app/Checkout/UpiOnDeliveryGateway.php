<?php
/**
 * UPI-on-delivery payment gateway.
 *
 * @package GalaxyOne\Core\Checkout
 */

namespace GalaxyOne\Core\Checkout;

use WC_Order;
use WC_Payment_Gateway;

final class UpiOnDeliveryGateway extends WC_Payment_Gateway {

	/**
	 * Initializes the gateway.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->id                 = 'galaxyone_upi_on_delivery';
		$this->method_title       = __( 'UPI on Delivery', 'galaxyone-core' );
		$this->method_description = __(
			'Allow customers to select UPI payment collected at delivery.',
			'galaxyone-core'
		);
		$this->has_fields         = false;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = (string) $this->get_option(
			'title',
			__( 'UPI on Delivery', 'galaxyone-core' )
		);
		$this->description = (string) $this->get_option(
			'description',
			__( 'Pay by UPI when your order is delivered.', 'galaxyone-core' )
		);
		$this->enabled     = (string) $this->get_option( 'enabled', 'no' );

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'process_admin_options' )
		);
	}

	/**
	 * Defines WooCommerce payment settings.
	 *
	 * @return void
	 */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable/Disable', 'galaxyone-core' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable UPI on Delivery', 'galaxyone-core' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'       => __( 'Title', 'galaxyone-core' ),
				'type'        => 'text',
				'description' => __( 'Shown to customers during checkout.', 'galaxyone-core' ),
				'default'     => __( 'UPI on Delivery', 'galaxyone-core' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'galaxyone-core' ),
				'type'        => 'textarea',
				'description' => __( 'Shown to customers during checkout.', 'galaxyone-core' ),
				'default'     => __( 'Pay by UPI when your order is delivered.', 'galaxyone-core' ),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Creates an unpaid order for payment collection at delivery.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array<string, string>
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			wc_add_notice(
				__( 'Your order could not be created. Please try again.', 'galaxyone-core' ),
				'error'
			);

			return array(
				'result'   => 'failure',
				'redirect' => '',
			);
		}

		$order->update_status(
			'on-hold',
			__( 'UPI on Delivery selected by customer.', 'galaxyone-core' )
		);

		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}
}
