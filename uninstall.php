<?php
/**
 * Uninstall routine for Coppermont Lead Capture.
 *
 * @package CoppermontLeadCapture
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$table_name = $wpdb->prefix . 'cmlc_events';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cmlc_leads" );

delete_option( 'cmlc_settings' );
delete_option( 'cmlc_analytics_seeded' );
delete_option( 'cmlc_analytics_legacy_offsets' );
