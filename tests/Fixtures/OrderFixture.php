<?php
/**
 * WooCommerce order fixture.
 *
 * @package GalaxyOne\Tests\Fixtures
 */

declare(strict_types=1);

namespace GalaxyOne\Tests\Fixtures;

use WC_Order;
use WC_Product_Simple;

final class OrderFixture {

	/**
	 * Creates a persisted WooCommerce order with one simple product.
	 *
	 * @return WC_Order
	 */
	public static function create(): WC_Order {
		$product = new WC_Product_Simple();
		$product->set_name( 'GalaxyOne Test Product' );
		$product->set_regular_price( '25.00' );
		$product->set_price( '25.00' );
		$product->set_status( 'publish' );
		$product->save();

		$order = wc_create_order();

		if ( ! $order instanceof WC_Order ) {
			throw new \RuntimeException( 'WooCommerce did not create the test order.' );
		}

		$order->add_product( $product, 1 );
		$order->set_address(
			array(
				'first_name' => 'GalaxyOne',
				'last_name'  => 'Tester',
				'email'      => 'galaxyone-test@example.test',
				'postcode'   => '600001',
				'country'    => 'IN',
			),
			'billing'
		);
		$order->calculate_totals();
		$order->save();

		return $order;
	}
}
