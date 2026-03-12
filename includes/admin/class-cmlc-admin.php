<?php
/**
 * Admin navigation and page router.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CMLC_PATH . 'includes/admin/class-cmlc-admin-page.php';
require_once CMLC_PATH . 'includes/admin/pages/class-cmlc-admin-page-dashboard.php';
require_once CMLC_PATH . 'includes/admin/pages/class-cmlc-admin-page-campaigns.php';
require_once CMLC_PATH . 'includes/admin/pages/class-cmlc-admin-page-leads.php';
require_once CMLC_PATH . 'includes/admin/pages/class-cmlc-admin-page-analytics.php';
require_once CMLC_PATH . 'includes/admin/pages/class-cmlc-admin-page-settings.php';

class CMLC_Admin {
	/**
	 * Admin pages keyed by slug.
	 *
	 * @var array<string,CMLC_Admin_Page>
	 */
	private $pages = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_pages();
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	/**
	 * Registers page objects.
	 *
	 * @return void
	 */
	private function register_pages() {
		$page_objects = array(
			new CMLC_Admin_Page_Dashboard(),
			new CMLC_Admin_Page_Campaigns(),
			new CMLC_Admin_Page_Leads(),
			new CMLC_Admin_Page_Analytics(),
			new CMLC_Admin_Page_Settings(),
		);

		foreach ( $page_objects as $page ) {
			$this->pages[ $page->get_slug() ] = $page;
		}
	}

	/**
	 * Registers top-level menu and submenus.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			'Lead Capture Dashboard',
			'Lead Capture',
			'manage_options',
			'cmlc-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-megaphone',
			58
		);

		add_submenu_page( 'cmlc-dashboard', 'Dashboard', 'Dashboard', 'manage_options', 'cmlc-dashboard', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'cmlc-dashboard', 'Campaigns', 'Campaigns', 'manage_options', 'cmlc-campaigns', array( $this, 'render_campaigns' ) );
		add_submenu_page( 'cmlc-dashboard', 'Leads', 'Leads', 'manage_options', 'cmlc-leads', array( $this, 'render_leads' ) );
		add_submenu_page( 'cmlc-dashboard', 'Analytics', 'Analytics', 'manage_options', 'cmlc-analytics', array( $this, 'render_analytics' ) );
		add_submenu_page( 'cmlc-dashboard', 'Settings', 'Settings', 'manage_options', 'cmlc-settings', array( $this, 'render_settings' ) );
	}

	public function render_dashboard() {
		$this->render_page_by_slug( 'cmlc-dashboard' );
	}

	public function render_campaigns() {
		$this->render_page_by_slug( 'cmlc-campaigns' );
	}

	public function render_leads() {
		$this->render_page_by_slug( 'cmlc-leads' );
	}

	public function render_analytics() {
		$this->render_page_by_slug( 'cmlc-analytics' );
	}

	public function render_settings() {
		$this->render_page_by_slug( 'cmlc-settings' );
	}

	/**
	 * Renders shared admin shell and page content.
	 *
	 * @param string $slug Page slug.
	 * @return void
	 */
	private function render_page_by_slug( $slug ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'coppermont-lead-capture' ) );
		}

		if ( ! isset( $this->pages[ $slug ] ) ) {
			wp_die( esc_html__( 'Page not found.', 'coppermont-lead-capture' ) );
		}

		$page = $this->pages[ $slug ];
		$this->add_help_tab( $page );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( 'Lead Capture - ' . $page->get_title() ); ?></h1>
			<p><?php echo esc_html( $page->get_description() ); ?></p>
			<h2 class="nav-tab-wrapper">
				<?php foreach ( $this->pages as $tab ) : ?>
					<?php $is_current = $tab->get_slug() === $page->get_slug(); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $tab->get_slug() ) ); ?>" class="nav-tab <?php echo esc_attr( $is_current ? 'nav-tab-active' : '' ); ?>"><?php echo esc_html( $tab->get_title() ); ?></a>
				<?php endforeach; ?>
			</h2>
			<div class="cmlc-admin-content" style="margin-top: 18px; max-width: 980px;">
				<?php $page->render_content(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Adds contextual help information.
	 *
	 * @param CMLC_Admin_Page $page Current page object.
	 * @return void
	 */
	private function add_help_tab( CMLC_Admin_Page $page ) {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		$screen->remove_help_tabs();
		$screen->add_help_tab(
			array(
				'id'      => 'cmlc-help-' . $page->get_slug(),
				'title'   => 'Overview',
				'content' => $page->get_help_content(),
			)
		);
		$screen->set_help_sidebar( '<p><strong>Need setup help?</strong></p><p>Visit Settings to configure display rules, then monitor results in Analytics.</p>' );
	}
}
