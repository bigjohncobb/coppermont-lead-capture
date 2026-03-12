<?php
/**
 * AJAX endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Ajax {
	/**
	 * Analytics service.
	 *
	 * @var CMLC_Analytics
	 */
	private $analytics;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->analytics = new CMLC_Analytics();

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

		$settings = CMLC_Settings::get();
		$context  = $this->build_event_context();

		$this->analytics->record_event( 'impression', $context );

		// Backwards compatibility for existing settings-based counters.
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

		$settings = CMLC_Settings::get();
		$context  = $this->build_event_context();

		$this->analytics->record_event( 'submission', $context );

		// Backwards compatibility for existing settings-based counters.
		$settings['analytics_submissions'] = absint( $settings['analytics_submissions'] ) + 1;
		update_option( CMLC_Settings::OPTION_KEY, $settings );

		/**
		 * Fires after lead form submission for CRM integrations.
		 */
		do_action( 'cmlc_lead_submitted', $email, $settings );

		wp_send_json_success( array( 'message' => 'Thanks! You are subscribed.' ) );
	}

	/**
	 * Builds event context from request/server information.
	 *
	 * @return array<string,mixed>
	 */
	private function build_event_context() {
		$page_id     = isset( $_POST['page_id'] ) ? absint( wp_unslash( $_POST['page_id'] ) ) : get_queried_object_id();
		$campaign_id = isset( $_POST['campaign_id'] ) ? absint( wp_unslash( $_POST['campaign_id'] ) ) : 0;
		$referrer    = wp_get_referer();

		if ( empty( $referrer ) && isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referrer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}

		$referrer_host = '';
		if ( ! empty( $referrer ) ) {
			$parsed_host = wp_parse_url( $referrer, PHP_URL_HOST );
			if ( is_string( $parsed_host ) ) {
				$referrer_host = strtolower( sanitize_text_field( $parsed_host ) );
			}
		}

		return array(
			'campaign_id'   => $campaign_id,
			'page_id'       => $page_id,
			'referrer_host' => $referrer_host,
		);
	}
}
