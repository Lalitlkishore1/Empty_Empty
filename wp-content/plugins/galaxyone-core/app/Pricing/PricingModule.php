<?php
/**
 * Pricing module.
 *
 * @package GalaxyOne\Core\Pricing
 */

namespace GalaxyOne\Core\Pricing;

use GalaxyOne\Core\Contracts\ModuleInterface;

final class PricingModule implements ModuleInterface {

	/**
	 * Registers the pricing module.
	 *
	 * Price resolution is service-driven in this phase. WooCommerce cart,
	 * checkout, and order hooks are introduced only in Phase 9.
	 *
	 * @return void
	 */
	public function register(): void {
	}
}
