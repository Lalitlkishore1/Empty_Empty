<?php
/**
 * Order query service unit tests.
 *
 * @package GalaxyOne\Tests\Unit
 */

declare(strict_types=1);

namespace GalaxyOne\Tests\Unit;

use GalaxyOne\Core\Orders\OrderQueryService;
use PHPUnit\Framework\TestCase;

final class OrderQueryServiceTest extends TestCase {

	/**
	 * Verifies each approved transition is accepted.
	 *
	 * @return void
	 */
	public function test_approved_status_transitions_are_allowed(): void {
		self::assertTrue( OrderQueryService::can_transition( 'pending', 'processing' ) );
		self::assertTrue( OrderQueryService::can_transition( 'processing', 'completed' ) );
		self::assertTrue( OrderQueryService::can_transition( 'processing', 'cancelled' ) );
		self::assertTrue( OrderQueryService::can_transition( 'completed', 'refunded' ) );
	}

	/**
	 * Verifies prohibited transitions are rejected.
	 *
	 * @return void
	 */
	public function test_unapproved_status_transitions_are_rejected(): void {
		self::assertFalse( OrderQueryService::can_transition( 'completed', 'processing' ) );
		self::assertFalse( OrderQueryService::can_transition( 'cancelled', 'processing' ) );
		self::assertFalse( OrderQueryService::can_transition( 'refunded', 'pending' ) );
		self::assertFalse( OrderQueryService::can_transition( 'pending', 'refunded' ) );
	}

	/**
	 * Verifies transition values are normalized before validation.
	 *
	 * @return void
	 */
	public function test_status_transition_values_are_sanitized(): void {
		self::assertTrue( OrderQueryService::can_transition( 'PENDING', 'processing' ) );
		self::assertFalse( OrderQueryService::can_transition( 'pending', '<script>completed</script>' ) );
	}
}
