<?php
/**
 * Data lifecycle management for plugin-owned entities.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Data_Manager {
	/**
	 * Option key that stores plugin settings.
	 *
	 * @var string
	 */
	const SETTINGS_OPTION_KEY = 'cmlc_settings';

	/**
	 * Deletes all plugin-owned data.
	 *
	 * @return void
	 */
	public static function purge_all_data() {
		self::delete_options();
		self::delete_site_options();
		self::delete_tables();
		self::delete_transients();
	}

	/**
	 * Plugin option keys to purge.
	 *
	 * @return array<int,string>
	 */
	public static function option_keys() {
		return array(
			self::SETTINGS_OPTION_KEY,
		);
	}

	/**
	 * Plugin custom table names without prefix.
	 *
	 * @return array<int,string>
	 */
	public static function table_names() {
		return array();
	}

	/**
	 * Plugin transient keys to purge.
	 *
	 * @return array<int,string>
	 */
	public static function transient_keys() {
		return array();
	}

	/**
	 * Deletes plugin options for current site.
	 *
	 * @return void
	 */
	private static function delete_options() {
		foreach ( self::option_keys() as $option_key ) {
			delete_option( $option_key );
		}
	}

	/**
	 * Deletes plugin network options for multisite.
	 *
	 * @return void
	 */
	private static function delete_site_options() {
		if ( ! is_multisite() ) {
			return;
		}

		foreach ( self::option_keys() as $option_key ) {
			delete_site_option( $option_key );
		}
	}

	/**
	 * Deletes plugin tables if any are defined.
	 *
	 * @return void
	 */
	private static function delete_tables() {
		global $wpdb;

		$table_names = self::table_names();
		if ( empty( $table_names ) ) {
			return;
		}

		foreach ( $table_names as $table_name ) {
			$full_table_name = $wpdb->prefix . $table_name;
			$wpdb->query( "DROP TABLE IF EXISTS `{$full_table_name}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Deletes plugin transients if any are defined.
	 *
	 * @return void
	 */
	private static function delete_transients() {
		foreach ( self::transient_keys() as $transient_key ) {
			delete_transient( $transient_key );
			delete_site_transient( $transient_key );
		}
	}
}
