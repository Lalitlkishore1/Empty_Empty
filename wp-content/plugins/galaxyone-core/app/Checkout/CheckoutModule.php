<?php
/**
 * Checkout module.
 *
 * @package GalaxyOne\Core\Checkout
 */

declare(strict_types=1);

namespace GalaxyOne\Core\Checkout;

use DateInterval;
use DateTimeImmutable;
use GalaxyOne\Core\Cart\CartRecalculationService;
use GalaxyOne\Core\Contracts\ModuleInterface;
use GalaxyOne\Core\Delivery\DeliverySlotService;
use GalaxyOne\Core\Orders\OrderMetaService;
use GalaxyOne\Core\Pricing\WaterDeliveryHandlingService;
use WC_Cart;
use WC_Order;
use WC_Order_Item_Product;
use WP_Error;

/**
 * Integrates GalaxyOne checkout requirements with WooCommerce.
 */
final class CheckoutModule implements ModuleInterface {
	/**
	 * Register checkout hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_payment_gateways' ) );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'configure_checkout_fields' ) );
		add_action( 'woocommerce_after_order_notes', array( $this, 'render_delivery_selection' ) );
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'capture_delivery_selection' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'store_order_item_snapshot' ), 10, 4 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'store_order_metadata' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'confirm_delivery_reservation' ), 10, 3 );
	}

	/**
	 * Registers GalaxyOne payment gateways with WooCommerce.
	 *
	 * @param array<int, class-string> $gateways WooCommerce payment gateway classes.
	 * @return array<int, class-string>
	 */
	public function register_payment_gateways( array $gateways ): array {
		if ( ! in_array( UpiOnDeliveryGateway::class, $gateways, true ) ) {
			$gateways[] = UpiOnDeliveryGateway::class;
		}

		return $gateways;
	}

	/**
	 * Configure the core checkout fields used by GalaxyOne.
	 *
	 * @param array<string, mixed> $fields Checkout fields.
	 * @return array<string, mixed>
	 */
	public function configure_checkout_fields( array $fields ): array {
		if ( isset( $fields['billing']['billing_postcode'] ) ) {
			$fields['billing']['billing_postcode']['required'] = true;
		}

		return $fields;
	}

	/**
	 * Render the delivery selection fields.
	 *
	 * @param object $checkout WooCommerce checkout object.
	 * @return void
	 */
	public function render_delivery_selection( object $checkout ): void {
		$selection            = CartRecalculationService::get_delivery_selection();
		$options              = $this->get_delivery_options();
		$cart                 = WC()->cart;
		$has_water            = $cart instanceof WC_Cart && WaterDeliveryHandlingService::cart_contains_water( $cart );
		$water_access_options = WaterDeliveryHandlingService::get_access_options();

		$template = GALAXYONE_CORE_PATH . 'templates/checkout/delivery-selection.php';

		if ( ! file_exists( $template ) ) {
			return;
		}

		require $template;
	}

	/**
	 * Capture delivery selection when WooCommerce updates checkout totals.
	 *
	 * @param string $posted_data Serialized checkout data.
	 * @return void
	 */
	public function capture_delivery_selection( string $posted_data ): void {
		parse_str( $posted_data, $fields );

		if ( ! is_array( $fields ) ) {
			return;
		}

		$postcode  = isset( $fields['billing_postcode'] ) && is_scalar( $fields['billing_postcode'] )
			? sanitize_text_field( (string) $fields['billing_postcode'] )
			: '';
		$address_1 = isset( $fields['billing_address_1'] ) && is_scalar( $fields['billing_address_1'] )
			? sanitize_text_field( (string) $fields['billing_address_1'] )
			: '';
		$city      = isset( $fields['billing_city'] ) && is_scalar( $fields['billing_city'] )
			? sanitize_text_field( (string) $fields['billing_city'] )
			: '';
		$date      = isset( $fields['galaxyone_delivery_date'] ) && is_scalar( $fields['galaxyone_delivery_date'] )
			? (string) $fields['galaxyone_delivery_date']
			: '';
		$slot_key  = isset( $fields['galaxyone_delivery_slot'] ) && is_scalar( $fields['galaxyone_delivery_slot'] )
			? (string) $fields['galaxyone_delivery_slot']
			: '';
		$water_access = isset( $fields['galaxyone_water_delivery_access'] ) && is_scalar( $fields['galaxyone_water_delivery_access'] )
			? (string) $fields['galaxyone_water_delivery_access']
			: '';
		$water_floor  = isset( $fields['galaxyone_water_delivery_floor'] ) && is_scalar( $fields['galaxyone_water_delivery_floor'] )
			? (string) $fields['galaxyone_water_delivery_floor']
			: '';

		CartRecalculationService::update_delivery_selection(
			$postcode,
			$date,
			$slot_key,
			$address_1,
			$city,
			$water_access,
			$water_floor
		);
	}

	/**
	 * Validate the final checkout submission.
	 *
	 * @param array<string, mixed> $data   Checkout data.
	 * @param WP_Error             $errors Validation errors.
	 * @return void
	 */
	public function validate_checkout( array $data, WP_Error $errors ): void {
		$postcode  = isset( $data['billing_postcode'] ) && is_scalar( $data['billing_postcode'] )
			? sanitize_text_field( (string) $data['billing_postcode'] )
			: '';
		$address_1 = isset( $data['billing_address_1'] ) && is_scalar( $data['billing_address_1'] )
			? sanitize_text_field( (string) $data['billing_address_1'] )
			: '';
		$city      = isset( $data['billing_city'] ) && is_scalar( $data['billing_city'] )
			? sanitize_text_field( (string) $data['billing_city'] )
			: '';

		// Delivery fields are rendered by the GalaxyOne template, not registered
		// WooCommerce checkout fields, so read and sanitize them directly.
		$date = isset( $_POST['galaxyone_delivery_date'] ) && is_scalar( $_POST['galaxyone_delivery_date'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce validates the checkout request.
			? sanitize_text_field( wp_unslash( (string) $_POST['galaxyone_delivery_date'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce validates the checkout request.
			: '';
		$slot_key = isset( $_POST['galaxyone_delivery_slot'] ) && is_scalar( $_POST['galaxyone_delivery_slot'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce validates the checkout request.
			? sanitize_key( wp_unslash( (string) $_POST['galaxyone_delivery_slot'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce validates the checkout request.
			: '';
		$water_access = isset( $_POST['galaxyone_water_delivery_access'] ) && is_scalar( $_POST['galaxyone_water_delivery_access'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce validates the checkout request.
			? sanitize_key( wp_unslash( (string) $_POST['galaxyone_water_delivery_access'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce validates the checkout request.
			: '';
		$water_floor  = isset( $_POST['galaxyone_water_delivery_floor'] ) && is_scalar( $_POST['galaxyone_water_delivery_floor'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce validates the checkout request.
			? sanitize_text_field( wp_unslash( (string) $_POST['galaxyone_water_delivery_floor'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce validates the checkout request.
			: '';

		CartRecalculationService::update_delivery_selection(
			$postcode,
			$date,
			$slot_key,
			$address_1,
			$city,
			$water_access,
			$water_floor
		);

		$validation_errors = CheckoutValidationService::validate( $data );

		foreach ( $validation_errors as $validation_error ) {
			if ( ! $validation_error instanceof WP_Error ) {
				continue;
			}

			foreach ( $validation_error->get_error_codes() as $error_code ) {
				foreach ( $validation_error->get_error_messages( $error_code ) as $error_message ) {
					$errors->add( $error_code, $error_message );
				}
			}
		}

		/*
		 * The final submitted delivery selection can differ from the most recent
		 * checkout AJAX refresh. Recalculate the normal WooCommerce totals before
		 * order creation so its fee callback resolves and stores the same current
		 * Water handling result that order metadata will snapshot.
		 */
		$cart = WC()->cart;

		if ( ! $errors->has_errors() && $cart instanceof WC_Cart ) {
			$cart->calculate_totals();
		}
	}

	/**
	 * Store the authoritative price snapshot on each order item.
	 *
	 * @param WC_Order_Item_Product $item          Order item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array<string, mixed>  $values        Cart item values.
	 * @param WC_Order              $order         Order.
	 * @return void
	 */
	public function store_order_item_snapshot(
		WC_Order_Item_Product $item,
		string $cart_item_key,
		array $values,
		WC_Order $order
	): void {
		OrderMetaService::store_line_item_snapshot( $item, $values );
	}

	/**
	 * Store delivery and pricing metadata on the order.
	 *
	 * @param WC_Order             $order Order.
	 * @param array<string, mixed> $data  Checkout data.
	 * @return void
	 */
	public function store_order_metadata( WC_Order $order, array $data ): void {
		OrderMetaService::store_checkout_metadata( $order, $data );
	}

	/**
	 * Confirm the delivery reservation once WooCommerce creates the order.
	 *
	 * @param int                  $order_id Order ID.
	 * @param array<string, mixed> $data     Checkout data.
	 * @param WC_Order             $order    Order.
	 * @return void
	 */
	public function confirm_delivery_reservation( int $order_id, array $data, WC_Order $order ): void {
		OrderMetaService::confirm_delivery_reservation( $order );
	}

	/**
	 * Get delivery-date and slot options for checkout rendering.
	 *
	 * @return array<string, mixed>
	 */
	private function get_delivery_options(): array {
		$options = array(
			'dates' => array(),
			'slots' => array(),
		);

		try {
			$today = new DateTimeImmutable( 'today', wp_timezone() );

			for ( $day_offset = 0; $day_offset < 14; ++$day_offset ) {
				$date = $today->add( new DateInterval( 'P' . $day_offset . 'D' ) );

				$delivery_date = $date->format( 'Y-m-d' );

				if (
					DeliverySlotService::is_valid_date( $delivery_date ) &&
					! DeliverySlotService::is_closed_date( $delivery_date )
				) {
					$options['dates'][] = $delivery_date;
				}
			}

			$options['slots'] = DeliverySlotService::get_slots();
		} catch ( \Exception $exception ) {
			$options = array(
				'dates' => array(),
				'slots' => array(),
			);
		}

		return $options;
	}
}
