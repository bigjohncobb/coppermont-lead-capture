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

		$honeypot = isset( $_POST['website'] ) ? sanitize_text_field( wp_unslash( $_POST['website'] ) ) : '';
		if ( ! empty( $honeypot ) ) {
			wp_send_json_error( array( 'message' => 'Submission rejected.' ), 400 );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Please provide a valid email.' ), 400 );
		}

		$settings = CMLC_Settings::get();

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

		$settings['analytics_submissions'] = absint( $settings['analytics_submissions'] ) + 1;
		update_option( CMLC_Settings::OPTION_KEY, $settings );

		/**
		 * Fires after lead form submission for CRM integrations.
		 */
		do_action( 'cmlc_lead_submitted', $email, $settings );

		wp_send_json_success( array( 'message' => 'Thanks! You are subscribed.' ) );
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
}
