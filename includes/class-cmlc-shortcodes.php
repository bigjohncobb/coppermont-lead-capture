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

		$campaign = ! empty( $atts['id'] ) ? CMLC_Campaigns::get_campaign( absint( $atts['id'] ) ) : CMLC_Campaigns::get_active_campaign();
		if ( empty( $campaign ) ) {
			return '';
		}

		CMLC_Renderer::enqueue_assets( $campaign );

		$settings = $campaign;
		if ( ! empty( $atts['headline'] ) ) {
			$settings['headline'] = sanitize_text_field( $atts['headline'] );
		}
		if ( ! empty( $atts['body'] ) ) {
			$settings['body'] = sanitize_text_field( $atts['body'] );
		}
		if ( ! empty( $atts['button'] ) ) {
			$settings['button_text'] = sanitize_text_field( $atts['button'] );
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

		ob_start();
		include CMLC_PATH . 'templates/infobar.php';
		return (string) ob_get_clean();
	}
}
