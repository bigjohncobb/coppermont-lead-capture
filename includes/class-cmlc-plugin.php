<?php
/**
 * Main plugin class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CMLC_PATH . 'includes/class-cmlc-settings.php';
require_once CMLC_PATH . 'includes/class-cmlc-leads-admin.php';
require_once CMLC_PATH . 'includes/class-cmlc-shortcodes.php';
require_once CMLC_PATH . 'includes/class-cmlc-renderer.php';
require_once CMLC_PATH . 'includes/class-cmlc-ajax.php';

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

		self::create_tables();
		CMLC_Settings::sync_submission_analytics();
	}

	/**
	 * Deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Intentionally retain settings and analytics.
	}

	/**
	 * Initializes feature classes.
	 *
	 * @return void
	 */
	public function bootstrap() {
		new CMLC_Settings();
		new CMLC_Leads_Admin();
		new CMLC_Shortcodes();
		new CMLC_Renderer();
		new CMLC_Ajax();
	}

	/**
	 * Returns fully-qualified leads table name.
	 *
	 * @return string
	 */
	public static function leads_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'cmlc_leads';
	}

	/**
	 * Creates custom database tables used by the plugin.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		$table_name      = self::leads_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(190) NOT NULL,
			source VARCHAR(100) DEFAULT '' NOT NULL,
			campaign_id VARCHAR(100) DEFAULT '' NOT NULL,
			metadata LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY email (email),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
