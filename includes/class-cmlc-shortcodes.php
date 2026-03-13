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

		$campaign = CMLC_Renderer::enqueue_assets( (int) $atts['campaign_id'] );
		if ( ! $campaign ) {
			return '';
		}

		$settings          = CMLC_Settings::get();
		$turnstile_enabled = ! empty( $settings['turnstile_enabled'] ) && ! empty( $settings['turnstile_site_key'] );

		$headline    = '' !== $atts['headline'] ? (string) $atts['headline'] : (string) $campaign['headline'];
		$body        = '' !== $atts['body'] ? (string) $atts['body'] : (string) $campaign['body'];
		$button_text = '' !== $atts['button'] ? (string) $atts['button'] : (string) $campaign['button_text'];

		ob_start();
		?>
		<div class="cmlc-shortcode-wrap" data-campaign-id="<?php echo esc_attr( (string) $campaign['id'] ); ?>">
			<div class="cmlc-shortcode-headline"><?php echo esc_html( $headline ); ?></div>
			<div class="cmlc-shortcode-body"><?php echo esc_html( $body ); ?></div>
			<form class="cmlc-shortcode-form" data-cmlc-form>
				<input type="hidden" name="campaign_id" value="<?php echo esc_attr( (string) $campaign['id'] ); ?>">
				<input type="email" name="email" required placeholder="Email address">
				<input type="text" name="cmlc_website" class="cmlc-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
				<input type="hidden" name="cmlc_form_token" value="<?php echo esc_attr( CMLC_Ajax::create_form_timestamp_token() ); ?>">
				<input type="hidden" name="captcha_token" value="">
				<?php if ( $turnstile_enabled ) : ?>
					<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $settings['turnstile_site_key'] ); ?>" data-action="cmlc_submit"></div>
					<input type="hidden" name="turnstile_token" value="">
				<?php endif; ?>
				<button type="submit"><?php echo esc_html( $button_text ); ?></button>
			</form>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}
