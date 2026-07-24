<?php
/**
 * Capability helpers.
 *
 * @package GalaxyOne\Core\Security
 */

namespace GalaxyOne\Core\Security;

final class Capabilities {

	/**
	 * Capability required to manage GalaxyOne administration settings.
	 *
	 * @var string
	 */
	private const MANAGE_CAPABILITY = 'manage_woocommerce';

	/**
	 * Returns the capability required to manage GalaxyOne.
	 *
	 * @return string
	 */
	public static function get_manage_capability(): string {
		return self::MANAGE_CAPABILITY;
	}

	/**
	 * Determines whether the current user can manage GalaxyOne settings.
	 *
	 * @return bool
	 */
	public static function can_manage_galaxyone(): bool {
		return current_user_can( self::get_manage_capability() );
	}
}
