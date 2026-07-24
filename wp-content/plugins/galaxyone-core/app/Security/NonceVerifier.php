<?php
/**
 * Nonce verification helper.
 *
 * @package GalaxyOne\Core\Security
 */

namespace GalaxyOne\Core\Security;

final class NonceVerifier {

	/**
	 * Verifies a nonce value from the current request.
	 *
	 * @param string $action Nonce action.
	 * @param string $field  Request field containing the nonce.
	 * @return bool
	 */
	public static function verify_request_nonce( string $action, string $field = '_wpnonce' ): bool {
		if ( ! isset( $_REQUEST[ $field ] ) || ! is_string( $_REQUEST[ $field ] ) ) {
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( $_REQUEST[ $field ] ) );

		return 1 === wp_verify_nonce( $nonce, $action );
	}
}
