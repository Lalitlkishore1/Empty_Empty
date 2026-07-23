<?php
/**
 * GalaxyOne Core uninstall handler.
 *
 * @package GalaxyOne\Core
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$galaxyone_core_autoload_file = __DIR__ . '/vendor/autoload.php';

if ( ! file_exists( $galaxyone_core_autoload_file ) ) {
	return;
}

require_once $galaxyone_core_autoload_file;

\GalaxyOne\Core\Database\SchemaManager::uninstall();
