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
	const OPTION_KEY = 'cmlc_settings';

	/**
	 * Resets analytics counters while retaining all other settings.
	 *
	 * @return bool|WP_Error
	 */
	public function reset_analytics() {
		$settings = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings['analytics_impressions'] = 0;
		$settings['analytics_submissions'] = 0;

		$updated = update_option( self::OPTION_KEY, $settings );
		if ( false === $updated ) {
			$latest = get_option( self::OPTION_KEY, array() );
			if ( isset( $latest['analytics_impressions'], $latest['analytics_submissions'] ) && 0 === (int) $latest['analytics_impressions'] && 0 === (int) $latest['analytics_submissions'] ) {
				return true;
			}

			return new WP_Error( 'cmlc_reset_failed', __( 'Unable to reset analytics. Please try again.', 'coppermont-lead-capture' ) );
		}

		return true;
	}

	/**
	 * Deletes all plugin-managed data.
	 *
	 * @return bool|WP_Error
	 */
	public function delete_all_data() {
		$deleted = delete_option( self::OPTION_KEY );
		if ( false === $deleted && false !== get_option( self::OPTION_KEY, false ) ) {
			return new WP_Error( 'cmlc_delete_failed', __( 'Unable to delete plugin data. Please try again.', 'coppermont-lead-capture' ) );
		}

		return true;
	}
}
