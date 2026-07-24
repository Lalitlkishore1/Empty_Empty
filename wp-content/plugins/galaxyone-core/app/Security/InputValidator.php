<?php
/**
 * Input validation helper.
 *
 * @package GalaxyOne\Core\Security
 */

namespace GalaxyOne\Core\Security;

final class InputValidator {

	/**
	 * Returns an integer within the supplied inclusive range.
	 *
	 * @param mixed $value   Value to validate.
	 * @param int   $minimum Inclusive minimum.
	 * @param int   $maximum Inclusive maximum.
	 * @param int   $default Fallback value.
	 * @return int
	 */
	public static function integer_in_range( mixed $value, int $minimum, int $maximum, int $default ): int {
		if ( is_int( $value ) ) {
			$integer_value = $value;
		} elseif ( is_string( $value ) && ctype_digit( $value ) ) {
			$integer_value = (int) $value;
		} else {
			return $default;
		}

		if ( $integer_value < $minimum || $integer_value > $maximum ) {
			return $default;
		}

		return $integer_value;
	}
}
