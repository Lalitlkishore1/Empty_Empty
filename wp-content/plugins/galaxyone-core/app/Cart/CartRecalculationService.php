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
	 * Customer-session key for stable delivery reservation operation data.
	 *
	 * @var string
	 */
	private const DELIVERY_OPERATION_SESSION_KEY = 'galaxyone_delivery_operation';

	/**
	 * Customer-session key indicating that a prior reservation could not be released.
	 *
	 * @var string
	 */
	private const DELIVERY_RELEASE_FAILED_SESSION_KEY = 'galaxyone_delivery_release_failed';

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
				'address_1' => (string) $selection['address_1'],
				'city'      => (string) $selection['city'],
				'postcode'  => (string) $selection['postcode'],
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
	 * A selection change releases a previous provisional reservation before
	 * allowing a new logical reservation attempt to be created.
	 *
	 * @param string $postcode      Selected postcode.
	 * @param string $delivery_date Selected date.
	 * @param string $slot_key      Selected slot key.
	 * @param string $address_1     Selected delivery address line.
	 * @param string $city          Selected delivery locality.
	 * @return void
	 */
	public static function update_delivery_selection(
		string $postcode,
		string $delivery_date,
		string $slot_key,
		string $address_1 = '',
		string $city = ''
	): void {
		$selection = array(
			'postcode'      => strtoupper( preg_replace( '/\s+/', '', sanitize_text_field( $postcode ) ) ),
			'delivery_date' => sanitize_text_field( $delivery_date ),
			'slot_key'      => sanitize_title( $slot_key ),
			'address_1'     => sanitize_text_field( $address_1 ),
			'city'          => sanitize_text_field( $city ),
		);
		$current   = self::get_delivery_selection();

		if ( $selection !== $current ) {
			if ( ! self::release_cart_reservation() ) {
				self::set_session_value( self::DELIVERY_RELEASE_FAILED_SESSION_KEY, true );

				return;
			}

			self::clear_delivery_operation();
		}

		self::set_session_value( self::DELIVERY_RELEASE_FAILED_SESSION_KEY, false );
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
				'address_1'     => '',
				'city'          => '',
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
			'address_1'     => isset( $selection['address_1'] ) && is_scalar( $selection['address_1'] )
				? sanitize_text_field( (string) $selection['address_1'] )
				: '',
			'city'          => isset( $selection['city'] ) && is_scalar( $selection['city'] )
				? sanitize_text_field( (string) $selection['city'] )
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
		$quantity  = 1;

		if ( ! self::has_complete_delivery_selection( $selection ) ) {
			return new WP_Error(
				'galaxyone_delivery_selection_required',
				__( 'Choose a delivery date and time slot before placing your order.', 'galaxyone-core' )
			);
		}

		if ( true === self::get_session_value( self::DELIVERY_RELEASE_FAILED_SESSION_KEY ) ) {
			return new WP_Error(
				'galaxyone_delivery_reservation_release_failed',
				__( 'Your previous delivery reservation could not be released. Please try again.', 'galaxyone-core' )
			);
		}

		$operation_key = self::get_or_create_delivery_operation_key(
			(string) $selection['delivery_date'],
			(string) $selection['slot_key'],
			$quantity
		);

		if ( '' === $operation_key ) {
			return new WP_Error(
				'galaxyone_delivery_reservation_unavailable',
				__( 'The selected delivery slot could not be reserved. Please try again.', 'galaxyone-core' )
			);
		}

		$reservation = DeliveryValidationService::reserve(
			array(
				'address_1' => (string) $selection['address_1'],
				'city'      => (string) $selection['city'],
				'postcode'  => (string) $selection['postcode'],
			),
			(string) $selection['delivery_date'],
			(string) $selection['slot_key'],
			$quantity,
			0,
			$operation_key
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
	 * @return bool
	 */
	public static function release_cart_reservation(): bool {
		$reservation = self::get_delivery_reservation();

		if ( ! isset( $reservation['token'] ) || ! is_scalar( $reservation['token'] ) ) {
			self::set_session_value( self::DELIVERY_RESERVATION_SESSION_KEY, array() );
			self::clear_delivery_operation();

			return true;
		}

		if ( ! DeliveryReservationService::release( (string) $reservation['token'] ) ) {
			return false;
		}

		self::set_session_value( self::DELIVERY_RESERVATION_SESSION_KEY, array() );
		self::clear_delivery_operation();

		return true;
	}

	/**
	 * Clears the cart-held provisional reservation after it is assigned to an order.
	 *
	 * @return void
	 */
	public static function clear_cart_reservation(): void {
		self::set_session_value( self::DELIVERY_RESERVATION_SESSION_KEY, array() );
		self::clear_delivery_operation();
		self::set_session_value( self::DELIVERY_RELEASE_FAILED_SESSION_KEY, false );
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
	 * Returns a stable server-generated operation key for one delivery attempt.
	 *
	 * @param string $delivery_date Normalized delivery date.
	 * @param string $slot_key      Normalized delivery slot key.
	 * @param int    $quantity      Reserved capacity.
	 * @return string
	 */
	private static function get_or_create_delivery_operation_key(
		string $delivery_date,
		string $slot_key,
		int $quantity
	): string {
		$delivery_date = sanitize_text_field( $delivery_date );
		$slot_key      = sanitize_title( $slot_key );

		if (
			! DeliveryValidationService::is_valid_delivery_operation(
				$delivery_date,
				$slot_key,
				$quantity
			)
		) {
			return '';
		}

		$reservation = self::get_delivery_reservation();

		if ( self::reservation_has_expired( $reservation ) ) {
			self::set_session_value( self::DELIVERY_RESERVATION_SESSION_KEY, array() );
			self::clear_delivery_operation();
			$reservation = array();
		}

		$operation = self::get_delivery_operation();

		if ( self::reservation_has_token( $reservation ) ) {
			if (
				! self::reservation_matches(
					$reservation,
					$delivery_date,
					$slot_key,
					$quantity
				) ||
				! self::operation_matches(
					$operation,
					$delivery_date,
					$slot_key,
					$quantity
				)
			) {
				return '';
			}

			return (string) $operation['key'];
		}

		if ( self::operation_matches( $operation, $delivery_date, $slot_key, $quantity ) ) {
			return (string) $operation['key'];
		}

		$key = wp_generate_uuid4();

		if ( ! wp_is_uuid( $key ) ) {
			return '';
		}

		self::set_session_value(
			self::DELIVERY_OPERATION_SESSION_KEY,
			array(
				'key'           => $key,
				'delivery_date' => $delivery_date,
				'slot_key'      => $slot_key,
				'quantity'      => $quantity,
			)
		);

		return $key;
	}

	/**
	 * Returns the current delivery operation data.
	 *
	 * @return array<string, int|string>
	 */
	private static function get_delivery_operation(): array {
		$operation = self::get_session_value( self::DELIVERY_OPERATION_SESSION_KEY );

		if ( ! is_array( $operation ) ) {
			return array();
		}

		return array(
			'key'           => isset( $operation['key'] ) && is_scalar( $operation['key'] )
				? trim( (string) $operation['key'] )
				: '',
			'delivery_date' => isset( $operation['delivery_date'] ) && is_scalar( $operation['delivery_date'] )
				? sanitize_text_field( (string) $operation['delivery_date'] )
				: '',
			'slot_key'      => isset( $operation['slot_key'] ) && is_scalar( $operation['slot_key'] )
				? sanitize_title( (string) $operation['slot_key'] )
				: '',
			'quantity'      => isset( $operation['quantity'] ) ? absint( $operation['quantity'] ) : 0,
		);
	}

	/**
	 * Determines whether the stored operation is for this exact attempt.
	 *
	 * @param array<string, int|string> $operation     Stored operation data.
	 * @param string                    $delivery_date Normalized date.
	 * @param string                    $slot_key      Normalized slot.
	 * @param int                       $quantity      Reserved quantity.
	 * @return bool
	 */
	private static function operation_matches(
		array $operation,
		string $delivery_date,
		string $slot_key,
		int $quantity
	): bool {
		return isset(
			$operation['key'],
			$operation['delivery_date'],
			$operation['slot_key'],
			$operation['quantity']
		) &&
			wp_is_uuid( (string) $operation['key'] ) &&
			$delivery_date === (string) $operation['delivery_date'] &&
			$slot_key === (string) $operation['slot_key'] &&
			$quantity === (int) $operation['quantity'];
	}

	/**
	 * Determines whether a session reservation matches the requested attempt.
	 *
	 * @param array<string, int|string> $reservation   Session reservation.
	 * @param string                    $delivery_date Normalized date.
	 * @param string                    $slot_key      Normalized slot.
	 * @param int                       $quantity      Reserved quantity.
	 * @return bool
	 */
	private static function reservation_matches(
		array $reservation,
		string $delivery_date,
		string $slot_key,
		int $quantity
	): bool {
		return isset(
			$reservation['delivery_date'],
			$reservation['slot_key'],
			$reservation['quantity']
		) &&
			$delivery_date === sanitize_text_field( (string) $reservation['delivery_date'] ) &&
			$slot_key === sanitize_title( (string) $reservation['slot_key'] ) &&
			$quantity === (int) $reservation['quantity'];
	}

	/**
	 * Determines whether a session reservation contains a valid token.
	 *
	 * @param array<string, int|string> $reservation Session reservation.
	 * @return bool
	 */
	private static function reservation_has_token( array $reservation ): bool {
		return isset( $reservation['token'] ) &&
			is_scalar( $reservation['token'] ) &&
			wp_is_uuid( (string) $reservation['token'] );
	}

	/**
	 * Determines whether a provisional reservation has reached its known expiry.
	 *
	 * @param array<string, int|string> $reservation Session reservation.
	 * @return bool
	 */
	private static function reservation_has_expired( array $reservation ): bool {
		if (
			! self::reservation_has_token( $reservation ) ||
			! isset( $reservation['expires_at'] ) ||
			! is_scalar( $reservation['expires_at'] )
		) {
			return false;
		}

		$expires_at = (string) $reservation['expires_at'];

		return '' !== $expires_at && $expires_at <= gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Clears the stable operation data after a known terminal state.
	 *
	 * @return void
	 */
	private static function clear_delivery_operation(): void {
		self::set_session_value( self::DELIVERY_OPERATION_SESSION_KEY, array() );
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
