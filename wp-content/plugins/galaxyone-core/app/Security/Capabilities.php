<?php
/**
 * Capability helpers.
 *
 * @package GalaxyOne\Core\Security
 */

namespace GalaxyOne\Core\Security;

final class Capabilities {

	/**
	 * Determines whether the current user can manage GalaxyOne settings.
	 *
	 * @return bool
	 */
	public static function can_manage_galaxyone(): bool {
		return current_user_can( 'manage_woocommerce' );
	}
}
