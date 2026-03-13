<?php
/**
 * Leads admin page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Admin_Page_Leads implements CMLC_Admin_Page {
	public function get_slug() { return 'cmlc-leads'; }
	public function get_title() { return 'Lead Capture Leads'; }
	public function get_menu_title() { return 'Leads'; }
	public function is_default() { return false; }

	public function render() {
		$settings = CMLC_Settings::get();
		?>
		<div class="cmlc-section">
			<h2>Leads Summary</h2>
			<p>Total email submissions recorded by the plugin:</p>
			<p><strong><?php echo esc_html( (string) (int) $settings['analytics_submissions'] ); ?></strong></p>
			<p class="description">To store full lead records, connect this plugin with your CRM via the <code>cmlc_lead_submitted</code> action.</p>
		</div>
		<?php
	}

	public function get_help_tabs() {
		return array(
			array(
				'id'      => 'leads',
				'title'   => 'Lead tracking',
				'content' => 'This page displays submission totals. Integrate with external systems for full lead storage.',
			),
		);
	}
}
