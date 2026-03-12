<?php
/**
 * Admin security helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Admin_Security {
	/**
	 * Checks whether the current user can manage plugin admin actions.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage_options() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Verifies admin referer for state-changing admin actions.
	 *
	 * @param string $action Nonce action key.
	 * @param string $query_arg Nonce query arg.
	 * @return bool
	 */
	public static function check_admin_referer_or_false( $action, $query_arg = '_wpnonce' ) {
		return false !== check_admin_referer( $action, $query_arg );
	}

	/**
	 * Enforces authn/authz for admin-post handlers.
	 *
	 * @param string $nonce_action Nonce action key.
	 * @param string $nonce_arg Nonce query arg.
	 * @return void
	 */
	public static function enforce_admin_action_or_die( $nonce_action, $nonce_arg = '_wpnonce' ) {
		if ( ! self::current_user_can_manage_options() ) {
			wp_die( esc_html__( 'Unauthorized request.', 'coppermont-lead-capture' ), 403 );
		}

		self::check_admin_referer_or_false( $nonce_action, $nonce_arg );
	}

	/**
	 * Enforces authn/authz for authenticated AJAX handlers.
	 *
	 * @param string $nonce_action Nonce action key.
	 * @param string $nonce_arg Nonce query arg.
	 * @return void
	 */
	public static function enforce_admin_ajax_or_json_error( $nonce_action, $nonce_arg = '_wpnonce' ) {
		if ( ! self::current_user_can_manage_options() ) {
			wp_send_json_error( array( 'message' => 'Unauthorized request.' ), 403 );
		}

		if ( false === check_ajax_referer( $nonce_action, $nonce_arg, false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
		}
	}
}
