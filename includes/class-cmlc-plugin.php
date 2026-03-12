<?php
/**
 * Main plugin class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CMLC_PATH . 'includes/class-cmlc-settings.php';
require_once CMLC_PATH . 'includes/class-cmlc-shortcodes.php';
require_once CMLC_PATH . 'includes/class-cmlc-renderer.php';
require_once CMLC_PATH . 'includes/class-cmlc-ajax.php';
require_once CMLC_PATH . 'includes/admin/class-cmlc-analytics-page.php';
require_once CMLC_PATH . 'includes/admin/class-cmlc-admin.php';

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
		$settings       = new CMLC_Settings();
		$analytics_page = new CMLC_Analytics_Page();
		new CMLC_Admin( $settings, $analytics_page );
		new CMLC_Shortcodes();
		new CMLC_Renderer();
		new CMLC_Ajax();
	}
}
