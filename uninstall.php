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

$table_name = $wpdb->prefix . 'cmlc_leads';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
