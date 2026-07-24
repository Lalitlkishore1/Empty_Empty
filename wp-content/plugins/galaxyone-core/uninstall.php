<?php
/**
 * GalaxyOne Core uninstall handler.
 *
 * @package GalaxyOne\Core
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$galaxyone_activity_log_table = $wpdb->prefix . 'galaxy_activity_logs';

$wpdb->query( "DROP TABLE IF EXISTS {$galaxyone_activity_log_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'galaxyone_core_schema_version' );
