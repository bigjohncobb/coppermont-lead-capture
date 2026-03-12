<?php
/**
 * Admin request security helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Admin_Security {
	/**
	 * Checks for required admin capability.
	 *
	 * @param bool $json_response Whether to return JSON 403 instead of wp_die.
	 * @param bool $die_on_failure Whether helper should immediately deny the request.
	 * @return bool
	 */
	public static function require_manage_options( $json_response = false, $die_on_failure = true ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		if ( ! $die_on_failure ) {
			return false;
		}

		self::deny_access( $json_response, __( 'You are not allowed to perform this action.', 'coppermont-lead-capture' ) );
	}

	/**
	 * Validates admin nonce for privileged actions.
	 *
	 * @param string $action Nonce action.
	 * @param bool   $json_response Whether to return JSON 403 instead of wp_die.
	 * @param string $query_arg Nonce query argument name.
	 * @return bool
	 */
	public static function require_admin_referer( $action, $json_response = false, $query_arg = '_wpnonce' ) {
		if ( false !== check_admin_referer( $action, $query_arg ) ) {
			return true;
		}

		self::deny_access( $json_response, __( 'Security check failed.', 'coppermont-lead-capture' ) );
	}

	/**
	 * Handles unauthorized access responses.
	 *
	 * @param bool   $json_response Whether to return JSON 403 instead of wp_die.
	 * @param string $message Error message.
	 * @return void
	 */
	private static function deny_access( $json_response, $message ) {
		if ( $json_response ) {
			wp_send_json_error(
				array(
					'message' => $message,
				),
				403
			);
		}

		wp_die( esc_html( $message ), esc_html__( 'Forbidden', 'coppermont-lead-capture' ), array( 'response' => 403 ) );
	}
}
