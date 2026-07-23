<?php
/**
 * Shared logging helper.
 *
 * @package GalaxyOne\Core\Support
 */

namespace GalaxyOne\Core\Support;

final class Logger {

	/**
	 * Emits a debug message through a WordPress action when debugging is enabled.
	 *
	 * @param string $message Debug message.
	 * @return void
	 */
	public static function debug( string $message ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		/**
		 * Fires when GalaxyOne Core emits a debug message.
		 *
		 * @param string $message Debug message.
		 */
		do_action( 'galaxyone_core_debug', $message );
	}
}
