<?php
/**
 * Uninstall routine for Coppermont Lead Capture.
 *
 * @package CoppermontLeadCapture
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'cmlc_settings', array() );
$settings = is_array( $settings ) ? $settings : array();

// Respect administrator uninstall preference. Default is to retain data when this flag is missing.
$keep_data_on_uninstall = isset( $settings['keep_data_on_uninstall'] ) ? (int) $settings['keep_data_on_uninstall'] : 1;
if ( 1 === $keep_data_on_uninstall ) {
	return;
}

// Delete plugin options (primary settings and any known standalone plugin options).
delete_option( 'cmlc_settings' );
delete_site_option( 'cmlc_settings' );

// Delete plugin-owned custom tables if present (analytics, leads, and campaigns storage).
global $wpdb;
$tables = array(
	$wpdb->prefix . 'cmlc_analytics',
	$wpdb->prefix . 'cmlc_leads',
	$wpdb->prefix . 'cmlc_campaigns',
);

foreach ( $tables as $table_name ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
}

// Delete campaign CPT content and attached metadata if campaigns are stored as posts.
$campaign_post_ids = get_posts(
	array(
		'post_type'      => 'cmlc_campaign',
		'post_status'    => 'any',
		'numberposts'    => -1,
		'fields'         => 'ids',
		'suppress_filters' => false,
	)
);

if ( is_array( $campaign_post_ids ) ) {
	foreach ( $campaign_post_ids as $campaign_post_id ) {
		wp_delete_post( (int) $campaign_post_id, true );
	}
}

// Delete plugin transients (cached runtime state and counters) in single-site and multisite contexts.
delete_transient( 'cmlc_runtime_state' );
delete_transient( 'cmlc_campaign_cache' );
delete_site_transient( 'cmlc_runtime_state' );
delete_site_transient( 'cmlc_campaign_cache' );

// Unschedule plugin cron hooks so no orphaned events remain after data removal.
wp_clear_scheduled_hook( 'cmlc_cleanup' );
wp_clear_scheduled_hook( 'cmlc_process_queue' );
