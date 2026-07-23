<?php
/**
 * Runtime requirements.
 *
 * @package GalaxyOne\Core\Support
 */

namespace GalaxyOne\Core\Support;

final class Requirements {

	/**
	 * Minimum supported PHP version.
	 *
	 * @var string
	 */
	private const MINIMUM_PHP_VERSION = '8.1';

	/**
	 * Determines whether all runtime requirements are available.
	 *
	 * @return bool
	 */
	public static function are_met(): bool {
		return empty( self::get_errors() );
	}

	/**
	 * Returns unmet runtime requirements.
	 *
	 * @return array<int, string>
	 */
	public static function get_errors(): array {
		$errors = array();

		if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'GalaxyOne Core requires PHP %1$s or later. Current version: %2$s.', 'galaxyone-core' ),
				self::MINIMUM_PHP_VERSION,
				PHP_VERSION
			);
		}

		if ( ! defined( 'WC_VERSION' ) ) {
			$errors[] = __( 'GalaxyOne Core requires WooCommerce to be installed and active.', 'galaxyone-core' );
		}

		return $errors;
	}
}
