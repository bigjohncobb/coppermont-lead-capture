<?php
/**
 * Campaigns admin page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Admin_Page_Campaigns extends CMLC_Admin_Page {
	public function get_slug() {
		return 'cmlc-campaigns';
	}

	public function get_title() {
		return 'Campaigns';
	}

	public function get_description() {
		return 'Plan and review lead capture campaign settings.';
	}

	public function get_help_content() {
		return '<p>Campaigns represent how and where the lead capture infobar appears. Configure triggers and targeting in Settings.</p>';
	}

	public function render_content() {
		$settings = CMLC_Settings::get();
		?>
		<table class="widefat striped" style="max-width: 900px;">
			<tbody>
				<tr><th scope="row">Scroll Trigger (%)</th><td><?php echo esc_html( (string) $settings['scroll_trigger_percent'] ); ?></td></tr>
				<tr><th scope="row">Delay (seconds)</th><td><?php echo esc_html( (string) $settings['time_delay_seconds'] ); ?></td></tr>
				<tr><th scope="row">Exit Intent</th><td><?php echo esc_html( ! empty( $settings['enable_exit_intent'] ) ? 'Enabled' : 'Disabled' ); ?></td></tr>
				<tr><th scope="row">Mobile Delivery</th><td><?php echo esc_html( ! empty( $settings['enable_mobile'] ) ? 'Enabled' : 'Disabled' ); ?></td></tr>
				<tr><th scope="row">Targeting Mode</th><td><?php echo esc_html( ucfirst( (string) $settings['page_target_mode'] ) ); ?></td></tr>
			</tbody>
		</table>
		<p style="margin-top: 16px;">Need to make changes? Use the <a href="<?php echo esc_url( admin_url( 'admin.php?page=cmlc-settings' ) ); ?>">Settings</a> page.</p>
		<?php
	}
}
