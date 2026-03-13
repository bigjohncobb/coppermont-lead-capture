<?php
/**
 * Data management service.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Data_Manager {
	/**
	 * Plugin option key.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'cmlc_settings';

	/**
	 * Resets analytics counters while preserving all other settings.
	 *
	 * @return true|WP_Error
	 */
	public static function reset_analytics_only() {
		$settings = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $settings ) ) {
			return new WP_Error( 'cmlc_invalid_settings', __( 'Could not reset analytics because settings data is invalid.', 'coppermont-lead-capture' ) );
		}

		$settings['analytics_impressions'] = 0;
		$settings['analytics_submissions'] = 0;

		if ( false === update_option( self::OPTION_KEY, $settings ) ) {
			$current = get_option( self::OPTION_KEY, array() );

			if ( ! is_array( $current ) || 0 !== absint( $current['analytics_impressions'] ?? 0 ) || 0 !== absint( $current['analytics_submissions'] ?? 0 ) ) {
				return new WP_Error( 'cmlc_reset_failed', __( 'Analytics reset failed. Please try again.', 'coppermont-lead-capture' ) );
			}
		}

		return true;
	}

	/**
	 * Deletes all plugin data.
	 *
	 * @return true|WP_Error
	 */
	public static function delete_all_plugin_data() {
		global $wpdb;

		// Drop custom tables.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cmlc_events" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cmlc_leads" );

		// Remove plugin options.
		delete_option( self::OPTION_KEY );
		delete_option( 'cmlc_analytics_seeded' );
		delete_option( 'cmlc_analytics_legacy_offsets' );
		delete_option( 'cmlc_campaign_migrated' );
		delete_option( 'cmlc_default_campaign_id' );

		if ( false !== get_option( self::OPTION_KEY, false ) ) {
			return new WP_Error( 'cmlc_delete_failed', __( 'Plugin data could not be fully deleted.', 'coppermont-lead-capture' ) );
		}

		return true;
	}
}
