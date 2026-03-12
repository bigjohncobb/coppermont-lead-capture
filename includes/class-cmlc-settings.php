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
	 * Data manager service.
	 *
	 * @var CMLC_Data_Manager
	 */
	private $data_manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->data_manager = new CMLC_Data_Manager();
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_cmlc_reset_analytics', array( $this, 'handle_reset_analytics' ) );
		add_action( 'admin_post_cmlc_delete_all_data', array( $this, 'handle_delete_all_data' ) );
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
		);
	}

	/**
	 * Adds settings page.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_menu_page(
			'Coppermont Lead Capture',
			'Lead Capture',
			'manage_options',
			'cmlc-settings',
			array( $this, 'render_page' ),
			'dashicons-email-alt',
			56
		);
	}

	/**
	 * Registers settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		register_setting(
			'cmlc_settings_group',
			self::OPTION_KEY,
			array( $this, 'sanitize' )
		);
	}

	/**
	 * Sanitizes settings.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ) {
		$defaults = self::defaults();
		$output   = wp_parse_args( is_array( $input ) ? $input : array(), $defaults );

		$output['enabled']                   = empty( $output['enabled'] ) ? 0 : 1;
		$output['headline']                  = sanitize_text_field( $output['headline'] );
		$output['body']                      = sanitize_text_field( $output['body'] );
		$output['button_text']               = sanitize_text_field( $output['button_text'] );
		$output['bg_color']                  = sanitize_hex_color( $output['bg_color'] ) ?: $defaults['bg_color'];
		$output['text_color']                = sanitize_hex_color( $output['text_color'] ) ?: $defaults['text_color'];
		$output['button_color']              = sanitize_hex_color( $output['button_color'] ) ?: $defaults['button_color'];
		$output['button_text_color']         = sanitize_hex_color( $output['button_text_color'] ) ?: $defaults['button_text_color'];
		$output['scroll_trigger_percent']    = max( 0, min( 100, absint( $output['scroll_trigger_percent'] ) ) );
		$output['time_delay_seconds']        = max( 0, absint( $output['time_delay_seconds'] ) );
		$output['repetition_cooldown_hours'] = max( 1, absint( $output['repetition_cooldown_hours'] ) );
		$output['max_views']                 = max( 1, absint( $output['max_views'] ) );
		$output['enable_exit_intent']        = empty( $output['enable_exit_intent'] ) ? 0 : 1;
		$output['enable_mobile']             = empty( $output['enable_mobile'] ) ? 0 : 1;
		$output['allowed_referrers']         = sanitize_text_field( $output['allowed_referrers'] );
		$output['page_target_mode']          = in_array( $output['page_target_mode'], array( 'all', 'include', 'exclude' ), true ) ? $output['page_target_mode'] : 'all';
		$output['page_ids']                  = sanitize_text_field( $output['page_ids'] );
		$output['schedule_start']            = sanitize_text_field( $output['schedule_start'] );
		$output['schedule_end']              = sanitize_text_field( $output['schedule_end'] );
		$output['analytics_impressions']     = isset( $output['analytics_impressions'] ) ? absint( $output['analytics_impressions'] ) : 0;
		$output['analytics_submissions']     = isset( $output['analytics_submissions'] ) ? absint( $output['analytics_submissions'] ) : 0;

		return $output;
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
	 * Handles analytics reset requests.
	 *
	 * @return void
	 */
	public function handle_reset_analytics() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'coppermont-lead-capture' ) );
		}

		check_admin_referer( 'cmlc_reset_analytics_action', 'cmlc_nonce' );

		$result = $this->data_manager->reset_analytics();
		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( 'error', $result->get_error_message() );
		}

		$this->redirect_with_notice( 'success', __( 'Analytics counters were reset successfully.', 'coppermont-lead-capture' ) );
	}

	/**
	 * Handles full plugin data deletion requests.
	 *
	 * @return void
	 */
	public function handle_delete_all_data() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'coppermont-lead-capture' ) );
		}

		check_admin_referer( 'cmlc_delete_all_data_action', 'cmlc_nonce' );

		$confirmation = isset( $_POST['cmlc_confirmation_phrase'] ) ? sanitize_text_field( wp_unslash( $_POST['cmlc_confirmation_phrase'] ) ) : '';
		if ( 'DELETE ALL DATA' !== $confirmation ) {
			$this->redirect_with_notice( 'error', __( 'Confirmation phrase did not match. No data was deleted.', 'coppermont-lead-capture' ) );
		}

		$result = $this->data_manager->delete_all_data();
		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( 'error', $result->get_error_message() );
		}

		$this->redirect_with_notice( 'success', __( 'All plugin data was deleted.', 'coppermont-lead-capture' ) );
	}

	/**
	 * Redirects back to settings page with an admin notice payload.
	 *
	 * @param string $type    Notice type.
	 * @param string $message Notice message.
	 * @return void
	 */
	private function redirect_with_notice( $type, $message ) {
		$allowed_type = in_array( $type, array( 'success', 'error' ), true ) ? $type : 'error';
		$url          = add_query_arg(
			array(
				'page'        => 'cmlc-settings',
				'cmlc_notice' => $allowed_type,
				'cmlc_msg'    => $message,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Renders admin notice for data management actions.
	 *
	 * @return void
	 */
	private function render_notice() {
		if ( empty( $_GET['cmlc_notice'] ) || empty( $_GET['cmlc_msg'] ) ) {
			return;
		}

		$type    = sanitize_key( wp_unslash( $_GET['cmlc_notice'] ) );
		$class   = 'success' === $type ? 'notice notice-success' : 'notice notice-error';
		$message = sanitize_text_field( wp_unslash( $_GET['cmlc_msg'] ) );
		?>
		<div class="<?php echo esc_attr( $class ); ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
		<?php
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

		$settings = self::get();
		?>
		<div class="wrap">
			<h1>Coppermont Lead Capture</h1>
			<?php $this->render_notice(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'cmlc_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row">Enable Infobar</th><td><input type="checkbox" name="cmlc_settings[enabled]" value="1" <?php checked( 1, $settings['enabled'] ); ?>></td></tr>
					<tr><th scope="row">Headline</th><td><input class="regular-text" name="cmlc_settings[headline]" value="<?php echo esc_attr( $settings['headline'] ); ?>"></td></tr>
					<tr><th scope="row">Body</th><td><input class="regular-text" name="cmlc_settings[body]" value="<?php echo esc_attr( $settings['body'] ); ?>"></td></tr>
					<tr><th scope="row">Button Text</th><td><input name="cmlc_settings[button_text]" value="<?php echo esc_attr( $settings['button_text'] ); ?>"></td></tr>
					<tr><th scope="row">Background Color</th><td><input type="color" name="cmlc_settings[bg_color]" value="<?php echo esc_attr( $settings['bg_color'] ); ?>"></td></tr>
					<tr><th scope="row">Text Color</th><td><input type="color" name="cmlc_settings[text_color]" value="<?php echo esc_attr( $settings['text_color'] ); ?>"></td></tr>
					<tr><th scope="row">Button Color</th><td><input type="color" name="cmlc_settings[button_color]" value="<?php echo esc_attr( $settings['button_color'] ); ?>"></td></tr>
					<tr><th scope="row">Button Text Color</th><td><input type="color" name="cmlc_settings[button_text_color]" value="<?php echo esc_attr( $settings['button_text_color'] ); ?>"></td></tr>
					<tr><th scope="row">Scroll Trigger %</th><td><input type="number" min="0" max="100" name="cmlc_settings[scroll_trigger_percent]" value="<?php echo esc_attr( $settings['scroll_trigger_percent'] ); ?>"></td></tr>
					<tr><th scope="row">Delay Seconds</th><td><input type="number" min="0" name="cmlc_settings[time_delay_seconds]" value="<?php echo esc_attr( $settings['time_delay_seconds'] ); ?>"></td></tr>
					<tr><th scope="row">Cooldown Hours</th><td><input type="number" min="1" name="cmlc_settings[repetition_cooldown_hours]" value="<?php echo esc_attr( $settings['repetition_cooldown_hours'] ); ?>"></td></tr>
					<tr><th scope="row">Max Views</th><td><input type="number" min="1" name="cmlc_settings[max_views]" value="<?php echo esc_attr( $settings['max_views'] ); ?>"></td></tr>
					<tr><th scope="row">Exit Intent Trigger</th><td><input type="checkbox" name="cmlc_settings[enable_exit_intent]" value="1" <?php checked( 1, $settings['enable_exit_intent'] ); ?>></td></tr>
					<tr><th scope="row">Enable on Mobile</th><td><input type="checkbox" name="cmlc_settings[enable_mobile]" value="1" <?php checked( 1, $settings['enable_mobile'] ); ?>></td></tr>
					<tr><th scope="row">Allowed Referrers</th><td><input class="regular-text" name="cmlc_settings[allowed_referrers]" value="<?php echo esc_attr( $settings['allowed_referrers'] ); ?>"><p class="description">Comma-separated domains.</p></td></tr>
					<tr><th scope="row">Page Targeting Mode</th><td><select name="cmlc_settings[page_target_mode]"><option value="all" <?php selected( 'all', $settings['page_target_mode'] ); ?>>All pages</option><option value="include" <?php selected( 'include', $settings['page_target_mode'] ); ?>>Include only listed IDs</option><option value="exclude" <?php selected( 'exclude', $settings['page_target_mode'] ); ?>>Exclude listed IDs</option></select></td></tr>
					<tr><th scope="row">Page IDs</th><td><input class="regular-text" name="cmlc_settings[page_ids]" value="<?php echo esc_attr( $settings['page_ids'] ); ?>"><p class="description">Comma-separated post/page IDs.</p></td></tr>
					<tr><th scope="row">Schedule Start</th><td><input type="datetime-local" name="cmlc_settings[schedule_start]" value="<?php echo esc_attr( $settings['schedule_start'] ); ?>"></td></tr>
					<tr><th scope="row">Schedule End</th><td><input type="datetime-local" name="cmlc_settings[schedule_end]" value="<?php echo esc_attr( $settings['schedule_end'] ); ?>"></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<h2>Analytics</h2>
			<p><strong>Infobar Shows:</strong> <?php echo esc_html( (string) $settings['analytics_impressions'] ); ?></p>
			<p><strong>Email Submissions:</strong> <?php echo esc_html( (string) $settings['analytics_submissions'] ); ?></p>

			<h2>Data Management</h2>
			<p class="description">Use these tools for deliberate administrative cleanup. They cannot be undone.</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:1rem;">
				<input type="hidden" name="action" value="cmlc_reset_analytics">
				<?php wp_nonce_field( 'cmlc_reset_analytics_action', 'cmlc_nonce' ); ?>
				<?php submit_button( 'Reset analytics only', 'secondary', 'submit', false ); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cmlc_delete_all_data">
				<?php wp_nonce_field( 'cmlc_delete_all_data_action', 'cmlc_nonce' ); ?>
				<p><label for="cmlc_confirmation_phrase"><strong>Type DELETE ALL DATA to confirm:</strong></label></p>
				<input id="cmlc_confirmation_phrase" name="cmlc_confirmation_phrase" class="regular-text" autocomplete="off">
				<p>
					<?php submit_button( 'Delete all plugin data (irreversible)', 'delete', 'submit', false ); ?>
				</p>
			</form>
		</div>
		<?php
	}
}
