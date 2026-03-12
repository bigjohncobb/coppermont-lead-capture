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
	 * Enqueues assets and campaign config.
	 *
	 * @param int $campaign_id Preferred campaign ID.
	 * @return array<string,mixed>|null
	 */
	public function enqueue() {
		self::enqueue_assets( 0 );
	}

	/**
	 * Enqueues campaign-specific assets.
	 *
	 * @param int $campaign_id Preferred campaign ID.
	 * @return array<string,mixed>|null
	 */
	public static function enqueue_assets( $campaign_id = 0 ) {
		$campaign = CMLC_Campaigns::resolve_campaign( $campaign_id );
		if ( ! $campaign ) {
			return null;
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

		return $campaign;
	}

	/**
	 * Renders infobar in footer.
	 *
	 * @return void
	 */
	public function render_infobar() {
		$campaign = self::enqueue_assets( 0 );
		if ( ! $campaign ) {
			return;
		}

		$style = sprintf(
			'--cmlc-bg:%1$s;--cmlc-text:%2$s;--cmlc-btn:%3$s;--cmlc-btn-text:%4$s;--cmlc-opacity:%5$s;max-width:%6$s;',
			esc_attr( $campaign['bg_color'] ),
			esc_attr( $campaign['text_color'] ),
			esc_attr( $campaign['button_color'] ),
			esc_attr( $campaign['button_text_color'] ),
			esc_attr( (string) ( (int) $campaign['opacity'] / 100 ) ),
			esc_attr( explode( 'x', (string) $campaign['dimensions'] )[0] . 'px' )
		);

		$settings = $campaign;
		include CMLC_PATH . 'templates/infobar.php';
	}
}
