<?php
/**
 * Module provider.
 *
 * @package GalaxyOne\Core\Providers
 */

namespace GalaxyOne\Core\Providers;

use GalaxyOne\Core\ActivityLog\ActivityLogModule;
use GalaxyOne\Core\Admin\AdminModule;
use GalaxyOne\Core\Admin\DashboardModule;
use GalaxyOne\Core\Admin\ReportsModule;
use GalaxyOne\Core\Cart\CartModule;
use GalaxyOne\Core\Checkout\CheckoutModule;
use GalaxyOne\Core\Contracts\ModuleInterface;
use GalaxyOne\Core\Customers\CustomerModule;
use GalaxyOne\Core\Delivery\DeliveryModule;
use GalaxyOne\Core\Frontend\FrontendModule;
use GalaxyOne\Core\Inventory\InventoryModule;
use GalaxyOne\Core\Notifications\NotificationsModule;
use GalaxyOne\Core\Offers\OffersModule;
use GalaxyOne\Core\Orders\OrdersModule;
use GalaxyOne\Core\Pricing\PricingModule;
use GalaxyOne\Core\Products\ProductsModule;
use GalaxyOne\Core\RewardedAds\RewardedAdsModule;
use GalaxyOne\Core\Settings\SettingsModule;

final class ModuleProvider {

	/**
	 * Returns the modules available in the current implementation phase.
	 *
	 * @return array<int, ModuleInterface>
	 */
	public static function get_modules(): array {
		return array(
			new ActivityLogModule(),
			new SettingsModule(),
			new AdminModule(),
			new DashboardModule(),
			new ReportsModule(),
			new InventoryModule(),
			new ProductsModule(),
			new DeliveryModule(),
			new PricingModule(),
			new OffersModule(),
			new FrontendModule(),
			new CartModule(),
			new CheckoutModule(),
			new OrdersModule(),
			new CustomerModule(),
			new NotificationsModule(),
			new RewardedAdsModule(),
		);
	}
}
