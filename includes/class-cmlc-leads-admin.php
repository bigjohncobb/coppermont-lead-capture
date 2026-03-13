<?php
/**
 * Leads admin page.
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
				'singular' => 'lead',
				'plural'   => 'leads',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns() {
		return array(
			'cb'          => '<input type="checkbox" />',
			'email'       => 'Email',
			'source'      => 'Source',
			'campaign_id' => 'Campaign ID',
			'created_at'  => 'Created',
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string,array<int|string>>
	 */
	protected function get_sortable_columns() {
		return array(
			'email'      => array( 'email', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string,string>
	 */
	protected function get_bulk_actions() {
		return array(
			'delete' => 'Delete',
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param array<string,mixed> $item Row item.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="lead_ids[]" value="%d" />', (int) $item['id'] );
	}

	/**
	 * Default column render.
	 *
	 * @param array<string,mixed> $item Row item.
	 * @param string              $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		if ( 'created_at' === $column_name ) {
			return esc_html( mysql2date( 'Y-m-d H:i:s', (string) $item['created_at'] ) );
		}

		return esc_html( (string) $item[ $column_name ] );
	}

	/**
	 * Email column with row actions.
	 *
	 * @param array<string,mixed> $item Row.
	 * @return string
	 */
	protected function column_email( $item ) {
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'    => 'cmlc-leads',
					'action'  => 'delete',
					'lead_id' => (int) $item['id'],
				),
				admin_url( 'admin.php' )
			),
			'cmlc_delete_lead'
		);

		$actions = array(
			'delete' => '<a href="' . esc_url( $delete_url ) . '">Delete</a>',
		);

		return sprintf( '%1$s %2$s', esc_html( (string) $item['email'] ), $this->row_actions( $actions ) );
	}

	/**
	 * Prepare items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$per_page = 20;
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$orderby  = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at';
		$order    = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc';
		$page_num = $this->get_pagenum();

		$this->items = CMLC_Leads::get_leads(
			array(
				'per_page' => $per_page,
				'page'     => $page_num,
				'search'   => $search,
				'orderby'  => $orderby,
				'order'    => $order,
			)
		);

		$total_items = CMLC_Leads::count_leads( $search );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);
	}
}

class CMLC_Leads_Admin {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/**
	 * Handles row and bulk actions.
	 *
	 * @return void
	 */
	public function handle_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['action'], $_GET['page'], $_GET['lead_id'] ) && 'cmlc-leads' === sanitize_key( wp_unslash( $_GET['page'] ) ) && 'delete' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
			check_admin_referer( 'cmlc_delete_lead' );
			CMLC_Leads::delete_leads( array( absint( wp_unslash( $_GET['lead_id'] ) ) ) );
			$this->sync_submission_counter();
			wp_safe_redirect( add_query_arg( array( 'page' => 'cmlc-leads', 'deleted' => 1 ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( isset( $_POST['action'], $_POST['page'] ) && 'cmlc-leads' === sanitize_key( wp_unslash( $_POST['page'] ) ) ) {
			$action = sanitize_key( wp_unslash( $_POST['action'] ) );
			if ( '-1' === $action && isset( $_POST['action2'] ) ) {
				$action = sanitize_key( wp_unslash( $_POST['action2'] ) );
			}

			if ( 'delete' === $action ) {
				check_admin_referer( 'bulk-leads' );
				$ids = isset( $_POST['lead_ids'] ) ? array_map( 'absint', (array) $_POST['lead_ids'] ) : array();
				if ( ! empty( $ids ) ) {
					CMLC_Leads::delete_leads( $ids );
					$this->sync_submission_counter();
				}

				wp_safe_redirect( add_query_arg( array( 'page' => 'cmlc-leads', 'deleted' => count( $ids ) ), admin_url( 'admin.php' ) ) );
				exit;
			}
		}

		if ( isset( $_GET['page'], $_GET['cmlc_action'] ) && 'cmlc-leads' === sanitize_key( wp_unslash( $_GET['page'] ) ) && 'export_csv' === sanitize_key( wp_unslash( $_GET['cmlc_action'] ) ) ) {
			check_admin_referer( 'cmlc_export_leads' );
			$this->export_csv();
		}
	}

	/**
	 * Renders leads page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'coppermont-lead-capture' ) );
		}

		$list_table = new CMLC_Leads_List_Table();
		$list_table->prepare_items();
		$export_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'        => 'cmlc-leads',
					'cmlc_action' => 'export_csv',
				),
				admin_url( 'admin.php' )
			),
			'cmlc_export_leads'
		);
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Leads</h1>
			<a class="page-title-action" href="<?php echo esc_url( $export_url ); ?>">Export CSV</a>
			<hr class="wp-header-end" />
			<form method="get">
				<input type="hidden" name="page" value="cmlc-leads" />
				<?php $list_table->search_box( 'Search Leads', 'cmlc-leads-search' ); ?>
			</form>
			<form method="post">
				<input type="hidden" name="page" value="cmlc-leads" />
				<?php
				$list_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Exports leads as CSV.
	 *
	 * @return void
	 */
	private function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export leads.', 'coppermont-lead-capture' ) );
		}

		$leads = CMLC_Leads::get_leads(
			array(
				'per_page' => 100000,
				'page'     => 1,
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=cmlc-leads-' . gmdate( 'Y-m-d' ) . '.csv' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: no-store' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'id', 'email', 'source', 'campaign_id', 'metadata', 'created_at' ) );

		foreach ( $leads as $lead ) {
			fputcsv(
				$output,
				array(
					(int) $lead['id'],
					self::sanitize_csv_field( (string) $lead['email'] ),
					self::sanitize_csv_field( (string) $lead['source'] ),
					self::sanitize_csv_field( (string) $lead['campaign_id'] ),
					self::sanitize_csv_field( (string) $lead['metadata'] ),
					(string) $lead['created_at'],
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * Prefixes formula-triggering characters to prevent CSV injection.
	 *
	 * @param string $value Raw cell value.
	 * @return string
	 */
	private static function sanitize_csv_field( $value ) {
		if ( '' !== $value && preg_match( '/^[=+\-@\t\r|!]/', $value ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Sync settings counter with lead table count.
	 *
	 * @return void
	 */
	private function sync_submission_counter() {
		$settings                          = CMLC_Settings::get();
		$settings['analytics_submissions'] = CMLC_Leads::count_leads();
		update_option( CMLC_Settings::OPTION_KEY, $settings );
	}
}
