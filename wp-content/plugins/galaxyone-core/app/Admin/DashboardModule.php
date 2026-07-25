<?php
/**
 * Operational dashboard module.
 *
 * @package GalaxyOne\Core\Admin
 */

namespace GalaxyOne\Core\Admin;

use GalaxyOne\Core\ActivityLog\ActivityLogRepository;
use GalaxyOne\Core\Contracts\ModuleInterface;
use GalaxyOne\Core\Delivery\DeliverySlotService;
use GalaxyOne\Core\Delivery\ServiceAreaService;
use GalaxyOne\Core\Offers\CampaignService;
use GalaxyOne\Core\Orders\OrderQueryService;
use GalaxyOne\Core\RewardedAds\RewardCampaignRepository;
use GalaxyOne\Core\Security\Capabilities;

final class DashboardModule implements ModuleInterface {

	/**
	 * Dashboard page slug.
	 *
	 * @var string
	 */
	private const DASHBOARD_PAGE_SLUG = 'galaxyone-dashboard';

	/**
	 * Activity-log page slug.
	 *
	 * @var string
	 */
	private const ACTIVITY_PAGE_SLUG = 'galaxyone-activity-log';

	/**
	 * Registers dashboard administration.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 15 );
	}

	/**
	 * Registers dashboard and activity-log submenus.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			'galaxyone-core',
			__( 'Dashboard', 'galaxyone-core' ),
			__( 'Dashboard', 'galaxyone-core' ),
			Capabilities::get_manage_capability(),
			self::DASHBOARD_PAGE_SLUG,
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'galaxyone-core',
			__( 'Activity Log', 'galaxyone-core' ),
			__( 'Activity Log', 'galaxyone-core' ),
			Capabilities::get_manage_capability(),
			self::ACTIVITY_PAGE_SLUG,
			array( $this, 'render_activity_log' )
		);
	}

	/**
	 * Renders the operational dashboard.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		$this->assert_access();

		$metrics = OrderQueryService::get_dashboard_metrics();
		$context = array(
			'service_areas'     => count( ServiceAreaService::get_service_areas() ),
			'delivery_slots'    => count( DeliverySlotService::get_slots() ),
			'offer_campaigns'   => count( CampaignService::get_campaigns() ),
			'reward_campaigns'  => count( RewardCampaignRepository::get_campaigns() ),
		);
		$recent_activity = ActivityLogRepository::get_recent( 10 );

		require GALAXYONE_CORE_PATH . 'templates/admin/dashboard/dashboard-page.php';
	}

	/**
	 * Renders recent auditable activity.
	 *
	 * @return void
	 */
	public function render_activity_log(): void {
		$this->assert_access();

		$entries = ActivityLogRepository::get_recent( 100 );

		require GALAXYONE_CORE_PATH . 'templates/admin/activity-log/activity-log-page.php';
	}

	/**
	 * Stops unauthorized access before data is read.
	 *
	 * @return void
	 */
	private function assert_access(): void {
		if ( Capabilities::can_manage_galaxyone() ) {
			return;
		}

		wp_die(
			esc_html__( 'You do not have permission to access GalaxyOne operations.', 'galaxyone-core' ),
			esc_html__( 'GalaxyOne Operations', 'galaxyone-core' ),
			array( 'response' => 403 )
		);
	}
}
