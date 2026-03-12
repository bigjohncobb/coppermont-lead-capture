<?php
/**
 * Leads admin page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Admin_Page_Leads extends CMLC_Admin_Page {
	public function get_slug() {
		return 'cmlc-leads';
	}

	public function get_title() {
		return 'Leads';
	}

	public function get_description() {
		return 'Review lead submission performance and follow-up readiness.';
	}

	public function get_help_content() {
		return '<p>This plugin tracks lead volume metrics. Use the <code>cmlc_lead_submitted</code> action to forward leads to external CRMs.</p>';
	}

	public function render_content() {
		$settings = CMLC_Settings::get();
		?>
		<p>Lead submissions recorded: <strong><?php echo esc_html( (string) $settings['analytics_submissions'] ); ?></strong></p>
		<p>To store individual lead records, connect a CRM integration to the <code>cmlc_lead_submitted</code> action hook.</p>
		<?php
	}
}
