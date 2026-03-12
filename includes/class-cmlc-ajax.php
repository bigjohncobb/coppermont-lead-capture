<?php
/**
 * AJAX endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Ajax {
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

		$ip_address  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
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

		$settings['analytics_impressions']                 = absint( $settings['analytics_impressions'] ) + 1;
		$settings['analytics_daily_impressions']           = $this->increment_daily_counter( 'impressions' );
		$settings['analytics_daily_last_rollover_date']    = gmdate( 'Y-m-d' );
		update_option( CMLC_Settings::OPTION_KEY, $settings );

		wp_send_json_success(
			array(
				'impressions'      => $settings['analytics_impressions'],
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

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Please provide a valid email.' ), 400 );
		}

		$settings                                = CMLC_Settings::get();
		$settings['analytics_submissions']       = absint( $settings['analytics_submissions'] ) + 1;
		$settings['analytics_daily_submissions'] = $this->increment_daily_counter( 'submissions' );
		$settings['analytics_daily_last_rollover_date'] = gmdate( 'Y-m-d' );
		update_option( CMLC_Settings::OPTION_KEY, $settings );

		/**
		 * Fires after lead form submission for CRM integrations.
		 */
		do_action( 'cmlc_lead_submitted', $email, $settings );

		wp_send_json_success( array( 'message' => 'Thanks! You are subscribed.' ) );
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
