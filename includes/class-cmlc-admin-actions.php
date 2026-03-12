<?php
/**
 * Admin-only action handlers for future dashboard pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Admin_Actions {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_cmlc_admin_dashboard_filters', array( $this, 'dashboard_filters' ) );
		add_action( 'admin_post_cmlc_export_leads', array( $this, 'export_leads' ) );
		add_action( 'admin_post_cmlc_bulk_delete_leads', array( $this, 'bulk_delete_leads' ) );
		add_action( 'admin_post_cmlc_reset_analytics', array( $this, 'reset_analytics' ) );
	}

	/**
	 * Handles dashboard filter requests.
	 *
	 * @return void
	 */
	public function dashboard_filters() {
		CMLC_Admin_Security::require_manage_options( true );
		CMLC_Admin_Security::require_admin_referer( 'cmlc_admin_dashboard_filters', true, 'security' );

		$date_start = isset( $_POST['date_start'] ) ? sanitize_text_field( wp_unslash( $_POST['date_start'] ) ) : '';
		$date_end   = isset( $_POST['date_end'] ) ? sanitize_text_field( wp_unslash( $_POST['date_end'] ) ) : '';

		wp_send_json_success(
			array(
				'filters' => array(
					'date_start' => $date_start,
					'date_end'   => $date_end,
				),
			)
		);
	}

	/**
	 * Handles lead export requests.
	 *
	 * @return void
	 */
	public function export_leads() {
		CMLC_Admin_Security::require_manage_options();
		CMLC_Admin_Security::require_admin_referer( 'cmlc_export_leads' );

		wp_safe_redirect( admin_url( 'options-general.php?page=cmlc-settings&cmlc_notice=export_ready' ) );
		exit;
	}

	/**
	 * Handles bulk lead deletion requests.
	 *
	 * @return void
	 */
	public function bulk_delete_leads() {
		CMLC_Admin_Security::require_manage_options();
		CMLC_Admin_Security::require_admin_referer( 'cmlc_bulk_delete_leads' );

		wp_safe_redirect( admin_url( 'options-general.php?page=cmlc-settings&cmlc_notice=bulk_delete_complete' ) );
		exit;
	}

	/**
	 * Handles analytics reset requests.
	 *
	 * @return void
	 */
	public function reset_analytics() {
		CMLC_Admin_Security::require_manage_options();
		CMLC_Admin_Security::require_admin_referer( 'cmlc_reset_analytics' );

		$settings                          = CMLC_Settings::get();
		$settings['analytics_impressions'] = 0;
		$settings['analytics_submissions'] = 0;
		update_option( CMLC_Settings::OPTION_KEY, $settings );

		wp_safe_redirect( admin_url( 'options-general.php?page=cmlc-settings&cmlc_notice=analytics_reset' ) );
		exit;
	}
}
