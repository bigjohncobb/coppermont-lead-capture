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

$keep_data_on_uninstall = 1;
if ( array_key_exists( 'keep_data_on_uninstall', $settings ) ) {
	$keep_data_on_uninstall = empty( $settings['keep_data_on_uninstall'] ) ? 0 : 1;
}

if ( 1 === $keep_data_on_uninstall ) {
	return;
}

// Delete plugin options so admin configuration and analytics counters do not remain after a full delete.
delete_option( 'cmlc_settings' );
delete_site_option( 'cmlc_settings' );

// Delete plugin-owned custom tables if they exist now or are introduced by future plugin versions.
global $wpdb;
$tables = array(
	$wpdb->prefix . 'cmlc_analytics',
	$wpdb->prefix . 'cmlc_leads',
	$wpdb->prefix . 'cmlc_campaigns',
);

foreach ( $tables as $table_name ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
}

// Delete campaign posts and their post meta when/if campaigns are stored as a custom post type.
$campaign_posts = get_posts(
	array(
		'post_type'      => 'cmlc_campaign',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	)
);

foreach ( $campaign_posts as $campaign_post_id ) {
	wp_delete_post( (int) $campaign_post_id, true );
}

// Clear plugin transients and cron jobs so no plugin-owned runtime artifacts are left behind.
delete_transient( 'cmlc_infobar_cache' );
delete_site_transient( 'cmlc_infobar_cache' );

$cron_hooks = array(
	'cmlc_daily_cleanup',
	'cmlc_process_queue',
);

foreach ( $cron_hooks as $hook ) {
	wp_clear_scheduled_hook( $hook );
}
