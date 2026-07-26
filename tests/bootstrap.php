<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package GalaxyOne
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Provides the WordPress sanitize_key behavior required by standalone unit tests.
	 *
	 * @param string $key Key value.
	 * @return string
	 */
	function sanitize_key( string $key ): string {
		$key       = strtolower( $key );
		$sanitized = preg_replace( '/[^a-z0-9_\-]/', '', $key );

		return is_string( $sanitized ) ? $sanitized : '';
	}
}
