<?php
/**
 * Module provider.
 *
 * @package GalaxyOne\Core\Providers
 */

namespace GalaxyOne\Core\Providers;

use GalaxyOne\Core\ActivityLog\ActivityLogModule;
use GalaxyOne\Core\Admin\AdminModule;
use GalaxyOne\Core\Contracts\ModuleInterface;
use GalaxyOne\Core\Delivery\DeliveryModule;
use GalaxyOne\Core\Inventory\InventoryModule;
use GalaxyOne\Core\Products\ProductsModule;
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
			new InventoryModule(),
			new ProductsModule(),
			new DeliveryModule(),
		);
	}
}
