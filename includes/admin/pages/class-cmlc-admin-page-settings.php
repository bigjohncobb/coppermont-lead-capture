<?php
/**
 * Settings admin page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Admin_Page_Settings extends CMLC_Admin_Page {
	public function get_slug() {
		return 'cmlc-settings';
	}

	public function get_title() {
		return 'Settings';
	}

	public function get_description() {
		return 'Configure triggers, targeting, appearance, and scheduling for lead capture.';
	}

	public function get_help_content() {
		return '<p>Adjust campaign behavior and style settings here. Save changes to immediately apply them to front-end rendering.</p>';
	}

	public function render_content() {
		CMLC_Settings::render_settings_form();
	}
}
