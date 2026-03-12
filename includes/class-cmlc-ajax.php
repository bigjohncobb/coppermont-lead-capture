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
		$campaign = $this->get_campaign_from_request();
		if ( empty( $campaign ) ) {
			wp_send_json_error( array( 'message' => 'Campaign not found.' ), 404 );
		}

		$campaign['analytics_impressions'] = absint( $campaign['analytics_impressions'] ) + 1;
		CMLC_Campaigns::update_campaign( (int) $campaign['campaign_id'], $campaign );

		wp_send_json_success(
			array(
				'campaign_id'  => (int) $campaign['campaign_id'],
				'impressions'  => $campaign['analytics_impressions'],
			)
		);
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

		$campaign = $this->get_campaign_from_request();
		if ( empty( $campaign ) ) {
			wp_send_json_error( array( 'message' => 'Campaign not found.' ), 404 );
		}

		$campaign['analytics_submissions'] = absint( $campaign['analytics_submissions'] ) + 1;
		CMLC_Campaigns::update_campaign( (int) $campaign['campaign_id'], $campaign );

		do_action( 'cmlc_lead_submitted', $email, $campaign );

		wp_send_json_success(
			array(
				'campaign_id' => (int) $campaign['campaign_id'],
				'message'     => 'Thanks! You are subscribed.',
			)
		);
	}

	/**
	 * Resolves campaign from request payload.
	 *
	 * @return array<string,mixed>|null
	 */
	private function get_campaign_from_request() {
		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( wp_unslash( $_POST['campaign_id'] ) ) : 0;
		if ( $campaign_id ) {
			return CMLC_Campaigns::get_campaign( $campaign_id );
		}

		return CMLC_Campaigns::get_active_campaign();
	}
}
