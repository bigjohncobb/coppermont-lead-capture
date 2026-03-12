<?php
/**
 * Admin action endpoints for dashboard operations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Admin_Actions {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_post_cmlc_dashboard_filter', array( $this, 'dashboard_filter' ) );
		add_action( 'admin_post_cmlc_export_data', array( $this, 'export_data' ) );
		add_action( 'admin_post_cmlc_bulk_delete', array( $this, 'bulk_delete' ) );
		add_action( 'admin_post_cmlc_reset_analytics', array( $this, 'reset_analytics' ) );

		add_action( 'wp_ajax_cmlc_dashboard_filter', array( $this, 'dashboard_filter_ajax' ) );
		add_action( 'wp_ajax_cmlc_export_data', array( $this, 'export_data_ajax' ) );
		add_action( 'wp_ajax_cmlc_bulk_delete', array( $this, 'bulk_delete_ajax' ) );
		add_action( 'wp_ajax_cmlc_reset_analytics', array( $this, 'reset_analytics_ajax' ) );
	}

	/**
	 * Handles admin-post dashboard filter actions.
	 *
	 * @return void
	 */
	public function dashboard_filter() {
		CMLC_Admin_Security::enforce_admin_action_or_die( 'cmlc_dashboard_filter' );
		wp_safe_redirect( admin_url( 'options-general.php?page=cmlc-settings' ) );
		exit;
	}

	/**
	 * Handles admin-post data export actions.
	 *
	 * @return void
	 */
	public function export_data() {
		CMLC_Admin_Security::enforce_admin_action_or_die( 'cmlc_export_data' );
		wp_safe_redirect( admin_url( 'options-general.php?page=cmlc-settings&cmlc-export=queued' ) );
		exit;
	}

	/**
	 * Handles admin-post bulk delete actions.
	 *
	 * @return void
	 */
	public function bulk_delete() {
		CMLC_Admin_Security::enforce_admin_action_or_die( 'cmlc_bulk_delete' );
		wp_safe_redirect( admin_url( 'options-general.php?page=cmlc-settings&cmlc-bulk-delete=done' ) );
		exit;
	}

	/**
	 * Handles admin-post analytics reset action.
	 *
	 * @return void
	 */
	public function reset_analytics() {
		CMLC_Admin_Security::enforce_admin_action_or_die( 'cmlc_reset_analytics' );

		$settings                          = CMLC_Settings::get();
		$settings['analytics_impressions'] = 0;
		$settings['analytics_submissions'] = 0;
		update_option( CMLC_Settings::OPTION_KEY, $settings );

		wp_safe_redirect( admin_url( 'options-general.php?page=cmlc-settings&cmlc-reset=done' ) );
		exit;
	}

	/**
	 * Handles AJAX dashboard filter actions.
	 *
	 * @return void
	 */
	public function dashboard_filter_ajax() {
		CMLC_Admin_Security::enforce_admin_ajax_or_json_error( 'cmlc_dashboard_filter', 'nonce' );
		wp_send_json_success( array( 'message' => 'Dashboard filters validated.' ) );
	}

	/**
	 * Handles AJAX export actions.
	 *
	 * @return void
	 */
	public function export_data_ajax() {
		CMLC_Admin_Security::enforce_admin_ajax_or_json_error( 'cmlc_export_data', 'nonce' );
		wp_send_json_success( array( 'message' => 'Export queued.' ) );
	}

	/**
	 * Handles AJAX bulk delete actions.
	 *
	 * @return void
	 */
	public function bulk_delete_ajax() {
		CMLC_Admin_Security::enforce_admin_ajax_or_json_error( 'cmlc_bulk_delete', 'nonce' );
		wp_send_json_success( array( 'message' => 'Bulk delete request validated.' ) );
	}

	/**
	 * Handles AJAX analytics reset action.
	 *
	 * @return void
	 */
	public function reset_analytics_ajax() {
		CMLC_Admin_Security::enforce_admin_ajax_or_json_error( 'cmlc_reset_analytics', 'nonce' );

		$settings                          = CMLC_Settings::get();
		$settings['analytics_impressions'] = 0;
		$settings['analytics_submissions'] = 0;
		update_option( CMLC_Settings::OPTION_KEY, $settings );

		wp_send_json_success( array( 'message' => 'Analytics reset.' ) );
	}
}
