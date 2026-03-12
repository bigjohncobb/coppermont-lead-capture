<?php
/**
 * Uninstall routine for Coppermont Lead Capture.
 *
 * @package CoppermontLeadCapture
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-cmlc-data-manager.php';

if ( CMLC_Data_Manager::should_keep_data_on_uninstall() ) {
	return;
}

CMLC_Data_Manager::purge_all_data();
