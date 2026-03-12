<?php
/**
 * AJAX endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Ajax {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_cmlc_track_impression', array( $this, 'track_impression' ) );
		add_action( 'wp_ajax_nopriv_cmlc_track_impression', array( $this, 'track_impression' ) );
		add_action( 'wp_ajax_cmlc_submit_email', array( $this, 'submit_email' ) );
		add_action( 'wp_ajax_nopriv_cmlc_submit_email', array( $this, 'submit_email' ) );
	}

	/**
	 * Tracks infobar show count.
	 *
	 * @return void
	 */
	public function track_impression() {
		if ( ! wp_doing_ajax() || 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			wp_send_json_error( array( 'message' => 'Invalid request method.' ), 405 );
		}

		check_ajax_referer( 'cmlc_nonce', 'nonce' );

		$settings                          = CMLC_Settings::get();
		$settings['analytics_impressions'] = absint( $settings['analytics_impressions'] ) + 1;
		update_option( CMLC_Settings::OPTION_KEY, $settings );

		wp_send_json_success( array( 'impressions' => $settings['analytics_impressions'] ) );
	}

	/**
	 * Handles email submission and tracks conversion.
	 *
	 * @return void
	 */
	public function submit_email() {
		if ( ! wp_doing_ajax() || 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			wp_send_json_error( array( 'message' => 'Invalid request method.' ), 405 );
		}

		check_ajax_referer( 'cmlc_nonce', 'nonce' );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Please provide a valid email.' ), 400 );
		}

		$source      = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';
		$campaign_id = isset( $_POST['campaign_id'] ) ? sanitize_text_field( wp_unslash( $_POST['campaign_id'] ) ) : '';
		$metadata    = isset( $_POST['metadata'] ) ? wp_unslash( $_POST['metadata'] ) : '';

		if ( is_array( $metadata ) || is_object( $metadata ) ) {
			$metadata = wp_json_encode( $metadata );
		} else {
			$metadata = sanitize_textarea_field( (string) $metadata );
		}

		global $wpdb;
		$table_name = CMLC_Plugin::leads_table_name();
		$inserted   = $wpdb->insert(
			$table_name,
			array(
				'email'       => $email,
				'source'      => $source,
				'campaign_id' => $campaign_id,
				'metadata'    => $metadata,
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			wp_send_json_error( array( 'message' => 'Could not save your submission at this time.' ), 500 );
		}

		$settings = CMLC_Settings::sync_submission_analytics();

		/**
		 * Fires after lead form submission for CRM integrations.
		 */
		do_action( 'cmlc_lead_submitted', $email, $settings );

		wp_send_json_success( array( 'message' => 'Thanks! You are subscribed.' ) );
	}
}
