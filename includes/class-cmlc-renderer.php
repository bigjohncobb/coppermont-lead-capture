<?php
/**
 * Front-end rendering and targeting.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Renderer {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render_infobar' ) );
	}

	/**
	 * Enqueues assets and settings.
	 *
	 * @return void
	 */
	public function enqueue() {
		$settings = CMLC_Settings::get();

		if ( empty( $settings['enabled'] ) || ! $this->is_eligible_page( $settings ) ) {
			return;
		}

		wp_enqueue_style( 'cmlc-frontend', CMLC_URL . 'assets/css/frontend.css', array(), CMLC_VERSION );
		wp_enqueue_script( 'cmlc-frontend', CMLC_URL . 'assets/js/frontend.js', array(), CMLC_VERSION, true );

		wp_localize_script(
			'cmlc-frontend',
			'cmlcConfig',
			array(
				'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
				'nonce'                 => wp_create_nonce( 'cmlc_nonce' ),
				'scrollPercent'         => (int) $settings['scroll_trigger_percent'],
				'timeDelay'             => (int) $settings['time_delay_seconds'],
				'cooldownHours'         => (int) $settings['repetition_cooldown_hours'],
				'maxViews'              => (int) $settings['max_views'],
				'enableExitIntent'      => ! empty( $settings['enable_exit_intent'] ),
				'enableMobile'          => ! empty( $settings['enable_mobile'] ),
				'enableCaptcha'         => ! empty( $settings['enable_captcha_validation'] ),
			)
		);
	}

	/**
	 * Renders infobar in footer.
	 *
	 * @return void
	 */
	public function render_infobar() {
		$settings = CMLC_Settings::get();

		if ( empty( $settings['enabled'] ) || ! $this->is_eligible_page( $settings ) ) {
			return;
		}

		$style = sprintf(
			'--cmlc-bg:%1$s;--cmlc-text:%2$s;--cmlc-btn:%3$s;--cmlc-btn-text:%4$s;',
			esc_attr( $settings['bg_color'] ),
			esc_attr( $settings['text_color'] ),
			esc_attr( $settings['button_color'] ),
			esc_attr( $settings['button_text_color'] )
		);

		include CMLC_PATH . 'templates/infobar.php';
	}

	/**
	 * Evaluates page eligibility, schedule and referrer.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return bool
	 */
	private function is_eligible_page( $settings ) {
		if ( is_admin() ) {
			return false;
		}

		if ( ! $this->is_within_schedule( $settings ) ) {
			return false;
		}

		if ( ! $this->passes_referrer_rules( $settings ) ) {
			return false;
		}

		$post_id = get_queried_object_id();
		$ids     = array_filter( array_map( 'absint', array_map( 'trim', explode( ',', (string) $settings['page_ids'] ) ) ) );

		if ( 'include' === $settings['page_target_mode'] ) {
			return in_array( $post_id, $ids, true );
		}

		if ( 'exclude' === $settings['page_target_mode'] ) {
			return ! in_array( $post_id, $ids, true );
		}

		return true;
	}

	/**
	 * Checks scheduler window.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return bool
	 */
	private function is_within_schedule( $settings ) {
		$timezone = wp_timezone();
		$now      = new DateTimeImmutable( 'now', $timezone );

		if ( ! empty( $settings['schedule_start'] ) ) {
			$start = date_create_immutable( (string) $settings['schedule_start'], $timezone );
			if ( $start && $now < $start ) {
				return false;
			}
		}

		if ( ! empty( $settings['schedule_end'] ) ) {
			$end = date_create_immutable( (string) $settings['schedule_end'], $timezone );
			if ( $end && $now > $end ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Applies referral detection rules.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return bool
	 */
	private function passes_referrer_rules( $settings ) {
		$allowed = array_filter( array_map( 'trim', explode( ',', (string) $settings['allowed_referrers'] ) ) );
		if ( empty( $allowed ) ) {
			return true;
		}

		$referrer = wp_get_referer();
		if ( empty( $referrer ) ) {
			return false;
		}

		$host = wp_parse_url( $referrer, PHP_URL_HOST );
		if ( empty( $host ) ) {
			return false;
		}

		foreach ( $allowed as $domain ) {
			if ( false !== stripos( $host, $domain ) ) {
				return true;
			}
		}

		return false;
	}
}
