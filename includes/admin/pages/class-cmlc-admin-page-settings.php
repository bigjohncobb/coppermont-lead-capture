<?php
/**
 * Settings admin page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Admin_Page_Settings implements CMLC_Admin_Page {
	public function get_slug() { return 'cmlc-settings'; }
	public function get_title() { return 'Lead Capture Settings'; }
	public function get_menu_title() { return 'Settings'; }
	public function is_default() { return false; }

	public function render() {
		$settings = new CMLC_Settings();
		$settings->render_settings_content();
	}

	public function get_help_tabs() {
		return array(
			array(
				'id'      => 'settings',
				'title'   => 'Settings guide',
				'content' => 'Configure global defaults, visual style, trigger behavior, targeting, and Turnstile settings.',
			),
		);
	}
}
