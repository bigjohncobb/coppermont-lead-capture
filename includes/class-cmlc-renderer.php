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
	 * Enqueues assets and campaign settings.
	 *
	 * @return void
	 */
	public function enqueue() {
		$campaign = CMLC_Campaigns::get_active_campaign();
		if ( empty( $campaign ) ) {
			return;
		}

		self::enqueue_assets( $campaign );
	}

	/**
	 * Enqueues shared frontend assets.
	 *
	 * @param array<string,mixed> $campaign Campaign settings.
	 * @return void
	 */
	public static function enqueue_assets( $campaign ) {
		wp_enqueue_style( 'cmlc-frontend', CMLC_URL . 'assets/css/frontend.css', array(), CMLC_VERSION );
		wp_enqueue_script( 'cmlc-frontend', CMLC_URL . 'assets/js/frontend.js', array(), CMLC_VERSION, true );

		wp_localize_script(
			'cmlc-frontend',
			'cmlcConfig',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'cmlc_nonce' ),
				'scrollPercent'    => (int) $campaign['scroll_trigger_percent'],
				'timeDelay'        => (int) $campaign['time_delay_seconds'],
				'cooldownHours'    => (int) $campaign['repetition_cooldown_hours'],
				'maxViews'         => (int) $campaign['max_views'],
				'enableExitIntent' => ! empty( $campaign['enable_exit_intent'] ),
				'enableMobile'     => ! empty( $campaign['enable_mobile'] ),
				'campaignId'       => (int) $campaign['campaign_id'],
			)
		);
	}

	/**
	 * Renders infobar in footer.
	 *
	 * @return void
	 */
	public function render_infobar() {
		$settings = CMLC_Campaigns::get_active_campaign();
		if ( empty( $settings ) ) {
			return;
		}

		$style = sprintf(
			'--cmlc-bg:%1$s;--cmlc-text:%2$s;--cmlc-btn:%3$s;--cmlc-btn-text:%4$s;--cmlc-opacity:%5$s;--cmlc-width:%6$s;--cmlc-height:%7$s;',
			esc_attr( $settings['bg_color'] ),
			esc_attr( $settings['text_color'] ),
			esc_attr( $settings['button_color'] ),
			esc_attr( $settings['button_text_color'] ),
			esc_attr( (string) $settings['opacity'] ),
			esc_attr( (string) $settings['dimensions_width'] ),
			esc_attr( (string) $settings['dimensions_height'] )
		);

		include CMLC_PATH . 'templates/infobar.php';
	}

	/**
	 * Evaluates page eligibility, schedule and referrer.
	 *
	 * @param array<string,mixed> $settings Campaign settings.
	 * @return bool
	 */
	public static function is_eligible_page( $settings ) {
		if ( is_admin() || empty( $settings['enabled'] ) ) {
			return false;
		}

		if ( ! self::is_within_schedule( $settings ) ) {
			return false;
		}

		if ( ! self::passes_referrer_rules( $settings ) ) {
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
	 * @param array<string,mixed> $settings Campaign settings.
	 * @return bool
	 */
	private static function is_within_schedule( $settings ) {
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
	 * @param array<string,mixed> $settings Campaign settings.
	 * @return bool
	 */
	private static function passes_referrer_rules( $settings ) {
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
