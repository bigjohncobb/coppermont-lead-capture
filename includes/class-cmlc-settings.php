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
		add_action( 'admin_init', array( $this, 'handle_tab_save' ) );
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
	 * Handles saving a single tab.
	 *
	 * @return void
	 */
	public function handle_tab_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
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
			admin_url( 'options-general.php' )
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
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'coppermont-lead-capture' ) );
		}

		$settings   = self::get();
		$tabs       = $this->get_tabs();
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';

		if ( ! isset( $tabs[ $active_tab ] ) ) {
			$active_tab = 'general';
		}
		?>
		<div class="wrap">
			<h1>Coppermont Lead Capture</h1>
			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
					<?php $tab_class = ( $active_tab === $tab_key ) ? 'nav-tab nav-tab-active' : 'nav-tab'; ?>
					<a class="<?php echo esc_attr( $tab_class ); ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'cmlc-settings', 'tab' => $tab_key ), admin_url( 'options-general.php' ) ) ); ?>"><?php echo esc_html( $tab_label ); ?></a>
				<?php endforeach; ?>
			</h2>

			<?php settings_errors( 'cmlc_settings' ); ?>

			<?php if ( in_array( $active_tab, array( 'analytics', 'leads' ), true ) ) : ?>
				<?php if ( 'analytics' === $active_tab ) : ?>
					<h2><?php esc_html_e( 'Analytics', 'coppermont-lead-capture' ); ?></h2>
					<p><strong>Infobar Shows:</strong> <?php echo esc_html( (string) $settings['analytics_impressions'] ); ?></p>
					<p><strong>Email Submissions:</strong> <?php echo esc_html( (string) $settings['analytics_submissions'] ); ?></p>
				<?php else : ?>
					<h2><?php esc_html_e( 'Leads', 'coppermont-lead-capture' ); ?></h2>
					<p><?php esc_html_e( 'Lead records are managed in your integrations or custom storage layer.', 'coppermont-lead-capture' ); ?></p>
				<?php endif; ?>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( add_query_arg( array( 'page' => 'cmlc-settings' ), admin_url( 'options-general.php' ) ) ); ?>">
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
						<?php elseif ( 'design' === $active_tab ) : ?>
							<tr><th scope="row">Background Color</th><td><input type="color" name="cmlc_settings[bg_color]" value="<?php echo esc_attr( $settings['bg_color'] ); ?>"></td></tr>
							<tr><th scope="row">Text Color</th><td><input type="color" name="cmlc_settings[text_color]" value="<?php echo esc_attr( $settings['text_color'] ); ?>"></td></tr>
							<tr><th scope="row">Button Color</th><td><input type="color" name="cmlc_settings[button_color]" value="<?php echo esc_attr( $settings['button_color'] ); ?>"></td></tr>
							<tr><th scope="row">Button Text Color</th><td><input type="color" name="cmlc_settings[button_text_color]" value="<?php echo esc_attr( $settings['button_text_color'] ); ?>"></td></tr>
						<?php elseif ( 'triggers' === $active_tab ) : ?>
							<tr><th scope="row">Scroll Trigger %</th><td><input type="number" min="0" max="100" name="cmlc_settings[scroll_trigger_percent]" value="<?php echo esc_attr( $settings['scroll_trigger_percent'] ); ?>"></td></tr>
							<tr><th scope="row">Delay Seconds</th><td><input type="number" min="0" name="cmlc_settings[time_delay_seconds]" value="<?php echo esc_attr( $settings['time_delay_seconds'] ); ?>"></td></tr>
							<tr><th scope="row">Cooldown Hours</th><td><input type="number" min="1" name="cmlc_settings[repetition_cooldown_hours]" value="<?php echo esc_attr( $settings['repetition_cooldown_hours'] ); ?>"></td></tr>
							<tr><th scope="row">Max Views</th><td><input type="number" min="1" name="cmlc_settings[max_views]" value="<?php echo esc_attr( $settings['max_views'] ); ?>"></td></tr>
							<tr><th scope="row">Exit Intent Trigger</th><td><input type="checkbox" name="cmlc_settings[enable_exit_intent]" value="1" <?php checked( 1, $settings['enable_exit_intent'] ); ?>></td></tr>
							<tr><th scope="row">Enable on Mobile</th><td><input type="checkbox" name="cmlc_settings[enable_mobile]" value="1" <?php checked( 1, $settings['enable_mobile'] ); ?>></td></tr>
						<?php elseif ( 'targeting' === $active_tab ) : ?>
							<tr><th scope="row">Allowed Referrers</th><td><input class="regular-text" name="cmlc_settings[allowed_referrers]" value="<?php echo esc_attr( $settings['allowed_referrers'] ); ?>"><p class="description">Comma-separated domains.</p></td></tr>
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
		</div>
		<?php
	}
}
