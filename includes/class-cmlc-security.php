<?php
/**
 * Security helpers for campaign context verification.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Security {
	/**
	 * Context token lifetime in seconds.
	 */
	const CONTEXT_TTL = 600;

	/**
	 * Generates campaign hash from active settings.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return string
	 */
	public static function campaign_hash( $settings ) {
		$fields = array(
			'headline'              => (string) $settings['headline'],
			'body'                  => (string) $settings['body'],
			'button_text'           => (string) $settings['button_text'],
			'page_target_mode'      => (string) $settings['page_target_mode'],
			'page_ids'              => (string) $settings['page_ids'],
			'schedule_start'        => (string) $settings['schedule_start'],
			'schedule_end'          => (string) $settings['schedule_end'],
			'allowed_referrers'     => (string) $settings['allowed_referrers'],
			'scroll_trigger_percent'=> (int) $settings['scroll_trigger_percent'],
			'time_delay_seconds'    => (int) $settings['time_delay_seconds'],
		);

		return hash( 'sha256', wp_json_encode( $fields ) );
	}

	/**
	 * Builds page hash.
	 *
	 * @param string $page_location Absolute page URL.
	 * @return string
	 */
	public static function page_hash( $page_location ) {
		$normalized = esc_url_raw( $page_location );
		if ( empty( $normalized ) ) {
			return '';
		}

		return hash( 'sha256', strtolower( $normalized ) );
	}

	/**
	 * Issues signed render context token.
	 *
	 * @param string $campaign_hash Campaign hash.
	 * @param string $page_hash Page hash.
	 * @return string
	 */
	public static function issue_context_token( $campaign_hash, $page_hash ) {
		$payload = array(
			'campaign' => $campaign_hash,
			'page'     => $page_hash,
			'exp'      => time() + self::CONTEXT_TTL,
		);

		$encoded_payload = self::base64url_encode( wp_json_encode( $payload ) );
		$signature       = hash_hmac( 'sha256', $encoded_payload, wp_salt( 'cmlc_context_token' ) );

		return $encoded_payload . '.' . $signature;
	}

	/**
	 * Verifies a signed render context token.
	 *
	 * @param string $token Signed token.
	 * @param string $expected_campaign_hash Expected campaign hash.
	 * @param string $expected_page_hash Expected page hash.
	 * @return bool
	 */
	public static function verify_context_token( $token, $expected_campaign_hash, $expected_page_hash ) {
		$parts = explode( '.', (string) $token );
		if ( 2 !== count( $parts ) ) {
			return false;
		}

		$encoded_payload = $parts[0];
		$signature       = $parts[1];
		$expected_sig    = hash_hmac( 'sha256', $encoded_payload, wp_salt( 'cmlc_context_token' ) );

		if ( ! hash_equals( $expected_sig, $signature ) ) {
			return false;
		}

		$decoded = json_decode( self::base64url_decode( $encoded_payload ), true );
		if ( ! is_array( $decoded ) ) {
			return false;
		}

		if ( empty( $decoded['campaign'] ) || empty( $decoded['page'] ) || empty( $decoded['exp'] ) ) {
			return false;
		}

		if ( time() > (int) $decoded['exp'] ) {
			return false;
		}

		return hash_equals( (string) $decoded['campaign'], $expected_campaign_hash )
			&& hash_equals( (string) $decoded['page'], $expected_page_hash );
	}

	/**
	 * @param string $value Input string.
	 * @return string
	 */
	private static function base64url_encode( $value ) {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	/**
	 * @param string $value Input string.
	 * @return string
	 */
	private static function base64url_decode( $value ) {
		$padding = strlen( $value ) % 4;
		if ( 0 !== $padding ) {
			$value .= str_repeat( '=', 4 - $padding );
		}

		return base64_decode( strtr( $value, '-_', '+/' ) );
	}
}
