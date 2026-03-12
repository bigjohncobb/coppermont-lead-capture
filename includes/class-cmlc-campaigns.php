<?php
/**
 * Campaign entity registration and management.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Campaigns {
	/**
	 * Campaign post type.
	 */
	const POST_TYPE = 'cmlc_campaign';

	/**
	 * Migration flag option.
	 */
	const MIGRATION_OPTION = 'cmlc_campaigns_migrated';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta_box' ) );
		add_action( 'admin_notices', array( $this, 'show_migration_notice' ) );
	}

	/**
	 * Registers campaign post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Infobar Campaigns', 'coppermont-lead-capture' ),
					'singular_name' => __( 'Infobar Campaign', 'coppermont-lead-capture' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'menu_icon'    => 'dashicons-megaphone',
				'menu_position'=> 59,
				'supports'     => array( 'title' ),
				'show_in_menu' => true,
			)
		);

		$this->maybe_migrate_legacy_settings();
	}

	/**
	 * Default campaign values.
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
			'priority'                  => 10,
			'bar_width'                 => 100,
			'bar_opacity'               => 100,
			'analytics_impressions'     => 0,
			'analytics_submissions'     => 0,
		);
	}

	/**
	 * Gets campaign config by post id.
	 *
	 * @param int $campaign_id Campaign post id.
	 * @return array<string,mixed>|null
	 */
	public static function get_campaign( $campaign_id ) {
		$post = get_post( $campaign_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}

		$defaults = self::defaults();
		$data     = array();

		foreach ( $defaults as $key => $default ) {
			$value = get_post_meta( $campaign_id, '_cmlc_' . $key, true );
			$data[ $key ] = '' === $value ? $default : $value;
		}

		$data['id']    = (int) $campaign_id;
		$data['title'] = get_the_title( $campaign_id );

		return self::sanitize_campaign( $data );
	}

	/**
	 * Sanitizes campaign values.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return array<string,mixed>
	 */
	public static function sanitize_campaign( $input ) {
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
		$output['priority']                  = max( 0, absint( $output['priority'] ) );
		$output['bar_width']                 = max( 40, min( 100, absint( $output['bar_width'] ) ) );
		$output['bar_opacity']               = max( 10, min( 100, absint( $output['bar_opacity'] ) ) );
		$output['analytics_impressions']     = absint( $output['analytics_impressions'] );
		$output['analytics_submissions']     = absint( $output['analytics_submissions'] );

		return $output;
	}

	/**
	 * Gets all campaigns.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_all_campaigns() {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
			)
		);

		$campaigns = array();
		foreach ( $ids as $id ) {
			$campaign = self::get_campaign( (int) $id );
			if ( $campaign ) {
				$campaigns[] = $campaign;
			}
		}

		usort(
			$campaigns,
			static function ( $a, $b ) {
				if ( (int) $a['priority'] === (int) $b['priority'] ) {
					return (int) $b['id'] <=> (int) $a['id'];
				}

				return (int) $b['priority'] <=> (int) $a['priority'];
			}
		);

		return $campaigns;
	}

	/**
	 * Registers campaign meta box.
	 *
	 * @return void
	 */
	public function register_meta_box() {
		add_meta_box( 'cmlc_campaign_settings', __( 'Campaign Settings', 'coppermont-lead-capture' ), array( $this, 'render_meta_box' ), self::POST_TYPE, 'normal', 'default' );
	}

	/**
	 * Renders campaign settings meta box.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		$campaign = self::get_campaign( $post->ID );
		if ( ! $campaign ) {
			$campaign       = self::defaults();
			$campaign['id'] = (int) $post->ID;
		}
		wp_nonce_field( 'cmlc_campaign_meta', 'cmlc_campaign_meta_nonce' );
		?>
		<table class="form-table" role="presentation">
			<tr><th scope="row">Enable Infobar</th><td><input type="checkbox" name="cmlc_campaign[enabled]" value="1" <?php checked( 1, $campaign['enabled'] ); ?>></td></tr>
			<tr><th scope="row">Headline</th><td><input class="regular-text" name="cmlc_campaign[headline]" value="<?php echo esc_attr( $campaign['headline'] ); ?>"></td></tr>
			<tr><th scope="row">Body</th><td><input class="regular-text" name="cmlc_campaign[body]" value="<?php echo esc_attr( $campaign['body'] ); ?>"></td></tr>
			<tr><th scope="row">Button Text</th><td><input name="cmlc_campaign[button_text]" value="<?php echo esc_attr( $campaign['button_text'] ); ?>"></td></tr>
			<tr><th scope="row">Background Color</th><td><input type="color" name="cmlc_campaign[bg_color]" value="<?php echo esc_attr( $campaign['bg_color'] ); ?>"></td></tr>
			<tr><th scope="row">Text Color</th><td><input type="color" name="cmlc_campaign[text_color]" value="<?php echo esc_attr( $campaign['text_color'] ); ?>"></td></tr>
			<tr><th scope="row">Button Color</th><td><input type="color" name="cmlc_campaign[button_color]" value="<?php echo esc_attr( $campaign['button_color'] ); ?>"></td></tr>
			<tr><th scope="row">Button Text Color</th><td><input type="color" name="cmlc_campaign[button_text_color]" value="<?php echo esc_attr( $campaign['button_text_color'] ); ?>"></td></tr>
			<tr><th scope="row">Priority</th><td><input type="number" min="0" name="cmlc_campaign[priority]" value="<?php echo esc_attr( $campaign['priority'] ); ?>"><p class="description">Higher numbers win conflicts.</p></td></tr>
			<tr><th scope="row">Width (%)</th><td><input type="number" min="40" max="100" name="cmlc_campaign[bar_width]" value="<?php echo esc_attr( $campaign['bar_width'] ); ?>"></td></tr>
			<tr><th scope="row">Opacity (%)</th><td><input type="number" min="10" max="100" name="cmlc_campaign[bar_opacity]" value="<?php echo esc_attr( $campaign['bar_opacity'] ); ?>"></td></tr>
			<tr><th scope="row">Scroll Trigger %</th><td><input type="number" min="0" max="100" name="cmlc_campaign[scroll_trigger_percent]" value="<?php echo esc_attr( $campaign['scroll_trigger_percent'] ); ?>"></td></tr>
			<tr><th scope="row">Delay Seconds</th><td><input type="number" min="0" name="cmlc_campaign[time_delay_seconds]" value="<?php echo esc_attr( $campaign['time_delay_seconds'] ); ?>"></td></tr>
			<tr><th scope="row">Cooldown Hours</th><td><input type="number" min="1" name="cmlc_campaign[repetition_cooldown_hours]" value="<?php echo esc_attr( $campaign['repetition_cooldown_hours'] ); ?>"></td></tr>
			<tr><th scope="row">Max Views</th><td><input type="number" min="1" name="cmlc_campaign[max_views]" value="<?php echo esc_attr( $campaign['max_views'] ); ?>"></td></tr>
			<tr><th scope="row">Exit Intent Trigger</th><td><input type="checkbox" name="cmlc_campaign[enable_exit_intent]" value="1" <?php checked( 1, $campaign['enable_exit_intent'] ); ?>></td></tr>
			<tr><th scope="row">Enable on Mobile</th><td><input type="checkbox" name="cmlc_campaign[enable_mobile]" value="1" <?php checked( 1, $campaign['enable_mobile'] ); ?>></td></tr>
			<tr><th scope="row">Allowed Referrers</th><td><input class="regular-text" name="cmlc_campaign[allowed_referrers]" value="<?php echo esc_attr( $campaign['allowed_referrers'] ); ?>"><p class="description">Comma-separated domains.</p></td></tr>
			<tr><th scope="row">Page Targeting Mode</th><td><select name="cmlc_campaign[page_target_mode]"><option value="all" <?php selected( 'all', $campaign['page_target_mode'] ); ?>>All pages</option><option value="include" <?php selected( 'include', $campaign['page_target_mode'] ); ?>>Include only listed IDs</option><option value="exclude" <?php selected( 'exclude', $campaign['page_target_mode'] ); ?>>Exclude listed IDs</option></select></td></tr>
			<tr><th scope="row">Page IDs</th><td><input class="regular-text" name="cmlc_campaign[page_ids]" value="<?php echo esc_attr( $campaign['page_ids'] ); ?>"><p class="description">Comma-separated post/page IDs.</p></td></tr>
			<tr><th scope="row">Schedule Start</th><td><input type="datetime-local" name="cmlc_campaign[schedule_start]" value="<?php echo esc_attr( $campaign['schedule_start'] ); ?>"></td></tr>
			<tr><th scope="row">Schedule End</th><td><input type="datetime-local" name="cmlc_campaign[schedule_end]" value="<?php echo esc_attr( $campaign['schedule_end'] ); ?>"></td></tr>
			<tr><th scope="row">Analytics</th><td><strong>Shows:</strong> <?php echo esc_html( (string) $campaign['analytics_impressions'] ); ?> | <strong>Submissions:</strong> <?php echo esc_html( (string) $campaign['analytics_submissions'] ); ?></td></tr>
		</table>
		<?php
	}

	/**
	 * Saves campaign meta.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public function save_meta_box( $post_id ) {
		if ( ! isset( $_POST['cmlc_campaign_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cmlc_campaign_meta_nonce'] ) ), 'cmlc_campaign_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( empty( $_POST['cmlc_campaign'] ) || ! is_array( $_POST['cmlc_campaign'] ) ) {
			return;
		}

		$input = wp_unslash( $_POST['cmlc_campaign'] );
		$data  = self::sanitize_campaign( $input );

		foreach ( self::defaults() as $key => $default ) {
			if ( in_array( $key, array( 'analytics_impressions', 'analytics_submissions' ), true ) ) {
				continue;
			}
			update_post_meta( $post_id, '_cmlc_' . $key, $data[ $key ] );
		}
	}

	/**
	 * Migrates legacy single settings into first campaign.
	 *
	 * @return void
	 */
	public function maybe_migrate_legacy_settings() {
		if ( get_option( self::MIGRATION_OPTION ) ) {
			return;
		}

		$existing = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);
		if ( ! empty( $existing ) ) {
			update_option( self::MIGRATION_OPTION, 1 );
			return;
		}

		$legacy = get_option( CMLC_Settings::OPTION_KEY, array() );
		if ( ! is_array( $legacy ) ) {
			$legacy = array();
		}

		$campaign = self::sanitize_campaign( wp_parse_args( $legacy, self::defaults() ) );

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => __( 'Migrated Default Campaign', 'coppermont-lead-capture' ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return;
		}

		foreach ( self::defaults() as $key => $default ) {
			update_post_meta( $post_id, '_cmlc_' . $key, $campaign[ $key ] );
		}

		update_option( self::MIGRATION_OPTION, 1 );
		set_transient( 'cmlc_campaign_migrated_notice', 1, 60 );
	}

	/**
	 * Displays migration success notice.
	 *
	 * @return void
	 */
	public function show_migration_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! get_transient( 'cmlc_campaign_migrated_notice' ) ) {
			return;
		}

		delete_transient( 'cmlc_campaign_migrated_notice' );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Coppermont Lead Capture migrated existing settings to a campaign. Manage campaigns under Infobar Campaigns.', 'coppermont-lead-capture' ) . '</p></div>';
	}
}
