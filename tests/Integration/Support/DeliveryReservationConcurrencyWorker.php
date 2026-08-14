<?php
/**
 * Delivery-reservation concurrency worker.
 *
 * @package GalaxyOne\Tests\Integration\Support
 */

declare(strict_types=1);

use GalaxyOne\Core\Delivery\DeliveryReservationService;
use GalaxyOne\Core\Delivery\DeliverySlotService;

const GALAXYONE_WORKER_BOOTSTRAP_FAILURE = 10;
const GALAXYONE_WORKER_VALIDATION_FAILURE = 11;
const GALAXYONE_WORKER_SERVICE_FAILURE = 12;
const GALAXYONE_WORKER_UNEXPECTED_FAILURE = 13;
ob_start();

/**
 * Writes a structured worker result and terminates the process.
 *
 * @param int                  $exit_code Process exit code.
 * @param array<string, mixed> $result    Structured result.
 * @return never
 */
function galaxyone_worker_exit( int $exit_code, array $result ): never {
	$encoded_result = function_exists( 'wp_json_encode' )
		? wp_json_encode( $result )
		: json_encode( $result );

	if ( ! is_string( $encoded_result ) ) {
		$encoded_result = '{"success":false,"error":"serialization_failure"}';
	}

	while ( ob_get_level() > 0 ) {
	    ob_end_clean();
    }

    echo $encoded_result . PHP_EOL;
    exit( $exit_code );
    }

/**
 * Validates a process-coordination directory.
 *
 * @param mixed $directory Candidate directory.
 * @return string
 */
function galaxyone_worker_validate_directory( $directory ): string {
	if ( ! is_string( $directory ) || '' === $directory || ! is_dir( $directory ) ) {
		return '';
	}

	$resolved_directory = realpath( $directory );

	return is_string( $resolved_directory ) ? $resolved_directory : '';
}

/**
 * Waits for a test-owned start signal.
 *
 * @param string $directory Coordination directory.
 * @param string $worker_id Worker identifier.
 * @return bool
 */
function galaxyone_worker_wait_for_start( string $directory, string $worker_id ): bool {
	$ready_file = $directory . DIRECTORY_SEPARATOR . $worker_id . '.ready';
	$start_file = $directory . DIRECTORY_SEPARATOR . 'start';
	$deadline   = microtime( true ) + 20;

	if ( false === file_put_contents( $ready_file, 'ready' ) ) {
		return false;
	}

	while ( microtime( true ) < $deadline ) {
		if ( file_exists( $start_file ) ) {
			return true;
		}

		usleep( 10000 );
	}

	return false;
}

/**
 * Waits at the optional test-only operation barrier.
 *
 * @param string $directory Coordination directory.
 * @param string $worker_id Worker identifier.
 * @return bool
 */
function galaxyone_worker_wait_for_operation_start( string $directory, string $worker_id ): bool {
	$ready_file = $directory . DIRECTORY_SEPARATOR . $worker_id . '.operation-ready';
	$start_file = $directory . DIRECTORY_SEPARATOR . 'operation-start';
	$deadline   = microtime( true ) + 20;

	if ( false === file_put_contents( $ready_file, 'ready' ) ) {
		return false;
	}

	while ( microtime( true ) < $deadline ) {
		if ( file_exists( $start_file ) ) {
			return true;
		}

		usleep( 10000 );
	}

	return false;
}

/**
 * Waits at the optional operation barrier when requested by an integration test.
 *
 * @param string $directory Coordination directory.
 * @param string $worker_id Worker identifier.
 * @return bool
 */
function galaxyone_worker_wait_for_optional_operation_start( string $directory, string $worker_id ): bool {
	if ( '' === $directory ) {
		return true;
	}

	return galaxyone_worker_wait_for_operation_start( $directory, $worker_id );
}

try {
	$encoded_payload = isset( $argv[1] ) && is_string( $argv[1] ) ? $argv[1] : '';
	$decoded_payload = base64_decode( $encoded_payload, true );
	$payload         = is_string( $decoded_payload ) ? json_decode( $decoded_payload, true ) : null;

	if ( ! is_array( $payload ) ) {
		galaxyone_worker_exit(
			GALAXYONE_WORKER_VALIDATION_FAILURE,
			array(
				'success' => false,
				'error'   => 'invalid_payload',
			)
		);
	}

	$bootstrap_file = dirname( __DIR__ ) . '/bootstrap.php';

	if ( ! file_exists( $bootstrap_file ) ) {
		galaxyone_worker_exit(
			GALAXYONE_WORKER_BOOTSTRAP_FAILURE,
			array(
				'success' => false,
				'error'   => 'bootstrap_missing',
			)
		);
	}
    putenv( 'WP_TESTS_SKIP_INSTALL=1' );
	require_once $bootstrap_file;

	if (
		! defined( 'GALAXYONE_INTEGRATION_BOOTSTRAPPED' ) ||
		! class_exists( DeliveryReservationService::class )
	) {
		galaxyone_worker_exit(
			GALAXYONE_WORKER_BOOTSTRAP_FAILURE,
			array(
				'success' => false,
				'error'   => 'runtime_unavailable',
			)
		);
	}

	$action    = isset( $payload['action'] ) && is_string( $payload['action'] ) ? $payload['action'] : '';
	$worker_id = isset( $payload['worker_id'] ) && is_string( $payload['worker_id'] )
		? sanitize_key( $payload['worker_id'] )
		: '';
	$directory = galaxyone_worker_validate_directory( $payload['barrier_directory'] ?? null );

	$operation_directory = '';

	if ( array_key_exists( 'operation_barrier_directory', $payload ) ) {
		$operation_directory = galaxyone_worker_validate_directory(
			$payload['operation_barrier_directory']
		);
	}

	if (
		! in_array( $action, array( 'create', 'release', 'confirm', 'expire' ), true ) ||
		'' === $worker_id ||
		'' === $directory ||
		( array_key_exists( 'operation_barrier_directory', $payload ) && '' === $operation_directory )
	) {
		galaxyone_worker_exit(
			GALAXYONE_WORKER_VALIDATION_FAILURE,
			array(
				'success' => false,
				'error'   => 'invalid_operation',
			)
		);
	}

	if ( ! galaxyone_worker_wait_for_start( $directory, $worker_id ) ) {
		galaxyone_worker_exit(
			GALAXYONE_WORKER_UNEXPECTED_FAILURE,
			array(
				'success' => false,
				'error'   => 'start_timeout',
			)
		);
	}

	if ( 'create' === $action ) {
		$delivery_date   = isset( $payload['delivery_date'] ) && is_string( $payload['delivery_date'] )
			? sanitize_text_field( $payload['delivery_date'] )
			: '';
		$slot_key        = isset( $payload['slot_key'] ) && is_string( $payload['slot_key'] )
			? sanitize_title( $payload['slot_key'] )
			: '';
		$quantity        = isset( $payload['quantity'] ) ? absint( $payload['quantity'] ) : 0;
		$idempotency_key = isset( $payload['idempotency_key'] ) && is_string( $payload['idempotency_key'] )
			? trim( $payload['idempotency_key'] )
			: '';

		if (
			! DeliverySlotService::is_valid_date( $delivery_date ) ||
			'' === $slot_key ||
			$quantity <= 0 ||
			! wp_is_uuid( $idempotency_key )
		) {
			galaxyone_worker_exit(
				GALAXYONE_WORKER_VALIDATION_FAILURE,
				array(
					'success' => false,
					'error'   => 'invalid_create_input',
				)
			);
		}

		if (
			! galaxyone_worker_wait_for_optional_operation_start(
				$operation_directory,
				$worker_id
			)
		) {
			galaxyone_worker_exit(
				GALAXYONE_WORKER_UNEXPECTED_FAILURE,
				array(
					'success' => false,
					'error'   => 'operation_start_timeout',
				)
			);
		}

		$reservation = DeliveryReservationService::create(
			$delivery_date,
			$slot_key,
			$quantity,
			0,
			$idempotency_key
		);

		galaxyone_worker_exit(
			is_array( $reservation ) ? 0 : GALAXYONE_WORKER_SERVICE_FAILURE,
			array(
				'success'     => is_array( $reservation ),
				'reservation' => is_array( $reservation ) ? $reservation : array(),
			)
		);
	}

	if ( 'release' === $action ) {
		$reservation_token = isset( $payload['reservation_token'] ) && is_string( $payload['reservation_token'] )
			? trim( $payload['reservation_token'] )
			: '';

		if ( ! wp_is_uuid( $reservation_token ) ) {
			galaxyone_worker_exit(
				GALAXYONE_WORKER_VALIDATION_FAILURE,
				array(
					'success' => false,
					'error'   => 'invalid_release_input',
				)
			);
		}

		if (
			! galaxyone_worker_wait_for_optional_operation_start(
				$operation_directory,
				$worker_id
			)
		) {
			galaxyone_worker_exit(
				GALAXYONE_WORKER_UNEXPECTED_FAILURE,
				array(
					'success' => false,
					'error'   => 'operation_start_timeout',
				)
			);
		}

		$released = DeliveryReservationService::release( $reservation_token );

		galaxyone_worker_exit(
			$released ? 0 : GALAXYONE_WORKER_SERVICE_FAILURE,
			array(
				'success' => $released,
			)
		);
	}

	if ( 'confirm' === $action ) {
		$reservation_token = isset( $payload['reservation_token'] ) && is_string( $payload['reservation_token'] )
			? trim( $payload['reservation_token'] )
			: '';
		$order_id          = isset( $payload['order_id'] ) ? absint( $payload['order_id'] ) : 0;

		if ( ! wp_is_uuid( $reservation_token ) || $order_id <= 0 ) {
			galaxyone_worker_exit(
				GALAXYONE_WORKER_VALIDATION_FAILURE,
				array(
					'success' => false,
					'error'   => 'invalid_confirmation_input',
				)
			);
		}

		if (
			! galaxyone_worker_wait_for_optional_operation_start(
				$operation_directory,
				$worker_id
			)
		) {
			galaxyone_worker_exit(
				GALAXYONE_WORKER_UNEXPECTED_FAILURE,
				array(
					'success' => false,
					'error'   => 'operation_start_timeout',
				)
			);
		}

		$confirmed = DeliveryReservationService::assign_to_order(
			$reservation_token,
			$order_id
		);

		galaxyone_worker_exit(
			$confirmed ? 0 : GALAXYONE_WORKER_SERVICE_FAILURE,
			array(
				'success' => $confirmed,
			)
		);
	}

	if (
		! galaxyone_worker_wait_for_optional_operation_start(
			$operation_directory,
			$worker_id
		)
	) {
		galaxyone_worker_exit(
			GALAXYONE_WORKER_UNEXPECTED_FAILURE,
			array(
				'success' => false,
				'error'   => 'operation_start_timeout',
			)
		);
	}

	$expired = DeliveryReservationService::release_expired();

	galaxyone_worker_exit(
		0,
		array(
			'success' => true,
			'expired' => $expired,
		)
	);
} catch ( Throwable $exception ) {
	galaxyone_worker_exit(
		GALAXYONE_WORKER_UNEXPECTED_FAILURE,
		array(
			'success' => false,
			'error'   => 'unexpected_failure',
		)
	);
}
