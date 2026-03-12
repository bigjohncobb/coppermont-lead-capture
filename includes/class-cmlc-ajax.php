<?php
/**
 * AJAX endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Ajax {
	/**
	 * Option key for throttle diagnostics.
	 */
	const THROTTLE_DIAGNOSTICS_OPTION = 'cmlc_throttle_diagnostics';

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

		$ip_address = $this->get_client_ip();
		if ( ! $this->is_rate_limit_allowlisted( 'track_impression', '', $ip_address ) ) {
			$this->assert_rate_limit( 'track_impression', 'ip_minute', $ip_address, 60, 120 );
			$this->assert_rate_limit( 'track_impression', 'ip_hour', $ip_address, HOUR_IN_SECONDS, 2000 );
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

		check_ajax_referer( 'cmlc_nonce', 'nonce' );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Please provide a valid email.' ), 400 );
		}

		$ip_address = $this->get_client_ip();

		if ( ! $this->is_rate_limit_allowlisted( 'submit_email', $email, $ip_address ) ) {
			$this->assert_rate_limit( 'submit_email', 'ip_minute', $ip_address, 60, 20 );
			$this->assert_rate_limit( 'submit_email', 'ip_hour', $ip_address, HOUR_IN_SECONDS, 120 );
			$this->assert_rate_limit( 'submit_email', 'email_minute', $email, 60, 6 );
			$this->assert_rate_limit( 'submit_email', 'email_hour', $email, HOUR_IN_SECONDS, 20 );
			$this->assert_identical_email_cooldown( $email, $ip_address );
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
	 * Checks if a request should bypass throttling.
	 *
	 * @param string $action     AJAX action context.
	 * @param string $email      Email, when available.
	 * @param string $ip_address Client IP address.
	 *
	 * @return bool
	 */
	private function is_rate_limit_allowlisted( $action, $email, $ip_address ) {
		$allowlisted = apply_filters( 'cmlc_rate_limit_allowlisted', false, $action, $email, $ip_address );

		return (bool) $allowlisted;
	}

	/**
	 * Enforces a single rate limit bucket.
	 *
	 * @param string $action         AJAX action context.
	 * @param string $bucket         Bucket name.
	 * @param string $identifier     Bucket identifier (IP/email).
	 * @param int    $window_seconds Sliding window length in seconds.
	 * @param int    $default_limit  Default max requests in window.
	 *
	 * @return void
	 */
	private function assert_rate_limit( $action, $bucket, $identifier, $window_seconds, $default_limit ) {
		$limit = (int) apply_filters( 'cmlc_rate_limit_max_requests', $default_limit, $action, $bucket, $identifier, $window_seconds );
		if ( $limit <= 0 ) {
			return;
		}

		$key         = $this->build_throttle_key( $action, $bucket, $identifier );
		$current     = (int) get_transient( $key );
		$current    += 1;
		$is_limited  = $current > $limit;

		set_transient( $key, $current, $window_seconds );

		if ( $is_limited ) {
			$this->record_throttle_event(
				$action,
				$bucket,
				$identifier,
				array(
					'limit'   => $limit,
					'window'  => $window_seconds,
					'current' => $current,
				)
			);
			wp_send_json_error( array( 'message' => 'Too many requests. Please try again later.' ), 429 );
		}
	}

	/**
	 * Blocks repeated identical submissions for a short cooldown.
	 *
	 * @param string $email      Submitted email.
	 * @param string $ip_address Client IP address.
	 *
	 * @return void
	 */
	private function assert_identical_email_cooldown( $email, $ip_address ) {
		$cooldown = (int) apply_filters( 'cmlc_submit_email_identical_cooldown_seconds', 15, $email, $ip_address );
		if ( $cooldown <= 0 ) {
			return;
		}

		$key = $this->build_throttle_key( 'submit_email', 'identical', $email . '|' . $ip_address );
		if ( get_transient( $key ) ) {
			$this->record_throttle_event(
				'submit_email',
				'identical_cooldown',
				$email,
				array(
					'cooldown' => $cooldown,
				)
			);
			wp_send_json_error( array( 'message' => 'Please wait before submitting the same email again.' ), 429 );
		}

		set_transient( $key, 1, $cooldown );
	}

	/**
	 * Builds a short transient key for a throttle bucket.
	 *
	 * @param string $action     AJAX action context.
	 * @param string $bucket     Bucket name.
	 * @param string $identifier Bucket identifier.
	 *
	 * @return string
	 */
	private function build_throttle_key( $action, $bucket, $identifier ) {
		$normalized = strtolower( trim( (string) $identifier ) );
		if ( '' === $normalized ) {
			$normalized = 'unknown';
		}

		return 'cmlc_rl_' . md5( $action . '|' . $bucket . '|' . $normalized );
	}

	/**
	 * Attempts to resolve the client IP.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * Filters detected client IP for reverse proxy setups.
		 */
		return (string) apply_filters( 'cmlc_rate_limit_client_ip', $ip );
	}

	/**
	 * Records throttling diagnostics for administrators.
	 *
	 * @param string $action     AJAX action context.
	 * @param string $bucket     Bucket name.
	 * @param string $identifier Identifier being throttled.
	 * @param array  $details    Additional details.
	 *
	 * @return void
	 */
	private function record_throttle_event( $action, $bucket, $identifier, $details = array() ) {
		$diagnostics = get_option( self::THROTTLE_DIAGNOSTICS_OPTION, array() );
		if ( ! is_array( $diagnostics ) ) {
			$diagnostics = array();
		}

		if ( empty( $diagnostics['counters'] ) || ! is_array( $diagnostics['counters'] ) ) {
			$diagnostics['counters'] = array();
		}

		$counter_key = $action . ':' . $bucket;
		if ( empty( $diagnostics['counters'][ $counter_key ] ) ) {
			$diagnostics['counters'][ $counter_key ] = 0;
		}
		$diagnostics['counters'][ $counter_key ] += 1;

		$max_events = (int) apply_filters( 'cmlc_rate_limit_diagnostics_events_max', 25 );
		$event      = array(
			'time'       => current_time( 'mysql' ),
			'action'     => $action,
			'bucket'     => $bucket,
			'identifier' => substr( md5( strtolower( trim( (string) $identifier ) ) ), 0, 12 ),
			'details'    => $details,
		);

		if ( empty( $diagnostics['events'] ) || ! is_array( $diagnostics['events'] ) ) {
			$diagnostics['events'] = array();
		}
		$diagnostics['events'][] = $event;

		if ( $max_events > 0 && count( $diagnostics['events'] ) > $max_events ) {
			$diagnostics['events'] = array_slice( $diagnostics['events'], -1 * $max_events );
		}

		update_option( self::THROTTLE_DIAGNOSTICS_OPTION, $diagnostics, false );
	}
}
