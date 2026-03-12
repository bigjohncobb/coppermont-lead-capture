<?php
/**
 * AJAX endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Ajax {
	/**
	 * Rate limit transient key prefix.
	 *
	 * @var string
	 */
	const RATE_LIMIT_KEY_PREFIX = 'cmlc_rate_limit_';

	/**
	 * Duplicate email submission cooldown key prefix.
	 *
	 * @var string
	 */
	const DUPLICATE_EMAIL_COOLDOWN_KEY_PREFIX = 'cmlc_email_cooldown_';

	/**
	 * Throttle event option key.
	 *
	 * @var string
	 */
	const THROTTLE_LOG_OPTION_KEY = 'cmlc_throttle_events';

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

		// Nonce check is for CSRF protection only and not abuse mitigation.
		check_ajax_referer( 'cmlc_nonce', 'nonce' );

		$client_ip = $this->get_client_ip();
		if ( ! $this->is_rate_limit_allowlisted( $client_ip, 'track_impression' ) ) {
			$this->enforce_rate_limit( 'track_impression', 'ip:' . $client_ip, 120, HOUR_IN_SECONDS, 'ip_hourly' );
			$this->enforce_rate_limit( 'track_impression', 'ip:' . $client_ip, 30, MINUTE_IN_SECONDS, 'ip_minute' );
		}

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

		// Nonce check is for CSRF protection only and not abuse mitigation.
		check_ajax_referer( 'cmlc_nonce', 'nonce' );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Please provide a valid email.' ), 400 );
		}

		$client_ip = $this->get_client_ip();
		if ( ! $this->is_rate_limit_allowlisted( $client_ip, 'submit_email', $email ) ) {
			$this->enforce_rate_limit( 'submit_email', 'ip:' . $client_ip, 20, HOUR_IN_SECONDS, 'ip_hourly' );
			$this->enforce_rate_limit( 'submit_email', 'ip:' . $client_ip, 5, MINUTE_IN_SECONDS, 'ip_minute' );
			$this->enforce_rate_limit( 'submit_email', 'email:' . strtolower( $email ), 6, HOUR_IN_SECONDS, 'email_hourly' );
			$this->enforce_email_cooldown( $client_ip, $email, 30 );
		}

		$settings                          = CMLC_Settings::get();
		$settings['analytics_submissions'] = absint( $settings['analytics_submissions'] ) + 1;
		update_option( CMLC_Settings::OPTION_KEY, $settings );

		/**
		 * Fires after lead form submission for CRM integrations.
		 */
		do_action( 'cmlc_lead_submitted', $email, $settings );

		wp_send_json_success( array( 'message' => 'Thanks! You are subscribed.' ) );
	}

	/**
	 * Returns the client IP in a normalized format.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$raw_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		$ip     = sanitize_text_field( $raw_ip );

		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return 'unknown';
		}

		return $ip;
	}

	/**
	 * Checks if this request should bypass rate-limiting.
	 *
	 * @param string $client_ip Request IP.
	 * @param string $endpoint  Endpoint slug.
	 * @param string $email     Submitted email, if available.
	 *
	 * @return bool
	 */
	private function is_rate_limit_allowlisted( $client_ip, $endpoint, $email = '' ) {
		$allowlisted_ips = (array) apply_filters( 'cmlc_rate_limit_allowlisted_ips', array(), $endpoint, $email );
		if ( in_array( $client_ip, $allowlisted_ips, true ) ) {
			return true;
		}

		return (bool) apply_filters( 'cmlc_rate_limit_allowlisted', false, $client_ip, $endpoint, $email );
	}

	/**
	 * Enforces a generic rate-limit for an endpoint + identity.
	 *
	 * @param string $endpoint      Endpoint slug.
	 * @param string $identity      Identity (IP/email).
	 * @param int    $max_requests  Allowed requests in window.
	 * @param int    $window_seconds Rate window in seconds.
	 * @param string $reason        Diagnostic throttle reason.
	 *
	 * @return void
	 */
	private function enforce_rate_limit( $endpoint, $identity, $max_requests, $window_seconds, $reason ) {
		$max_requests = max( 1, (int) $max_requests );
		$window       = max( 1, (int) $window_seconds );
		$now          = time();
		$key          = self::RATE_LIMIT_KEY_PREFIX . md5( $endpoint . '|' . $identity . '|' . $reason );

		$record = get_transient( $key );
		if ( ! is_array( $record ) || empty( $record['expires_at'] ) || empty( $record['count'] ) || $record['expires_at'] <= $now ) {
			$record = array(
				'count'      => 0,
				'expires_at' => $now + $window,
			);
		}

		if ( $record['count'] >= $max_requests ) {
			$retry_after = max( 1, (int) $record['expires_at'] - $now );
			$this->log_throttle_event( $endpoint, $reason, $identity, $retry_after );

			wp_send_json_error(
				array(
					'message'     => 'Too many requests. Please try again shortly.',
					'retry_after' => $retry_after,
				),
				429
			);
		}

		$record['count'] = absint( $record['count'] ) + 1;
		set_transient( $key, $record, max( 1, (int) $record['expires_at'] - $now ) );
	}

	/**
	 * Enforces cooldown on repeated submissions for identical email payloads.
	 *
	 * @param string $client_ip       Request IP.
	 * @param string $email           Submitted email.
	 * @param int    $cooldown_seconds Cooldown duration in seconds.
	 *
	 * @return void
	 */
	private function enforce_email_cooldown( $client_ip, $email, $cooldown_seconds ) {
		$cooldown      = max( 1, (int) $cooldown_seconds );
		$now           = time();
		$composite     = strtolower( $client_ip . '|' . $email );
		$cooldown_key  = self::DUPLICATE_EMAIL_COOLDOWN_KEY_PREFIX . md5( $composite );
		$cooldown_data = get_transient( $cooldown_key );

		if ( is_array( $cooldown_data ) && ! empty( $cooldown_data['expires_at'] ) && $cooldown_data['expires_at'] > $now ) {
			$retry_after = max( 1, (int) $cooldown_data['expires_at'] - $now );
			$this->log_throttle_event( 'submit_email', 'duplicate_email_cooldown', 'ip_email:' . $composite, $retry_after );

			wp_send_json_error(
				array(
					'message'     => 'Please wait before submitting that email again.',
					'retry_after' => $retry_after,
				),
				429
			);
		}

		set_transient(
			$cooldown_key,
			array(
				'expires_at' => $now + $cooldown,
			),
			$cooldown
		);
	}

	/**
	 * Stores lightweight throttle diagnostics for admins.
	 *
	 * @param string $endpoint    Endpoint slug.
	 * @param string $reason      Throttle reason.
	 * @param string $identity    Identity hash source.
	 * @param int    $retry_after Retry-after seconds.
	 *
	 * @return void
	 */
	private function log_throttle_event( $endpoint, $reason, $identity, $retry_after ) {
		$events = get_option( self::THROTTLE_LOG_OPTION_KEY, array() );
		if ( ! is_array( $events ) ) {
			$events = array();
		}

		$bucket_key = sanitize_key( $endpoint . '_' . $reason );
		if ( empty( $events[ $bucket_key ] ) || ! is_array( $events[ $bucket_key ] ) ) {
			$events[ $bucket_key ] = array(
				'count'       => 0,
				'last_seen'   => 0,
				'last_retry'  => 0,
				'last_source' => '',
			);
		}

		$events[ $bucket_key ]['count']       = absint( $events[ $bucket_key ]['count'] ) + 1;
		$events[ $bucket_key ]['last_seen']   = time();
		$events[ $bucket_key ]['last_retry']  = absint( $retry_after );
		$events[ $bucket_key ]['last_source'] = wp_hash( $identity );

		update_option( self::THROTTLE_LOG_OPTION_KEY, $events, false );

		do_action( 'cmlc_throttle_triggered', $endpoint, $reason, $retry_after, $identity );
	}
}
