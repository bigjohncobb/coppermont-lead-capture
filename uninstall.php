<?php
/**
 * Uninstall routine for Coppermont Lead Capture.
 *
 * @package CoppermontLeadCapture
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$plugin_path = plugin_dir_path( __FILE__ );

require_once $plugin_path . 'includes/class-cmlc-data-manager.php';

CMLC_Data_Manager::delete_all_plugin_data();
