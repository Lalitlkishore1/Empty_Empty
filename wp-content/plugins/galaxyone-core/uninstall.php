<?php
/**
 * GalaxyOne Core uninstall handler.
 *
 * @package GalaxyOne\Core
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'galaxyone_core_schema_version' );
