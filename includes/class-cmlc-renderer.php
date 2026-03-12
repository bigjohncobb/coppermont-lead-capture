<?php
/**
 * Front-end rendering and targeting.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Renderer {
	/**
	 * Active campaign for current request.
	 *
	 * @var array<string,mixed>|null
	 */
	private $active_campaign = null;

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
		$campaign = $this->get_active_campaign();
		if ( ! $campaign ) {
			return;
		}

		wp_enqueue_style( 'cmlc-frontend', CMLC_URL . 'assets/css/frontend.css', array(), CMLC_VERSION );
		wp_enqueue_script( 'cmlc-frontend', CMLC_URL . 'assets/js/frontend.js', array(), CMLC_VERSION, true );

		wp_localize_script(
			'cmlc-frontend',
			'cmlcConfig',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'cmlc_nonce' ),
				'campaignId'       => (int) $campaign['id'],
				'scrollPercent'    => (int) $campaign['scroll_trigger_percent'],
				'timeDelay'        => (int) $campaign['time_delay_seconds'],
				'cooldownHours'    => (int) $campaign['repetition_cooldown_hours'],
				'maxViews'         => (int) $campaign['max_views'],
				'enableExitIntent' => ! empty( $campaign['enable_exit_intent'] ),
				'enableMobile'     => ! empty( $campaign['enable_mobile'] ),
			)
		);
	}

	/**
	 * Renders infobar in footer.
	 *
	 * @return void
	 */
	public function render_infobar() {
		$campaign = $this->get_active_campaign();
		if ( ! $campaign ) {
			return;
		}

		$style = sprintf(
			'--cmlc-bg:%1$s;--cmlc-text:%2$s;--cmlc-btn:%3$s;--cmlc-btn-text:%4$s;--cmlc-width:%5$s%%;--cmlc-opacity:%6$s;',
			esc_attr( $campaign['bg_color'] ),
			esc_attr( $campaign['text_color'] ),
			esc_attr( $campaign['button_color'] ),
			esc_attr( $campaign['button_text_color'] ),
			esc_attr( (string) $campaign['bar_width'] ),
			esc_attr( (string) ( (float) $campaign['bar_opacity'] / 100 ) )
		);

		$settings = $campaign;
		include CMLC_PATH . 'templates/infobar.php';
	}

	/**
	 * Gets highest-priority active campaign.
	 *
	 * @return array<string,mixed>|null
	 */
	public function get_active_campaign() {
		if ( null !== $this->active_campaign ) {
			return $this->active_campaign;
		}

		foreach ( CMLC_Campaigns::get_all_campaigns() as $campaign ) {
			if ( empty( $campaign['enabled'] ) ) {
				continue;
			}
			if ( ! $this->is_eligible_page( $campaign ) ) {
				continue;
			}
			$this->active_campaign = $campaign;
			return $this->active_campaign;
		}

		return null;
	}

	/**
	 * Evaluates page eligibility, schedule and referrer.
	 *
	 * @param array<string,mixed> $settings Campaign settings.
	 * @return bool
	 */
	public function is_eligible_page( $settings ) {
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
	 * @param array<string,mixed> $settings Campaign settings.
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
	 * @param array<string,mixed> $settings Campaign settings.
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
