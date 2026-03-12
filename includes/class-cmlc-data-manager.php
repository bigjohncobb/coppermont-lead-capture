<?php
/**
 * Data lifecycle management.
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
	 * Determines whether uninstall should preserve plugin-owned data.
	 *
	 * @return bool
	 */
	public static function should_keep_data_on_uninstall() {
		$settings = get_option( self::SETTINGS_OPTION_KEY, array() );

		if ( ! is_array( $settings ) ) {
			return true;
		}

		if ( ! array_key_exists( 'keep_data_on_uninstall', $settings ) ) {
			return true;
		}

		return ! empty( $settings['keep_data_on_uninstall'] );
	}

	/**
	 * Deletes all plugin-owned data.
	 *
	 * @return void
	 */
	public static function purge_all_data() {
		global $wpdb;

		foreach ( self::get_option_keys() as $option_key ) {
			delete_option( $option_key );
			delete_site_option( $option_key );
		}

		foreach ( self::get_transient_keys() as $transient_key ) {
			delete_transient( $transient_key );
			delete_site_transient( $transient_key );
		}

		foreach ( self::get_table_names() as $table_name ) {
			$safe_table_name = str_replace( '`', '', $table_name );
			$wpdb->query( "DROP TABLE IF EXISTS `{$safe_table_name}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
	}

	/**
	 * Returns plugin-owned options that should be removed.
	 *
	 * @return array<int,string>
	 */
	private static function get_option_keys() {
		$option_keys = array(
			self::SETTINGS_OPTION_KEY,
		);

		/**
		 * Filters plugin-owned option keys deleted on uninstall purge.
		 *
		 * @param array<int,string> $option_keys Option keys.
		 */
		return (array) apply_filters( 'cmlc_data_manager_option_keys', $option_keys );
	}

	/**
	 * Returns plugin-owned transients that should be removed.
	 *
	 * @return array<int,string>
	 */
	private static function get_transient_keys() {
		$transient_keys = array();

		/**
		 * Filters plugin-owned transient keys deleted on uninstall purge.
		 *
		 * @param array<int,string> $transient_keys Transient keys.
		 */
		return (array) apply_filters( 'cmlc_data_manager_transient_keys', $transient_keys );
	}

	/**
	 * Returns plugin-owned tables that should be removed.
	 *
	 * @return array<int,string>
	 */
	private static function get_table_names() {
		global $wpdb;

		$table_names = array(
			$wpdb->prefix . 'cmlc_leads',
		);

		/**
		 * Filters plugin-owned table names deleted on uninstall purge.
		 *
		 * @param array<int,string> $table_names Table names.
		 */
		return (array) apply_filters( 'cmlc_data_manager_table_names', $table_names );
	}
}
