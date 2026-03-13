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
	 * Notice query key.
	 *
	 * @var string
	 */
	const NOTICE_QUERY_KEY = 'cmlc_notice';

	/**
	 * Confirmation phrase for destructive deletion.
	 *
	 * @var string
	 */
	const DELETE_CONFIRMATION_PHRASE = 'DELETE ALL PLUGIN DATA';

	/**
	 * Whether hooks have been registered.
	 *
	 * @var bool
	 */
	private static $hooks_registered = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! self::$hooks_registered ) {
			add_action( 'admin_init', array( $this, 'handle_tab_save' ) );
			add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
			add_action( 'admin_notices', array( $this, 'render_admin_notice' ) );
			add_action( 'admin_post_cmlc_reset_analytics', array( $this, 'handle_reset_analytics' ) );
			add_action( 'admin_post_cmlc_delete_all_data', array( $this, 'handle_delete_all_data' ) );
			self::$hooks_registered = true;
		}
	}

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'enabled'                    => 1,
			'headline'                   => 'Get weekly growth tips',
			'body'                       => 'Join our email list for practical lead generation insights.',
			'button_text'                => 'Subscribe',
			'bg_color'                   => '#1f2937',
			'text_color'                 => '#ffffff',
			'button_color'               => '#f59e0b',
			'button_text_color'          => '#111827',
			'bar_width'                  => '960px',
			'bar_height'                 => '96px',
			'bar_opacity'                => 0.95,
			'scroll_trigger_percent'     => 40,
			'time_delay_seconds'         => 8,
			'repetition_cooldown_hours'  => 24,
			'max_views'                  => 3,
			'enable_exit_intent'         => 1,
			'enable_mobile'              => 1,
			'allowed_referrers'          => '',
			'page_target_mode'           => 'all',
			'page_ids'                   => '',
			'schedule_start'             => '',
			'schedule_end'               => '',
			'analytics_impressions'      => 0,
			'analytics_submissions'      => 0,
			'enable_captcha_validation'  => 0,
			'turnstile_enabled'          => 0,
			'turnstile_site_key'         => '',
			'turnstile_secret_key'       => '',
			'turnstile_strict_mode'      => 1,
			'analytics_retention_days'   => 180,
			'analytics_daily_impressions'=> 0,
			'analytics_daily_submissions'=> 0,
			'analytics_suspected_bot_traffic' => 0,
			'analytics_daily_suspected_bot' => 0,
			'analytics_daily_last_rollover_date' => '',
			'forms_bridge_enabled'               => 0,
			'forms_bridge_form_ids'              => '',
			'beehiiv_enabled'                    => 0,
			'beehiiv_api_key'                    => '',
			'beehiiv_publication_id'             => '',
			'ghl_enabled'                        => 0,
			'ghl_api_key'                        => '',
			'ghl_location_id'                    => '',
		);
	}

	/**
	 * Handles saving a single tab.
	 *
	 * @return void
	 */
	public function handle_tab_save() {
		if ( ! CMLC_Admin_Security::current_user_can_manage_options() ) {
			return;
		}

		if ( ! isset( $_GET['page'] ) || 'cmlc-settings' !== $_GET['page'] ) {
			return;
		}

		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['cmlc_tab_action'] ) ) {
			return;
		}

		$tab = isset( $_POST['cmlc_tab'] ) ? sanitize_key( wp_unslash( $_POST['cmlc_tab'] ) ) : 'general';
		if ( ! in_array( $tab, array_keys( $this->get_tabs() ), true ) ) {
			$tab = 'general';
		}

		check_admin_referer( 'cmlc_save_' . $tab, 'cmlc_nonce' );

		$settings = self::get();
		$raw      = isset( $_POST['cmlc_settings'] ) && is_array( $_POST['cmlc_settings'] ) ? wp_unslash( $_POST['cmlc_settings'] ) : array();

		switch ( $tab ) {
			case 'general':
				$settings = $this->sanitize_general_tab( $settings, $raw );
				break;
			case 'design':
				$settings = $this->sanitize_design_tab( $settings, $raw );
				break;
			case 'triggers':
				$settings = $this->sanitize_triggers_tab( $settings, $raw );
				break;
			case 'targeting':
				$settings = $this->sanitize_targeting_tab( $settings, $raw );
				break;
			case 'schedule':
				$settings = $this->sanitize_schedule_tab( $settings, $raw );
				break;
		}

		update_option( self::OPTION_KEY, $settings );
		add_settings_error( 'cmlc_settings', 'cmlc_settings_saved', __( 'Settings saved.', 'coppermont-lead-capture' ), 'updated' );

		$redirect = add_query_arg(
			array(
				'page' => 'cmlc-settings',
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);

		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Returns available settings tabs.
	 *
	 * @return array<string,string>
	 */
	private function get_tabs() {
		return array(
			'general'   => __( 'General', 'coppermont-lead-capture' ),
			'design'    => __( 'Design', 'coppermont-lead-capture' ),
			'triggers'  => __( 'Triggers', 'coppermont-lead-capture' ),
			'targeting' => __( 'Targeting', 'coppermont-lead-capture' ),
			'schedule'  => __( 'Schedule', 'coppermont-lead-capture' ),
			'analytics' => __( 'Analytics', 'coppermont-lead-capture' ),
			'leads'     => __( 'Leads', 'coppermont-lead-capture' ),
		);
	}

	/**
	 * Sanitizes general tab values.
	 *
	 * @param array<string,mixed> $settings Existing values.
	 * @param array<string,mixed> $raw Raw submitted values.
	 * @return array<string,mixed>
	 */
	private function sanitize_general_tab( $settings, $raw ) {
		$settings['enabled']     = empty( $raw['enabled'] ) ? 0 : 1;
		$settings['headline']    = isset( $raw['headline'] ) ? sanitize_text_field( $raw['headline'] ) : $settings['headline'];
		$settings['body']        = isset( $raw['body'] ) ? sanitize_text_field( $raw['body'] ) : $settings['body'];
		$settings['button_text'] = isset( $raw['button_text'] ) ? sanitize_text_field( $raw['button_text'] ) : $settings['button_text'];
		$settings['enable_captcha_validation'] = empty( $raw['enable_captcha_validation'] ) ? 0 : 1;
		$settings['turnstile_enabled']     = empty( $raw['turnstile_enabled'] ) ? 0 : 1;
		$settings['turnstile_site_key']    = isset( $raw['turnstile_site_key'] ) ? sanitize_text_field( $raw['turnstile_site_key'] ) : $settings['turnstile_site_key'];
		$settings['turnstile_secret_key']  = isset( $raw['turnstile_secret_key'] ) ? sanitize_text_field( $raw['turnstile_secret_key'] ) : $settings['turnstile_secret_key'];
		$settings['turnstile_strict_mode'] = empty( $raw['turnstile_strict_mode'] ) ? 0 : 1;

		$settings['analytics_retention_days'] = isset( $raw['analytics_retention_days'] ) ? absint( $raw['analytics_retention_days'] ) : $settings['analytics_retention_days'];
		$settings['analytics_retention_days'] = in_array( $settings['analytics_retention_days'], array( 90, 180, 365 ), true ) ? $settings['analytics_retention_days'] : 180;

		$settings['forms_bridge_enabled']  = empty( $raw['forms_bridge_enabled'] ) ? 0 : 1;
		$settings['forms_bridge_form_ids'] = isset( $raw['forms_bridge_form_ids'] ) ? sanitize_text_field( $raw['forms_bridge_form_ids'] ) : $settings['forms_bridge_form_ids'];

		$settings['beehiiv_enabled']        = empty( $raw['beehiiv_enabled'] ) ? 0 : 1;
		$settings['beehiiv_api_key']        = isset( $raw['beehiiv_api_key'] ) ? sanitize_text_field( $raw['beehiiv_api_key'] ) : $settings['beehiiv_api_key'];
		$settings['beehiiv_publication_id'] = isset( $raw['beehiiv_publication_id'] ) ? sanitize_text_field( $raw['beehiiv_publication_id'] ) : $settings['beehiiv_publication_id'];
		$settings['ghl_enabled']            = empty( $raw['ghl_enabled'] ) ? 0 : 1;
		$settings['ghl_api_key']            = isset( $raw['ghl_api_key'] ) ? sanitize_text_field( $raw['ghl_api_key'] ) : $settings['ghl_api_key'];
		$settings['ghl_location_id']        = isset( $raw['ghl_location_id'] ) ? sanitize_text_field( $raw['ghl_location_id'] ) : $settings['ghl_location_id'];

		if ( empty( $settings['headline'] ) ) {
			add_settings_error( 'cmlc_settings', 'cmlc_headline_required', __( 'Headline is required.', 'coppermont-lead-capture' ), 'error' );
		}

		return $settings;
	}

	/**
	 * Sanitizes design tab values.
	 *
	 * @param array<string,mixed> $settings Existing values.
	 * @param array<string,mixed> $raw Raw submitted values.
	 * @return array<string,mixed>
	 */
	private function sanitize_design_tab( $settings, $raw ) {
		$defaults                      = self::defaults();
		$settings['bg_color']         = isset( $raw['bg_color'] ) ? ( sanitize_hex_color( $raw['bg_color'] ) ?: $defaults['bg_color'] ) : $settings['bg_color'];
		$settings['text_color']       = isset( $raw['text_color'] ) ? ( sanitize_hex_color( $raw['text_color'] ) ?: $defaults['text_color'] ) : $settings['text_color'];
		$settings['button_color']     = isset( $raw['button_color'] ) ? ( sanitize_hex_color( $raw['button_color'] ) ?: $defaults['button_color'] ) : $settings['button_color'];
		$settings['button_text_color'] = isset( $raw['button_text_color'] ) ? ( sanitize_hex_color( $raw['button_text_color'] ) ?: $defaults['button_text_color'] ) : $settings['button_text_color'];
		$settings['bar_width']        = isset( $raw['bar_width'] ) ? $this->sanitize_design_dimension(
			$raw['bar_width'],
			$defaults['bar_width'],
			array(
				'px' => array( 280, 1400 ),
				'%'  => array( 35, 100 ),
				'vw' => array( 35, 100 ),
			)
		) : $settings['bar_width'];
		$settings['bar_height']       = isset( $raw['bar_height'] ) ? $this->sanitize_design_dimension(
			$raw['bar_height'],
			$defaults['bar_height'],
			array(
				'px' => array( 56, 360 ),
				'%'  => array( 8, 100 ),
				'vw' => array( 8, 100 ),
			)
		) : $settings['bar_height'];
		$settings['bar_opacity']      = isset( $raw['bar_opacity'] ) ? $this->sanitize_opacity( $raw['bar_opacity'], (float) $defaults['bar_opacity'] ) : $settings['bar_opacity'];

		return $settings;
	}

	/**
	 * Sanitizes trigger tab values.
	 *
	 * @param array<string,mixed> $settings Existing values.
	 * @param array<string,mixed> $raw Raw submitted values.
	 * @return array<string,mixed>
	 */
	private function sanitize_triggers_tab( $settings, $raw ) {
		$settings['scroll_trigger_percent']    = isset( $raw['scroll_trigger_percent'] ) ? max( 0, min( 100, absint( $raw['scroll_trigger_percent'] ) ) ) : $settings['scroll_trigger_percent'];
		$settings['time_delay_seconds']        = isset( $raw['time_delay_seconds'] ) ? max( 0, absint( $raw['time_delay_seconds'] ) ) : $settings['time_delay_seconds'];
		$settings['repetition_cooldown_hours'] = isset( $raw['repetition_cooldown_hours'] ) ? max( 1, absint( $raw['repetition_cooldown_hours'] ) ) : $settings['repetition_cooldown_hours'];
		$settings['max_views']                 = isset( $raw['max_views'] ) ? max( 1, absint( $raw['max_views'] ) ) : $settings['max_views'];
		$settings['enable_exit_intent']        = empty( $raw['enable_exit_intent'] ) ? 0 : 1;
		$settings['enable_mobile']             = empty( $raw['enable_mobile'] ) ? 0 : 1;

		return $settings;
	}

	/**
	 * Sanitizes targeting tab values.
	 *
	 * @param array<string,mixed> $settings Existing values.
	 * @param array<string,mixed> $raw Raw submitted values.
	 * @return array<string,mixed>
	 */
	private function sanitize_targeting_tab( $settings, $raw ) {
		$settings['allowed_referrers'] = isset( $raw['allowed_referrers'] ) ? sanitize_text_field( $raw['allowed_referrers'] ) : $settings['allowed_referrers'];
		$settings['page_target_mode']  = isset( $raw['page_target_mode'] ) && in_array( $raw['page_target_mode'], array( 'all', 'include', 'exclude' ), true ) ? $raw['page_target_mode'] : 'all';
		$settings['page_ids']          = isset( $raw['page_ids'] ) ? sanitize_text_field( $raw['page_ids'] ) : $settings['page_ids'];

		return $settings;
	}

	/**
	 * Sanitizes schedule tab values.
	 *
	 * @param array<string,mixed> $settings Existing values.
	 * @param array<string,mixed> $raw Raw submitted values.
	 * @return array<string,mixed>
	 */
	private function sanitize_schedule_tab( $settings, $raw ) {
		$settings['schedule_start'] = isset( $raw['schedule_start'] ) ? sanitize_text_field( $raw['schedule_start'] ) : $settings['schedule_start'];
		$settings['schedule_end']   = isset( $raw['schedule_end'] ) ? sanitize_text_field( $raw['schedule_end'] ) : $settings['schedule_end'];

		if ( ! empty( $settings['schedule_start'] ) && ! empty( $settings['schedule_end'] ) && strtotime( $settings['schedule_start'] ) > strtotime( $settings['schedule_end'] ) ) {
			add_settings_error( 'cmlc_settings', 'cmlc_schedule_invalid', __( 'Schedule end must be after schedule start.', 'coppermont-lead-capture' ), 'error' );
		}

		return $settings;
	}

	/**
	 * Sanitizes a design dimension that supports px, %, and vw units.
	 *
	 * @param mixed                $value  Raw value.
	 * @param string               $default Default fallback.
	 * @param array<string,array> $limits Value boundaries keyed by unit.
	 * @return string
	 */
	private function sanitize_design_dimension( $value, $default, $limits ) {
		$raw = strtolower( trim( (string) $value ) );

		if ( ! preg_match( '/^(\d+(?:\.\d+)?)(px|%|vw)$/', $raw, $matches ) ) {
			return $default;
		}

		$number = (float) $matches[1];
		$unit   = $matches[2];

		if ( ! isset( $limits[ $unit ] ) || ! is_array( $limits[ $unit ] ) ) {
			return $default;
		}

		$min = (float) $limits[ $unit ][0];
		$max = (float) $limits[ $unit ][1];
		if ( $min > $max ) {
			return $default;
		}

		$number = max( $min, min( $max, $number ) );
		$number = ( floor( $number ) === $number ) ? (string) (int) $number : rtrim( rtrim( sprintf( '%.2f', $number ), '0' ), '.' );

		return $number . $unit;
	}

	/**
	 * Sanitizes opacity between 0 and 1.
	 *
	 * @param mixed $value Raw value.
	 * @param float $default Default fallback.
	 * @return float
	 */
	private function sanitize_opacity( $value, $default ) {
		if ( ! is_numeric( $value ) ) {
			return $default;
		}

		$opacity = (float) $value;

		return max( 0, min( 1, $opacity ) );
	}

	/**
	 * Retrieves settings merged with defaults.
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
	/**
	 * Renders settings form content (without wrapper).
	 *
	 * @return void
	 */
	public function render_settings_content() {
		if ( ! CMLC_Admin_Security::current_user_can_manage_options() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'coppermont-lead-capture' ) );
		}

		$settings   = self::get();
		$tabs       = $this->get_tabs();
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';

		if ( ! isset( $tabs[ $active_tab ] ) ) {
			$active_tab = 'general';
		}
		?>
		<h2 class="nav-tab-wrapper" style="margin-top: 16px;">
			<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
				<?php $tab_class = ( $active_tab === $tab_key ) ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>
				<a class="<?php echo esc_attr( $tab_class ); ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'cmlc-settings', 'tab' => $tab_key ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $tab_label ); ?></a>
			<?php endforeach; ?>
		</h2>

		<?php settings_errors( 'cmlc_settings' ); ?>

			<?php if ( in_array( $active_tab, array( 'analytics', 'leads' ), true ) ) : ?>
				<?php if ( 'analytics' === $active_tab ) : ?>
					<?php $totals = CMLC_Analytics::get_totals(); ?>
					<?php
					$daily_counters = get_option( 'cmlc_daily_counters', array() );
					$daily_counters = is_array( $daily_counters ) ? $daily_counters : array();
					$today          = gmdate( 'Y-m-d' );
					$today_counts   = isset( $daily_counters[ $today ] ) && is_array( $daily_counters[ $today ] ) ? $daily_counters[ $today ] : array();
					?>
					<h2><?php esc_html_e( 'Analytics', 'coppermont-lead-capture' ); ?></h2>
					<p><strong>Infobar Shows (lifetime):</strong> <?php echo esc_html( (string) $totals['impressions'] ); ?></p>
					<p><strong>Infobar Shows (today):</strong> <?php echo esc_html( (string) absint( $today_counts['impressions'] ?? 0 ) ); ?></p>
					<p><strong>Email Submissions (lifetime):</strong> <?php echo esc_html( (string) $totals['submissions'] ); ?></p>
					<p><strong>Email Submissions (today):</strong> <?php echo esc_html( (string) absint( $today_counts['submissions'] ?? 0 ) ); ?></p>
					<p><strong>Suspected Bot Traffic (lifetime):</strong> <?php echo esc_html( (string) $settings['analytics_suspected_bot_traffic'] ); ?></p>
					<p><strong>Suspected Bot Traffic (today):</strong> <?php echo esc_html( (string) absint( $today_counts['suspected_bot'] ?? 0 ) ); ?></p>
					<p><strong>Conversion Rate:</strong> <?php echo esc_html( number_format_i18n( (float) $totals['conversion_rate'], 2 ) ); ?>%</p>
					<p class="description">Legacy counters are still updated for backward compatibility and included in totals via one-time migration offsets.</p>
					<?php $top_pages = CMLC_Analytics::get_top_pages( 5 ); ?>
					<?php if ( ! empty( $top_pages ) ) : ?>
						<h3>Top Pages</h3>
						<ul>
							<?php foreach ( $top_pages as $row ) : ?>
								<li><?php echo esc_html( 'Page #' . $row['label'] . ': ' . absint( $row['total'] ) ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<h3>Campaign Performance</h3>
					<?php $this->render_campaign_performance(); ?>
				<?php else : ?>
					<h2><?php esc_html_e( 'Leads', 'coppermont-lead-capture' ); ?></h2>
					<p><strong><?php esc_html_e( 'Total leads:', 'coppermont-lead-capture' ); ?></strong> <?php echo esc_html( (string) CMLC_Leads::count_leads() ); ?></p>
					<p class="description"><?php esc_html_e( 'Manage leads from the dedicated Leads page in the Lead Capture menu.', 'coppermont-lead-capture' ); ?></p>
				<?php endif; ?>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( add_query_arg( array( 'page' => 'cmlc-settings' ), admin_url( 'admin.php' ) ) ); ?>">
					<input type="hidden" name="cmlc_tab_action" value="1">
					<input type="hidden" name="cmlc_tab" value="<?php echo esc_attr( $active_tab ); ?>">
					<?php wp_nonce_field( 'cmlc_save_' . $active_tab, 'cmlc_nonce' ); ?>

					<table class="form-table" role="presentation">
						<?php if ( 'general' === $active_tab ) : ?>
							<tr><th scope="row">Enable Infobar</th><td><input type="checkbox" name="cmlc_settings[enabled]" value="1" <?php checked( 1, $settings['enabled'] ); ?>></td></tr>
							<tr><th scope="row">Headline</th><td><input class="regular-text" name="cmlc_settings[headline]" value="<?php echo esc_attr( $settings['headline'] ); ?>"></td></tr>
							<tr><th scope="row">Body</th><td><input class="regular-text" name="cmlc_settings[body]" value="<?php echo esc_attr( $settings['body'] ); ?>"></td></tr>
							<tr><th scope="row">Button Text</th><td><input name="cmlc_settings[button_text]" value="<?php echo esc_attr( $settings['button_text'] ); ?>"></td></tr>
							<tr><th scope="row">Enable CAPTCHA Validation</th><td><input type="checkbox" name="cmlc_settings[enable_captcha_validation]" value="1" <?php checked( 1, $settings['enable_captcha_validation'] ); ?>><p class="description">Uses the cmlc_validate_captcha filter for reCAPTCHA/hCaptcha providers.</p></td></tr>
							<tr>
								<th scope="row">Enable Turnstile</th>
								<td>
									<input type="checkbox" name="cmlc_settings[turnstile_enabled]" value="1" <?php checked( 1, $settings['turnstile_enabled'] ); ?>>
									<p class="description">Require Cloudflare Turnstile verification before accepting submissions from infobar and shortcode forms.</p>
								</td>
							</tr>
							<tr><th scope="row">Turnstile Site Key</th><td><input class="regular-text" name="cmlc_settings[turnstile_site_key]" value="<?php echo esc_attr( $settings['turnstile_site_key'] ); ?>"></td></tr>
							<tr><th scope="row">Turnstile Secret Key</th><td><input type="password" class="regular-text" name="cmlc_settings[turnstile_secret_key]" value="<?php echo esc_attr( $settings['turnstile_secret_key'] ); ?>" autocomplete="new-password"></td></tr>
							<tr>
								<th scope="row">Turnstile Strict Mode</th>
								<td>
									<input type="checkbox" name="cmlc_settings[turnstile_strict_mode]" value="1" <?php checked( 1, $settings['turnstile_strict_mode'] ); ?>>
									<p class="description">Fail closed when Turnstile verification service times out or errors. Recommended for production anti-spam protection.</p>
								</td>
							</tr>
							<tr><th scope="row">Analytics Retention</th><td><select name="cmlc_settings[analytics_retention_days]"><option value="90" <?php selected( 90, (int) $settings['analytics_retention_days'] ); ?>>90 days</option><option value="180" <?php selected( 180, (int) $settings['analytics_retention_days'] ); ?>>180 days</option><option value="365" <?php selected( 365, (int) $settings['analytics_retention_days'] ); ?>>365 days</option></select></td></tr>
							<tr><td colspan="2" style="padding: 0;"><hr style="margin: 8px 0;"></td></tr>
							<tr><td colspan="2" style="padding: 4px 10px 0;"><strong><?php esc_html_e( 'Coppermont Forms Integration', 'coppermont-lead-capture' ); ?></strong>
								<?php if ( ! is_plugin_active( 'coppermont-forms/coppermont-forms.php' ) ) : ?>
									<br><span style="color: #6b7280; font-weight: normal;">Coppermont Forms plugin is not active. Install and activate it to use this feature.</span>
								<?php endif; ?>
							</td></tr>
							<tr>
								<th scope="row">Capture Form Emails as Leads</th>
								<td>
									<input type="checkbox" name="cmlc_settings[forms_bridge_enabled]" value="1" <?php checked( 1, $settings['forms_bridge_enabled'] ); ?>>
									<p class="description">When enabled, email addresses submitted through Coppermont Forms are automatically saved as leads.</p>
								</td>
							</tr>
							<tr>
								<th scope="row">Limit to Form IDs</th>
								<td>
									<input class="regular-text" name="cmlc_settings[forms_bridge_form_ids]" value="<?php echo esc_attr( $settings['forms_bridge_form_ids'] ); ?>" placeholder="All forms">
									<p class="description">Comma-separated form IDs. Leave blank to capture leads from all forms.</p>
								</td>
							</tr>
							<tr><td colspan="2" style="padding: 0;"><hr style="margin: 8px 0;"></td></tr>
							<tr><td colspan="2" style="padding: 4px 10px 0;"><strong><?php esc_html_e( 'beehiiv', 'coppermont-lead-capture' ); ?></strong></td></tr>
							<tr>
								<th scope="row">Enable beehiiv</th>
								<td>
									<input type="checkbox" name="cmlc_settings[beehiiv_enabled]" value="1" <?php checked( 1, $settings['beehiiv_enabled'] ); ?>>
									<p class="description">Push every new lead to your beehiiv publication as a subscriber.</p>
								</td>
							</tr>
							<tr>
								<th scope="row">beehiiv API Key</th>
								<td><input type="password" class="regular-text" name="cmlc_settings[beehiiv_api_key]" value="<?php echo esc_attr( $settings['beehiiv_api_key'] ); ?>" autocomplete="new-password"></td>
							</tr>
							<tr>
								<th scope="row">Publication ID</th>
								<td>
									<input class="regular-text" name="cmlc_settings[beehiiv_publication_id]" value="<?php echo esc_attr( $settings['beehiiv_publication_id'] ); ?>" placeholder="pub_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
									<p class="description">Found in beehiiv Settings &rarr; Integrations &rarr; API.</p>
								</td>
							</tr>
							<tr><td colspan="2" style="padding: 0;"><hr style="margin: 8px 0;"></td></tr>
							<tr><td colspan="2" style="padding: 4px 10px 0;"><strong><?php esc_html_e( 'GoHighLevel', 'coppermont-lead-capture' ); ?></strong></td></tr>
							<tr>
								<th scope="row">Enable GoHighLevel</th>
								<td>
									<input type="checkbox" name="cmlc_settings[ghl_enabled]" value="1" <?php checked( 1, $settings['ghl_enabled'] ); ?>>
									<p class="description">Create a contact in GoHighLevel for every new lead.</p>
								</td>
							</tr>
							<tr>
								<th scope="row">GHL API Key</th>
								<td><input type="password" class="regular-text" name="cmlc_settings[ghl_api_key]" value="<?php echo esc_attr( $settings['ghl_api_key'] ); ?>" autocomplete="new-password"></td>
							</tr>
							<tr>
								<th scope="row">Location ID</th>
								<td>
									<input class="regular-text" name="cmlc_settings[ghl_location_id]" value="<?php echo esc_attr( $settings['ghl_location_id'] ); ?>" placeholder="Optional — for v2 API">
									<p class="description">Required for GHL API v2. Leave blank to use v1 API.</p>
								</td>
							</tr>
						<?php elseif ( 'design' === $active_tab ) : ?>
							<tr><th scope="row">Background Color</th><td><input type="color" name="cmlc_settings[bg_color]" value="<?php echo esc_attr( $settings['bg_color'] ); ?>"></td></tr>
							<tr><th scope="row">Text Color</th><td><input type="color" name="cmlc_settings[text_color]" value="<?php echo esc_attr( $settings['text_color'] ); ?>"></td></tr>
							<tr><th scope="row">Button Color</th><td><input type="color" name="cmlc_settings[button_color]" value="<?php echo esc_attr( $settings['button_color'] ); ?>"></td></tr>
							<tr><th scope="row">Button Text Color</th><td><input type="color" name="cmlc_settings[button_text_color]" value="<?php echo esc_attr( $settings['button_text_color'] ); ?>"></td></tr>
							<tr><th scope="row">Infobar Width</th><td><input name="cmlc_settings[bar_width]" value="<?php echo esc_attr( $settings['bar_width'] ); ?>"><p class="description">Use <code>px</code>, <code>%</code>, or <code>vw</code> (for example: <code>960px</code>, <code>90%</code>, <code>92vw</code>). On smaller screens, width is automatically constrained so the bar remains usable.</p></td></tr>
							<tr><th scope="row">Infobar Min Height</th><td><input name="cmlc_settings[bar_height]" value="<?php echo esc_attr( $settings['bar_height'] ); ?>"><p class="description">Supports <code>px</code>, <code>%</code>, or <code>vw</code>. This controls minimum height only, so content can still expand naturally on mobile.</p></td></tr>
							<tr><th scope="row">Infobar Background Opacity</th><td><input type="number" min="0" max="1" step="0.01" name="cmlc_settings[bar_opacity]" value="<?php echo esc_attr( (string) $settings['bar_opacity'] ); ?>"><p class="description">Set from <code>0</code> (fully transparent) to <code>1</code> (fully opaque). Works with your selected background color.</p></td></tr>
						<?php elseif ( 'triggers' === $active_tab ) : ?>
							<tr><th scope="row">Scroll Trigger %</th><td><input type="number" min="0" max="100" name="cmlc_settings[scroll_trigger_percent]" value="<?php echo esc_attr( $settings['scroll_trigger_percent'] ); ?>"></td></tr>
							<tr><th scope="row">Delay Seconds</th><td><input type="number" min="0" name="cmlc_settings[time_delay_seconds]" value="<?php echo esc_attr( $settings['time_delay_seconds'] ); ?>"></td></tr>
							<tr><th scope="row">Cooldown Hours</th><td><input type="number" min="1" name="cmlc_settings[repetition_cooldown_hours]" value="<?php echo esc_attr( $settings['repetition_cooldown_hours'] ); ?>"></td></tr>
							<tr><th scope="row">Max Views</th><td><input type="number" min="1" name="cmlc_settings[max_views]" value="<?php echo esc_attr( $settings['max_views'] ); ?>"></td></tr>
							<tr><th scope="row">Exit Intent Trigger</th><td><input type="checkbox" name="cmlc_settings[enable_exit_intent]" value="1" <?php checked( 1, $settings['enable_exit_intent'] ); ?>></td></tr>
							<tr><th scope="row">Enable on Mobile</th><td><input type="checkbox" name="cmlc_settings[enable_mobile]" value="1" <?php checked( 1, $settings['enable_mobile'] ); ?>></td></tr>
						<?php elseif ( 'targeting' === $active_tab ) : ?>
							<tr><th scope="row">Allowed Referrers</th><td><input class="regular-text" name="cmlc_settings[allowed_referrers]" value="<?php echo esc_attr( $settings['allowed_referrers'] ); ?>"><p class="description">Comma-separated domains with strict matching. Use <code>example.com</code> for exact host matches only. Use <code>*.example.com</code> to allow subdomains only (does not include the apex domain).</p></td></tr>
							<tr><th scope="row">Page Targeting Mode</th><td><select name="cmlc_settings[page_target_mode]"><option value="all" <?php selected( 'all', $settings['page_target_mode'] ); ?>>All pages</option><option value="include" <?php selected( 'include', $settings['page_target_mode'] ); ?>>Include only listed IDs</option><option value="exclude" <?php selected( 'exclude', $settings['page_target_mode'] ); ?>>Exclude listed IDs</option></select></td></tr>
							<tr><th scope="row">Page IDs</th><td><input class="regular-text" name="cmlc_settings[page_ids]" value="<?php echo esc_attr( $settings['page_ids'] ); ?>"><p class="description">Comma-separated post/page IDs.</p></td></tr>
						<?php elseif ( 'schedule' === $active_tab ) : ?>
							<tr><th scope="row">Schedule Start</th><td><input type="datetime-local" name="cmlc_settings[schedule_start]" value="<?php echo esc_attr( $settings['schedule_start'] ); ?>"></td></tr>
							<tr><th scope="row">Schedule End</th><td><input type="datetime-local" name="cmlc_settings[schedule_end]" value="<?php echo esc_attr( $settings['schedule_end'] ); ?>"></td></tr>
						<?php endif; ?>
					</table>
					<?php submit_button(); ?>
				</form>
			<?php endif; ?>
		<?php
	}

	/**
	 * Renders full standalone settings page (with wrapper).
	 *
	 * @return void
	 */
	public function render_page() {
		?>
		<div class="wrap">
			<h1>Coppermont Lead Capture</h1>
			<?php $this->render_settings_content(); ?>
		</div>
		<?php
	}

	/**
	 * Registers admin dashboard widget.
	 *
	 * @return void
	 */
	public function register_dashboard_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget( 'cmlc_campaign_performance', __( 'Lead Campaign Performance', 'coppermont-lead-capture' ), array( $this, 'render_campaign_performance' ) );
	}

	/**
	 * Renders campaign performance table.
	 *
	 * @return void
	 */
	public function render_campaign_performance() {
		$rows = class_exists( 'CMLC_Analytics' ) ? CMLC_Analytics::get_campaign_performance() : array();

		if ( empty( $rows ) ) {
			echo '<p>No campaigns available yet. Create one under Lead Capture &rarr; Campaigns.</p>';
			return;
		}

		$active_count   = count( array_filter( $rows, static function( $row ) { return 'active' === $row['status']; } ) );
		$inactive_count = count( $rows ) - $active_count;
		echo '<p><strong>Active campaigns:</strong> ' . esc_html( (string) $active_count ) . ' &nbsp; <strong>Inactive campaigns:</strong> ' . esc_html( (string) $inactive_count ) . '</p>';

		echo '<table class="widefat striped"><thead><tr><th>Campaign</th><th>Status</th><th>Impressions</th><th>Submissions</th><th>Conversion Rate</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( $row['title'] ) . '</td>';
			echo '<td>' . esc_html( ucfirst( $row['status'] ) ) . '</td>';
			echo '<td>' . esc_html( (string) $row['impressions'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['submissions'] ) . '</td>';
			echo '<td>' . esc_html( number_format_i18n( (float) $row['conversion'], 2 ) ) . '%</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Adds data management submenu page under the Lead Capture menu.
	 *
	 * @return void
	 */
	public function add_data_management_page() {
		add_submenu_page(
			'cmlc-dashboard',
			'Lead Capture Data Management',
			'Data Management',
			'manage_options',
			'cmlc-data-management',
			array( $this, 'render_data_management_page' )
		);
	}

	/**
	 * Renders data management page.
	 *
	 * @return void
	 */
	public function render_data_management_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'coppermont-lead-capture' ) );
		}
		?>
		<div class="wrap">
			<h1>Lead Capture Data Management</h1>
			<p>Use these controls only when you explicitly want to remove analytics or plugin data.</p>

			<h2>Reset Analytics Only</h2>
			<p>This action keeps your plugin settings and only resets analytics counters to zero.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cmlc_reset_analytics">
				<?php wp_nonce_field( 'cmlc_reset_analytics_action', 'cmlc_reset_analytics_nonce' ); ?>
				<?php submit_button( 'Reset analytics only', 'secondary', 'submit', false ); ?>
			</form>

			<hr>

			<h2>Delete All Plugin Data (Irreversible)</h2>
			<p>This permanently removes plugin settings and analytics data. This cannot be undone.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cmlc_delete_all_data">
				<?php wp_nonce_field( 'cmlc_delete_all_data_action', 'cmlc_delete_all_data_nonce' ); ?>
				<p>
					<label for="cmlc-delete-confirmation"><strong>Type <?php echo esc_html( self::DELETE_CONFIRMATION_PHRASE ); ?> to confirm:</strong></label>
					<br>
					<input id="cmlc-delete-confirmation" class="regular-text" type="text" name="cmlc_delete_confirmation" value="" autocomplete="off" required>
				</p>
				<?php submit_button( 'Delete all plugin data (irreversible)', 'delete', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handles analytics reset.
	 *
	 * @return void
	 */
	public function handle_reset_analytics() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'coppermont-lead-capture' ) );
		}

		check_admin_referer( 'cmlc_reset_analytics_action', 'cmlc_reset_analytics_nonce' );

		$result = CMLC_Data_Manager::reset_analytics_only();
		$notice = true === $result ? 'analytics_reset_success' : 'analytics_reset_failed';

		$this->redirect_to_data_management( $notice );
	}

	/**
	 * Handles full data deletion.
	 *
	 * @return void
	 */
	public function handle_delete_all_data() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'coppermont-lead-capture' ) );
		}

		check_admin_referer( 'cmlc_delete_all_data_action', 'cmlc_delete_all_data_nonce' );

		$confirmation = isset( $_POST['cmlc_delete_confirmation'] ) ? sanitize_text_field( wp_unslash( $_POST['cmlc_delete_confirmation'] ) ) : '';
		if ( self::DELETE_CONFIRMATION_PHRASE !== $confirmation ) {
			$this->redirect_to_data_management( 'delete_confirmation_mismatch' );
			return;
		}

		$result = CMLC_Data_Manager::delete_all_plugin_data();
		$notice = true === $result ? 'data_delete_success' : 'data_delete_failed';

		$this->redirect_to_data_management( $notice );
	}

	/**
	 * Redirects back to data management page with notice query arg.
	 *
	 * @param string $notice Notice code.
	 * @return void
	 */
	private function redirect_to_data_management( $notice ) {
		$url = add_query_arg(
			array(
				'page'                   => 'cmlc-data-management',
				self::NOTICE_QUERY_KEY   => $notice,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Renders admin notice for completed data management actions.
	 *
	 * @return void
	 */
	public function render_admin_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['page'] ) || 'cmlc-data-management' !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		$notice = isset( $_GET[ self::NOTICE_QUERY_KEY ] ) ? sanitize_key( wp_unslash( $_GET[ self::NOTICE_QUERY_KEY ] ) ) : '';
		if ( empty( $notice ) ) {
			return;
		}

		$notices = array(
			'analytics_reset_success'      => array( 'success', 'Analytics counters were reset successfully.' ),
			'analytics_reset_failed'       => array( 'error', 'Analytics counters could not be reset. No data was deleted.' ),
			'data_delete_success'          => array( 'success', 'All plugin data was deleted successfully.' ),
			'data_delete_failed'           => array( 'error', 'Plugin data deletion failed. Please verify database permissions and try again.' ),
			'delete_confirmation_mismatch' => array( 'warning', 'Confirmation phrase did not match. No data was deleted.' ),
		);

		if ( ! isset( $notices[ $notice ] ) ) {
			return;
		}

		list( $type, $message ) = $notices[ $notice ];
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}
}
