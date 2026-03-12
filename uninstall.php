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
delete_option( 'cmlc_default_campaign_id' );

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cmlc_analytics" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cmlc_leads" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

$campaign_ids = get_posts(
	array(
		'post_type'      => 'cmlc_campaign',
		'post_status'    => 'any',
		'fields'         => 'ids',
		'posts_per_page' => -1,
	)
);

foreach ( $campaign_ids as $campaign_id ) {
	wp_delete_post( $campaign_id, true );
}
