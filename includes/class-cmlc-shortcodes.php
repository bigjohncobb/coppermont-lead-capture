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
				'id'       => 0,
				'headline' => '',
				'body'     => '',
				'button'   => '',
			),
			$atts,
			'coppermont_infobar'
		);

		$campaign = null;
		if ( ! empty( $atts['id'] ) ) {
			$campaign = CMLC_Campaigns::get_campaign( absint( $atts['id'] ) );
		}
		if ( ! $campaign ) {
			$campaigns = CMLC_Campaigns::get_all_campaigns();
			foreach ( $campaigns as $candidate ) {
				if ( ! empty( $candidate['enabled'] ) ) {
					$campaign = $candidate;
					break;
				}
			}
		}
		if ( ! $campaign ) {
			return '';
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

		$atts['headline'] = '' !== $atts['headline'] ? $atts['headline'] : $campaign['headline'];
		$atts['body']     = '' !== $atts['body'] ? $atts['body'] : $campaign['body'];
		$atts['button']   = '' !== $atts['button'] ? $atts['button'] : $campaign['button_text'];

		ob_start();
		?>
		<div class="cmlc-shortcode-wrap" data-campaign-id="<?php echo esc_attr( (string) $campaign['id'] ); ?>">
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
