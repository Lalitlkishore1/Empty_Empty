<?php
/**
 * Shared validation helpers.
 *
 * @package GalaxyOne\Core\Support
 */

namespace GalaxyOne\Core\Support;

final class Validator {

	/**
	 * Determines whether a value is a non-empty string after trimming.
	 *
	 * @param mixed $value Value to validate.
	 * @return bool
	 */
	public static function is_non_empty_string( mixed $value ): bool {
		return is_string( $value ) && '' !== trim( $value );
	}
}
