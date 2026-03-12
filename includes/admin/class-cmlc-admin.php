<?php
/**
 * Admin menu controller.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Admin {
	/**
	 * Settings controller.
	 *
	 * @var CMLC_Settings
	 */
	private $settings;

	/**
	 * Analytics page controller.
	 *
	 * @var CMLC_Analytics_Page
	 */
	private $analytics_page;

	/**
	 * Analytics page hook suffix.
	 *
	 * @var string
	 */
	private $analytics_hook = '';

	/**
	 * Constructor.
	 *
	 * @param CMLC_Settings       $settings Settings controller.
	 * @param CMLC_Analytics_Page $analytics_page Analytics page controller.
	 */
	public function __construct( CMLC_Settings $settings, CMLC_Analytics_Page $analytics_page ) {
		$this->settings       = $settings;
		$this->analytics_page = $analytics_page;

		add_action( 'admin_menu', array( $this, 'register_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Registers top-level and submenu pages.
	 *
	 * @return void
	 */
	public function register_menu_pages() {
		add_menu_page(
			'Coppermont Lead Capture',
			'Lead Capture',
			'manage_options',
			'cmlc-dashboard',
			array( $this, 'render_dashboard_page' ),
			'dashicons-chart-line',
			56
		);

		add_submenu_page(
			'cmlc-dashboard',
			'Dashboard',
			'Dashboard',
			'manage_options',
			'cmlc-dashboard',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'cmlc-dashboard',
			'Campaigns',
			'Campaigns',
			'manage_options',
			'cmlc-campaigns',
			array( $this, 'render_campaigns_page' )
		);

		add_submenu_page(
			'cmlc-dashboard',
			'Leads',
			'Leads',
			'manage_options',
			'cmlc-leads',
			array( $this, 'render_leads_page' )
		);

		$this->analytics_hook = add_submenu_page(
			'cmlc-dashboard',
			'Analytics',
			'Analytics',
			'manage_options',
			'cmlc-analytics',
			array( $this->analytics_page, 'render_page' )
		);

		add_submenu_page(
			'cmlc-dashboard',
			'Settings',
			'Settings',
			'manage_options',
			'cmlc-settings',
			array( $this->settings, 'render_page' )
		);
	}

	/**
	 * Enqueues analytics-only admin assets.
	 *
	 * @param string $hook Current admin screen hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( $this->analytics_hook !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'cmlc-admin-analytics',
			CMLC_URL . 'assets/css/admin-analytics.css',
			array(),
			CMLC_VERSION
		);

		wp_enqueue_script(
			'cmlc-admin-analytics',
			CMLC_URL . 'assets/js/admin-analytics.js',
			array(),
			CMLC_VERSION,
			true
		);
	}

	/**
	 * Renders dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'coppermont-lead-capture' ) );
		}

		$settings        = CMLC_Settings::get();
		$impressions     = (int) $settings['analytics_impressions'];
		$submissions     = (int) $settings['analytics_submissions'];
		$conversion_rate = $impressions > 0 ? round( ( $submissions / $impressions ) * 100, 2 ) : 0;
		?>
		<div class="wrap">
			<h1>Lead Capture Dashboard</h1>
			<p>Use the left navigation to manage campaigns, leads, analytics, and settings.</p>
			<ul>
				<li><strong>Total Impressions:</strong> <?php echo esc_html( (string) $impressions ); ?></li>
				<li><strong>Total Submissions:</strong> <?php echo esc_html( (string) $submissions ); ?></li>
				<li><strong>Conversion Rate:</strong> <?php echo esc_html( (string) $conversion_rate ); ?>%</li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Renders campaigns page placeholder.
	 *
	 * @return void
	 */
	public function render_campaigns_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'coppermont-lead-capture' ) );
		}
		?>
		<div class="wrap"><h1>Campaigns</h1><p>Campaign management controls will appear here.</p></div>
		<?php
	}

	/**
	 * Renders leads page placeholder.
	 *
	 * @return void
	 */
	public function render_leads_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'coppermont-lead-capture' ) );
		}
		?>
		<div class="wrap"><h1>Leads</h1><p>Lead management controls will appear here.</p></div>
		<?php
	}
}
