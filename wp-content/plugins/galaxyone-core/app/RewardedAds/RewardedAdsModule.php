<?php
/**
 * Rewarded Ads module.
 *
 * @package GalaxyOne\Core\RewardedAds
 */

namespace GalaxyOne\Core\RewardedAds;

use GalaxyOne\Core\ActivityLog\ActivityLogRepository;
use GalaxyOne\Core\Contracts\ModuleInterface;
use GalaxyOne\Core\Pricing\PriceSnapshotService;
use GalaxyOne\Core\Security\Capabilities;
use GalaxyOne\Core\Security\NonceVerifier;
use WC_Cart;
use WC_Order;

final class RewardedAdsModule implements ModuleInterface {

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	private const PAGE_SLUG = 'galaxyone-rewarded-ads';

	/**
	 * Admin action.
	 *
	 * @var string
	 */
	private const ADMIN_ACTION = 'galaxyone_save_reward_campaign';

	/**
	 * Reward cleanup hook.
	 *
	 * @var string
	 */
	private const CLEANUP_HOOK = 'galaxyone_expire_reward_events';

	/**
	 * Registers the module.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_post_' . self::ADMIN_ACTION, array( $this, 'save_campaign' ) );
		add_shortcode( 'galaxyone_rewarded_offer', array( $this, 'render_rewarded_offer' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_galaxyone_start_reward', array( $this, 'start_reward' ) );
		add_action( 'wp_ajax_nopriv_galaxyone_start_reward', array( $this, 'start_reward' ) );
		add_action( 'wp_ajax_galaxyone_complete_reward', array( $this, 'complete_reward' ) );
		add_action( 'wp_ajax_nopriv_galaxyone_complete_reward', array( $this, 'complete_reward' ) );

		add_action(
			'woocommerce_before_calculate_totals',
			array( $this, 'apply_rewarded_cart_prices' ),
			30
		);
		add_action(
			'woocommerce_checkout_order_created',
			array( RewardRedemptionService::class, 'redeem_order_rewards' ),
			20
		);

		add_action( 'init', array( $this, 'schedule_cleanup' ) );
		add_action( self::CLEANUP_HOOK, array( RewardEventRepository::class, 'expire_events' ) );
	}

	/**
	 * Registers the rewarded-ads submenu page.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'galaxyone-core',
			__( 'Rewarded Offers', 'galaxyone-core' ),
			__( 'Rewarded Offers', 'galaxyone-core' ),
			Capabilities::get_manage_capability(),
			self::PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Renders reward campaign administration.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		if ( ! Capabilities::can_manage_galaxyone() ) {
			wp_die(
				esc_html__( 'You do not have permission to manage rewarded offers.', 'galaxyone-core' ),
				esc_html__( 'Rewarded Offers', 'galaxyone-core' ),
				array( 'response' => 403 )
			);
		}

		$campaigns = RewardCampaignRepository::get_campaigns();
		$notice    = isset( $_GET['galaxyone_notice'] ) && is_string( $_GET['galaxyone_notice'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_key( wp_unslash( $_GET['galaxyone_notice'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			: '';

		require GALAXYONE_CORE_PATH . 'templates/admin/rewarded-ads/campaigns-page.php';
	}

	/**
	 * Saves an authorized reward campaign.
	 *
	 * @return void
	 */
	public function save_campaign(): void {
		if ( ! Capabilities::can_manage_galaxyone() ) {
			wp_die(
				esc_html__( 'You do not have permission to update rewarded offers.', 'galaxyone-core' ),
				esc_html__( 'Rewarded Offers', 'galaxyone-core' ),
				array( 'response' => 403 )
			);
		}

		if ( ! NonceVerifier::verify_request_nonce( self::ADMIN_ACTION, 'galaxyone_reward_campaign_nonce' ) ) {
			wp_die(
				esc_html__( 'The security check failed. No rewarded offer was changed.', 'galaxyone-core' ),
				esc_html__( 'Rewarded Offers', 'galaxyone-core' ),
				array( 'response' => 403 )
			);
		}

		$campaign = array(
			'campaign_key'         => $this->post_value( 'campaign_key' ),
			'name'                 => $this->post_value( 'name' ),
			'product_id'           => $this->post_value( 'product_id' ),
			'offer_price'          => $this->post_value( 'offer_price' ),
			'provider_key'         => $this->post_value( 'provider_key' ),
			'required_completions' => $this->post_value( 'required_completions' ),
			'reward_ttl_minutes'   => $this->post_value( 'reward_ttl_minutes' ),
			'status'               => $this->post_value( 'status' ),
			'starts_at'            => $this->post_value( 'starts_at' ),
			'ends_at'              => $this->post_value( 'ends_at' ),
		);
		$saved    = RewardCampaignRepository::save( $campaign );

		if ( $saved ) {
			ActivityLogRepository::record(
				'reward_campaign_saved',
				array(),
				array(
					'campaign_key' => sanitize_title( (string) $campaign['campaign_key'] ),
					'product_id'   => absint( $campaign['product_id'] ),
				),
				array( 'source' => 'rewarded_ads_admin' )
			);
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => self::PAGE_SLUG,
					'galaxyone_notice' => $saved ? 'saved' : 'invalid',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Renders one optional reward-offer interface.
	 *
	 * @param array<string, string> $attributes Shortcode attributes.
	 * @return string
	 */
	public function render_rewarded_offer( array $attributes ): string {
		$attributes = shortcode_atts(
			array( 'product_id' => '0' ),
			$attributes,
			'galaxyone_rewarded_offer'
		);
		$product_id = absint( $attributes['product_id'] );
		$campaign   = RewardEligibilityService::get_campaign( $product_id );

		if ( ! is_array( $campaign ) ) {
			return '';
		}

		ob_start();

		require GALAXYONE_CORE_PATH . 'templates/frontend/rewarded-offers/rewarded-offer.php';

		return (string) ob_get_clean();
	}

	/**
	 * Enqueues frontend reward behavior.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		wp_enqueue_script(
			'galaxyone-rewarded-ads',
			plugins_url( 'assets/js/rewarded-ads.js', GALAXYONE_CORE_FILE ),
			array(),
			GALAXYONE_CORE_VERSION,
			true
		);

		wp_localize_script(
			'galaxyone-rewarded-ads',
			'galaxyoneRewardedAds',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'galaxyone_rewarded_ads' ),
			)
		);
	}

	/**
	 * Starts a secure reward event.
	 *
	 * @return void
	 */
	public function start_reward(): void {
		$this->verify_ajax_nonce();

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$result     = RewardCompletionService::begin( $product_id );

		if ( $result instanceof \WP_Error ) {
			wp_send_json_error(
				array( 'message' => $result->get_error_message() ),
				400
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Completes a staging reward event through server-side provider verification.
	 *
	 * @return void
	 */
	public function complete_reward(): void {
		$this->verify_ajax_nonce();

		$event_token = isset( $_POST['event_token'] ) && is_string( $_POST['event_token'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? sanitize_text_field( wp_unslash( $_POST['event_token'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			: '';
		$result      = RewardCompletionService::complete( $event_token );

		if ( $result instanceof \WP_Error ) {
			wp_send_json_error(
				array( 'message' => $result->get_error_message() ),
				400
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Applies a verified unlocked reward to cart item prices.
	 *
	 * @param WC_Cart $cart WooCommerce cart.
	 * @return void
	 */
	public function apply_rewarded_cart_prices( WC_Cart $cart ): void {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! isset( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
				continue;
			}

			$product_id = isset( $cart_item['variation_id'] ) && absint( $cart_item['variation_id'] ) > 0
				? absint( $cart_item['variation_id'] )
				: absint( $cart_item['product_id'] ?? 0 );
			$reward     = RewardRedemptionService::get_rewarded_offer( $product_id );

			if ( ! is_array( $reward ) ) {
				continue;
			}

			$snapshot = PriceSnapshotService::create( $product_id, $reward );

			if ( ! is_array( $snapshot ) || 'rewarded_offer' !== $snapshot['source'] ) {
				continue;
			}

			$cart_item['data']->set_price( (string) $snapshot['price'] );
			$cart->cart_contents[ $cart_item_key ]['galaxyone_price_snapshot'] = $snapshot;
		}
	}

	/**
	 * Schedules reward expiry cleanup exactly once.
	 *
	 * @return void
	 */
	public function schedule_cleanup(): void {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CLEANUP_HOOK );
		}
	}

	/**
	 * Verifies the public reward-action nonce.
	 *
	 * @return void
	 */
	private function verify_ajax_nonce(): void {
		$nonce = isset( $_POST['nonce'] ) && is_string( $_POST['nonce'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			: '';

		if ( 1 !== wp_verify_nonce( $nonce, 'galaxyone_rewarded_ads' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'The reward security check failed.', 'galaxyone-core' ),
				),
				403
			);
		}
	}

	/**
	 * Returns an unslashed post value.
	 *
	 * @param string $field Field name.
	 * @return string
	 */
	private function post_value( string $field ): string {
		return isset( $_POST[ $field ] ) && is_string( $_POST[ $field ] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			? wp_unslash( $_POST[ $field ] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			: '';
	}
}
