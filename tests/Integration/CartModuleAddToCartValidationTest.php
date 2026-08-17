<?php
/**
 * Cart-module add-to-cart validation integration tests.
 *
 * @package GalaxyOne\Tests\Integration
 */

declare(strict_types=1);

namespace GalaxyOne\Tests\Integration;

use GalaxyOne\Core\Cart\CartModule;
use GalaxyOne\Core\Inventory\InventoryModule;
use GalaxyOne\Core\Inventory\InventoryService;
use GalaxyOne\Core\Products\ProductCategoryResolver;
use GalaxyOne\Core\Products\ProductsModule;
use GalaxyOne\Tests\Support\IntegrationTestCase;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WC_Session;

final class CartModuleAddToCartValidationTest extends IntegrationTestCase {

	private CartModule $cart_module;

	private InventoryModule $inventory_module;

	private ProductsModule $products_module;

	/**
	 * @var array<int, int>
	 */
	private array $product_ids = array();

	/**
	 * @var mixed
	 */
	private $previous_session;

	/**
	 * @var array<string, mixed>
	 */
	private array $session_values = array();

	public function setUp(): void {
		parent::setUp();

		$this->configure_notice_session();
		$this->cart_module      = new CartModule();
		$this->inventory_module = new InventoryModule();
		$this->products_module  = new ProductsModule();
		$this->cart_module->register();
		$this->inventory_module->register();
		$this->products_module->register();
		wc_clear_notices();
	}

	public function tearDown(): void {
		$this->remove_module_hooks();

		foreach ( $this->product_ids as $product_id ) {
			wp_delete_post( $product_id, true );
		}

		$woocommerce          = WC();
		$woocommerce->session = $this->previous_session;

		parent::tearDown();
	}

	public function test_eligible_simple_product_accepts_the_three_argument_filter_shape(): void {
		$product_id = $this->create_water_product( true );
		$execution  = array();
		$before      = static function ( bool $passed ) use ( &$execution ): bool {
			$execution[] = 'before_cart';

			return $passed;
		};
		$after_inventory = static function ( bool $passed ) use ( &$execution ): bool {
			$execution[] = 'after_inventory';

			return $passed;
		};
		$after_products = static function ( bool $passed ) use ( &$execution ): bool {
			$execution[] = 'after_products';

			return $passed;
		};

		add_filter( 'woocommerce_add_to_cart_validation', $before, 9, 1 );
		add_filter( 'woocommerce_add_to_cart_validation', $after_inventory, 11, 1 );
		add_filter( 'woocommerce_add_to_cart_validation', $after_products, 21, 1 );

		try {
			self::assertTrue(
				apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, 2 )
			);
			self::assertSame(
				array( 'before_cart', 'after_inventory', 'after_products' ),
				$execution
			);
		} finally {
			remove_filter( 'woocommerce_add_to_cart_validation', $before, 9 );
			remove_filter( 'woocommerce_add_to_cart_validation', $after_inventory, 11 );
			remove_filter( 'woocommerce_add_to_cart_validation', $after_products, 21 );
		}
	}

	public function test_unavailable_product_remains_rejected_with_the_three_argument_filter_shape(): void {
		$product_id = $this->create_water_product( false );

		self::assertFalse(
			apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, 2 )
		);
		self::assertSame(
			array(
				array(
					'notice' => 'This product is currently unavailable and cannot be purchased.',
					'data'   => array(),
				),
			),
			wc_get_notices( 'error' )
		);
	}

	public function test_five_argument_filter_shape_preserves_variation_product_selection(): void {
		$parent_id    = $this->create_water_variable_product();
		$variation_id = $this->create_unavailable_water_variation( $parent_id );

		self::assertFalse(
			apply_filters(
				'woocommerce_add_to_cart_validation',
				true,
				$parent_id,
				2,
				$variation_id,
				array( 'attribute_pa_size' => 'large' )
			)
		);
	}

	public function test_cart_module_registration_accepts_up_to_five_arguments(): void {
		self::assertSame(
			10,
			has_filter(
				'woocommerce_add_to_cart_validation',
				array( $this->cart_module, 'validate_add_to_cart' )
			)
		);

		$hook              = $GLOBALS['wp_filter']['woocommerce_add_to_cart_validation'];
		$registered_filter = null;

		foreach ( $hook->callbacks[10] as $callback ) {
			if ( array( $this->cart_module, 'validate_add_to_cart' ) === $callback['function'] ) {
				$registered_filter = $callback;
				break;
			}
		}

		self::assertIsArray( $registered_filter );
		self::assertSame( 5, $registered_filter['accepted_args'] );
	}

	private function configure_notice_session(): void {
		$session_values = &$this->session_values;
		$session        = $this->createMock( WC_Session::class );

		$session->method( 'get' )->willReturnCallback(
			static function ( string $key, mixed $default = null ) use ( &$session_values ): mixed {
				return array_key_exists( $key, $session_values )
					? $session_values[ $key ]
					: $default;
			}
		);
		$session->method( 'set' )->willReturnCallback(
			static function ( string $key, mixed $value ) use ( &$session_values ): void {
				$session_values[ $key ] = $value;
			}
		);

		$woocommerce            = WC();
		$this->previous_session = $woocommerce->session;
		$woocommerce->session   = $session;
	}

	private function remove_module_hooks(): void {
		remove_filter(
			'woocommerce_add_to_cart_validation',
			array( $this->cart_module, 'validate_add_to_cart' ),
			10
		);
		remove_filter(
			'woocommerce_add_to_cart_validation',
			array( $this->inventory_module, 'validate_stock' ),
			10
		);
		remove_filter(
			'woocommerce_add_to_cart_validation',
			array( $this->products_module, 'validate_product_availability' ),
			20
		);
		remove_filter(
			'woocommerce_is_purchasable',
			array( $this->products_module, 'filter_purchasable_status' ),
			10
		);
		remove_action(
			'woocommerce_product_options_inventory_product_data',
			array( $this->products_module, 'render_water_availability_field' )
		);
		remove_action(
			'woocommerce_admin_process_product_object',
			array( $this->products_module, 'save_water_availability' )
		);
		remove_action(
			'woocommerce_single_product_summary',
			array( $this->products_module, 'render_unavailable_message' ),
			29
		);
	}

	private function create_water_product( bool $is_available ): int {
		$product = new WC_Product_Simple();
		$product->set_name( 'Cart validation Water product' );
		$product->set_regular_price( '25.00' );
		$product->set_price( '25.00' );
		$product->set_status( 'publish' );
		$product->set_stock_status( 'instock' );
		$product->save();

		$product_id = $product->get_id();

		self::assertGreaterThan( 0, $product_id );
		self::assertFalse(
			is_wp_error(
				wp_set_object_terms(
					$product_id,
					ProductCategoryResolver::WATER_CATEGORY,
					'product_cat'
				)
			)
		);
		update_post_meta(
			$product_id,
			InventoryService::WATER_AVAILABILITY_META_KEY,
			$is_available ? 'yes' : 'no'
		);
		$this->product_ids[] = $product_id;

		return $product_id;
	}

	private function create_water_variable_product(): int {
		$product = new WC_Product_Variable();
		$product->set_name( 'Cart validation Water variable product' );
		$product->set_regular_price( '25.00' );
		$product->set_price( '25.00' );
		$product->set_status( 'publish' );
		$product->set_stock_status( 'instock' );
		$product->save();

		$product_id = $product->get_id();

		self::assertGreaterThan( 0, $product_id );
		self::assertFalse(
			is_wp_error(
				wp_set_object_terms(
					$product_id,
					ProductCategoryResolver::WATER_CATEGORY,
					'product_cat'
				)
			)
		);
		update_post_meta(
			$product_id,
			InventoryService::WATER_AVAILABILITY_META_KEY,
			'yes'
		);
		$this->product_ids[] = $product_id;

		return $product_id;
	}

	private function create_unavailable_water_variation( int $parent_id ): int {
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent_id );
		$variation->set_regular_price( '25.00' );
		$variation->set_price( '25.00' );
		$variation->set_status( 'publish' );
		$variation->set_stock_status( 'outofstock' );
		$variation->save();

		$variation_id = $variation->get_id();

		self::assertGreaterThan( 0, $variation_id );
		$this->product_ids[] = $variation_id;

		return $variation_id;
	}
}
