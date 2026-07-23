<?php
/**
 * Module provider.
 *
 * @package GalaxyOne\Core\Providers
 */

namespace GalaxyOne\Core\Providers;

use GalaxyOne\Core\Contracts\ModuleInterface;

final class ModuleProvider {

	/**
	 * Returns the modules available in the current implementation phase.
	 *
	 * @return array<int, ModuleInterface>
	 */
	public static function get_modules(): array {
		return array();
	}
}
