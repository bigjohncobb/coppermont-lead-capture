<?php
/**
 * Data management service.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Data_Manager {
	/**
	 * Settings option key.
	 *
	 * @var string
	 */
	const SETTINGS_OPTION_KEY = 'cmlc_settings';

	/**
	 * Resets analytics counters while preserving all other settings.
	 *
	 * @return bool
	 */
	public static function reset_analytics() {
		$settings = get_option( self::SETTINGS_OPTION_KEY, array() );
		$settings = is_array( $settings ) ? $settings : array();

		$settings['analytics_impressions'] = 0;
		$settings['analytics_submissions'] = 0;

		$updated = update_option( self::SETTINGS_OPTION_KEY, $settings );

		if ( ! $updated ) {
			$persisted = get_option( self::SETTINGS_OPTION_KEY, array() );
			if ( is_array( $persisted ) && 0 === (int) ( $persisted['analytics_impressions'] ?? -1 ) && 0 === (int) ( $persisted['analytics_submissions'] ?? -1 ) ) {
				return true;
			}
		}

		return (bool) $updated;
	}

	/**
	 * Deletes all plugin data.
	 *
	 * @return bool
	 */
	public static function delete_all_data() {
		$deleted = delete_option( self::SETTINGS_OPTION_KEY );

		if ( ! $deleted && false !== get_option( self::SETTINGS_OPTION_KEY, false ) ) {
			return false;
		}

		return true;
	}
}
