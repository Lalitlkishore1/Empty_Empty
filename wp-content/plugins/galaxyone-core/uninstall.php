<?php
/**
 * GalaxyOne Core uninstall handler.
 *
 * @package GalaxyOne\Core
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$galaxyone_delivery_reservations_table  = $wpdb->prefix . 'galaxy_delivery_reservations';
$galaxyone_delivery_capacities_table    = $wpdb->prefix . 'galaxy_delivery_capacities';
$galaxyone_delivery_rules_table         = $wpdb->prefix . 'galaxy_delivery_rules';
$galaxyone_flower_daily_price_table     = $wpdb->prefix . 'galaxy_flower_daily_prices';
$galaxyone_activity_log_table           = $wpdb->prefix . 'galaxy_activity_logs';

$wpdb->query( "DROP TABLE IF EXISTS {$galaxyone_delivery_reservations_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$galaxyone_delivery_capacities_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$galaxyone_delivery_rules_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$galaxyone_flower_daily_price_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$galaxyone_activity_log_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'galaxyone_core_schema_version' );
