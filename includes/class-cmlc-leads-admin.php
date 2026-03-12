<?php
/**
 * Leads admin screens.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Leads_Admin {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_post_cmlc_export_leads', array( $this, 'export_csv' ) );
	}

	/**
	 * Adds Leads submenu page.
	 *
	 * @return void
	 */
	public function add_menu_pages() {
		add_submenu_page(
			'cmlc-settings',
			'Captured Leads',
			'Leads',
			'manage_options',
			'cmlc-leads',
			array( $this, 'render_leads_page' )
		);
	}

	/**
	 * Renders leads management page.
	 *
	 * @return void
	 */
	public function render_leads_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'coppermont-lead-capture' ) );
		}

		require_once CMLC_PATH . 'includes/class-cmlc-leads-list-table.php';

		$list_table = new CMLC_Leads_List_Table();
		$list_table->prepare_items();

		$export_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=cmlc_export_leads' ),
			'cmlc_export_leads',
			'cmlc_export_nonce'
		);
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Captured Leads</h1>
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
	 * Exports captured leads as CSV.
	 *
	 * @return void
	 */
	public function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export leads.', 'coppermont-lead-capture' ) );
		}

		$nonce = isset( $_GET['cmlc_export_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['cmlc_export_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cmlc_export_leads' ) ) {
			wp_die( esc_html__( 'Invalid export request.', 'coppermont-lead-capture' ) );
		}

		global $wpdb;
		$table_name = CMLC_Plugin::leads_table_name();
		$rows       = $wpdb->get_results( "SELECT id, email, source, campaign_id, metadata, created_at FROM {$table_name} ORDER BY created_at DESC" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=cmlc-leads-' . gmdate( 'Ymd-His' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			wp_die( esc_html__( 'Unable to generate CSV export.', 'coppermont-lead-capture' ) );
		}

		fputcsv( $output, array( 'ID', 'Email', 'Source', 'Campaign ID', 'Metadata', 'Created At' ) );
		foreach ( $rows as $row ) {
			fputcsv(
				$output,
				array(
					$row->id,
					$row->email,
					$row->source,
					$row->campaign_id,
					$row->metadata,
					$row->created_at,
				)
			);
		}

		fclose( $output );
		exit;
	}
}
