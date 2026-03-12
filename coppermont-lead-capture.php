<?php
/**
 * Plugin Name: Coppermont Lead Capture
 * Plugin URI:  https://example.com
 * Description: Lead generation popups and infobars with targeting, scheduling, and analytics.
 * Version:     0.2.0
 * Author:      Coppermont
 * Text Domain: coppermont-lead-capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CMLC_VERSION', '0.2.0' );
define( 'CMLC_PATH', plugin_dir_path( __FILE__ ) );
define( 'CMLC_URL', plugin_dir_url( __FILE__ ) );

require_once CMLC_PATH . 'includes/class-cmlc-plugin.php';

/**
 * Bootstraps the plugin.
 *
 * @return CMLC_Plugin
 */
function cmlc_plugin() {
	return CMLC_Plugin::instance();
}

register_activation_hook( __FILE__, array( 'CMLC_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CMLC_Plugin', 'deactivate' ) );

cmlc_plugin();
