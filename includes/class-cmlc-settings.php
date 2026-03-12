<?php
/**
 * Settings controller.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Settings {
	/**
	 * Option key.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'cmlc_settings';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
	}

	/**
	 * Default (legacy) settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'enabled'                   => 1,
			'headline'                  => 'Get weekly growth tips',
			'body'                      => 'Join our email list for practical lead generation insights.',
			'button_text'               => 'Subscribe',
			'bg_color'                  => '#1f2937',
			'text_color'                => '#ffffff',
			'button_color'              => '#f59e0b',
			'button_text_color'         => '#111827',
			'scroll_trigger_percent'    => 40,
			'time_delay_seconds'        => 8,
			'repetition_cooldown_hours' => 24,
			'max_views'                 => 3,
			'enable_exit_intent'        => 1,
			'enable_mobile'             => 1,
			'allowed_referrers'         => '',
			'page_target_mode'          => 'all',
			'page_ids'                  => '',
			'schedule_start'            => '',
			'schedule_end'              => '',
			'analytics_impressions'     => 0,
			'analytics_submissions'     => 0,
		);
	}

	/**
	 * Adds settings page.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			'Coppermont Lead Capture',
			'Lead Capture',
			'manage_options',
			'cmlc-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Retrieves legacy settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get() {
		$defaults = self::defaults();
		$saved    = get_option( self::OPTION_KEY, array() );

		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	/**
	 * Renders settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'coppermont-lead-capture' ) );
		}

		$campaigns = CMLC_Campaigns::get_all_campaigns();
		?>
		<div class="wrap">
			<h1>Coppermont Lead Capture</h1>
			<p>Lead capture campaigns are now managed as reusable campaign entities.</p>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . CMLC_Campaigns::POST_TYPE ) ); ?>">Manage Campaigns</a></p>
			<h2>Campaign Analytics</h2>
			<?php if ( empty( $campaigns ) ) : ?>
				<p>No campaigns found yet.</p>
			<?php else : ?>
				<ul>
					<?php foreach ( $campaigns as $campaign ) : ?>
						<li><strong><?php echo esc_html( $campaign['title'] ); ?></strong> (#<?php echo esc_html( (string) $campaign['id'] ); ?>) — Shows: <?php echo esc_html( (string) $campaign['analytics_impressions'] ); ?>, Submissions: <?php echo esc_html( (string) $campaign['analytics_submissions'] ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}
}
