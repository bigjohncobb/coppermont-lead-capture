<?php
/**
 * Main plugin class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CMLC_PATH . 'includes/class-cmlc-settings.php';
require_once CMLC_PATH . 'includes/admin/class-cmlc-admin-page.php';
require_once CMLC_PATH . 'includes/admin/class-cmlc-admin.php';
require_once CMLC_PATH . 'includes/admin/pages/class-cmlc-admin-page-dashboard.php';
require_once CMLC_PATH . 'includes/admin/pages/class-cmlc-admin-page-campaigns.php';
require_once CMLC_PATH . 'includes/admin/pages/class-cmlc-admin-page-leads.php';
require_once CMLC_PATH . 'includes/admin/pages/class-cmlc-admin-page-analytics.php';
require_once CMLC_PATH . 'includes/admin/pages/class-cmlc-admin-page-settings.php';
require_once CMLC_PATH . 'includes/class-cmlc-campaigns.php';
require_once CMLC_PATH . 'includes/class-cmlc-leads.php';
require_once CMLC_PATH . 'includes/class-cmlc-leads-admin.php';
require_once CMLC_PATH . 'includes/class-cmlc-data-manager.php';
require_once CMLC_PATH . 'includes/class-cmlc-shortcodes.php';
require_once CMLC_PATH . 'includes/class-cmlc-renderer.php';
require_once CMLC_PATH . 'includes/class-cmlc-security.php';
require_once CMLC_PATH . 'includes/class-cmlc-ajax.php';
require_once CMLC_PATH . 'includes/class-cmlc-admin-security.php';
require_once CMLC_PATH . 'includes/class-cmlc-admin-actions.php';
require_once CMLC_PATH . 'includes/class-cmlc-analytics.php';
require_once CMLC_PATH . 'includes/class-cmlc-forms-bridge.php';

class CMLC_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var CMLC_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Retrieves singleton instance.
	 *
	 * @return CMLC_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'bootstrap' ) );
	}

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( 'cmlc_settings' ) ) {
			add_option( 'cmlc_settings', CMLC_Settings::defaults() );
		}

		CMLC_Analytics::install_schema();
		CMLC_Analytics::schedule_cleanup();
		CMLC_Leads::maybe_create_table();

		( new CMLC_Campaigns() )->register_post_type();
		self::migrate_global_settings_to_default_campaign();
		update_option( 'cmlc_campaign_migrated', 1 );
		flush_rewrite_rules();
	}

	/**
	 * Deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Intentionally retain settings and analytics.
		wp_clear_scheduled_hook( CMLC_Analytics::CLEANUP_HOOK );
	}

	/**
	 * Initializes feature classes.
	 *
	 * @return void
	 */
	public function bootstrap() {
		self::maybe_run_migration();
		CMLC_Leads::maybe_create_table();
		new CMLC_Settings();
		new CMLC_Admin(
			array(
				new CMLC_Admin_Page_Dashboard(),
				new CMLC_Admin_Page_Campaigns(),
				new CMLC_Admin_Page_Leads(),
				new CMLC_Admin_Page_Analytics(),
				new CMLC_Admin_Page_Settings(),
			)
		);
		new CMLC_Campaigns();
		new CMLC_Leads_Admin();
		new CMLC_Shortcodes();
		new CMLC_Renderer();
		new CMLC_Ajax();
		new CMLC_Admin_Actions();
		new CMLC_Analytics();
		new CMLC_Forms_Bridge();

		CMLC_Analytics::install_schema();
	}


	/**
	 * Ensures migration runs once on existing installs.
	 *
	 * @return void
	 */
	private static function maybe_run_migration() {
		if ( get_option( 'cmlc_campaign_migrated' ) ) {
			return;
		}

		( new CMLC_Campaigns() )->register_post_type();
		CMLC_Analytics::create_tables();
		self::migrate_global_settings_to_default_campaign();
		update_option( 'cmlc_campaign_migrated', 1 );
	}
	/**
	 * Migrates legacy global settings into a default campaign.
	 *
	 * @return int Campaign ID.
	 */
	public static function migrate_global_settings_to_default_campaign() {
		$existing = get_posts(
			array(
				'post_type'      => CMLC_Campaigns::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_key'       => 'cmlc_is_default_campaign',
				'meta_value'     => '1',
			)
		);

		if ( ! empty( $existing ) ) {
			return (int) $existing[0]->ID;
		}

		$settings  = CMLC_Settings::get();
		$campaign  = CMLC_Campaigns::defaults();
		$campaign['status'] = ! empty( $settings['enabled'] ) ? 'active' : 'inactive';
		$campaign['headline'] = (string) $settings['headline'];
		$campaign['body'] = (string) $settings['body'];
		$campaign['button_text'] = (string) $settings['button_text'];
		$campaign['bg_color'] = (string) $settings['bg_color'];
		$campaign['text_color'] = (string) $settings['text_color'];
		$campaign['button_color'] = (string) $settings['button_color'];
		$campaign['button_text_color'] = (string) $settings['button_text_color'];
		$campaign['scroll_trigger_percent'] = (int) $settings['scroll_trigger_percent'];
		$campaign['time_delay_seconds'] = (int) $settings['time_delay_seconds'];
		$campaign['repetition_cooldown_hours'] = (int) $settings['repetition_cooldown_hours'];
		$campaign['max_views'] = (int) $settings['max_views'];
		$campaign['enable_exit_intent'] = ! empty( $settings['enable_exit_intent'] ) ? 1 : 0;
		$campaign['enable_mobile'] = ! empty( $settings['enable_mobile'] ) ? 1 : 0;
		$campaign['allowed_referrers'] = (string) $settings['allowed_referrers'];
		$campaign['page_target_mode'] = (string) $settings['page_target_mode'];
		$campaign['page_ids'] = (string) $settings['page_ids'];
		$campaign['schedule_start'] = (string) $settings['schedule_start'];
		$campaign['schedule_end'] = (string) $settings['schedule_end'];
		$campaign['baseline_impressions'] = (int) $settings['analytics_impressions'];
		$campaign['baseline_submissions'] = (int) $settings['analytics_submissions'];
		$campaign = CMLC_Campaigns::sanitize_campaign( $campaign );

		$campaign_id = wp_insert_post(
			array(
				'post_type'   => CMLC_Campaigns::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => __( 'Default Campaign', 'coppermont-lead-capture' ),
			),
			true
		);

		if ( is_wp_error( $campaign_id ) || ! $campaign_id ) {
			return 0;
		}

		foreach ( $campaign as $key => $value ) {
			update_post_meta( $campaign_id, 'cmlc_' . $key, $value );
		}
		update_post_meta( $campaign_id, 'cmlc_is_default_campaign', 1 );
		update_option( 'cmlc_default_campaign_id', (int) $campaign_id );

		return (int) $campaign_id;
	}
}
