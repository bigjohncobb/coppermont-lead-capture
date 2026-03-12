<?php
/**
 * Analytics admin page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Admin_Page_Analytics extends CMLC_Admin_Page {
	public function get_slug() {
		return 'cmlc-analytics';
	}

	public function get_title() {
		return 'Analytics';
	}

	public function get_description() {
		return 'Monitor display and conversion outcomes for the lead capture infobar.';
	}

	public function get_help_content() {
		return '<p>Analytics are cumulative and stored in plugin settings. Reset the plugin on uninstall if you need a fresh baseline.</p>';
	}

	public function render_content() {
		$settings = CMLC_Settings::get();
		?>
		<table class="widefat striped" style="max-width: 700px;">
			<tbody>
				<tr><th scope="row">Impressions</th><td><?php echo esc_html( (string) $settings['analytics_impressions'] ); ?></td></tr>
				<tr><th scope="row">Submissions</th><td><?php echo esc_html( (string) $settings['analytics_submissions'] ); ?></td></tr>
				<tr><th scope="row">Conversion Rate</th><td><?php echo esc_html( (string) CMLC_Settings::format_conversion_rate( $settings ) ); ?></td></tr>
			</tbody>
		</table>
		<?php
	}
}
