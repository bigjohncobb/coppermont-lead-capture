<?php
/**
 * AJAX endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Ajax {
	/**
	 * Impression dedupe window (seconds).
	 */
	const DEDUPE_WINDOW = 90;

	/**
	 * Burst threshold per client/window.
	 */
	const BURST_THRESHOLD = 8;

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

		$context = $this->validate_context_token();
		if ( is_wp_error( $context ) ) {
			$this->increment_suspected_bot_traffic();
			wp_send_json_success( array( 'impressions' => CMLC_Settings::get()['analytics_impressions'], 'dropped' => true ) );
		}

		$dedupe_key = $this->build_dedupe_key( $context );
		if ( $this->should_quiet_drop_impression( $dedupe_key ) ) {
			$this->increment_suspected_bot_traffic();
			wp_send_json_success( array( 'impressions' => CMLC_Settings::get()['analytics_impressions'], 'dropped' => true ) );
		}

		$settings                          = CMLC_Settings::get();
		$settings['analytics_impressions'] = absint( $settings['analytics_impressions'] ) + 1;
		$settings                          = $this->increment_daily_counter( $settings, 'analytics_daily_impressions' );
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

		$context = $this->validate_context_token();
		if ( is_wp_error( $context ) ) {
			$this->increment_suspected_bot_traffic();
			wp_send_json_error( array( 'message' => 'Invalid request context.' ), 403 );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Please provide a valid email.' ), 400 );
		}

		$settings                          = CMLC_Settings::get();
		$settings['analytics_submissions'] = absint( $settings['analytics_submissions'] ) + 1;
		$settings                          = $this->increment_daily_counter( $settings, 'analytics_daily_submissions' );
		update_option( CMLC_Settings::OPTION_KEY, $settings );

		/**
		 * Fires after lead form submission for CRM integrations.
		 */
		do_action( 'cmlc_lead_submitted', $email, $settings );

		wp_send_json_success( array( 'message' => 'Thanks! You are subscribed.' ) );
	}

	/**
	 * Validates the signed request context.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	private function validate_context_token() {
		$token = isset( $_POST['context_token'] ) ? sanitize_text_field( wp_unslash( $_POST['context_token'] ) ) : '';
		if ( empty( $token ) || false === strpos( $token, '.' ) ) {
			return new WP_Error( 'missing_context', 'Missing context token.' );
		}

		$parts = explode( '.', $token, 2 );
		if ( 2 !== count( $parts ) ) {
			return new WP_Error( 'invalid_context', 'Invalid context token.' );
		}

		$payload_encoded = $parts[0];
		$signature       = $parts[1];
		$payload_json    = base64_decode( $payload_encoded, true );

		if ( false === $payload_json ) {
			return new WP_Error( 'invalid_context', 'Malformed context payload.' );
		}

		$expected_signature = hash_hmac( 'sha256', $payload_json, wp_salt( 'nonce' ) );
		if ( ! hash_equals( $expected_signature, $signature ) ) {
			return new WP_Error( 'invalid_context_signature', 'Invalid context signature.' );
		}

		$payload = json_decode( $payload_json, true );
		if ( ! is_array( $payload ) || empty( $payload['campaign_hash'] ) || empty( $payload['page_hash'] ) || empty( $payload['expires_at'] ) ) {
			return new WP_Error( 'invalid_context_payload', 'Incomplete context payload.' );
		}

		if ( time() > absint( $payload['expires_at'] ) ) {
			return new WP_Error( 'expired_context', 'Expired context token.' );
		}

		return $payload;
	}

	/**
	 * Builds a dedupe key for impression events.
	 *
	 * @param array<string,mixed> $context Context payload.
	 * @return string
	 */
	private function build_dedupe_key( $context ) {
		$ip_address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : 'unknown';
		$window     = (int) floor( time() / self::DEDUPE_WINDOW );

		return 'cmlc_imp_' . hash( 'sha256', $ip_address . '|' . $user_agent . '|' . $context['campaign_hash'] . '|' . $window );
	}

	/**
	 * Determines whether an impression should be dropped.
	 *
	 * @param string $dedupe_key Key for this request fingerprint.
	 * @return bool
	 */
	private function should_quiet_drop_impression( $dedupe_key ) {
		$existing = get_transient( $dedupe_key );
		$count    = is_numeric( $existing ) ? absint( $existing ) : 0;

		if ( $count >= self::BURST_THRESHOLD ) {
			set_transient( $dedupe_key, $count + 1, self::DEDUPE_WINDOW );
			return true;
		}

		if ( $count > 0 ) {
			set_transient( $dedupe_key, $count + 1, self::DEDUPE_WINDOW );
			return true;
		}

		set_transient( $dedupe_key, 1, self::DEDUPE_WINDOW );
		return false;
	}

	/**
	 * Increments a daily counter in settings.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param string              $key Daily analytics key.
	 * @return array<string,mixed>
	 */
	private function increment_daily_counter( $settings, $key ) {
		$today = gmdate( 'Y-m-d' );
		$data  = isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ? $settings[ $key ] : array();
		$value = isset( $data[ $today ] ) ? absint( $data[ $today ] ) : 0;
		$data[ $today ]   = $value + 1;
		$settings[ $key ] = $data;

		return $settings;
	}

	/**
	 * Records suspected bot traffic events.
	 *
	 * @return void
	 */
	private function increment_suspected_bot_traffic() {
		$settings                                = CMLC_Settings::get();
		$settings['analytics_suspected_bots']    = absint( $settings['analytics_suspected_bots'] ) + 1;
		$settings                                = $this->increment_daily_counter( $settings, 'analytics_daily_suspected_bots' );
		update_option( CMLC_Settings::OPTION_KEY, $settings );
	}
}
