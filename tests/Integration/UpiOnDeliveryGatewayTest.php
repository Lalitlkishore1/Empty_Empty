<?php
/**
 * UPI-on-delivery gateway integration tests.
 *
 * @package GalaxyOne\Tests\Integration
 */

declare(strict_types=1);

namespace GalaxyOne\Tests\Integration;

use GalaxyOne\Core\Checkout\CheckoutModule;
use GalaxyOne\Core\Checkout\UpiOnDeliveryGateway;
use GalaxyOne\Tests\Support\IntegrationTestCase;
use WC_Gateway_COD;
use WC_Payment_Gateway;

final class UpiOnDeliveryGatewayTest extends IntegrationTestCase {

	private CheckoutModule $checkout_module;

	public function setUp(): void {
		parent::setUp();

		$this->checkout_module = new CheckoutModule();
		$this->checkout_module->register();
	}

	public function tearDown(): void {
		remove_filter(
			'woocommerce_payment_gateways',
			array( $this->checkout_module, 'register_payment_gateways' )
		);

		parent::tearDown();
	}

	public function test_upi_on_delivery_gateway_is_registered_without_replacing_existing_gateways(): void {
		$gateways = apply_filters(
			'woocommerce_payment_gateways',
			array( WC_Gateway_COD::class )
		);

		self::assertSame(
			array(
				WC_Gateway_COD::class,
				UpiOnDeliveryGateway::class,
			),
			$gateways
		);
	}

	public function test_upi_on_delivery_gateway_registration_is_idempotent_and_instantiable(): void {
		$gateways = apply_filters(
			'woocommerce_payment_gateways',
			array( WC_Gateway_COD::class )
		);
		$gateways = apply_filters( 'woocommerce_payment_gateways', $gateways );

		self::assertSame( 1, count( array_keys( $gateways, UpiOnDeliveryGateway::class, true ) ) );
		self::assertContains( WC_Gateway_COD::class, $gateways );

		$gateway_class = UpiOnDeliveryGateway::class;
		$gateway       = new $gateway_class();

		self::assertInstanceOf( WC_Payment_Gateway::class, $gateway );
		self::assertSame( 'galaxyone_upi_on_delivery', $gateway->id );
		self::assertSame( 'no', $gateway->enabled );
		self::assertContains( 'products', $gateway->supports );
	}
}
