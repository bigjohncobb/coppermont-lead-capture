<?php
/**
 * Uninstall routine for Coppermont Lead Capture.
 *
 * @package CoppermontLeadCapture
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'cmlc_settings' );

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cmlc_leads" );
