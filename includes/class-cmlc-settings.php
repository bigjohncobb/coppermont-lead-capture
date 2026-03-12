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
		add_action( 'admin_init', array( $this, 'register_settings' ) );
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
		);
	}

	/**
	 * Tab definitions.
	 *
	 * @return array<string,string>
	 */
	private function get_tabs() {
		return array(
			'general'   => 'General',
			'design'    => 'Design',
			'triggers'  => 'Triggers',
			'targeting' => 'Targeting',
			'schedule'  => 'Schedule',
			'analytics' => 'Analytics',
			'leads'     => 'Leads',
		);
	}

	/**
	 * Editable tabs.
	 *
	 * @return array<int,string>
	 */
	private function get_editable_tabs() {
		return array( 'general', 'design', 'triggers', 'targeting', 'schedule' );
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
	 * Handles tab save actions.
	 *
	 * @return void
	 */
	public function handle_tab_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['cmlc_action'] ) || 'save_tab' !== sanitize_key( wp_unslash( $_POST['cmlc_action'] ) ) ) {
			return;
		}

		$tab  = isset( $_POST['cmlc_tab'] ) ? sanitize_key( wp_unslash( $_POST['cmlc_tab'] ) ) : 'general';
		$tabs = $this->get_tabs();
		if ( ! isset( $tabs[ $tab ] ) || ! in_array( $tab, $this->get_editable_tabs(), true ) ) {
			$tab = 'general';
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'cmlc_save_' . $tab ) ) {
			add_settings_error( 'cmlc_settings', 'cmlc_nonce_error_' . $tab, 'Security check failed. Please try again.', 'error' );
			$this->redirect_to_editor( $tab );
		}

		$current  = self::get();
		$incoming = isset( $_POST['cmlc_settings'] ) ? wp_unslash( $_POST['cmlc_settings'] ) : array();
		$updated  = $this->sanitize_tab( $tab, is_array( $incoming ) ? $incoming : array(), $current );

		if ( 'schedule' === $tab && ! empty( $updated['schedule_start'] ) && ! empty( $updated['schedule_end'] ) && strtotime( $updated['schedule_end'] ) < strtotime( $updated['schedule_start'] ) ) {
			add_settings_error( 'cmlc_settings', 'cmlc_schedule_order', 'Schedule end must be later than schedule start.', 'error' );
			$this->redirect_to_editor( $tab );
		}

		update_option( self::OPTION_KEY, $updated );
		add_settings_error( 'cmlc_settings', 'cmlc_saved_' . $tab, $tabs[ $tab ] . ' settings saved.', 'updated' );
		$this->redirect_to_editor( $tab );
	}

	/**
	 * Redirects to campaign editor preserving active tab.
	 *
	 * @param string $tab Active tab.
	 * @return void
	 */
	private function redirect_to_editor( $tab ) {
		$url = add_query_arg(
			array(
				'page'     => 'cmlc-settings',
				'view'     => 'edit',
				'campaign' => 'default',
				'tab'      => $tab,
			),
			admin_url( 'options-general.php' )
		);
		wp_safe_redirect( $url );
		exit;
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
	 * Sanitizes one tab and merges with current settings.
	 *
	 * @param string              $tab Active tab.
	 * @param array<string,mixed> $input Raw tab input.
	 * @param array<string,mixed> $current Current settings.
	 * @return array<string,mixed>
	 */
	private function sanitize_tab( $tab, $input, $current ) {
		$updated = $current;

		switch ( $tab ) {
			case 'general':
				$updated['enabled']     = empty( $input['enabled'] ) ? 0 : 1;
				$updated['headline']    = sanitize_text_field( $input['headline'] ?? '' );
				$updated['body']        = sanitize_text_field( $input['body'] ?? '' );
				$updated['button_text'] = sanitize_text_field( $input['button_text'] ?? '' );
				break;
			case 'design':
				$defaults                     = self::defaults();
				$updated['bg_color']          = sanitize_hex_color( $input['bg_color'] ?? '' ) ?: $defaults['bg_color'];
				$updated['text_color']        = sanitize_hex_color( $input['text_color'] ?? '' ) ?: $defaults['text_color'];
				$updated['button_color']      = sanitize_hex_color( $input['button_color'] ?? '' ) ?: $defaults['button_color'];
				$updated['button_text_color'] = sanitize_hex_color( $input['button_text_color'] ?? '' ) ?: $defaults['button_text_color'];
				break;
			case 'triggers':
				$updated['scroll_trigger_percent']    = max( 0, min( 100, absint( $input['scroll_trigger_percent'] ?? 0 ) ) );
				$updated['time_delay_seconds']        = max( 0, absint( $input['time_delay_seconds'] ?? 0 ) );
				$updated['repetition_cooldown_hours'] = max( 1, absint( $input['repetition_cooldown_hours'] ?? 1 ) );
				$updated['max_views']                 = max( 1, absint( $input['max_views'] ?? 1 ) );
				$updated['enable_exit_intent']        = empty( $input['enable_exit_intent'] ) ? 0 : 1;
				$updated['enable_mobile']             = empty( $input['enable_mobile'] ) ? 0 : 1;
				break;
			case 'targeting':
				$updated['allowed_referrers'] = sanitize_text_field( $input['allowed_referrers'] ?? '' );
				$page_target_mode             = sanitize_text_field( $input['page_target_mode'] ?? 'all' );
				$updated['page_target_mode']  = in_array( $page_target_mode, array( 'all', 'include', 'exclude' ), true ) ? $page_target_mode : 'all';
				$updated['page_ids']          = sanitize_text_field( $input['page_ids'] ?? '' );
				break;
			case 'schedule':
				$updated['schedule_start'] = sanitize_text_field( $input['schedule_start'] ?? '' );
				$updated['schedule_end']   = sanitize_text_field( $input['schedule_end'] ?? '' );
				break;
		}

		return $updated;
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

		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'campaigns';

		echo '<div class="wrap">';
		echo '<h1>Coppermont Lead Capture</h1>';

		if ( 'edit' !== $view ) {
			$this->render_campaign_list();
			echo '</div>';
			return;
		}

		$tabs       = $this->get_tabs();
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		if ( ! isset( $tabs[ $active_tab ] ) ) {
			$active_tab = 'general';
		}

		$settings = self::get();
		$base_url = add_query_arg(
			array(
				'page'     => 'cmlc-settings',
				'view'     => 'edit',
				'campaign' => 'default',
			),
			admin_url( 'options-general.php' )
		);

		echo '<p><a href="' . esc_url( add_query_arg( array( 'page' => 'cmlc-settings' ), admin_url( 'options-general.php' ) ) ) . '">&larr; Back to campaigns</a></p>';
		echo '<h2>Editing Campaign: Default Campaign</h2>';
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $tab_slug => $tab_label ) {
			$tab_url    = add_query_arg( 'tab', $tab_slug, $base_url );
			$tab_class  = 'nav-tab';
			$tab_class .= $tab_slug === $active_tab ? ' nav-tab-active' : '';
			echo '<a class="' . esc_attr( $tab_class ) . '" href="' . esc_url( $tab_url ) . '">' . esc_html( $tab_label ) . '</a>';
		}
		echo '</h2>';

		settings_errors( 'cmlc_settings' );
		$this->render_tab_panel( $active_tab, $settings );
		echo '</div>';
	}

	/**
	 * Renders campaign list screen.
	 *
	 * @return void
	 */
	private function render_campaign_list() {
		$edit_url = add_query_arg(
			array(
				'page'     => 'cmlc-settings',
				'view'     => 'edit',
				'campaign' => 'default',
				'tab'      => 'general',
			),
			admin_url( 'options-general.php' )
		);
		?>
		<p>Manage lead-capture campaigns. Multi-campaign support can be expanded from this screen.</p>
		<table class="widefat striped" role="presentation">
			<thead>
				<tr>
					<th>Campaign</th>
					<th>Status</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>Default Campaign</td>
					<td><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> Active</td>
					<td><a class="button button-primary" href="<?php echo esc_url( $edit_url ); ?>">Edit Campaign</a></td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renders a tab content panel.
	 *
	 * @param string              $tab Active tab.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	private function render_tab_panel( $tab, $settings ) {
		if ( in_array( $tab, $this->get_editable_tabs(), true ) ) {
			?>
			<form method="post">
				<input type="hidden" name="cmlc_action" value="save_tab">
				<input type="hidden" name="cmlc_tab" value="<?php echo esc_attr( $tab ); ?>">
				<?php wp_nonce_field( 'cmlc_save_' . $tab ); ?>
				<table class="form-table" role="presentation">
					<?php $this->render_tab_fields( $tab, $settings ); ?>
				</table>
				<?php submit_button( 'Save ' . ucfirst( $tab ) ); ?>
			</form>
			<?php
			return;
		}

		if ( 'analytics' === $tab ) {
			?>
			<h2>Analytics</h2>
			<p><strong>Infobar Shows:</strong> <?php echo esc_html( (string) $settings['analytics_impressions'] ); ?></p>
			<p><strong>Email Submissions:</strong> <?php echo esc_html( (string) $settings['analytics_submissions'] ); ?></p>
			<?php
			return;
		}

		?>
		<h2>Leads</h2>
		<p>Lead records are not yet stored in this plugin. Add lead persistence to populate this screen.</p>
		<?php
	}

	/**
	 * Renders fields for the active tab.
	 *
	 * @param string              $tab Tab slug.
	 * @param array<string,mixed> $settings Settings array.
	 * @return void
	 */
	private function render_tab_fields( $tab, $settings ) {
		switch ( $tab ) {
			case 'general':
				?>
				<tr><th scope="row">Enable Infobar</th><td><input type="checkbox" name="cmlc_settings[enabled]" value="1" <?php checked( 1, $settings['enabled'] ); ?>></td></tr>
				<tr><th scope="row">Headline</th><td><input class="regular-text" name="cmlc_settings[headline]" value="<?php echo esc_attr( $settings['headline'] ); ?>"></td></tr>
				<tr><th scope="row">Body</th><td><input class="regular-text" name="cmlc_settings[body]" value="<?php echo esc_attr( $settings['body'] ); ?>"></td></tr>
				<tr><th scope="row">Button Text</th><td><input name="cmlc_settings[button_text]" value="<?php echo esc_attr( $settings['button_text'] ); ?>"></td></tr>
				<?php
				break;
			case 'design':
				?>
				<tr><th scope="row">Background Color</th><td><input type="color" name="cmlc_settings[bg_color]" value="<?php echo esc_attr( $settings['bg_color'] ); ?>"></td></tr>
				<tr><th scope="row">Text Color</th><td><input type="color" name="cmlc_settings[text_color]" value="<?php echo esc_attr( $settings['text_color'] ); ?>"></td></tr>
				<tr><th scope="row">Button Color</th><td><input type="color" name="cmlc_settings[button_color]" value="<?php echo esc_attr( $settings['button_color'] ); ?>"></td></tr>
				<tr><th scope="row">Button Text Color</th><td><input type="color" name="cmlc_settings[button_text_color]" value="<?php echo esc_attr( $settings['button_text_color'] ); ?>"></td></tr>
				<?php
				break;
			case 'triggers':
				?>
				<tr><th scope="row">Scroll Trigger %</th><td><input type="number" min="0" max="100" name="cmlc_settings[scroll_trigger_percent]" value="<?php echo esc_attr( $settings['scroll_trigger_percent'] ); ?>"></td></tr>
				<tr><th scope="row">Delay Seconds</th><td><input type="number" min="0" name="cmlc_settings[time_delay_seconds]" value="<?php echo esc_attr( $settings['time_delay_seconds'] ); ?>"></td></tr>
				<tr><th scope="row">Cooldown Hours</th><td><input type="number" min="1" name="cmlc_settings[repetition_cooldown_hours]" value="<?php echo esc_attr( $settings['repetition_cooldown_hours'] ); ?>"></td></tr>
				<tr><th scope="row">Max Views</th><td><input type="number" min="1" name="cmlc_settings[max_views]" value="<?php echo esc_attr( $settings['max_views'] ); ?>"></td></tr>
				<tr><th scope="row">Exit Intent Trigger</th><td><input type="checkbox" name="cmlc_settings[enable_exit_intent]" value="1" <?php checked( 1, $settings['enable_exit_intent'] ); ?>></td></tr>
				<tr><th scope="row">Enable on Mobile</th><td><input type="checkbox" name="cmlc_settings[enable_mobile]" value="1" <?php checked( 1, $settings['enable_mobile'] ); ?>></td></tr>
				<?php
				break;
			case 'targeting':
				?>
				<tr><th scope="row">Allowed Referrers</th><td><input class="regular-text" name="cmlc_settings[allowed_referrers]" value="<?php echo esc_attr( $settings['allowed_referrers'] ); ?>"><p class="description">Comma-separated domains.</p></td></tr>
				<tr><th scope="row">Page Targeting Mode</th><td><select name="cmlc_settings[page_target_mode]"><option value="all" <?php selected( 'all', $settings['page_target_mode'] ); ?>>All pages</option><option value="include" <?php selected( 'include', $settings['page_target_mode'] ); ?>>Include only listed IDs</option><option value="exclude" <?php selected( 'exclude', $settings['page_target_mode'] ); ?>>Exclude listed IDs</option></select></td></tr>
				<tr><th scope="row">Page IDs</th><td><input class="regular-text" name="cmlc_settings[page_ids]" value="<?php echo esc_attr( $settings['page_ids'] ); ?>"><p class="description">Comma-separated post/page IDs.</p></td></tr>
				<?php
				break;
			case 'schedule':
				?>
				<tr><th scope="row">Schedule Start</th><td><input type="datetime-local" name="cmlc_settings[schedule_start]" value="<?php echo esc_attr( $settings['schedule_start'] ); ?>"></td></tr>
				<tr><th scope="row">Schedule End</th><td><input type="datetime-local" name="cmlc_settings[schedule_end]" value="<?php echo esc_attr( $settings['schedule_end'] ); ?>"></td></tr>
				<?php
				break;
		}
	}
}
