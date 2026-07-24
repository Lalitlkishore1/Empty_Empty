<?php
/**
 * Plugin Name: GalaxyOne Core
 * Description: Core business-logic foundation for GalaxyOne.
 * Version: 0.2.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: GalaxyOne
 * Text Domain: galaxyone-core
 *
 * @package GalaxyOne\Core
 */

defined( 'ABSPATH' ) || exit;

define( 'GALAXYONE_CORE_VERSION', '0.2.0' );
define( 'GALAXYONE_CORE_FILE', __FILE__ );
define( 'GALAXYONE_CORE_PATH', plugin_dir_path( __FILE__ ) );

$galaxyone_core_autoload_file = GALAXYONE_CORE_PATH . 'vendor/autoload.php';

register_activation_hook(
	__FILE__,
	static function (): void {
		$errors = array();

		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'GalaxyOne Core requires PHP %1$s or later. Current version: %2$s.', 'galaxyone-core' ),
				'8.1',
				PHP_VERSION
			);
		}

		if ( ! defined( 'WC_VERSION' ) ) {
			$errors[] = __( 'GalaxyOne Core requires WooCommerce to be installed and active.', 'galaxyone-core' );
		}

		$autoload_file = GALAXYONE_CORE_PATH . 'vendor/autoload.php';

		if ( ! file_exists( $autoload_file ) ) {
			$errors[] = __( 'GalaxyOne Core requires Composer dependencies. Run composer install inside the plugin directory.', 'galaxyone-core' );
		}

		if ( ! empty( $errors ) ) {
			deactivate_plugins( plugin_basename( GALAXYONE_CORE_FILE ) );

			wp_die(
				esc_html( implode( ' ', $errors ) ),
				esc_html__( 'GalaxyOne Core activation failed', 'galaxyone-core' ),
				array(
					'back_link' => true,
				)
			);
		}

		require_once $autoload_file;

		\GalaxyOne\Core\Plugin::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		$autoload_file = GALAXYONE_CORE_PATH . 'vendor/autoload.php';

		if ( ! file_exists( $autoload_file ) ) {
			return;
		}

		require_once $autoload_file;

		\GalaxyOne\Core\Plugin::deactivate();
	}
);

if ( ! file_exists( $galaxyone_core_autoload_file ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					esc_html_e(
						'GalaxyOne Core requires Composer dependencies. Run composer install inside the plugin directory.',
						'galaxyone-core'
					);
					?>
				</p>
			</div>
			<?php
		}
	);

	return;
}

require_once $galaxyone_core_autoload_file;

add_action(
	'plugins_loaded',
	array( \GalaxyOne\Core\Plugin::class, 'boot' )
);
