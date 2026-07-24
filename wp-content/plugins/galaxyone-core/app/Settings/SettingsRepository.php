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
	 * @return array<string, int>
	 */
	public static function get_defaults(): array {
		return array(
			'activity_log_display_limit' => 50,
		);
	}

	/**
	 * Returns the stored settings merged with defaults.
	 *
	 * @return array<string, int>
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
	 * @return array<string, int>
	 */
	public static function sanitize( mixed $settings ): array {
		$defaults = self::get_defaults();
		$settings = is_array( $settings ) ? $settings : array();

		return array(
			'activity_log_display_limit' => InputValidator::integer_in_range(
				$settings['activity_log_display_limit'] ?? $defaults['activity_log_display_limit'],
				10,
				100,
				$defaults['activity_log_display_limit']
			),
		);
	}
}
