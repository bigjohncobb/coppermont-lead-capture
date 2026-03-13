<?php
/**
 * Campaigns admin page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Admin_Page_Campaigns implements CMLC_Admin_Page {
	public function get_slug() { return 'cmlc-campaigns'; }
	public function get_title() { return 'Lead Capture Campaigns'; }
	public function get_menu_title() { return 'Campaigns'; }
	public function is_default() { return false; }

	public function render() {
		$settings = CMLC_Settings::get();
		?>
		<div class="cmlc-section">
			<h2>Campaign Configuration</h2>
			<p>Campaign controls are configured from Settings. Review your active targeting and triggers below.</p>
			<table class="widefat striped">
				<tbody>
					<tr><td><strong>Page Targeting Mode</strong></td><td><?php echo esc_html( $settings['page_target_mode'] ); ?></td></tr>
					<tr><td><strong>Page IDs</strong></td><td><?php echo esc_html( $settings['page_ids'] ? $settings['page_ids'] : 'All pages' ); ?></td></tr>
					<tr><td><strong>Allowed Referrers</strong></td><td><?php echo esc_html( $settings['allowed_referrers'] ? $settings['allowed_referrers'] : 'Any domain' ); ?></td></tr>
					<tr><td><strong>Schedule Window</strong></td><td><?php echo esc_html( $settings['schedule_start'] ? $settings['schedule_start'] : 'No start' ); ?> — <?php echo esc_html( $settings['schedule_end'] ? $settings['schedule_end'] : 'No end' ); ?></td></tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function get_help_tabs() {
		return array(
			array(
				'id'      => 'campaigns',
				'title'   => 'Campaign setup',
				'content' => 'Campaigns summarize targeting and scheduling. Use the Settings page to edit values.',
			),
		);
	}
}
