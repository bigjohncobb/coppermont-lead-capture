<?php
/**
 * Bridge between Coppermont Forms and Lead Capture.
 *
 * Listens for form submissions from coppermont-forms and stores
 * email addresses as leads when the integration is enabled.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Forms_Bridge {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'cfrm_entry_created', array( $this, 'on_form_entry' ), 15, 4 );
	}

	/**
	 * Handles a new form entry from Coppermont Forms.
	 *
	 * @param int                $entry_id   Entry ID.
	 * @param array<string,mixed> $entry_data Field data keyed by field ID.
	 * @param array<string,mixed> $config     Form configuration.
	 * @param int                $form_id    Form post ID.
	 * @return void
	 */
	public function on_form_entry( $entry_id, $entry_data, $config, $form_id ) {
		$settings = CMLC_Settings::get();

		if ( empty( $settings['forms_bridge_enabled'] ) ) {
			return;
		}

		// Check if this form is in the allowed list (empty = all forms).
		$allowed_ids = self::parse_form_ids( $settings['forms_bridge_form_ids'] ?? '' );
		if ( ! empty( $allowed_ids ) && ! in_array( $form_id, $allowed_ids, true ) ) {
			return;
		}

		// Find the first email field value.
		$email = self::extract_email( $entry_data, $config );
		if ( ! $email ) {
			return;
		}

		$form_title = get_the_title( $form_id );
		$source     = 'form:' . ( $form_title ?: $form_id );

		$metadata = array(
			'form_id'    => $form_id,
			'form_title' => $form_title,
			'entry_id'   => $entry_id,
		);

		// Include additional captured fields as metadata.
		$name = self::extract_name_fields( $entry_data, $config );
		if ( $name ) {
			$metadata['name'] = $name;
		}

		CMLC_Leads::insert_lead( $email, $source, '', $metadata );

		/**
		 * Fires when a form submission is captured as a lead.
		 *
		 * @param string $email      Email address.
		 * @param int    $form_id    Form post ID.
		 * @param int    $entry_id   Entry ID.
		 * @param array  $entry_data Field data.
		 */
		do_action( 'cmlc_form_lead_captured', $email, $form_id, $entry_id, $entry_data );
	}

	/**
	 * Extracts the first email address from form entry data.
	 *
	 * @param array<string,mixed> $entry_data Field data.
	 * @param array<string,mixed> $config     Form config.
	 * @return string|false
	 */
	private static function extract_email( $entry_data, $config ) {
		$fields = $config['fields'] ?? array();

		foreach ( $fields as $field ) {
			if ( 'email' === ( $field['type'] ?? '' ) ) {
				$field_id = $field['id'] ?? '';
				if ( $field_id && ! empty( $entry_data[ $field_id ] ) ) {
					$email = sanitize_email( $entry_data[ $field_id ] );
					if ( is_email( $email ) ) {
						return $email;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Extracts name from text fields labeled with common name patterns.
	 *
	 * @param array<string,mixed> $entry_data Field data.
	 * @param array<string,mixed> $config     Form config.
	 * @return string
	 */
	private static function extract_name_fields( $entry_data, $config ) {
		$fields     = $config['fields'] ?? array();
		$name_parts = array();

		foreach ( $fields as $field ) {
			if ( 'text' !== ( $field['type'] ?? '' ) ) {
				continue;
			}

			$label    = strtolower( $field['label'] ?? '' );
			$field_id = $field['id'] ?? '';

			if ( ! $field_id || empty( $entry_data[ $field_id ] ) ) {
				continue;
			}

			if ( preg_match( '/\b(first\s*name|last\s*name|full\s*name|name)\b/', $label ) ) {
				$name_parts[] = sanitize_text_field( $entry_data[ $field_id ] );
			}
		}

		return implode( ' ', $name_parts );
	}

	/**
	 * Parses comma-separated form IDs.
	 *
	 * @param string $raw Raw form IDs string.
	 * @return array<int>
	 */
	private static function parse_form_ids( $raw ) {
		if ( empty( $raw ) ) {
			return array();
		}

		return array_filter( array_map( 'absint', array_map( 'trim', explode( ',', $raw ) ) ) );
	}
}
