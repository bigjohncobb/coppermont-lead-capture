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
	 * Minimum number of seconds expected before a human submission.
	 *
	 * @var int
	 */
	const MIN_FILL_SECONDS = 3;

	/**
	 * Generic message for anti-automation failures.
	 *
	 * @var string
	 */
	const BOT_FAILURE_MESSAGE = 'Unable to process your request.';

	/**
	 * Honeypot field name.
	 *
	 * @var string
	 */
	const HONEYPOT_FIELD = 'cmlc_website';

	/**
	 * Form timestamp token field name.
	 *
	 * @var string
	 */
	const TIMESTAMP_FIELD = 'cmlc_form_token';

	/**
	 * Dedupe window in seconds.
	 */
	const DEDUPE_WINDOW_SECONDS = 90;

	/**
	 * Burst threshold within dedupe window.
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

		$settings      = CMLC_Settings::get();
		$page_location = isset( $_POST['pageLocation'] ) ? esc_url_raw( wp_unslash( $_POST['pageLocation'] ) ) : '';
		$context_token = isset( $_POST['contextToken'] ) ? sanitize_text_field( wp_unslash( $_POST['contextToken'] ) ) : '';
		$campaign_hash = CMLC_Security::campaign_hash( $settings );
		$page_hash     = CMLC_Security::page_hash( $page_location );

		if ( empty( $page_hash ) || ! CMLC_Security::verify_context_token( $context_token, $campaign_hash, $page_hash ) ) {
			$this->increment_suspected_bot_counter( $settings );
			wp_send_json_success( array( 'ignored' => true ) );
		}

		$ip_address = $this->get_client_ip();
		if ( ! $this->is_rate_limit_allowlisted( 'track_impression', '', $ip_address ) ) {
			$this->assert_rate_limit( 'track_impression', 'ip_minute', $ip_address, 60, 120 );
			$this->assert_rate_limit( 'track_impression', 'ip_hour', $ip_address, HOUR_IN_SECONDS, 2000 );
		}

		$user_agent  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : 'unknown';
		$window_slot = (string) floor( time() / self::DEDUPE_WINDOW_SECONDS );
		$dedupe_key  = hash( 'sha256', implode( '|', array( $ip_address, $user_agent, $campaign_hash, $window_slot ) ) );
		$dedupe_id   = 'cmlc_imp_' . $dedupe_key;
		$burst_key   = 'cmlc_burst_' . $dedupe_key;
		$burst_count = (int) get_transient( $burst_key );

		if ( $burst_count >= self::BURST_THRESHOLD ) {
			$this->increment_suspected_bot_counter( $settings );
			wp_send_json_success( array( 'ignored' => true ) );
		}

		set_transient( $burst_key, $burst_count + 1, self::DEDUPE_WINDOW_SECONDS );

		if ( get_transient( $dedupe_id ) ) {
			wp_send_json_success( array( 'deduped' => true ) );
		}

		set_transient( $dedupe_id, 1, self::DEDUPE_WINDOW_SECONDS );

		$page_id       = isset( $_POST['page_id'] ) ? absint( wp_unslash( $_POST['page_id'] ) ) : 0;
		$campaign_id   = isset( $_POST['campaign_id'] ) ? absint( wp_unslash( $_POST['campaign_id'] ) ) : 0;
		$referrer_host = isset( $_POST['referrer_host'] ) ? sanitize_text_field( wp_unslash( $_POST['referrer_host'] ) ) : '';

		$campaign = CMLC_Campaigns::resolve_campaign( $campaign_id );
		if ( ! $campaign ) {
			wp_send_json_error( array( 'message' => 'Campaign not found.' ), 404 );
		}

		$resolved_campaign_id = (int) $campaign['id'];

		CMLC_Analytics::record_event(
			'impression',
			array(
				'page_id'       => $page_id,
				'campaign_id'   => $resolved_campaign_id,
				'referrer_host' => $referrer_host,
			)
		);

		// Legacy counters are retained for backward compatibility.
		$settings['analytics_impressions']              = absint( $settings['analytics_impressions'] ) + 1;
		$settings['analytics_daily_impressions']        = $this->increment_daily_counter( 'impressions' );
		$settings['analytics_daily_last_rollover_date'] = gmdate( 'Y-m-d' );
		update_option( CMLC_Settings::OPTION_KEY, $settings );

		$totals = CMLC_Analytics::get_totals();
		wp_send_json_success(
			array(
				'campaign_id'      => $resolved_campaign_id,
				'impressions'      => $totals['impressions'],
				'dailyImpressions' => $settings['analytics_daily_impressions'],
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

		if ( $this->is_bot_submission() ) {
			wp_send_json_error( array( 'message' => self::BOT_FAILURE_MESSAGE ), 400 );
		}

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

		$page_id       = isset( $_POST['page_id'] ) ? absint( wp_unslash( $_POST['page_id'] ) ) : 0;
		$campaign_id   = isset( $_POST['campaign_id'] ) ? absint( wp_unslash( $_POST['campaign_id'] ) ) : 0;
		$referrer_host = isset( $_POST['referrer_host'] ) ? sanitize_text_field( wp_unslash( $_POST['referrer_host'] ) ) : '';

		$campaign = CMLC_Campaigns::resolve_campaign( $campaign_id );
		if ( ! $campaign ) {
			wp_send_json_error( array( 'message' => 'Campaign not found.' ), 404 );
		}

		$resolved_campaign_id = (int) $campaign['id'];

		$settings = CMLC_Settings::get();

		if ( ! empty( $settings['enable_captcha_validation'] ) && ! $this->passes_captcha_validation( $settings ) ) {
			wp_send_json_error( array( 'message' => self::BOT_FAILURE_MESSAGE ), 400 );
		}

		if ( ! empty( $settings['turnstile_enabled'] ) ) {
			$token = isset( $_POST['turnstile_token'] ) ? sanitize_text_field( wp_unslash( $_POST['turnstile_token'] ) ) : '';
			if ( empty( $token ) ) {
				$token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
			}

			if ( empty( $token ) ) {
				wp_send_json_error( array( 'message' => 'Captcha verification is required.' ), 400 );
			}

			$verification_result = $this->verify_turnstile_token( $token, $settings );
			if ( is_wp_error( $verification_result ) ) {
				$status = 'strict_mode' === $verification_result->get_error_code() ? 403 : 400;
				wp_send_json_error( array( 'message' => $verification_result->get_error_message() ), $status );
			}
		}

		CMLC_Analytics::record_event(
			'submission',
			array(
				'page_id'       => $page_id,
				'campaign_id'   => $resolved_campaign_id,
				'referrer_host' => $referrer_host,
			)
		);

		CMLC_Analytics::record_lead( $resolved_campaign_id, $email );

		// Also persist in dedicated leads table with enriched metadata.
		$source      = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : 'infobar';
		$metadata    = array(
			'page_url'   => isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '',
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'ip'         => $ip_address,
			'page_id'    => $page_id,
		);

		CMLC_Leads::insert_lead( $email, $source, (string) $resolved_campaign_id, $metadata );

		// Legacy counters are retained for backward compatibility.
		$settings['analytics_submissions']              = absint( $settings['analytics_submissions'] ) + 1;
		$settings['analytics_daily_submissions']        = $this->increment_daily_counter( 'submissions' );
		$settings['analytics_daily_last_rollover_date'] = gmdate( 'Y-m-d' );
		update_option( CMLC_Settings::OPTION_KEY, $settings );

		/**
		 * Fires after lead form submission for CRM integrations.
		 */
		do_action( 'cmlc_lead_submitted', $email, $campaign );

		wp_send_json_success( array( 'message' => 'Thanks! You are subscribed.' ) );
	}

	/**
	 * Builds a signed form timestamp token.
	 *
	 * @return string
	 */
	public static function create_form_timestamp_token() {
		$timestamp = time();
		$signature = hash_hmac( 'sha256', (string) $timestamp, wp_salt( 'nonce' ) );

		return $timestamp . ':' . $signature;
	}

	/**
	 * Detects likely bot submissions.
	 *
	 * @return bool
	 */
	private function is_bot_submission() {
		$honeypot = isset( $_POST[ self::HONEYPOT_FIELD ] ) ? trim( (string) wp_unslash( $_POST[ self::HONEYPOT_FIELD ] ) ) : '';
		if ( '' !== $honeypot ) {
			return true;
		}

		$token = isset( $_POST[ self::TIMESTAMP_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::TIMESTAMP_FIELD ] ) ) : '';
		if ( ! $this->is_valid_timestamp_token( $token ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Validates timestamp token signature and minimum fill time.
	 *
	 * @param string $token Token from form.
	 * @return bool
	 */
	private function is_valid_timestamp_token( $token ) {
		if ( empty( $token ) || false === strpos( $token, ':' ) ) {
			return false;
		}

		list( $timestamp_raw, $signature ) = explode( ':', $token, 2 );
		$timestamp = absint( $timestamp_raw );

		if ( empty( $timestamp ) || empty( $signature ) ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', (string) $timestamp, wp_salt( 'nonce' ) );
		if ( ! hash_equals( $expected, $signature ) ) {
			return false;
		}

		$elapsed = time() - $timestamp;
		if ( $elapsed < self::MIN_FILL_SECONDS || $elapsed > DAY_IN_SECONDS ) {
			return false;
		}

		return true;
	}

	/**
	 * Verifies a Turnstile token.
	 *
	 * @param string              $token Turnstile response token.
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return true|WP_Error
	 */
	private function verify_turnstile_token( $token, $settings ) {
		$secret = isset( $settings['turnstile_secret_key'] ) ? trim( (string) $settings['turnstile_secret_key'] ) : '';
		if ( empty( $secret ) ) {
			return new WP_Error( 'turnstile_config', 'Turnstile secret key is not configured.' );
		}

		$request = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 8,
				'body'    => array(
					'secret'   => $secret,
					'response' => $token,
					'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				),
			)
		);

		if ( is_wp_error( $request ) ) {
			if ( ! empty( $settings['turnstile_strict_mode'] ) ) {
				return new WP_Error( 'strict_mode', 'Turnstile verification service is unavailable. Submission blocked by strict mode.' );
			}

			return true;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $request );
		$body        = json_decode( (string) wp_remote_retrieve_body( $request ), true );
		if ( 200 !== $status_code || ! is_array( $body ) ) {
			if ( ! empty( $settings['turnstile_strict_mode'] ) ) {
				return new WP_Error( 'strict_mode', 'Turnstile verification did not return a valid response. Submission blocked by strict mode.' );
			}

			return true;
		}

		if ( empty( $body['success'] ) ) {
			return new WP_Error( 'turnstile_failed', 'Captcha verification failed. Please try again.' );
		}

		$expected_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! empty( $body['hostname'] ) && ! empty( $expected_host ) && ! hash_equals( strtolower( (string) $expected_host ), strtolower( (string) $body['hostname'] ) ) ) {
			return new WP_Error( 'turnstile_failed', 'Captcha verification failed hostname validation.' );
		}

		if ( ! empty( $body['action'] ) && 'cmlc_submit' !== (string) $body['action'] ) {
			return new WP_Error( 'turnstile_failed', 'Captcha verification failed action validation.' );
		}

		return true;
	}

	/**
	 * Runs pluggable CAPTCHA validation.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return bool
	 */
	private function passes_captcha_validation( $settings ) {
		$captcha_token = isset( $_POST['captcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['captcha_token'] ) ) : '';

		/**
		 * Filters CAPTCHA verification result.
		 *
		 * Return true when the configured provider token is valid.
		 * Compatible with reCAPTCHA/hCaptcha integrations.
		 *
		 * @param bool               $is_valid      Whether the CAPTCHA is valid.
		 * @param string             $captcha_token Provider token from request.
		 * @param array<string,mixed> $settings     Plugin settings.
		 * @param array<string,mixed> $request      Request payload.
		 */
		return (bool) apply_filters( 'cmlc_validate_captcha', false, $captcha_token, $settings, $_POST );
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

	/**
	 * Increments per-day counter in separate option store.
	 *
	 * @param string $metric Metric name.
	 * @return int
	 */
	private function increment_daily_counter( $metric ) {
		$today    = gmdate( 'Y-m-d' );
		$option   = get_option( 'cmlc_daily_counters', array() );
		$counters = is_array( $option ) ? $option : array();

		if ( empty( $counters[ $today ] ) || ! is_array( $counters[ $today ] ) ) {
			$counters[ $today ] = array(
				'impressions'   => 0,
				'submissions'   => 0,
				'suspected_bot' => 0,
			);
		}

		$counters[ $today ][ $metric ] = absint( $counters[ $today ][ $metric ] ) + 1;

		// Keep the latest 30 days for anomaly review.
		if ( count( $counters ) > 30 ) {
			ksort( $counters );
			$counters = array_slice( $counters, -30, null, true );
		}

		update_option( 'cmlc_daily_counters', $counters, false );

		return (int) $counters[ $today ][ $metric ];
	}

	/**
	 * Increments suspected bot counters in totals + daily store.
	 *
	 * @param array<string,mixed> $settings Current settings.
	 * @return void
	 */
	private function increment_suspected_bot_counter( $settings ) {
		$settings['analytics_suspected_bot_traffic'] = absint( $settings['analytics_suspected_bot_traffic'] ) + 1;
		$settings['analytics_daily_suspected_bot']   = $this->increment_daily_counter( 'suspected_bot' );
		$settings['analytics_daily_last_rollover_date'] = gmdate( 'Y-m-d' );
		update_option( CMLC_Settings::OPTION_KEY, $settings );
	}
}
