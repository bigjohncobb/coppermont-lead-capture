<?php
/**
 * Dashboard admin page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Admin_Page_Dashboard extends CMLC_Admin_Page {
	public function get_slug() {
		return 'cmlc-dashboard';
	}

	public function get_title() {
		return 'Dashboard';
	}

	public function get_description() {
		return 'Review plugin status and quick performance metrics.';
	}

	public function get_help_content() {
		return '<p>Use this dashboard to review infobar activity and quickly jump into campaign configuration.</p>';
	}

	public function render_content() {
		$settings = CMLC_Settings::get();
		?>
		<table class="widefat striped" style="max-width: 900px;">
			<thead>
				<tr>
					<th>Metric</th>
					<th>Value</th>
				</tr>
			</thead>
			<tbody>
				<tr><td>Infobar Status</td><td><?php echo esc_html( ! empty( $settings['enabled'] ) ? 'Enabled' : 'Disabled' ); ?></td></tr>
				<tr><td>Impressions</td><td><?php echo esc_html( (string) $settings['analytics_impressions'] ); ?></td></tr>
				<tr><td>Submissions</td><td><?php echo esc_html( (string) $settings['analytics_submissions'] ); ?></td></tr>
				<tr><td>Conversion Rate</td><td><?php echo esc_html( (string) CMLC_Settings::format_conversion_rate( $settings ) ); ?></td></tr>
			</tbody>
		</table>
		<p style="margin-top: 16px;">Open the <a href="<?php echo esc_url( admin_url( 'admin.php?page=cmlc-settings' ) ); ?>">Settings</a> page to update form behavior and targeting options.</p>
		<?php
	}
}
