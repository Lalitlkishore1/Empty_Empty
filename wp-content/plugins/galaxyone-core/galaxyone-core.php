<?php
/**
 * Plugin Name: GalaxyOne Core
 * Description: Core business-logic foundation for GalaxyOne.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: GalaxyOne
 * Text Domain: galaxyone-core
 *
 * @package GalaxyOne\Core
 */

defined( 'ABSPATH' ) || exit;

define( 'GALAXYONE_CORE_VERSION', '0.1.0' );
define( 'GALAXYONE_CORE_FILE', __FILE__ );
define( 'GALAXYONE_CORE_PATH', plugin_dir_path( __FILE__ ) );

$galaxyone_core_autoload_file = GALAXYONE_CORE_PATH . 'vendor/autoload.php';

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

register_activation_hook(
	__FILE__,
	array( \GalaxyOne\Core\Plugin::class, 'activate' )
);

register_deactivation_hook(
	__FILE__,
	array( \GalaxyOne\Core\Plugin::class, 'deactivate' )
);

add_action(
	'plugins_loaded',
	array( \GalaxyOne\Core\Plugin::class, 'boot' )
);
