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

$settings = get_option( CMLC_Data_Manager::SETTINGS_OPTION_KEY, array() );
$keep_data_on_uninstall = isset( $settings['keep_data_on_uninstall'] ) ? (bool) $settings['keep_data_on_uninstall'] : true;

if ( ! $keep_data_on_uninstall ) {
	CMLC_Data_Manager::purge_all_data();
}
