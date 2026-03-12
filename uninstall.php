<?php
/**
 * Uninstall routine for Coppermont Lead Capture.
 *
 * @package CoppermontLeadCapture
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __FILE__ ) . '/includes/class-cmlc-data-manager.php';

$data_manager = new CMLC_Data_Manager();
$data_manager->delete_all_data();
