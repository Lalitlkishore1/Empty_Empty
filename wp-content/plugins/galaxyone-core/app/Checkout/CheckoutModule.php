<?php
/**
 * Checkout module.
 *
 * @package GalaxyOne\Core\Checkout
 */

namespace GalaxyOne\Core\Checkout;

use DateInterval;
use DateTimeImmutable;
use GalaxyOne\Core\Cart\CartRecalculationService;
use GalaxyOne\Core\Cart\CartValidationService;
use GalaxyOne\Core\Contracts\ModuleInterface;
use GalaxyOne\Core\Delivery\DeliverySlotService;
use GalaxyOne\Core\Orders\OrderMetaService;
use GalaxyOne\Core\Orders\OrderStatusService;
use WC_Order;
use WC_Order_Item_Product;
use WP_Error;

final class CheckoutModule implements ModuleInterface {

	/**
	 * Registers checkout integrations.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter(
			'woocommerce_checkout_fields',
			array( $this, 'configure_checkout_fields' )
		);

		add_action(
			'woocommerce_after_order_notes',
			array( $this, 'render_delivery_selection' )
		);

		add_action(
			'woocommerce_checkout_update_order_review',
			array( $this, 'capture_delivery_selection' )
		);

		add_action(
			'woocommerce_after_checkout_validation',
			array( $this, 'validate_checkout' ),
			20,
			2
		);

		add_action(
			'woocommerce_checkout_create_order_line_item',
			array( $this, 'store_order_item_snapshot' ),
			20,
			4
		);

		add_action(
			'woocommerce_checkout_create_order',
			array( $this, 'store_order_metadata' ),
			20,
			2
		);

		add_action(
			'woocommerce_checkout_order_created',
			array( $this, 'confirm_delivery_reservation' )
		);

		add_filter(
			'woocommerce_payment_gateways',
			array( $this, 'register_payment_gateways' )
		);

		OrderStatusService::register_hooks();
	}

	/**
	 * Requires the mobile number and adds the optional delivery landmark field.
	 *
	 * @param array<string, array<string, array<string, mixed>>> $fields Checkout fields.
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public function configure_checkout_fields( array $fields ): array {
		if ( isset( $fields['billing']['billing_phone'] ) ) {
			$fields['billing']['billing_phone']['required'] = true;
			$fields['billing']['billing_phone']['label']    = __( 'Mobile number', 'galaxyone-core' );
		}

		$fields['order']['galaxyone_landmark'] = array(
			'type'        => 'text',
			'label'       => __( 'Landmark', 'galaxyone-core' ),
			'required'    => false,
			'priority'    => 25,
			'autocomplete' => 'address-line2',
		);

		return $fields;
	}

	/**
	 * Renders the checkout delivery-selection fields.
	 *
	 * @param WC_Checkout $checkout WooCommerce checkout object.
	 * @return void
	 */
	public function render_delivery_selection( $checkout ): void {
		unset( $checkout );

		$selection        = CartRecalculationService::get_delivery_selection();
		$delivery_options = $this->get_delivery_options();

		require GALAXYONE_CORE_PATH . 'templates/frontend/checkout/delivery-selection.php';
	}

	/**
	 * Captures checkout-update values using WooCommerce's protected checkout request.
	 *
	 * @param string $posted_data Serialized checkout fields.
	 * @return void
	 */
	public function capture_delivery_selection( string $posted_data ): void {
		$fields = array();

		wp_parse_str( $posted_data, $fields );

		$postcode = isset( $fields['billing_postcode'] ) && is_scalar( $fields['billing_postcode'] )
			? (string) $fields['billing_postcode']
			: '';
		$date     = isset( $fields['galaxyone_delivery_date'] ) && is_scalar( $fields['galaxyone_delivery_date'] )
			? (string) $fields['galaxyone_delivery_date']
			: '';
		$slot_key = isset( $fields['galaxyone_delivery_slot'] ) && is_scalar( $fields['galaxyone_delivery_slot'] )
			? (string) $fields['galaxyone_delivery_slot']
			: '';

		CartRecalculationService::update_delivery_selection( $postcode, $date, $slot_key );
	}

	/**
	 * Performs final cart and checkout validation before order creation.
	 *
	 * @param array<string, mixed> $data   Validated WooCommerce checkout values.
	 * @param WP_Error             $errors WooCommerce validation errors.
	 * @return void
	 */
	public function validate_checkout( array $data, WP_Error $errors ): void {
		$postcode = isset( $data['billing_postcode'] ) && is_scalar( $data['billing_postcode'] )
			? (string) $data['billing_postcode']
			: '';
		$date     = isset( $data['galaxyone_delivery_date'] ) && is_scalar( $data['galaxyone_delivery_date'] )
			? (string) $data['galaxyone_delivery_date']
			: '';
		$slot_key = isset( $data['galaxyone_delivery_slot'] ) && is_scalar( $data['galaxyone_delivery_slot'] )
			? (string) $data['galaxyone_delivery_slot']
			: '';

		CartRecalculationService::update_delivery_selection( $postcode, $date, $slot_key );

		foreach ( CartValidationService::validate_cart() as $error ) {
			$errors->add( $error->get_error_code(), $error->get_error_message() );
		}

		foreach ( CheckoutValidationService::validate( $data ) as $error ) {
			$errors->add( $error->get_error_code(), $error->get_error_message() );
		}

		if ( $errors->has_errors() ) {
			return;
		}

		$reservation = CartRecalculationService::reserve_delivery_selection();

		if ( $reservation instanceof WP_Error ) {
			$errors->add(
				$reservation->get_error_code(),
				$reservation->get_error_message()
			);
		}
	}

	/**
	 * Stores the authoritative price snapshot on an order item.
	 *
	 * @param WC_Order_Item_Product $item          Order item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array<string, mixed>  $values        Cart item values.
	 * @param WC_Order              $order         WooCommerce order.
	 * @return void
	 */
	public function store_order_item_snapshot(
		WC_Order_Item_Product $item,
		string $cart_item_key,
		array $values,
		WC_Order $order
	): void {
		unset( $cart_item_key, $order );

		OrderMetaService::store_line_item_snapshot( $item, $values );
	}

	/**
	 * Persists final checkout metadata on the order.
	 *
	 * @param WC_Order              $order WooCommerce order.
	 * @param array<string, mixed>  $data  Validated checkout data.
	 * @return void
	 */
	public function store_order_metadata( WC_Order $order, array $data ): void {
		OrderMetaService::store_checkout_metadata( $order, $data );
	}

	/**
	 * Assigns the provisional capacity reservation to the created order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return void
	 */
	public function confirm_delivery_reservation( WC_Order $order ): void {
		OrderMetaService::confirm_delivery_reservation( $order );
	}

	/**
	 * Registers the UPI-on-delivery payment gateway.
	 *
	 * @param array<int, string> $gateways Payment gateway class names.
	 * @return array<int, string>
	 */
	public function register_payment_gateways( array $gateways ): array {
		$gateways[] = UpiOnDeliveryGateway::class;

		return $gateways;
	}

	/**
	 * Returns selectable upcoming dates and currently available slots.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_delivery_options(): array {
		$options      = array();
		$current_date = new DateTimeImmutable( 'today', wp_timezone() );
		$slots        = DeliverySlotService::get_slots();

		for ( $offset = 0; $offset < 14; ++$offset ) {
			$date            = $current_date->add( new DateInterval( 'P' . $offset . 'D' ) );
			$delivery_date   = $date->format( 'Y-m-d' );
			$available_slots = array();

			foreach ( $slots as $slot ) {
				if (
					isset( $slot->rule_key, $slot->label ) &&
					DeliverySlotService::is_slot_available(
						$delivery_date,
						(string) $slot->rule_key
					)
				) {
					$available_slots[] = array(
						'key'   => (string) $slot->rule_key,
						'label' => (string) $slot->label,
					);
				}
			}

			if ( ! empty( $available_slots ) ) {
				$options[] = array(
					'date'  => $delivery_date,
					'label' => wp_date( get_option( 'date_format' ), $date->getTimestamp() ),
					'slots' => $available_slots,
				);
			}
		}

		return $options;
	}
}
