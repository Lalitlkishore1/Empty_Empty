<?php
/**
 * Notification-log integration tests.
 *
 * @package GalaxyOne\Tests\Integration
 */

declare(strict_types=1);

namespace GalaxyOne\Tests\Integration;

use GalaxyOne\Core\Database\Migrations\CreateNotificationLogTable;
use GalaxyOne\Core\Notifications\NotificationLogRepository;
use GalaxyOne\Tests\Fixtures\OrderFixture;
use GalaxyOne\Tests\Support\IntegrationTestCase;

final class NotificationLogRepositoryTest extends IntegrationTestCase {

	/**
	 * Recreates the notification table for each isolated test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		CreateNotificationLogTable::down();
		self::assertTrue( CreateNotificationLogTable::up() );
	}

	/**
	 * Verifies a notification event produces one log entry only.
	 *
	 * @return void
	 */
	public function test_duplicate_order_events_are_not_queued_twice(): void {
		$order  = OrderFixture::create();
		$log_id = NotificationLogRepository::queue(
			$order->get_id(),
			'order_confirmation',
			'wp_mail',
			'galaxyone-test@example.test',
			'Order received'
		);

		self::assertIsInt( $log_id );
		self::assertGreaterThan( 0, $log_id );
		self::assertNull(
			NotificationLogRepository::queue(
				$order->get_id(),
				'order_confirmation',
				'wp_mail',
				'galaxyone-test@example.test',
				'Order received'
			)
		);
		self::assertCount( 1, NotificationLogRepository::get_for_order( $order->get_id() ) );
	}

	/**
	 * Verifies failed and skipped deliveries are persisted safely.
	 *
	 * @return void
	 */
	public function test_failed_and_skipped_statuses_are_persisted(): void {
		$order      = OrderFixture::create();
		$failed_id  = NotificationLogRepository::queue(
			$order->get_id(),
			'order_status_failed',
			'wp_mail',
			'galaxyone-test@example.test',
			'Order status update'
		);
		$skipped_id = NotificationLogRepository::queue(
			$order->get_id(),
			'order_status_cancelled',
			'wp_mail',
			'',
			'Order status update'
		);

		self::assertIsInt( $failed_id );
		self::assertIsInt( $skipped_id );
		self::assertTrue(
			NotificationLogRepository::mark_result(
				$failed_id,
				NotificationLogRepository::STATUS_FAILED,
				'Provider unavailable'
			)
		);
		self::assertTrue(
			NotificationLogRepository::mark_result(
				$skipped_id,
				NotificationLogRepository::STATUS_SKIPPED,
				'No valid recipient'
			)
		);

		$records = NotificationLogRepository::get_for_order( $order->get_id() );
		$statuses = array_map(
			static fn( object $record ): string => (string) $record->status,
			$records
		);

		self::assertContains( NotificationLogRepository::STATUS_FAILED, $statuses );
		self::assertContains( NotificationLogRepository::STATUS_SKIPPED, $statuses );
	}
}
