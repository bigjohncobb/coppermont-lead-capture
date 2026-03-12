<?php
/**
 * Dashboard admin page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Admin_Page_Dashboard implements CMLC_Admin_Page {
	public function get_slug() { return 'cmlc-dashboard'; }
	public function get_title() { return 'Lead Capture Dashboard'; }
	public function get_menu_title() { return 'Dashboard'; }
	public function is_default() { return true; }

	public function render() {
		$settings    = CMLC_Settings::get();
		$impressions = (int) $settings['analytics_impressions'];
		$submissions = (int) $settings['analytics_submissions'];
		$rate        = $impressions > 0 ? round( ( $submissions / $impressions ) * 100, 2 ) : 0;
		?>
		<div class="cmlc-section">
			<h2>Overview</h2>
			<table class="widefat striped">
				<tbody>
					<tr><td><strong>Infobar Status</strong></td><td><?php echo ! empty( $settings['enabled'] ) ? 'Enabled' : 'Disabled'; ?></td></tr>
					<tr><td><strong>Impressions</strong></td><td><?php echo esc_html( (string) $impressions ); ?></td></tr>
					<tr><td><strong>Submissions</strong></td><td><?php echo esc_html( (string) $submissions ); ?></td></tr>
					<tr><td><strong>Conversion Rate</strong></td><td><?php echo esc_html( (string) $rate ); ?>%</td></tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function get_help_tabs() {
		return array(
			array(
				'id'      => 'overview',
				'title'   => 'Dashboard overview',
				'content' => 'Use the dashboard for a quick snapshot of infobar performance and overall status.',
			),
		);
	}
}
