<?php
/**
 * Settings repository.
 *
 * @package GalaxyOne\Core\Settings
 */

namespace GalaxyOne\Core\Settings;

use GalaxyOne\Core\Security\InputValidator;

final class SettingsRepository {

	/**
	 * Settings API option group.
	 *
	 * @var string
	 */
	public const OPTION_GROUP = 'galaxyone_core_settings';

	/**
	 * WordPress option name.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'galaxyone_core_settings';

	/**
	 * Returns the default settings.
	 *
	 * @return array<string, int|string>
	 */
	public static function get_defaults(): array {
		return array(
			'activity_log_display_limit' => 50,
			'water_stairs_floor_1_rate' => '5.0000',
			'water_stairs_floor_2_rate' => '10.0000',
			'water_stairs_floor_3_rate' => '15.0000',
			'water_stairs_floor_4_rate' => '20.0000',
		);
	}

	/**
	 * Returns the stored settings merged with defaults.
	 *
	 * @return array<string, int|string>
	 */
	public static function get_settings(): array {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, self::get_defaults() );
	}

	/**
	 * Sanitizes settings submitted through the WordPress Settings API.
	 *
	 * @param mixed $settings Submitted settings.
	 * @return array<string, int|string>
	 */
	public static function sanitize( mixed $settings ): array {
		$defaults = self::get_defaults();
		$settings = is_array( $settings ) ? $settings : array();

		$sanitized = array(
			'activity_log_display_limit' => InputValidator::integer_in_range(
				$settings['activity_log_display_limit'] ?? $defaults['activity_log_display_limit'],
				10,
				100,
				$defaults['activity_log_display_limit']
			),
		);

		foreach ( self::get_water_stairs_rate_keys() as $floor => $key ) {
			$rate = self::normalize_non_negative_rate( $settings[ $key ] ?? null );

			if ( null === $rate ) {
				add_settings_error(
					self::OPTION_NAME,
					$key,
					__( 'Water stairs rates must be valid non-negative amounts.', 'galaxyone-core' ),
					'error'
				);
				$rate = self::get_water_stairs_rate( $floor );
			}

			$sanitized[ $key ] = is_string( $rate ) ? $rate : $defaults[ $key ];
		}

		return $sanitized;
	}

	/**
	 * Returns the configured per-can stairs rate for one supported floor.
	 *
	 * @param int $floor Stairs floor number.
	 * @return string|null
	 */
	public static function get_water_stairs_rate( int $floor ): ?string {
		$keys = self::get_water_stairs_rate_keys();

		if ( ! isset( $keys[ $floor ] ) ) {
			return null;
		}

		$settings = self::get_settings();
		$value    = $settings[ $keys[ $floor ] ] ?? null;

		return self::normalize_non_negative_rate( $value );
	}

	/**
	 * Returns the setting keys for independently configured stairs rates.
	 *
	 * @return array<int, string>
	 */
	private static function get_water_stairs_rate_keys(): array {
		return array(
			1 => 'water_stairs_floor_1_rate',
			2 => 'water_stairs_floor_2_rate',
			3 => 'water_stairs_floor_3_rate',
			4 => 'water_stairs_floor_4_rate',
		);
	}

	/**
	 * Normalizes one configured non-negative WooCommerce money amount.
	 *
	 * @param mixed $value Submitted setting value.
	 * @return string|null
	 */
	private static function normalize_non_negative_rate( mixed $value ): ?string {
		if ( ! is_scalar( $value ) ) {
			return null;
		}

		$value = trim( sanitize_text_field( (string) $value ) );

		if ( ! preg_match( '/^\d+(?:[.,]\d{1,4})?$/', $value ) ) {
			return null;
		}

		$rate = wc_format_decimal( $value, 4 );

		return '' !== $rate && (float) $rate >= 0 ? $rate : null;
	}
}
