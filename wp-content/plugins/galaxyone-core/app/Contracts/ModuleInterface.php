<?php
/**
 * Module contract.
 *
 * @package GalaxyOne\Core\Contracts
 */

namespace GalaxyOne\Core\Contracts;

interface ModuleInterface {

	/**
	 * Registers the module with WordPress.
	 *
	 * @return void
	 */
	public function register(): void;
}
