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

		$turnstile_enabled = ! empty( $settings['turnstile_enabled'] ) && ! empty( $settings['turnstile_site_key'] );
		if ( $turnstile_enabled ) {
			wp_enqueue_script( 'cmlc-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), CMLC_VERSION, true );
		}

		wp_localize_script(
			'cmlc-frontend',
			'cmlcConfig',
			array(
				'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
				'nonce'                 => wp_create_nonce( 'cmlc_nonce' ),
				'pageId'                => get_queried_object_id(),
				'campaignId'            => 1,
				'scrollPercent'         => (int) $settings['scroll_trigger_percent'],
				'timeDelay'             => (int) $settings['time_delay_seconds'],
				'cooldownHours'         => (int) $settings['repetition_cooldown_hours'],
				'maxViews'              => (int) $settings['max_views'],
				'enableExitIntent'      => ! empty( $settings['enable_exit_intent'] ),
				'enableMobile'          => ! empty( $settings['enable_mobile'] ),
				'enableCaptcha'         => ! empty( $settings['enable_captcha_validation'] ),
				'turnstileEnabled'      => $turnstile_enabled,
				'turnstileSiteKey'      => $turnstile_enabled ? (string) $settings['turnstile_site_key'] : '',
				'turnstileResponseField'=> 'cf-turnstile-response',
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

		$opacity = isset( $settings['bar_opacity'] ) ? (float) $settings['bar_opacity'] : 1;
		$opacity = max( 0, min( 1, $opacity ) );
		$bg_rgb  = $this->hex_to_rgb_string( (string) $settings['bg_color'] );

		$style = sprintf(
			'--cmlc-bg:%1$s;--cmlc-bg-rgb:%2$s;--cmlc-opacity:%3$s;--cmlc-text:%4$s;--cmlc-btn:%5$s;--cmlc-btn-text:%6$s;--cmlc-width:%7$s;--cmlc-height:%8$s;',
			esc_attr( $settings['bg_color'] ),
			esc_attr( $bg_rgb ),
			esc_attr( (string) $opacity ),
			esc_attr( $settings['text_color'] ),
			esc_attr( $settings['button_color'] ),
			esc_attr( $settings['button_text_color'] ),
			esc_attr( (string) $settings['bar_width'] ),
			esc_attr( (string) $settings['bar_height'] )
		);

		include CMLC_PATH . 'templates/infobar.php';
	}

	/**
	 * Converts a hex color to an RGB string.
	 *
	 * @param string $hex Hex color value.
	 * @return string
	 */
	private function hex_to_rgb_string( $hex ) {
		$color = sanitize_hex_color( $hex );
		if ( empty( $color ) ) {
			return '31, 41, 55';
		}

		$color = ltrim( $color, '#' );
		if ( 3 === strlen( $color ) ) {
			$color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
		}

		$red   = hexdec( substr( $color, 0, 2 ) );
		$green = hexdec( substr( $color, 2, 2 ) );
		$blue  = hexdec( substr( $color, 4, 2 ) );

		return sprintf( '%d, %d, %d', $red, $green, $blue );
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
		$allowed = self::normalize_allowed_referrer_domains( (string) $settings['allowed_referrers'] );
		if ( empty( $allowed ) ) {
			return true;
		}

		$referrer = wp_get_referer();
		if ( empty( $referrer ) ) {
			return false;
		}

		$host = self::normalize_domain( (string) wp_parse_url( $referrer, PHP_URL_HOST ) );
		if ( '' === $host ) {
			return false;
		}

		foreach ( $allowed as $rule ) {
			if ( self::host_matches_referrer_rule( $host, $rule ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalizes configured referrer allowlist domains.
	 *
	 * Supports exact domains (example.com) and explicit wildcard subdomains (*.example.com).
	 *
	 * @param string $allowed_referrers Comma-separated referrer domains.
	 * @return array<int,string>
	 */
	public static function normalize_allowed_referrer_domains( $allowed_referrers ) {
		$domains = array_filter( array_map( 'trim', explode( ',', $allowed_referrers ) ) );
		$rules   = array();

		foreach ( $domains as $domain ) {
			$wildcard = 0 === strpos( $domain, '*.' );
			$base     = $wildcard ? substr( $domain, 2 ) : $domain;
			$base     = self::normalize_domain( $base );

			if ( '' === $base ) {
				continue;
			}

			$rules[] = $wildcard ? '*.' . $base : $base;
		}

		return array_values( array_unique( $rules ) );
	}

	/**
	 * Normalizes domains for safe, exact host comparisons.
	 *
	 * @param string $domain Domain to normalize.
	 * @return string
	 */
	public static function normalize_domain( $domain ) {
		$domain = strtolower( trim( $domain ) );
		$domain = trim( $domain, ". \t\n\r\0\x0B" );

		if ( '' === $domain ) {
			return '';
		}

		if ( function_exists( 'idn_to_ascii' ) ) {
			$idn_domain = idn_to_ascii( $domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 );
			if ( false !== $idn_domain ) {
				$domain = strtolower( $idn_domain );
			}
		}

		return $domain;
	}

	/**
	 * Validates a host against an allowlist rule.
	 *
	 * @param string $host Normalized host.
	 * @param string $rule Normalized allowlist rule.
	 * @return bool
	 */
	public static function host_matches_referrer_rule( $host, $rule ) {
		if ( 0 === strpos( $rule, '*.' ) ) {
			$base = substr( $rule, 2 );

			if ( '' === $base || $host === $base ) {
				return false;
			}

			$suffix = '.' . $base;
			return strlen( $host ) > strlen( $suffix ) && substr( $host, -strlen( $suffix ) ) === $suffix;
		}

		return $host === $rule;
	}
}
