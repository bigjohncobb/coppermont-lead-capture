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
		$settings = CMLC_Settings::get();
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'cmlc_settings_group' ); ?>
			<h2>General</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Enable Infobar</th><td><input type="checkbox" name="cmlc_settings[enabled]" value="1" <?php checked( 1, $settings['enabled'] ); ?>></td></tr>
				<tr><th scope="row">Headline</th><td><input class="regular-text" name="cmlc_settings[headline]" value="<?php echo esc_attr( $settings['headline'] ); ?>"></td></tr>
				<tr><th scope="row">Body</th><td><input class="regular-text" name="cmlc_settings[body]" value="<?php echo esc_attr( $settings['body'] ); ?>"></td></tr>
				<tr><th scope="row">Button Text</th><td><input name="cmlc_settings[button_text]" value="<?php echo esc_attr( $settings['button_text'] ); ?>"></td></tr>
			</table>

			<h2>Design</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Background Color</th><td><input type="color" name="cmlc_settings[bg_color]" value="<?php echo esc_attr( $settings['bg_color'] ); ?>"></td></tr>
				<tr><th scope="row">Text Color</th><td><input type="color" name="cmlc_settings[text_color]" value="<?php echo esc_attr( $settings['text_color'] ); ?>"></td></tr>
				<tr><th scope="row">Button Color</th><td><input type="color" name="cmlc_settings[button_color]" value="<?php echo esc_attr( $settings['button_color'] ); ?>"></td></tr>
				<tr><th scope="row">Button Text Color</th><td><input type="color" name="cmlc_settings[button_text_color]" value="<?php echo esc_attr( $settings['button_text_color'] ); ?>"></td></tr>
			</table>

			<h2>Behavior</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Scroll Trigger %</th><td><input type="number" min="0" max="100" name="cmlc_settings[scroll_trigger_percent]" value="<?php echo esc_attr( $settings['scroll_trigger_percent'] ); ?>"></td></tr>
				<tr><th scope="row">Delay Seconds</th><td><input type="number" min="0" name="cmlc_settings[time_delay_seconds]" value="<?php echo esc_attr( $settings['time_delay_seconds'] ); ?>"></td></tr>
				<tr><th scope="row">Cooldown Hours</th><td><input type="number" min="1" name="cmlc_settings[repetition_cooldown_hours]" value="<?php echo esc_attr( $settings['repetition_cooldown_hours'] ); ?>"></td></tr>
				<tr><th scope="row">Max Views</th><td><input type="number" min="1" name="cmlc_settings[max_views]" value="<?php echo esc_attr( $settings['max_views'] ); ?>"></td></tr>
				<tr><th scope="row">Exit Intent Trigger</th><td><input type="checkbox" name="cmlc_settings[enable_exit_intent]" value="1" <?php checked( 1, $settings['enable_exit_intent'] ); ?>></td></tr>
				<tr><th scope="row">Enable on Mobile</th><td><input type="checkbox" name="cmlc_settings[enable_mobile]" value="1" <?php checked( 1, $settings['enable_mobile'] ); ?>></td></tr>
			</table>

			<h2>Targeting & Schedule</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Allowed Referrers</th><td><input class="regular-text" name="cmlc_settings[allowed_referrers]" value="<?php echo esc_attr( $settings['allowed_referrers'] ); ?>"><p class="description">Comma-separated domains.</p></td></tr>
				<tr><th scope="row">Page Targeting Mode</th><td><select name="cmlc_settings[page_target_mode]"><option value="all" <?php selected( 'all', $settings['page_target_mode'] ); ?>>All pages</option><option value="include" <?php selected( 'include', $settings['page_target_mode'] ); ?>>Include only listed IDs</option><option value="exclude" <?php selected( 'exclude', $settings['page_target_mode'] ); ?>>Exclude listed IDs</option></select></td></tr>
				<tr><th scope="row">Page IDs</th><td><input class="regular-text" name="cmlc_settings[page_ids]" value="<?php echo esc_attr( $settings['page_ids'] ); ?>"><p class="description">Comma-separated post/page IDs.</p></td></tr>
				<tr><th scope="row">Schedule Start</th><td><input type="datetime-local" name="cmlc_settings[schedule_start]" value="<?php echo esc_attr( $settings['schedule_start'] ); ?>"></td></tr>
				<tr><th scope="row">Schedule End</th><td><input type="datetime-local" name="cmlc_settings[schedule_end]" value="<?php echo esc_attr( $settings['schedule_end'] ); ?>"></td></tr>
			</table>
			<?php submit_button( 'Save Settings' ); ?>
		</form>
		<?php
	}

	public function get_help_tabs() {
		return array(
			array(
				'id'      => 'settings',
				'title'   => 'Settings guide',
				'content' => 'Configure visual style, trigger behavior, and targeting from this page.',
			),
		);
	}
}
