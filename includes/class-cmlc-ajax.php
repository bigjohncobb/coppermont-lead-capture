<?php
/**
 * AJAX endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Ajax {
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

		if ( $this->is_bot_submission() ) {
			wp_send_json_error( array( 'message' => self::BOT_FAILURE_MESSAGE ), 400 );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Please provide a valid email.' ), 400 );
		}

		$settings                          = CMLC_Settings::get();

		if ( ! empty( $settings['enable_captcha_validation'] ) && ! $this->passes_captcha_validation( $settings ) ) {
			wp_send_json_error( array( 'message' => self::BOT_FAILURE_MESSAGE ), 400 );
		}

		$settings['analytics_submissions'] = absint( $settings['analytics_submissions'] ) + 1;
		update_option( CMLC_Settings::OPTION_KEY, $settings );

		/**
		 * Fires after lead form submission for CRM integrations.
		 */
		do_action( 'cmlc_lead_submitted', $email, $settings );

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
}
