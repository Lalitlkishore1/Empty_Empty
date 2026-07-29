<?php
/**
 * Supported street normalization service.
 *
 * @package GalaxyOne\Core\Delivery
 */

namespace GalaxyOne\Core\Delivery;

final class StreetNormalizationService {

	/**
	 * Maximum Unicode code points allowed for street and locality values.
	 *
	 * @var int
	 */
	private const TEXT_MAXIMUM_CODE_POINTS = 191;

	/**
	 * Maximum Unicode code points allowed for a service area.
	 *
	 * @var int
	 */
	private const SERVICE_AREA_MAXIMUM_CODE_POINTS = 32;

	/**
	 * Prepares supported street values for persistence.
	 *
	 * @param string $street_name  Street display name.
	 * @param string $locality     Locality display name.
	 * @param string $service_area Optional service-area postcode.
	 * @return array<string, string>|null
	 */
	public static function prepare(
		string $street_name,
		string $locality,
		string $service_area = ''
	): ?array {
		$street_name = self::prepare_display_value( $street_name );
		$locality    = self::prepare_display_value( $locality );

		if (
			null === $street_name ||
			null === $locality ||
			! self::has_maximum_code_points(
				$street_name,
				self::TEXT_MAXIMUM_CODE_POINTS
			) ||
			! self::has_maximum_code_points(
				$locality,
				self::TEXT_MAXIMUM_CODE_POINTS
			)
		) {
			return null;
		}

		$normalized_street_name = strtolower( $street_name );
		$normalized_locality    = strtolower( $locality );

		if (
			! self::is_valid_normalized_value( $normalized_street_name ) ||
			! self::is_valid_normalized_value( $normalized_locality )
		) {
			return null;
		}

		$trimmed_service_area = trim( $service_area );

		if ( '' === $trimmed_service_area ) {
			$normalized_service_area = '';
		} else {
			$normalized_service_area = ServiceAreaService::normalize_postcode( $service_area );

			if ( '' === $normalized_service_area ) {
				return null;
			}
		}

		if (
			! self::has_maximum_code_points(
				$normalized_service_area,
				self::SERVICE_AREA_MAXIMUM_CODE_POINTS
			)
		) {
			return null;
		}

		$canonical_value = sprintf(
			'galaxyone-supported-street-v1|street:%d:%s|locality:%d:%s|service-area:%d:%s',
			strlen( $normalized_street_name ),
			$normalized_street_name,
			strlen( $normalized_locality ),
			$normalized_locality,
			strlen( $normalized_service_area ),
			$normalized_service_area
		);
		$identity_key    = hash( 'sha256', $canonical_value );
		$identity_match  = preg_match( '/^[a-f0-9]{64}$/D', $identity_key );

		if (
			! is_string( $identity_key ) ||
			64 !== strlen( $identity_key ) ||
			false === $identity_match ||
			1 !== $identity_match
		) {
			return null;
		}

		return array(
			'street_name'            => $street_name,
			'normalized_street_name' => $normalized_street_name,
			'locality'               => $locality,
			'normalized_locality'    => $normalized_locality,
			'service_area'           => $normalized_service_area,
			'street_identity_key'    => $identity_key,
		);
	}

	/**
	 * Sanitizes and normalizes a display value's whitespace.
	 *
	 * @param string $value Display value.
	 * @return string|null
	 */
	private static function prepare_display_value( string $value ): ?string {
		$value = sanitize_text_field( $value );
		$value = trim( $value );
		$value = preg_replace( '/[\p{Z}\s]+/u', ' ', $value );

		return is_string( $value ) ? $value : null;
	}

	/**
	 * Determines whether a normalized value is valid for persistence.
	 *
	 * @param string $value Normalized value.
	 * @return bool
	 */
	private static function is_valid_normalized_value( string $value ): bool {
		if ( '' === $value ) {
			return false;
		}

		$contains_letter_or_number = preg_match( '/[\p{L}\p{N}]/u', $value );

		if (
			false === $contains_letter_or_number ||
			1 !== $contains_letter_or_number
		) {
			return false;
		}

		return self::has_maximum_code_points(
			$value,
			self::TEXT_MAXIMUM_CODE_POINTS
		);
	}

	/**
	 * Determines whether a value is within its Unicode code-point limit.
	 *
	 * @param string $value          Value to count.
	 * @param int    $maximum_length Maximum allowed Unicode code points.
	 * @return bool
	 */
	private static function has_maximum_code_points(
		string $value,
		int $maximum_length
	): bool {
		$code_point_count = self::count_unicode_code_points( $value );

		return null !== $code_point_count && $code_point_count <= $maximum_length;
	}

	/**
	 * Counts Unicode code points without requiring mbstring.
	 *
	 * @param string $value Value to count.
	 * @return int|null
	 */
	private static function count_unicode_code_points( string $value ): ?int {
		$code_point_count = preg_match_all( '/./u', $value, $matches );

		return false === $code_point_count ? null : $code_point_count;
	}
}
