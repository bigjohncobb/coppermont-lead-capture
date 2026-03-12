<?php
/**
 * Shortcode registration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Shortcodes {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'coppermont_infobar', array( $this, 'render_infobar_shortcode' ) );
	}

	/**
	 * Renders infobar shortcode.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_infobar_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'campaign_id' => 0,
				'headline'    => '',
				'body'        => '',
				'button'      => '',
			),
			$atts,
			'coppermont_infobar'
		);

		$settings = CMLC_Campaigns::resolve_campaign( absint( $atts['campaign_id'] ) );

		wp_enqueue_style( 'cmlc-frontend', CMLC_URL . 'assets/css/frontend.css', array(), CMLC_VERSION );
		wp_enqueue_script( 'cmlc-frontend', CMLC_URL . 'assets/js/frontend.js', array(), CMLC_VERSION, true );
		wp_localize_script(
			'cmlc-frontend',
			'cmlcConfig',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'cmlc_nonce' ),
				'scrollPercent'    => (int) $settings['scroll_trigger_percent'],
				'timeDelay'        => (int) $settings['time_delay_seconds'],
				'cooldownHours'    => (int) $settings['repetition_cooldown_hours'],
				'maxViews'         => (int) $settings['max_views'],
				'enableExitIntent' => ! empty( $settings['enable_exit_intent'] ),
				'enableMobile'     => ! empty( $settings['enable_mobile'] ),
				'campaignId'       => (int) $settings['campaign_id'],
			)
		);
		$atts['headline'] = $atts['headline'] ? $atts['headline'] : $settings['headline'];
		$atts['body']     = $atts['body'] ? $atts['body'] : $settings['body'];
		$atts['button']   = $atts['button'] ? $atts['button'] : $settings['button_text'];

		ob_start();
		?>
		<div class="cmlc-shortcode-wrap" data-campaign-id="<?php echo esc_attr( (string) $settings['campaign_id'] ); ?>">
			<div class="cmlc-shortcode-headline"><?php echo esc_html( $atts['headline'] ); ?></div>
			<div class="cmlc-shortcode-body"><?php echo esc_html( $atts['body'] ); ?></div>
			<form class="cmlc-shortcode-form" data-cmlc-form>
				<input type="email" name="email" required placeholder="Email address">
				<button type="submit"><?php echo esc_html( $atts['button'] ); ?></button>
			</form>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}
