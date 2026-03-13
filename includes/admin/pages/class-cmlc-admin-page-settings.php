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
		$settings_url = add_query_arg( 'page', 'cmlc-dashboard', admin_url( 'admin.php' ) );
		?>
		<div class="notice notice-info">
			<p><?php printf( 'Settings have moved. <a href="%s">Go to Lead Capture Settings</a>.', esc_url( $settings_url ) ); ?></p>
		</div>
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
