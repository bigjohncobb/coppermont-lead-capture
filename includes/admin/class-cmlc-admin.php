<?php
/**
 * Admin menu controller.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Admin {
	/**
	 * Admin capability.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Top-level menu slug.
	 *
	 * @var string
	 */
	const MENU_SLUG = 'cmlc-dashboard';

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
	 * Analytics hook suffix.
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

		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Registers top-level and submenu pages.
	 *
	 * @return void
	 */
	public function register_menus() {
		add_menu_page(
			__( 'Lead Capture Dashboard', 'coppermont-lead-capture' ),
			__( 'Lead Capture', 'coppermont-lead-capture' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_dashboard_page' ),
			'dashicons-chart-area',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'coppermont-lead-capture' ),
			__( 'Dashboard', 'coppermont-lead-capture' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Campaigns', 'coppermont-lead-capture' ),
			__( 'Campaigns', 'coppermont-lead-capture' ),
			self::CAPABILITY,
			'cmlc-campaigns',
			array( $this, 'render_campaigns_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Leads', 'coppermont-lead-capture' ),
			__( 'Leads', 'coppermont-lead-capture' ),
			self::CAPABILITY,
			'cmlc-leads',
			array( $this, 'render_leads_page' )
		);

		$this->analytics_hook = add_submenu_page(
			self::MENU_SLUG,
			__( 'Analytics', 'coppermont-lead-capture' ),
			__( 'Analytics', 'coppermont-lead-capture' ),
			self::CAPABILITY,
			'cmlc-analytics',
			array( $this->analytics_page, 'render_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'coppermont-lead-capture' ),
			__( 'Settings', 'coppermont-lead-capture' ),
			self::CAPABILITY,
			'cmlc-settings',
			array( $this->settings, 'render_page' )
		);
	}

	/**
	 * Enqueues analytics-only assets.
	 *
	 * @param string $hook_suffix Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( empty( $this->analytics_hook ) || $hook_suffix !== $this->analytics_hook ) {
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
		$this->render_placeholder_page(
			__( 'Dashboard', 'coppermont-lead-capture' ),
			__( 'Use this area for high-level lead capture health, campaign status, and quick actions.', 'coppermont-lead-capture' )
		);
	}

	/**
	 * Renders campaigns page.
	 *
	 * @return void
	 */
	public function render_campaigns_page() {
		$this->render_placeholder_page(
			__( 'Campaigns', 'coppermont-lead-capture' ),
			__( 'Campaign management will appear here.', 'coppermont-lead-capture' )
		);
	}

	/**
	 * Renders leads page.
	 *
	 * @return void
	 */
	public function render_leads_page() {
		$this->render_placeholder_page(
			__( 'Leads', 'coppermont-lead-capture' ),
			__( 'Lead records and exports will appear here.', 'coppermont-lead-capture' )
		);
	}

	/**
	 * Renders placeholder submenu pages.
	 *
	 * @param string $title Page title.
	 * @param string $description Page description.
	 * @return void
	 */
	private function render_placeholder_page( $title, $description ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'coppermont-lead-capture' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $title ); ?></h1>
			<p><?php echo esc_html( $description ); ?></p>
		</div>
		<?php
	}
}
