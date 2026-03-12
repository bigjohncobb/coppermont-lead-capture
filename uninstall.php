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

// Respect admin-controlled retention policy: keep all plugin data unless explicitly disabled.
if ( ! empty( $settings['keep_data_on_uninstall'] ) ) {
	return;
}

// 1) Delete plugin options: current settings bundle and any present/future standalone cmlc_* options.
delete_option( 'cmlc_settings' );
delete_site_option( 'cmlc_settings' );

// 2) Delete prefixed plugin options directly to cover future plugin-owned keys.
global $wpdb;

if ( isset( $wpdb ) && $wpdb instanceof wpdb ) {
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			'cmlc_%'
		)
	);

	// 3) Delete plugin transients and their timeout rows for this plugin namespace.
	$like_patterns = array(
		'_transient_cmlc_%',
		'_transient_timeout_cmlc_%',
		'_site_transient_cmlc_%',
		'_site_transient_timeout_cmlc_%',
	);

	foreach ( $like_patterns as $like_pattern ) {
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$like_pattern
			)
		);
	}

	// 4) Delete plugin-owned custom tables, including forward-compatible table names for future modules.
	$table_suffixes = array(
		'cmlc_analytics',
		'cmlc_leads',
		'cmlc_campaigns',
	);

	foreach ( $table_suffixes as $suffix ) {
		$table_name = $wpdb->prefix . $suffix;
		$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );
	}

	// 5) Delete plugin-created campaign CPT content and associated post meta if campaigns are stored as posts.
	$campaign_ids = $wpdb->get_col(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'cmlc_campaign'"
	);

	if ( ! empty( $campaign_ids ) ) {
		$campaign_ids = array_map( 'absint', $campaign_ids );
		$campaign_ids = array_filter( $campaign_ids );

		if ( ! empty( $campaign_ids ) ) {
			$id_placeholders = implode( ',', array_fill( 0, count( $campaign_ids ), '%d' ) );

			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$id_placeholders})",
					$campaign_ids
				)
			);

			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->posts} WHERE ID IN ({$id_placeholders})",
					$campaign_ids
				)
			);
		}
	}
}

// 6) Unschedule known plugin cron hooks and clear all future events for those hooks.
$cron_hooks = array(
	'cmlc_cleanup',
	'cmlc_daily_maintenance',
	'cmlc_process_queue',
);

foreach ( $cron_hooks as $hook ) {
	wp_clear_scheduled_hook( $hook );
}
