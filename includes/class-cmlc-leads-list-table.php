<?php
/**
 * Leads list table for admin UI.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class CMLC_Leads_List_Table extends WP_List_Table {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'cmlc_lead',
				'plural'   => 'cmlc_leads',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Returns table columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns() {
		return array(
			'cb'          => '<input type="checkbox" />',
			'id'          => 'ID',
			'email'       => 'Email',
			'source'      => 'Source',
			'campaign_id' => 'Campaign ID',
			'created_at'  => 'Submitted At',
		);
	}

	/**
	 * Returns sortable columns.
	 *
	 * @return array<string,array<int,mixed>>
	 */
	protected function get_sortable_columns() {
		return array(
			'email'      => array( 'email', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	/**
	 * Returns bulk actions.
	 *
	 * @return array<string,string>
	 */
	protected function get_bulk_actions() {
		return array(
			'bulk-delete' => 'Delete',
		);
	}

	/**
	 * Checkbox column output.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="lead_ids[]" value="%d" />', absint( $item->id ) );
	}

	/**
	 * Default column output.
	 *
	 * @param object $item Item.
	 * @param string $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
				return (string) absint( $item->id );
			case 'email':
				return esc_html( $item->email );
			case 'source':
				return esc_html( $item->source );
			case 'campaign_id':
				return esc_html( $item->campaign_id );
			case 'created_at':
				$timestamp = strtotime( (string) $item->created_at );
				return $timestamp ? esc_html( wp_date( 'Y-m-d H:i:s', $timestamp ) ) : esc_html( (string) $item->created_at );
			default:
				return '';
		}
	}

	/**
	 * Handles bulk actions.
	 *
	 * @return void
	 */
	public function process_bulk_action() {
		if ( 'bulk-delete' !== $this->current_action() ) {
			return;
		}

		check_admin_referer( 'bulk-' . $this->_args['plural'] );

		$lead_ids = isset( $_REQUEST['lead_ids'] ) ? wp_unslash( $_REQUEST['lead_ids'] ) : array();
		$lead_ids = is_array( $lead_ids ) ? array_map( 'absint', $lead_ids ) : array();
		$lead_ids = array_filter( $lead_ids );

		if ( empty( $lead_ids ) ) {
			return;
		}

		global $wpdb;
		$table_name   = CMLC_Plugin::leads_table_name();
		$placeholders = implode( ',', array_fill( 0, count( $lead_ids ), '%d' ) );
		$query        = $wpdb->prepare( "DELETE FROM {$table_name} WHERE id IN ({$placeholders})", $lead_ids ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$wpdb->query( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		CMLC_Settings::sync_submission_analytics();
	}

	/**
	 * Loads rows for the current table view.
	 *
	 * @return void
	 */
	public function prepare_items() {
		global $wpdb;

		$this->process_bulk_action();

		$per_page     = $this->get_items_per_page( 'cmlc_leads_per_page', 20 );
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;
		$table_name   = CMLC_Plugin::leads_table_name();

		$search       = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$order_by     = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at';
		$order        = isset( $_REQUEST['order'] ) ? strtoupper( sanitize_key( wp_unslash( $_REQUEST['order'] ) ) ) : 'DESC';
		$allowed_sort = array( 'email', 'created_at' );

		if ( ! in_array( $order_by, $allowed_sort, true ) ) {
			$order_by = 'created_at';
		}

		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		$where_clause = '';
		$query_args   = array();
		if ( '' !== $search ) {
			$like         = '%' . $wpdb->esc_like( $search ) . '%';
			$where_clause = 'WHERE email LIKE %s OR source LIKE %s OR campaign_id LIKE %s';
			$query_args   = array( $like, $like, $like );
		}

		$count_query = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";
		if ( ! empty( $query_args ) ) {
			$count_query = $wpdb->prepare( $count_query, $query_args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$total_items = (int) $wpdb->get_var( $count_query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$data_query = "SELECT id, email, source, campaign_id, created_at FROM {$table_name} {$where_clause} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d";
		$query_vars = $query_args;
		$query_vars[] = $per_page;
		$query_vars[] = $offset;
		$data_query = $wpdb->prepare( $data_query, $query_vars ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$this->items = $wpdb->get_results( $data_query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);
	}
}
