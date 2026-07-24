<?php
/**
 * Activity-log repository.
 *
 * @package GalaxyOne\Core\ActivityLog
 */

namespace GalaxyOne\Core\ActivityLog;

use GalaxyOne\Core\Database\Migrations\CreateActivityLogTable;

final class ActivityLogRepository {

	/**
	 * Records an administrator activity event.
	 *
	 * @param string               $action    Event action.
	 * @param array<string, mixed> $old_value Previous value.
	 * @param array<string, mixed> $new_value New value.
	 * @param array<string, mixed> $context   Event context.
	 * @return void
	 */
	public static function record( string $action, array $old_value, array $new_value, array $context = array() ): void {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return;
		}

		$wpdb->insert(
			CreateActivityLogTable::get_table_name(),
			array(
				'user_id'    => get_current_user_id(),
				'action'     => sanitize_key( $action ),
				'old_value'  => wp_json_encode( $old_value ),
				'new_value'  => wp_json_encode( $new_value ),
				'context'    => wp_json_encode( $context ),
				'created_at' => current_time( 'mysql', true ),
			),
			array(
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);
	}

	/**
	 * Returns recent activity entries.
	 *
	 * @param int $limit Maximum number of entries.
	 * @return array<int, object>
	 */
	public static function get_recent( int $limit ): array {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return array();
		}

		$limit      = max( 1, min( 100, $limit ) );
		$table_name = CreateActivityLogTable::get_table_name();
		$query      = $wpdb->prepare(
			"SELECT id, user_id, action, old_value, new_value, context, created_at
			FROM {$table_name}
			ORDER BY id DESC
			LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$limit
		);

		$entries = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * Determines whether the activity-log table exists.
	 *
	 * @return bool
	 */
	private static function table_exists(): bool {
		global $wpdb;

		$table_name = CreateActivityLogTable::get_table_name();
		$found_name = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return $table_name === $found_name;
	}
}
