<?php
/**
 * Leads admin page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Admin_Page_Leads implements CMLC_Admin_Page {
	public function get_slug() { return 'cmlc-leads'; }
	public function get_title() { return 'Lead Capture Leads'; }
	public function get_menu_title() { return 'Leads'; }
	public function is_default() { return false; }

	public function render() {
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
		<div class="cmlc-section">
			<h2 class="wp-heading-inline">Leads</h2>
			<a class="page-title-action" href="<?php echo esc_url( $export_url ); ?>">Export CSV</a>
			<hr class="wp-header-end" />
			<form method="get">
				<input type="hidden" name="page" value="cmlc-leads" />
				<?php $list_table->search_box( 'Search Leads', 'cmlc-leads-search' ); ?>
			</form>
			<form method="post">
				<input type="hidden" name="page" value="cmlc-leads" />
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	public function get_help_tabs() {
		return array(
			array(
				'id'      => 'leads',
				'title'   => 'Lead tracking',
				'content' => 'View, search, delete, and export captured leads. Use the Export CSV button to download all leads.',
			),
		);
	}
}
