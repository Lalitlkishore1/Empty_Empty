<?php
/**
 * Delivery module.
 *
 * @package GalaxyOne\Core\Delivery
 */

namespace GalaxyOne\Core\Delivery;

use GalaxyOne\Core\ActivityLog\ActivityLogRepository;
use GalaxyOne\Core\Contracts\ModuleInterface;
use GalaxyOne\Core\Security\Capabilities;
use GalaxyOne\Core\Security\NonceVerifier;

final class DeliveryModule implements ModuleInterface {

	/**
	 * Delivery page slug.
	 *
	 * @var string
	 */
	private const PAGE_SLUG = 'galaxyone-delivery';

	/**
	 * Administrative form action.
	 *
	 * @var string
	 */
	private const ADMIN_ACTION = 'galaxyone_save_delivery_configuration';

	/**
	 * Administrative nonce action.
	 *
	 * @var string
	 */
	private const ADMIN_NONCE_ACTION = 'galaxyone_save_delivery_configuration';

	/**
	 * Administrative nonce field.
	 *
	 * @var string
	 */
	private const ADMIN_NONCE_FIELD = 'galaxyone_delivery_nonce';

	/**
	 * Registers the module with WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action(
			'admin_menu',
			array( $this, 'register_menu' ),
			20
		);

		add_action(
			'admin_post_' . self::ADMIN_ACTION,
			array( $this, 'save_configuration' )
		);

		add_shortcode(
			'galaxyone_delivery_check',
			array( $this, 'render_serviceability_check' )
		);
	}

	/**
	 * Registers the delivery settings page.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'galaxyone-core',
			__( 'Delivery', 'galaxyone-core' ),
			__( 'Delivery', 'galaxyone-core' ),
			Capabilities::get_manage_capability(),
			self::PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Renders delivery administration configuration.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		if ( ! Capabilities::can_manage_galaxyone() ) {
			wp_die(
				esc_html__( 'You do not have permission to access delivery settings.', 'galaxyone-core' ),
				esc_html__( 'Delivery', 'galaxyone-core' ),
				array(
					'response' => 403,
				)
			);
		}

		$notice        = isset( $_GET['galaxyone_notice'] ) && is_string( $_GET['galaxyone_notice'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_key( wp_unslash( $_GET['galaxyone_notice'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';
		$service_areas = ServiceAreaService::get_service_areas();
		$slots         = DeliverySlotService::get_slots();

		require GALAXYONE_CORE_PATH . 'templates/admin/delivery/settings-page.php';
	}

	/**
	 * Saves an authorized delivery configuration request.
	 *
	 * @return void
	 */
	public function save_configuration(): void {
		if ( ! Capabilities::can_manage_galaxyone() ) {
			wp_die(
				esc_html__( 'You do not have permission to update delivery settings.', 'galaxyone-core' ),
				esc_html__( 'Delivery', 'galaxyone-core' ),
				array(
					'response' => 403,
				)
			);
		}

		if ( ! NonceVerifier::verify_request_nonce( self::ADMIN_NONCE_ACTION, self::ADMIN_NONCE_FIELD ) ) {
			wp_die(
				esc_html__( 'The security check failed. No delivery configuration was changed.', 'galaxyone-core' ),
				esc_html__( 'Delivery', 'galaxyone-core' ),
				array(
					'response' => 403,
				)
			);
		}

		$configuration_action = isset( $_POST['delivery_configuration_action'] ) && is_string( $_POST['delivery_configuration_action'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? sanitize_key( wp_unslash( $_POST['delivery_configuration_action'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			: '';

		if ( 'service_area' === $configuration_action ) {
			$postcode = isset( $_POST['postcode'] ) && is_string( $_POST['postcode'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				? wp_unslash( $_POST['postcode'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				: '';
			$label    = isset( $_POST['label'] ) && is_string( $_POST['label'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				? wp_unslash( $_POST['label'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				: '';
			$fee      = isset( $_POST['fee'] ) && is_string( $_POST['fee'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				? wp_unslash( $_POST['fee'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				: '';

			$old_value = ServiceAreaService::get_service_area( $postcode );
			$saved     = ServiceAreaService::save_service_area( $postcode, $label, $fee );

			if ( $saved ) {
				ActivityLogRepository::record(
					'delivery_service_area_saved',
					is_array( $old_value ) ? $old_value : array(),
					array(
						'postcode' => ServiceAreaService::normalize_postcode( $postcode ),
						'label'    => sanitize_text_field( $label ),
						'fee'      => wc_format_decimal( $fee, 4 ),
					),
					array(
						'source' => 'delivery_admin',
					)
				);
			}

			$this->redirect( $saved ? 'saved' : 'invalid' );
		}

		if ( 'slot' === $configuration_action ) {
			$slot_key    = isset( $_POST['slot_key'] ) && is_string( $_POST['slot_key'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				? wp_unslash( $_POST['slot_key'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				: '';
			$label       = isset( $_POST['label'] ) && is_string( $_POST['label'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				? wp_unslash( $_POST['label'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				: '';
			$weekday     = isset( $_POST['weekday'] ) ? absint( $_POST['weekday'] ) : 7; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$weekday     = 7 === $weekday ? -1 : $weekday;
			$start_time  = isset( $_POST['start_time'] ) && is_string( $_POST['start_time'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				? wp_unslash( $_POST['start_time'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				: '';
			$end_time    = isset( $_POST['end_time'] ) && is_string( $_POST['end_time'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				? wp_unslash( $_POST['end_time'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				: '';
			$cutoff_time = isset( $_POST['cutoff_time'] ) && is_string( $_POST['cutoff_time'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				? wp_unslash( $_POST['cutoff_time'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				: '';

			$old_value = DeliverySlotService::get_slot( $slot_key );
			$saved     = DeliverySlotService::save_slot(
				$slot_key,
				$label,
				$weekday,
				$start_time,
				$end_time,
				$cutoff_time
			);

			if ( $saved ) {
				ActivityLogRepository::record(
					'delivery_slot_saved',
					is_array( $old_value ) ? $old_value : array(),
					array(
						'slot_key'    => sanitize_title( $slot_key ),
						'label'       => sanitize_text_field( $label ),
						'weekday'     => $weekday,
						'start_time'  => $start_time,
						'end_time'    => $end_time,
						'cutoff_time' => $cutoff_time,
					),
					array(
						'source' => 'delivery_admin',
					)
				);
			}

			$this->redirect( $saved ? 'saved' : 'invalid' );
		}

		if ( 'capacity' === $configuration_action ) {
			$delivery_date = isset( $_POST['delivery_date'] ) && is_string( $_POST['delivery_date'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				? wp_unslash( $_POST['delivery_date'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				: '';
			$slot_key      = isset( $_POST['slot_key'] ) && is_string( $_POST['slot_key'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				? wp_unslash( $_POST['slot_key'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				: '';
			$capacity      = isset( $_POST['capacity'] ) ? absint( $_POST['capacity'] ) : -1; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$old_value     = DeliveryCapacityService::get_capacity( $delivery_date, $slot_key );
			$saved         = DeliveryCapacityService::save_capacity( $delivery_date, $slot_key, $capacity );

			if ( $saved ) {
				ActivityLogRepository::record(
					'delivery_capacity_saved',
					is_array( $old_value ) ? $old_value : array(),
					array(
						'delivery_date' => $delivery_date,
						'slot_key'      => sanitize_title( $slot_key ),
						'capacity'      => $capacity,
					),
					array(
						'source' => 'delivery_admin',
					)
				);
			}

			$this->redirect( $saved ? 'saved' : 'invalid' );
		}

		if ( 'closed_date' === $configuration_action ) {
			$delivery_date = isset( $_POST['delivery_date'] ) && is_string( $_POST['delivery_date'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				? wp_unslash( $_POST['delivery_date'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				: '';
			$saved         = DeliverySlotService::close_date( $delivery_date );

			if ( $saved ) {
				ActivityLogRepository::record(
					'delivery_closed_date_saved',
					array(),
					array(
						'delivery_date' => $delivery_date,
					),
					array(
						'source' => 'delivery_admin',
					)
				);
			}

			$this->redirect( $saved ? 'saved' : 'invalid' );
		}

		$this->redirect( 'invalid' );
	}

	/**
	 * Renders a protected public serviceability-check form.
	 *
	 * @return string
	 */
	public function render_serviceability_check(): string {
		$postcode    = '';
		$result_type = '';
		$result_text = '';

		if (
			isset( $_POST['galaxyone_delivery_check_form'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'1' === $_POST['galaxyone_delivery_check_form'] && // phpcs:ignore WordPress.Security.NonceVerification.Missing
			NonceVerifier::verify_request_nonce(
				'galaxyone_delivery_check',
				'galaxyone_delivery_check_nonce'
			)
		) {
			$postcode = isset( $_POST['galaxyone_delivery_postcode'] ) && is_string( $_POST['galaxyone_delivery_postcode'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				? ServiceAreaService::normalize_postcode( wp_unslash( $_POST['galaxyone_delivery_postcode'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				: '';
			$area     = ServiceAreaService::get_service_area( $postcode );

			if ( is_array( $area ) ) {
				$result_type = 'success';
				$result_text = sprintf(
					/* translators: 1: service area label, 2: delivery fee. */
					__( 'Delivery is available in %1$s. Standard delivery fee: %2$s.', 'galaxyone-core' ),
					$area['label'],
					wc_price( $area['fee'] )
				);
			} else {
				$result_type = 'error';
				$result_text = __( 'Delivery is not available for this postcode.', 'galaxyone-core' );
			}
		}

		ob_start();

		require GALAXYONE_CORE_PATH . 'templates/frontend/delivery/serviceability-check.php';

		return (string) ob_get_clean();
	}

	/**
	 * Redirects to the delivery configuration page.
	 *
	 * @param string $notice Notice identifier.
	 * @return void
	 */
	private function redirect( string $notice ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => self::PAGE_SLUG,
					'galaxyone_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
