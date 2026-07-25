<?php
/**
 * Cart recalculation service.
 *
 * @package GalaxyOne\Core\Cart
 */

namespace GalaxyOne\Core\Cart;

use GalaxyOne\Core\Delivery\DeliveryReservationService;
use GalaxyOne\Core\Delivery\DeliveryValidationService;
use GalaxyOne\Core\Offers\FreeDeliveryOfferService;
use GalaxyOne\Core\Pricing\PriceSnapshotService;
use WC_Cart;
use WP_Error;

final class CartRecalculationService {

	/**
	 * Customer-session key for delivery selection data.
	 *
	 * @var string
	 */
	private const DELIVERY_SELECTION_SESSION_KEY = 'galaxyone_delivery_selection';

	/**
	 * Customer-session key for delivery reservation data.
	 *
	 * @var string
	 */
	private const DELIVERY_RESERVATION_SESSION_KEY = 'galaxyone_delivery_reservation';

	/**
	 * Customer-session key for recalculated delivery-fee data.
	 *
	 * @var string
	 */
	private const DELIVERY_FEE_SESSION_KEY = 'galaxyone_delivery_fee';

	/**
	 * Recalculates every cart item's price using the authoritative resolver.
	 *
	 * @param WC_Cart $cart WooCommerce cart.
	 * @return void
	 */
	public static function recalculate_prices( WC_Cart $cart ): void {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! isset( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
				continue;
			}

			$product_id = CartValidationService::get_cart_item_product_id( $cart_item );
			$snapshot   = PriceSnapshotService::create( $product_id );

			if ( ! is_array( $snapshot ) ) {
				continue;
			}

			$previous_snapshot = isset( $cart_item['galaxyone_price_snapshot'] ) && is_array( $cart_item['galaxyone_price_snapshot'] )
				? $cart_item['galaxyone_price_snapshot']
				: null;

			if (
				is_array( $previous_snapshot ) &&
				(
					(string) $previous_snapshot['price'] !== (string) $snapshot['price'] ||
					(string) $previous_snapshot['source'] !== (string) $snapshot['source'] ||
					(string) $previous_snapshot['campaign_key'] !== (string) $snapshot['campaign_key']
				)
			) {
				$cart->cart_contents[ $cart_item_key ]['galaxyone_price_change_detected'] = true;
			}

			$cart_item['data']->set_price( (string) $snapshot['price'] );
			$cart->cart_contents[ $cart_item_key ]['galaxyone_price_snapshot'] = $snapshot;
		}
	}

	/**
	 * Applies the current validated delivery fee to the cart.
	 *
	 * @param WC_Cart $cart WooCommerce cart.
	 * @return void
	 */
	public static function apply_delivery_fee( WC_Cart $cart ): void {
		$selection = self::get_delivery_selection();

		if ( ! self::has_complete_delivery_selection( $selection ) ) {
			self::set_session_value( self::DELIVERY_FEE_SESSION_KEY, array() );

			return;
		}

		$validation = DeliveryValidationService::validate(
			array(
				'postcode' => (string) $selection['postcode'],
			),
			(string) $selection['delivery_date'],
			(string) $selection['slot_key']
		);

		if ( $validation instanceof WP_Error ) {
			self::set_session_value( self::DELIVERY_FEE_SESSION_KEY, array() );

			return;
		}

		$product_ids       = self::get_cart_product_ids( $cart );
		$free_delivery     = FreeDeliveryOfferService::get_eligible_campaign( $product_ids );
		$normal_fee        = (string) $validation['delivery_fee'];
		$applied_fee       = is_array( $free_delivery ) ? '0' : $normal_fee;
		$campaign_key      = is_array( $free_delivery ) ? (string) $free_delivery['campaign_key'] : '';
		$delivery_fee_data = array(
			'normal_fee'  => $normal_fee,
			'applied_fee' => $applied_fee,
			'campaign_key' => $campaign_key,
		);

		self::set_session_value( self::DELIVERY_FEE_SESSION_KEY, $delivery_fee_data );

		if ( (float) $applied_fee > 0 ) {
			$cart->add_fee(
				__( 'GalaxyOne delivery', 'galaxyone-core' ),
				(float) $applied_fee,
				false
			);
		}
	}

	/**
	 * Stores a sanitized delivery selection in the WooCommerce customer session.
	 *
	 * A selection change releases a previous provisional reservation, ensuring
	 * abandoned or changed selections do not hold capacity unnecessarily.
	 *
	 * @param string $postcode      Selected postcode.
	 * @param string $delivery_date Selected date.
	 * @param string $slot_key      Selected slot key.
	 * @return void
	 */
	public static function update_delivery_selection(
		string $postcode,
		string $delivery_date,
		string $slot_key
	): void {
		$selection = array(
			'postcode'      => strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( $postcode ) ) ),
			'delivery_date' => sanitize_text_field( $delivery_date ),
			'slot_key'      => sanitize_title( $slot_key ),
		);
		$current   = self::get_delivery_selection();

		if ( $selection !== $current ) {
			self::release_cart_reservation();
		}

		self::set_session_value( self::DELIVERY_SELECTION_SESSION_KEY, $selection );
	}

	/**
	 * Returns the current delivery selection.
	 *
	 * @return array<string, string>
	 */
	public static function get_delivery_selection(): array {
		$selection = self::get_session_value( self::DELIVERY_SELECTION_SESSION_KEY );

		if ( ! is_array( $selection ) ) {
			return array(
				'postcode'      => '',
				'delivery_date' => '',
				'slot_key'      => '',
			);
		}

		return array(
			'postcode'      => isset( $selection['postcode'] ) && is_scalar( $selection['postcode'] )
				? strtoupper( preg_replace( '/\s+/', '', (string) $selection['postcode'] ) )
				: '',
			'delivery_date' => isset( $selection['delivery_date'] ) && is_scalar( $selection['delivery_date'] )
				? (string) $selection['delivery_date']
				: '',
			'slot_key'      => isset( $selection['slot_key'] ) && is_scalar( $selection['slot_key'] )
				? sanitize_title( (string) $selection['slot_key'] )
				: '',
		);
	}

	/**
	 * Returns the currently calculated delivery-fee data.
	 *
	 * @return array<string, string>
	 */
	public static function get_delivery_fee_snapshot(): array {
		$fee = self::get_session_value( self::DELIVERY_FEE_SESSION_KEY );

		if ( ! is_array( $fee ) ) {
			return array(
				'normal_fee'   => '',
				'applied_fee'  => '',
				'campaign_key' => '',
			);
		}

		return array(
			'normal_fee'   => isset( $fee['normal_fee'] ) && is_scalar( $fee['normal_fee'] )
				? (string) $fee['normal_fee']
				: '',
			'applied_fee'  => isset( $fee['applied_fee'] ) && is_scalar( $fee['applied_fee'] )
				? (string) $fee['applied_fee']
				: '',
			'campaign_key' => isset( $fee['campaign_key'] ) && is_scalar( $fee['campaign_key'] )
				? sanitize_title( (string) $fee['campaign_key'] )
				: '',
		);
	}

	/**
	 * Reserves capacity for the current valid delivery selection.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function reserve_delivery_selection(): array|WP_Error {
		$selection = self::get_delivery_selection();

		if ( ! self::has_complete_delivery_selection( $selection ) ) {
			return new WP_Error(
				'galaxyone_delivery_selection_required',
				__( 'Choose a delivery date and time slot before placing your order.', 'galaxyone-core' )
			);
		}

		self::release_cart_reservation();

		$reservation = DeliveryValidationService::reserve(
			array(
				'postcode' => (string) $selection['postcode'],
			),
			(string) $selection['delivery_date'],
			(string) $selection['slot_key']
		);

		if ( $reservation instanceof WP_Error ) {
			return $reservation;
		}

		if ( ! isset( $reservation['reservation'] ) || ! is_array( $reservation['reservation'] ) ) {
			return new WP_Error(
				'galaxyone_delivery_reservation_failed',
				__( 'The selected delivery slot could not be reserved. Please choose another slot.', 'galaxyone-core' )
			);
		}

		self::set_session_value(
			self::DELIVERY_RESERVATION_SESSION_KEY,
			$reservation['reservation']
		);

		return $reservation;
	}

	/**
	 * Returns the current provisional delivery reservation.
	 *
	 * @return array<string, int|string>
	 */
	public static function get_delivery_reservation(): array {
		$reservation = self::get_session_value( self::DELIVERY_RESERVATION_SESSION_KEY );

		if ( ! is_array( $reservation ) ) {
			return array();
		}

		return $reservation;
	}

	/**
	 * Releases the current provisional delivery reservation.
	 *
	 * @return void
	 */
	public static function release_cart_reservation(): void {
		$reservation = self::get_delivery_reservation();

		if ( isset( $reservation['token'] ) && is_scalar( $reservation['token'] ) ) {
			DeliveryReservationService::release( (string) $reservation['token'] );
		}

		self::set_session_value( self::DELIVERY_RESERVATION_SESSION_KEY, array() );
	}

	/**
	 * Clears the cart-held provisional reservation after it is assigned to an order.
	 *
	 * @return void
	 */
	public static function clear_cart_reservation(): void {
		self::set_session_value( self::DELIVERY_RESERVATION_SESSION_KEY, array() );
	}

	/**
	 * Clears changed-price acknowledgements after an explicit cart update.
	 *
	 * @return void
	 */
	public static function acknowledge_price_changes(): void {
		$cart = WC()->cart;

		if ( ! $cart instanceof WC_Cart ) {
			return;
		}

		foreach ( array_keys( $cart->cart_contents ) as $cart_item_key ) {
			unset( $cart->cart_contents[ $cart_item_key ]['galaxyone_price_change_detected'] );
		}
	}

	/**
	 * Determines whether a delivery selection has all required values.
	 *
	 * @param array<string, string> $selection Delivery selection.
	 * @return bool
	 */
	private static function has_complete_delivery_selection( array $selection ): bool {
		return '' !== $selection['postcode'] &&
			'' !== $selection['delivery_date'] &&
			'' !== $selection['slot_key'];
	}

	/**
	 * Returns all cart product or variation IDs.
	 *
	 * @param WC_Cart $cart WooCommerce cart.
	 * @return array<int, int>
	 */
	private static function get_cart_product_ids( WC_Cart $cart ): array {
		$product_ids = array();

		foreach ( $cart->get_cart() as $cart_item ) {
			$product_id = CartValidationService::get_cart_item_product_id( $cart_item );

			if ( $product_id > 0 ) {
				$product_ids[] = $product_id;
			}
		}

		return array_values( array_unique( $product_ids ) );
	}

	/**
	 * Reads a value from the WooCommerce customer session.
	 *
	 * @param string $key Session key.
	 * @return mixed
	 */
	private static function get_session_value( string $key ) {
		return WC()->session ? WC()->session->get( $key ) : null;
	}

	/**
	 * Saves a value to the WooCommerce customer session.
	 *
	 * @param string $key   Session key.
	 * @param mixed  $value Session value.
	 * @return void
	 */
	private static function set_session_value( string $key, $value ): void {
		if ( WC()->session ) {
			WC()->session->set( $key, $value );
		}
	}
}
