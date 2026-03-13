<?php
/**
 * Third-party integrations: beehiiv and GoHighLevel.
 *
 * Fires on every new lead (infobar submissions and form captures)
 * to push email + metadata to configured services.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Integrations {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'cmlc_lead_submitted', array( $this, 'on_lead_submitted' ), 10, 2 );
		add_action( 'cmlc_form_lead_captured', array( $this, 'on_form_lead_captured' ), 10, 4 );
	}

	/**
	 * Handles a lead from infobar/shortcode submission.
	 *
	 * @param string             $email    Email address.
	 * @param array<string,mixed> $campaign Campaign data.
	 * @return void
	 */
	public function on_lead_submitted( $email, $campaign ) {
		$this->dispatch_all( $email, array(
			'source'      => 'infobar',
			'campaign_id' => $campaign['id'] ?? 0,
			'campaign'    => $campaign['headline'] ?? '',
		) );
	}

	/**
	 * Handles a lead captured via the forms bridge.
	 *
	 * @param string             $email      Email address.
	 * @param int                $form_id    Form post ID.
	 * @param int                $entry_id   Entry ID.
	 * @param array<string,mixed> $entry_data Field data.
	 * @return void
	 */
	public function on_form_lead_captured( $email, $form_id, $entry_id, $entry_data ) {
		$this->dispatch_all( $email, array(
			'source'    => 'form',
			'form_id'   => $form_id,
			'form_name' => get_the_title( $form_id ),
		) );
	}

	/**
	 * Dispatches to all enabled integrations.
	 *
	 * @param string             $email   Email address.
	 * @param array<string,mixed> $context Source context.
	 * @return void
	 */
	private function dispatch_all( $email, $context ) {
		$settings = CMLC_Settings::get();

		if ( ! empty( $settings['beehiiv_enabled'] ) ) {
			$this->send_to_beehiiv( $email, $context, $settings );
		}

		if ( ! empty( $settings['ghl_enabled'] ) ) {
			$this->send_to_ghl( $email, $context, $settings );
		}
	}

	/**
	 * Sends a lead to beehiiv.
	 *
	 * @param string             $email    Email address.
	 * @param array<string,mixed> $context  Source context.
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return void
	 */
	private function send_to_beehiiv( $email, $context, $settings ) {
		$api_key        = trim( $settings['beehiiv_api_key'] ?? '' );
		$publication_id = trim( $settings['beehiiv_publication_id'] ?? '' );

		if ( empty( $api_key ) || empty( $publication_id ) ) {
			return;
		}

		$url = 'https://api.beehiiv.com/v2/publications/' . rawurlencode( $publication_id ) . '/subscriptions';

		$body = array(
			'email'            => $email,
			'reactivate_existing' => true,
			'utm_source'       => 'coppermont',
			'utm_medium'       => $context['source'] ?? 'infobar',
		);

		$response = wp_remote_post( $url, array(
			'timeout' => 10,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body' => wp_json_encode( $body ),
		) );

		self::log_response( 'beehiiv', $email, $response );
	}

	/**
	 * Sends a lead to GoHighLevel.
	 *
	 * @param string             $email    Email address.
	 * @param array<string,mixed> $context  Source context.
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return void
	 */
	private function send_to_ghl( $email, $context, $settings ) {
		$api_key    = trim( $settings['ghl_api_key'] ?? '' );
		$location_id = trim( $settings['ghl_location_id'] ?? '' );

		if ( empty( $api_key ) ) {
			return;
		}

		// GHL v2 API requires location ID; v1 does not.
		if ( ! empty( $location_id ) ) {
			$url = 'https://services.leadconnectorhq.com/contacts/';
		} else {
			$url = 'https://rest.gohighlevel.com/v1/contacts/';
		}

		$body = array(
			'email'  => $email,
			'source' => 'Coppermont ' . ( $context['source'] ?? 'website' ),
			'tags'   => array( 'coppermont-lead' ),
		);

		if ( ! empty( $location_id ) ) {
			$body['locationId'] = $location_id;
		}

		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $api_key,
		);

		if ( ! empty( $location_id ) ) {
			$headers['Version'] = '2021-07-28';
		}

		$response = wp_remote_post( $url, array(
			'timeout' => 10,
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
		) );

		self::log_response( 'ghl', $email, $response );
	}

	/**
	 * Logs integration response for debugging.
	 *
	 * @param string          $service  Service name.
	 * @param string          $email    Email sent.
	 * @param array|WP_Error  $response HTTP response or error.
	 * @return void
	 */
	private static function log_response( $service, $email, $response ) {
		if ( is_wp_error( $response ) ) {
			error_log( sprintf(
				'Coppermont Lead Capture [%s]: Failed to send %s — %s',
				$service,
				$email,
				$response->get_error_message()
			) );
			return;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$body = wp_remote_retrieve_body( $response );
			error_log( sprintf(
				'Coppermont Lead Capture [%s]: HTTP %d for %s — %s',
				$service,
				$code,
				$email,
				mb_substr( $body, 0, 500 )
			) );
		}
	}
}
