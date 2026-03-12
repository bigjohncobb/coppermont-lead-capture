<?php
/**
 * Analytics admin page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Admin_Page_Analytics implements CMLC_Admin_Page {
	public function get_slug() { return 'cmlc-analytics'; }
	public function get_title() { return 'Lead Capture Analytics'; }
	public function get_menu_title() { return 'Analytics'; }
	public function is_default() { return false; }

	public function render() {
		$settings    = CMLC_Settings::get();
		$impressions = (int) $settings['analytics_impressions'];
		$submissions = (int) $settings['analytics_submissions'];
		$rate        = $impressions > 0 ? round( ( $submissions / $impressions ) * 100, 2 ) : 0;
		?>
		<div class="cmlc-section">
			<h2>Performance</h2>
			<table class="widefat striped">
				<tbody>
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
				'id'      => 'analytics',
				'title'   => 'Analytics metrics',
				'content' => 'Compare impressions and submissions to understand conversion performance.',
			),
		);
	}
}
