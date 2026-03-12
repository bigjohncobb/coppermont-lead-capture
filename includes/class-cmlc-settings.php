<?php
/**
 * Settings controller.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Settings {
	/**
	 * Option key retained for backward compatibility.
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
	 * Retrieves legacy settings merged with campaign defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get() {
		$defaults = CMLC_Campaigns::defaults();
		$saved    = get_option( self::OPTION_KEY, array() );

		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
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
	 * Renders settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'coppermont-lead-capture' ) );
		}

		$active_campaign = CMLC_Campaigns::get_active_campaign();
		$campaigns       = get_posts(
			array(
				'post_type'      => 'cmlc_campaign',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$total_impressions = 0;
		$total_submissions = 0;
		foreach ( $campaigns as $campaign_post ) {
			$campaign            = CMLC_Campaigns::get_campaign( $campaign_post->ID );
			$total_impressions += isset( $campaign['analytics_impressions'] ) ? absint( $campaign['analytics_impressions'] ) : 0;
			$total_submissions += isset( $campaign['analytics_submissions'] ) ? absint( $campaign['analytics_submissions'] ) : 0;
		}
		?>
		<div class="wrap">
			<h1>Coppermont Lead Capture</h1>
			<p>Campaign settings are now managed under <strong>Infobar Campaigns</strong> in the admin menu.</p>
			<?php if ( $active_campaign ) : ?>
				<p><strong>Active campaign:</strong> <?php echo esc_html( get_the_title( (int) $active_campaign['campaign_id'] ) ); ?> (ID: <?php echo esc_html( (string) $active_campaign['campaign_id'] ); ?>)</p>
			<?php endif; ?>
			<h2>Shortcode usage</h2>
			<p><code>[coppermont_infobar id="123"]</code></p>
			<h2>Available Campaign IDs</h2>
			<ul>
				<?php foreach ( $campaigns as $campaign_post ) : ?>
					<li><?php echo esc_html( $campaign_post->post_title ); ?> — <code><?php echo esc_html( (string) $campaign_post->ID ); ?></code></li>
				<?php endforeach; ?>
			</ul>
			<h2>Analytics</h2>
			<p><strong>Total Infobar Shows:</strong> <?php echo esc_html( (string) $total_impressions ); ?></p>
			<p><strong>Total Email Submissions:</strong> <?php echo esc_html( (string) $total_submissions ); ?></p>
		</div>
		<?php
	}
}
