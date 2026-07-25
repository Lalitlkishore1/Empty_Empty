<?php
/**
 * Offers module.
 *
 * @package GalaxyOne\Core\Offers
 */

namespace GalaxyOne\Core\Offers;

use GalaxyOne\Core\ActivityLog\ActivityLogRepository;
use GalaxyOne\Core\Contracts\ModuleInterface;
use GalaxyOne\Core\Security\Capabilities;
use GalaxyOne\Core\Security\NonceVerifier;

final class OffersModule implements ModuleInterface {

	/**
	 * Administration page slug.
	 *
	 * @var string
	 */
	private const PAGE_SLUG = 'galaxyone-offers';

	/**
	 * Administrative form action.
	 *
	 * @var string
	 */
	private const ADMIN_ACTION = 'galaxyone_save_offer_campaign';

	/**
	 * Administrative nonce action.
	 *
	 * @var string
	 */
	private const NONCE_ACTION = 'galaxyone_save_offer_campaign';

	/**
	 * Administrative nonce field.
	 *
	 * @var string
	 */
	private const NONCE_FIELD = 'galaxyone_offer_campaign_nonce';

	/**
	 * Registers offers administration.
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
			array( $this, 'handle_campaign_request' )
		);
	}

	/**
	 * Registers the offers submenu page.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'galaxyone-core',
			__( 'Offers', 'galaxyone-core' ),
			__( 'Offers', 'galaxyone-core' ),
			Capabilities::get_manage_capability(),
			self::PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Renders offer-campaign administration.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		if ( ! Capabilities::can_manage_galaxyone() ) {
			wp_die(
				esc_html__( 'You do not have permission to access offer campaigns.', 'galaxyone-core' ),
				esc_html__( 'Offers', 'galaxyone-core' ),
				array(
					'response' => 403,
				)
			);
		}

		$campaign_key = isset( $_GET['campaign_key'] ) && is_string( $_GET['campaign_key'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_title( wp_unslash( $_GET['campaign_key'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';
		$notice       = isset( $_GET['galaxyone_notice'] ) && is_string( $_GET['galaxyone_notice'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_key( wp_unslash( $_GET['galaxyone_notice'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';
		$campaigns    = CampaignService::get_campaigns();
		$editing_campaign = '' === $campaign_key
			? null
			: CampaignService::get_campaign( $campaign_key );

		if ( is_array( $editing_campaign ) ) {
			$editing_campaign['starts_at_input'] = CampaignService::format_datetime_for_input(
				(string) $editing_campaign['starts_at']
			);
			$editing_campaign['ends_at_input'] = CampaignService::format_datetime_for_input(
				(string) $editing_campaign['ends_at']
			);
		}

		$active_free_delivery_campaigns = CampaignService::get_current_free_delivery_campaigns();

		require GALAXYONE_CORE_PATH . 'templates/admin/campaigns/campaigns-page.php';
	}

	/**
	 * Handles secure offer-campaign administration requests.
	 *
	 * @return void
	 */
	public function handle_campaign_request(): void {
		if ( ! Capabilities::can_manage_galaxyone() ) {
			wp_die(
				esc_html__( 'You do not have permission to update offer campaigns.', 'galaxyone-core' ),
				esc_html__( 'Offers', 'galaxyone-core' ),
				array(
					'response' => 403,
				)
			);
		}

		if ( ! NonceVerifier::verify_request_nonce( self::NONCE_ACTION, self::NONCE_FIELD ) ) {
			wp_die(
				esc_html__( 'The security check failed. No offer campaign was changed.', 'galaxyone-core' ),
				esc_html__( 'Offers', 'galaxyone-core' ),
				array(
					'response' => 403,
				)
			);
		}

		$request_action = isset( $_POST['offer_campaign_action'] ) && is_string( $_POST['offer_campaign_action'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? sanitize_key( wp_unslash( $_POST['offer_campaign_action'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			: '';

		if ( 'delete' === $request_action ) {
			$campaign_key = isset( $_POST['campaign_key'] ) && is_string( $_POST['campaign_key'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				? sanitize_title( wp_unslash( $_POST['campaign_key'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
				: '';
			$old_campaign = CampaignService::get_campaign( $campaign_key );
			$deleted      = CampaignService::delete_campaign( $campaign_key );

			if ( $deleted && is_array( $old_campaign ) ) {
				ActivityLogRepository::record(
					'offer_campaign_deleted',
					$old_campaign,
					array(),
					array(
						'source' => 'offers_admin',
					)
				);
			}

			$this->redirect( $deleted ? 'deleted' : 'invalid' );
		}

		if ( 'save' === $request_action ) {
			$campaign = array(
				'campaign_key'  => $this->post_string( 'campaign_key' ),
				'name'          => $this->post_string( 'name' ),
				'campaign_type' => $this->post_string( 'campaign_type' ),
				'product_id'    => $this->post_string( 'product_id' ),
				'offer_price'   => $this->post_string( 'offer_price' ),
				'status'        => $this->post_string( 'status' ),
				'starts_at'     => $this->post_string( 'starts_at' ),
				'ends_at'       => $this->post_string( 'ends_at' ),
			);
			$old_campaign = CampaignService::get_campaign(
				(string) $campaign['campaign_key']
			);
			$saved        = CampaignService::save_campaign( $campaign );

			if ( $saved ) {
				$new_campaign = CampaignService::get_campaign(
					(string) $campaign['campaign_key']
				);

				if ( is_array( $new_campaign ) ) {
					ActivityLogRepository::record(
						'offer_campaign_saved',
						is_array( $old_campaign ) ? $old_campaign : array(),
						$new_campaign,
						array(
							'source' => 'offers_admin',
						)
					);
				}
			}

			$this->redirect( $saved ? 'saved' : 'invalid' );
		}

		$this->redirect( 'invalid' );
	}

	/**
	 * Returns one unslashed request string.
	 *
	 * @param string $field Request field.
	 * @return string
	 */
	private function post_string( string $field ): string {
		return isset( $_POST[ $field ] ) && is_string( $_POST[ $field ] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? wp_unslash( $_POST[ $field ] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			: '';
	}

	/**
	 * Redirects to the offers administration page.
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
