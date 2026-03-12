<?php
/**
 * Campaign entity management.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CMLC_Campaigns {
	/**
	 * Migration option key.
	 *
	 * @var string
	 */
	const MIGRATION_OPTION = 'cmlc_campaigns_migrated';

	/**
	 * Registers campaign hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'maybe_migrate_legacy_settings' ), 20 );
		add_action( 'add_meta_boxes_cmlc_campaign', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_cmlc_campaign', array( $this, 'save_campaign_meta' ) );
	}

	/**
	 * Registers campaign post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		register_post_type(
			'cmlc_campaign',
			array(
				'labels'              => array(
					'name'          => __( 'Infobar Campaigns', 'coppermont-lead-capture' ),
					'singular_name' => __( 'Infobar Campaign', 'coppermont-lead-capture' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'supports'            => array( 'title' ),
				'menu_position'       => 58,
				'menu_icon'           => 'dashicons-megaphone',
				'capability_type'     => 'post',
				'exclude_from_search' => true,
			)
		);
	}

	/**
	 * Campaign defaults.
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
			'dimensions_width'          => '',
			'dimensions_height'         => '',
			'opacity'                   => 1,
			'analytics_impressions'     => 0,
			'analytics_submissions'     => 0,
		);
	}

	/**
	 * Sanitizes campaign data.
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
		$output['priority']                  = absint( $output['priority'] );
		$output['dimensions_width']          = sanitize_text_field( $output['dimensions_width'] );
		$output['dimensions_height']         = sanitize_text_field( $output['dimensions_height'] );
		$output['opacity']                   = min( 1, max( 0.1, (float) $output['opacity'] ) );
		$output['analytics_impressions']     = absint( $output['analytics_impressions'] );
		$output['analytics_submissions']     = absint( $output['analytics_submissions'] );

		return $output;
	}

	/**
	 * Gets campaign config from post meta.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array<string,mixed>|null
	 */
	public static function get_campaign( $campaign_id ) {
		$campaign_id = absint( $campaign_id );
		if ( ! $campaign_id || 'cmlc_campaign' !== get_post_type( $campaign_id ) ) {
			return null;
		}

		$raw = get_post_meta( $campaign_id, '_cmlc_campaign_settings', true );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$settings               = wp_parse_args( $raw, self::defaults() );
		$settings['campaign_id'] = $campaign_id;

		return self::sanitize_campaign( $settings );
	}

	/**
	 * Persists campaign config to post meta.
	 *
	 * @param int                 $campaign_id Campaign ID.
	 * @param array<string,mixed> $settings Settings.
	 * @return void
	 */
	public static function update_campaign( $campaign_id, $settings ) {
		$campaign_id = absint( $campaign_id );
		if ( ! $campaign_id || 'cmlc_campaign' !== get_post_type( $campaign_id ) ) {
			return;
		}

		$current = get_post_meta( $campaign_id, '_cmlc_campaign_settings', true );
		if ( ! is_array( $current ) ) {
			$current = array();
		}

		$merged = wp_parse_args( is_array( $settings ) ? $settings : array(), $current );
		update_post_meta( $campaign_id, '_cmlc_campaign_settings', self::sanitize_campaign( $merged ) );
	}

	/**
	 * Returns active campaign resolved by priority.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function get_active_campaign() {
		$campaigns = self::query_campaigns();
		if ( empty( $campaigns ) ) {
			return null;
		}

		return $campaigns[0];
	}

	/**
	 * Gets campaigns matching eligibility sorted by priority.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function query_campaigns() {
		$posts = get_posts(
			array(
				'post_type'      => 'cmlc_campaign',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);

		$eligible = array();
		foreach ( $posts as $post ) {
			$campaign = self::get_campaign( $post->ID );
			if ( empty( $campaign['enabled'] ) ) {
				continue;
			}

			if ( ! CMLC_Renderer::is_eligible_page( $campaign ) ) {
				continue;
			}

			$eligible[] = $campaign;
		}

		usort(
			$eligible,
			static function ( $a, $b ) {
				if ( $a['priority'] === $b['priority'] ) {
					return $b['campaign_id'] <=> $a['campaign_id'];
				}

				return $b['priority'] <=> $a['priority'];
			}
		);

		return $eligible;
	}


	/**
	 * Registers campaign meta box.
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box( 'cmlc_campaign_settings', __( 'Campaign Settings', 'coppermont-lead-capture' ), array( $this, 'render_meta_box' ), 'cmlc_campaign', 'normal', 'default' );
	}

	/**
	 * Renders campaign settings fields.
	 *
	 * @param WP_Post $post Campaign post.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		$campaign = self::get_campaign( $post->ID );
		if ( ! is_array( $campaign ) ) {
			$campaign = self::defaults();
		}
		wp_nonce_field( 'cmlc_campaign_meta', 'cmlc_campaign_nonce' );
		?>
		<p><label><input type="checkbox" name="cmlc_campaign[enabled]" value="1" <?php checked( 1, $campaign['enabled'] ); ?>> <?php esc_html_e( 'Enable campaign', 'coppermont-lead-capture' ); ?></label></p>
		<p><label><?php esc_html_e( 'Headline', 'coppermont-lead-capture' ); ?><br><input class="widefat" name="cmlc_campaign[headline]" value="<?php echo esc_attr( $campaign['headline'] ); ?>"></label></p>
		<p><label><?php esc_html_e( 'Body', 'coppermont-lead-capture' ); ?><br><input class="widefat" name="cmlc_campaign[body]" value="<?php echo esc_attr( $campaign['body'] ); ?>"></label></p>
		<p><label><?php esc_html_e( 'Button text', 'coppermont-lead-capture' ); ?><br><input class="widefat" name="cmlc_campaign[button_text]" value="<?php echo esc_attr( $campaign['button_text'] ); ?>"></label></p>
		<p><label><?php esc_html_e( 'Priority', 'coppermont-lead-capture' ); ?><br><input type="number" min="0" name="cmlc_campaign[priority]" value="<?php echo esc_attr( (string) $campaign['priority'] ); ?>"></label></p>
		<p><label><?php esc_html_e( 'Background color', 'coppermont-lead-capture' ); ?> <input type="color" name="cmlc_campaign[bg_color]" value="<?php echo esc_attr( $campaign['bg_color'] ); ?>"></label>
		<label><?php esc_html_e( 'Text color', 'coppermont-lead-capture' ); ?> <input type="color" name="cmlc_campaign[text_color]" value="<?php echo esc_attr( $campaign['text_color'] ); ?>"></label></p>
		<p><label><?php esc_html_e( 'Button color', 'coppermont-lead-capture' ); ?> <input type="color" name="cmlc_campaign[button_color]" value="<?php echo esc_attr( $campaign['button_color'] ); ?>"></label>
		<label><?php esc_html_e( 'Button text color', 'coppermont-lead-capture' ); ?> <input type="color" name="cmlc_campaign[button_text_color]" value="<?php echo esc_attr( $campaign['button_text_color'] ); ?>"></label></p>
		<p><label><?php esc_html_e( 'Scroll trigger %', 'coppermont-lead-capture' ); ?> <input type="number" min="0" max="100" name="cmlc_campaign[scroll_trigger_percent]" value="<?php echo esc_attr( (string) $campaign['scroll_trigger_percent'] ); ?>"></label>
		<label><?php esc_html_e( 'Delay (seconds)', 'coppermont-lead-capture' ); ?> <input type="number" min="0" name="cmlc_campaign[time_delay_seconds]" value="<?php echo esc_attr( (string) $campaign['time_delay_seconds'] ); ?>"></label></p>
		<p><label><?php esc_html_e( 'Cooldown (hours)', 'coppermont-lead-capture' ); ?> <input type="number" min="1" name="cmlc_campaign[repetition_cooldown_hours]" value="<?php echo esc_attr( (string) $campaign['repetition_cooldown_hours'] ); ?>"></label>
		<label><?php esc_html_e( 'Max views', 'coppermont-lead-capture' ); ?> <input type="number" min="1" name="cmlc_campaign[max_views]" value="<?php echo esc_attr( (string) $campaign['max_views'] ); ?>"></label></p>
		<p><label><input type="checkbox" name="cmlc_campaign[enable_exit_intent]" value="1" <?php checked( 1, $campaign['enable_exit_intent'] ); ?>> <?php esc_html_e( 'Enable exit intent', 'coppermont-lead-capture' ); ?></label>
		<label><input type="checkbox" name="cmlc_campaign[enable_mobile]" value="1" <?php checked( 1, $campaign['enable_mobile'] ); ?>> <?php esc_html_e( 'Enable on mobile', 'coppermont-lead-capture' ); ?></label></p>
		<p><label><?php esc_html_e( 'Allowed referrers (comma separated)', 'coppermont-lead-capture' ); ?><br><input class="widefat" name="cmlc_campaign[allowed_referrers]" value="<?php echo esc_attr( $campaign['allowed_referrers'] ); ?>"></label></p>
		<p><label><?php esc_html_e( 'Page targeting mode', 'coppermont-lead-capture' ); ?>
		<select name="cmlc_campaign[page_target_mode]"><option value="all" <?php selected( 'all', $campaign['page_target_mode'] ); ?>>All</option><option value="include" <?php selected( 'include', $campaign['page_target_mode'] ); ?>>Include</option><option value="exclude" <?php selected( 'exclude', $campaign['page_target_mode'] ); ?>>Exclude</option></select>
		</label></p>
		<p><label><?php esc_html_e( 'Page IDs', 'coppermont-lead-capture' ); ?><br><input class="widefat" name="cmlc_campaign[page_ids]" value="<?php echo esc_attr( $campaign['page_ids'] ); ?>"></label></p>
		<p><label><?php esc_html_e( 'Schedule start', 'coppermont-lead-capture' ); ?> <input type="datetime-local" name="cmlc_campaign[schedule_start]" value="<?php echo esc_attr( $campaign['schedule_start'] ); ?>"></label>
		<label><?php esc_html_e( 'Schedule end', 'coppermont-lead-capture' ); ?> <input type="datetime-local" name="cmlc_campaign[schedule_end]" value="<?php echo esc_attr( $campaign['schedule_end'] ); ?>"></label></p>
		<p><label><?php esc_html_e( 'Width', 'coppermont-lead-capture' ); ?> <input name="cmlc_campaign[dimensions_width]" value="<?php echo esc_attr( $campaign['dimensions_width'] ); ?>" placeholder="e.g. 900px"></label>
		<label><?php esc_html_e( 'Min height', 'coppermont-lead-capture' ); ?> <input name="cmlc_campaign[dimensions_height]" value="<?php echo esc_attr( $campaign['dimensions_height'] ); ?>" placeholder="e.g. 80px"></label>
		<label><?php esc_html_e( 'Opacity', 'coppermont-lead-capture' ); ?> <input type="number" min="0.1" max="1" step="0.1" name="cmlc_campaign[opacity]" value="<?php echo esc_attr( (string) $campaign['opacity'] ); ?>"></label></p>
		<?php
	}

	/**
	 * Saves campaign meta box fields.
	 *
	 * @param int $post_id Campaign ID.
	 * @return void
	 */
	public function save_campaign_meta( $post_id ) {
		if ( ! isset( $_POST['cmlc_campaign_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cmlc_campaign_nonce'] ) ), 'cmlc_campaign_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$input = isset( $_POST['cmlc_campaign'] ) ? (array) wp_unslash( $_POST['cmlc_campaign'] ) : array();
		self::update_campaign( $post_id, $input );
	}

	/**
	 * Migrates legacy option settings into a starter campaign.
	 *
	 * @return void
	 */
	public function maybe_migrate_legacy_settings() {
		if ( get_option( self::MIGRATION_OPTION ) ) {
			return;
		}

		$legacy = get_option( CMLC_Settings::OPTION_KEY, array() );
		if ( ! is_array( $legacy ) ) {
			$legacy = array();
		}

		$campaign = self::sanitize_campaign( wp_parse_args( $legacy, self::defaults() ) );
		$post_id  = wp_insert_post(
			array(
				'post_type'   => 'cmlc_campaign',
				'post_status' => 'publish',
				'post_title'  => __( 'Default Infobar Campaign', 'coppermont-lead-capture' ),
			),
			true
		);

		if ( ! is_wp_error( $post_id ) ) {
			self::update_campaign( $post_id, $campaign );
		}

		update_option( self::MIGRATION_OPTION, 1 );
	}
}
