<?php
/**
 * Lead storage helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Leads {
	/**
	 * Returns table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'cmlc_leads';
	}

	/**
	 * Creates leads table.
	 *
	 * @return void
	 */
	public static function maybe_create_table() {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(190) NOT NULL,
			source varchar(100) NOT NULL DEFAULT '',
			campaign_id varchar(100) NOT NULL DEFAULT '',
			metadata longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY email (email),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Inserts a lead row.
	 *
	 * @param string               $email Email.
	 * @param string               $source Source slug.
	 * @param string               $campaign_id Campaign identifier.
	 * @param array<string,mixed>  $metadata Metadata.
	 * @return int|false
	 */
	public static function insert_lead( $email, $source = '', $campaign_id = '', $metadata = array() ) {
		global $wpdb;

		$prepared_metadata = ! empty( $metadata ) ? wp_json_encode( $metadata ) : null;

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'email'       => sanitize_email( $email ),
				'source'      => sanitize_text_field( $source ),
				'campaign_id' => sanitize_text_field( $campaign_id ),
				'metadata'    => $prepared_metadata,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Counts leads.
	 *
	 * @param string $search Search query.
	 * @return int
	 */
	public static function count_leads( $search = '' ) {
		global $wpdb;

		$table_name = self::table_name();

		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE email LIKE %s", $like ) );
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
	}

	/**
	 * Retrieves leads.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_leads( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'per_page' => 20,
			'page'     => 1,
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'search'   => '',
		);

		$args       = wp_parse_args( $args, $defaults );
		$table_name = self::table_name();
		$offset     = ( max( 1, (int) $args['page'] ) - 1 ) * max( 1, (int) $args['per_page'] );
		$orderby    = in_array( $args['orderby'], array( 'email', 'created_at' ), true ) ? $args['orderby'] : 'created_at';
		$order      = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$where_sql  = '';
		$params     = array();

		if ( '' !== $args['search'] ) {
			$where_sql = 'WHERE email LIKE %s';
			$params[]  = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
		}

		$params[] = max( 1, (int) $args['per_page'] );
		$params[] = $offset;

		$query = "SELECT id, email, source, campaign_id, metadata, created_at FROM {$table_name} {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		if ( ! empty( $params ) ) {
			$query = $wpdb->prepare( $query, $params );
		}

		$rows = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Deletes leads by ids.
	 *
	 * @param array<int,int> $ids Lead ids.
	 * @return int
	 */
	public static function delete_leads( $ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', is_array( $ids ) ? $ids : array() ) );
		if ( empty( $ids ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$table_name   = self::table_name();
		$query        = $wpdb->prepare( "DELETE FROM {$table_name} WHERE id IN ({$placeholders})", $ids );
		$result       = $wpdb->query( $query );

		return false === $result ? 0 : (int) $result;
	}
}
